<?php
/**
 * patch-post-category-slugs.php
 *
 * Assigns correct categories to blog posts whose legacy category slug differs
 * from the rebuilt site's slug. Posts with these legacy slugs were skipped
 * during migration (the category didn't exist under the old name):
 *
 *   event-technology  →  event-management
 *   career-services   →  career-centers
 *
 * Reads from the legacy WXR to find affected post slugs, then assigns the
 * correct rebuilt category term to each matching post.
 *
 *   wp eval-file migrations/patch-post-category-slugs.php --user=<admin>
 *     → dry run (default)
 *
 *   wp eval-file migrations/patch-post-category-slugs.php live --user=<admin>
 *     → applies changes
 *
 * Set MOMENTIVE_PM_LEGACY_WXR to override the default WXR path.
 * Idempotent: wp_set_post_categories with append=true won't duplicate terms.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$dry = true;
foreach ( ( isset( $args ) && is_array( $args ) ? $args : [] ) as $tok ) {
	if ( 'live' === ltrim( (string) $tok, '-' ) ) { $dry = false; }
}

WP_CLI::log( '== patch-post-category-slugs ==' . ( $dry ? '  (DRY RUN)' : '  (LIVE)' ) );

$wxr = defined( 'MOMENTIVE_PM_LEGACY_WXR' )
	? MOMENTIVE_PM_LEGACY_WXR
	: __DIR__ . '/exports/momentivesoftware.posts.current.2026-09-01.xml';

if ( ! file_exists( $wxr ) ) {
	WP_CLI::error( "Legacy WXR not found at {$wxr}. Set MOMENTIVE_PM_LEGACY_WXR." );
}
$xml = file_get_contents( $wxr );

// Slug remapping: legacy category slug => rebuilt category slug.
$remap = [
	'event-technology' => 'event-management',
	'career-services'  => 'career-centers',
];

// Resolve rebuilt term IDs up front; bail if any target category is missing.
$target_terms = [];
foreach ( $remap as $legacy_slug => $rebuilt_slug ) {
	$term = get_term_by( 'slug', $rebuilt_slug, 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		WP_CLI::error( "Target category '{$rebuilt_slug}' not found in rebuilt site." );
	}
	$target_terms[ $legacy_slug ] = $term->term_id;
	WP_CLI::log( "  Remap: '{$legacy_slug}' → '{$rebuilt_slug}' (term_id {$term->term_id})" );
}

// Parse WXR: find post slugs that carry one of the legacy category slugs.
// Build a map of post_slug => [ term_ids_to_add ].
$to_patch = [];
if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
	foreach ( $items[1] as $item ) {
		if ( false === strpos( $item, '<wp:post_type><![CDATA[post]]>' ) ) {
			continue;
		}
		if ( preg_match( '#<wp:post_name><!\[CDATA\[(.*?)\]\]>#', $item, $m ) ) {
			$post_slug = $m[1];
		} elseif ( preg_match( '#<wp:post_name>(.*?)</wp:post_name>#', $item, $m ) ) {
			$post_slug = $m[1];
		} else {
			continue;
		}
		if ( '' === $post_slug ) { continue; }

		// Collect legacy category slugs on this post.
		preg_match_all( '#<category domain="category" nicename="([^"]+)">#', $item, $cm );
		$cat_slugs = $cm[1] ?? [];

		$term_ids_to_add = [];
		foreach ( $cat_slugs as $cat_slug ) {
			if ( isset( $target_terms[ $cat_slug ] ) ) {
				$term_ids_to_add[] = $target_terms[ $cat_slug ];
			}
		}
		if ( ! empty( $term_ids_to_add ) ) {
			$to_patch[ $post_slug ] = array_unique( $term_ids_to_add );
		}
	}
}

WP_CLI::log( sprintf( 'Found %d post(s) in WXR with remappable categories.', count( $to_patch ) ) );

$patched = 0;
$missing = 0;
$errors  = 0;

foreach ( $to_patch as $post_slug => $term_ids ) {
	$post = get_page_by_path( $post_slug, OBJECT, 'post' );
	if ( ! $post ) {
		WP_CLI::warning( "  Post not found in rebuilt site: '{$post_slug}' — skipped." );
		$missing++;
		continue;
	}
	$term_names = array_map(
		fn( $id ) => get_term( $id, 'category' )->name ?? $id,
		$term_ids
	);
	WP_CLI::log( sprintf(
		'  %s [%d]: adding %s',
		$post_slug,
		$post->ID,
		implode( ', ', $term_names )
	) );
	if ( ! $dry ) {
		$result = wp_set_post_categories( $post->ID, $term_ids, true ); // append=true
		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( "  Error on '{$post_slug}': " . $result->get_error_message() );
			$errors++;
			continue;
		}
	}
	$patched++;
}

WP_CLI::log( sprintf(
	'Done. patched=%d  missing=%d  errors=%d%s',
	$patched, $missing, $errors,
	$dry ? '  (dry run — no changes written)' : ''
) );
