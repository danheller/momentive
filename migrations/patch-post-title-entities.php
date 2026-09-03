<?php
/**
 * Patch: decode HTML entities in post titles.
 *
 * The xml_tag() regex helpers in the WXR migration scripts returned raw XML
 * entity-encoded text from non-CDATA nodes — so a title like
 *   <title>Types &amp; How It Works</title>
 * was stored literally as "Types &amp; How It Works" instead of
 * "Types & How It Works". This script finds all affected posts and decodes
 * their titles in place.
 *
 * Usage (WP-CLI):
 *   wp eval-file patch-post-title-entities.php                    # dry run (default)
 *   wp eval-file patch-post-title-entities.php live               # writes changes
 *   wp eval-file patch-post-title-entities.php live only=1234     # single post ID
 *
 * Conventions shared with every other patch script in this directory:
 *   - Dry-run by default; the `live` positional arg enables writes.
 *   - `only=<id>` limits to one post for quick spot-checking.
 *   - End-of-run summary: totals updated / skipped / errored.
 *   - Idempotent: re-running a second time finds no changes to make.
 *   - No `--user` flag required (no media/SVG capability check needed).
 */

$dry_run  = ! in_array( 'live', $args, true );
$only_id  = 0;
foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, 'only=' ) ) {
		$only_id = (int) substr( $arg, 5 );
	}
}

WP_CLI::log( $dry_run ? '=== DRY RUN — no changes will be written ===' : '=== LIVE MODE ===' );

// ── Scope ─────────────────────────────────────────────────────────────────

// All post types that run through WXR migration scripts (and therefore
// could have picked up the entity-encoding bug). Includes every resource
// CPT plus posts, press-articles, solutions, and people/team types that
// also have xml_tag-derived titles.
$post_types = array_filter( [
	'post', 'case-study', 'webinar', 'whitepaper', 'infographic', 'guide',
	'video', 'event', 'interactive-tool', 'toolkit',
	'press-article', 'solutions', 'people', 'testimonials',
], 'post_type_exists' );

// ── Query ─────────────────────────────────────────────────────────────────

global $wpdb;

// Find posts whose title contains an ampersand — the tell-tale sign of a
// surviving HTML entity (all entities contain &). The LIKE uses a DB-level
// wildcard; PHP does the precise html_entity_decode check per row so we
// never write a no-op update.
if ( $only_id ) {
	$placeholders = '%d';
	$values       = [ $only_id ];
	$type_in      = '';
} else {
	$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$values       = array_values( $post_types );
	$type_in      = "AND post_type IN ($placeholders)";
}

$query = $wpdb->prepare(
	"SELECT ID, post_title, post_type FROM {$wpdb->posts}
	 WHERE post_status IN ('publish','draft','pending','private','future')
	   AND post_title LIKE %s
	   $type_in
	 ORDER BY post_type, ID",
	array_merge( [ '%&%' ], $values )
);

$posts = $wpdb->get_results( $query );

if ( empty( $posts ) ) {
	WP_CLI::success( 'No posts with entity-encoded titles found.' );
	return;
}

WP_CLI::log( sprintf( 'Found %d post(s) with & in their titles. Checking each…', count( $posts ) ) );

// ── Process ───────────────────────────────────────────────────────────────

$updated = 0;
$skipped = 0;
$errored = 0;

foreach ( $posts as $post ) {
	$decoded = html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	if ( $decoded === $post->post_title ) {
		// The & is a literal ampersand already stored correctly, or
		// html_entity_decode found nothing to change — skip.
		$skipped++;
		continue;
	}

	WP_CLI::log( sprintf(
		'  [%d] %s | "%s" → "%s"',
		$post->ID,
		$post->post_type,
		$post->post_title,
		$decoded
	) );

	if ( $dry_run ) {
		$updated++;
		continue;
	}

	$result = wp_update_post( [
		'ID'         => (int) $post->ID,
		'post_title' => $decoded,
	], true );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( sprintf( '  → ERROR on ID %d: %s', $post->ID, $result->get_error_message() ) );
		$errored++;
	} else {
		$updated++;
	}
}

// ── Summary ───────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '── Summary ──────────────────────────────────────' );
WP_CLI::log( sprintf( '  %-12s %d', ( $dry_run ? 'Would update:' : 'Updated:' ), $updated ) );
WP_CLI::log( sprintf( '  %-12s %d  (& already correct)', 'Skipped:', $skipped ) );
if ( $errored ) {
	WP_CLI::warning( sprintf( '  %-12s %d', 'Errors:', $errored ) );
}

if ( $dry_run && $updated > 0 ) {
	WP_CLI::log( '' );
	WP_CLI::log( 'Re-run with the `live` token to apply changes:' );
	WP_CLI::log( '  wp eval-file patch-post-title-entities.php live' );
}

WP_CLI::success( 'Done.' );
