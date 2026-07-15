<?php
/**
 * Patch: write post_excerpt on migrated whitepaper posts.
 *
 * The initial migration set post_excerpt to '' for all posts because the
 * reference sheet incorrectly noted that all 68 posts had empty excerpts.
 * In reality 63/69 posts carry excerpt text in excerpt:encoded. This patch
 * reads the WXR and writes the excerpt on any migrated post that is missing it.
 *
 * Usage (dry run, safe default):
 *   wp eval-file migrations/patch-whitepaper-excerpts.php --user=<admin>
 *
 * Usage (live write):
 *   wp eval-file migrations/patch-whitepaper-excerpts.php live --user=<admin>
 *
 * Optional:
 *   wp eval-file migrations/patch-whitepaper-excerpts.php live only=build-vs-buy-ai-platform --user=<admin>
 *
 * Flags (positional, same pattern as other migration scripts):
 *   live / go      → write changes (default: dry run)
 *   only=<slug>    → patch one post by its rebuilt slug
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-whitepaper-excerpts.php [live] [only=slug] --user=<admin>' . PHP_EOL );
}

/* ---- Flags ---------------------------------------------------------------- */

$_flags = isset( $args ) && is_array( $args ) ? $args : [];
$dry    = true;
$only   = '';

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )               { $dry  = false; }
	if ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) { $dry  = true;  }
	if ( str_starts_with( $tok, 'only=' ) )                        { $only = substr( $tok, 5 ); }
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  Whitepaper patch: post_excerpt' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
if ( '' !== $only ) { WP_CLI::log( '  only: "' . $only . '"' ); }
WP_CLI::log( '=====================================================' );

/* ---- Build slug → excerpt map from WXR ----------------------------------- */

$wxr_path = __DIR__ . '/momentivesoftware.whitepapers.current.2026-07-01.xml';

if ( ! file_exists( $wxr_path ) ) {
	WP_CLI::error( "Legacy WXR not found: {$wxr_path}" );
	return;
}

$xml = file_get_contents( $wxr_path );
if ( false === $xml ) {
	WP_CLI::error( "Could not read: {$wxr_path}" );
	return;
}

preg_match_all( '#<item>(.*?)</item>#s', $xml, $_items );
$all_items = $_items[1] ?? [];

$excerpt_map = []; // slug → excerpt string

foreach ( $all_items as $item ) {
	if ( false === strpos( $item, 'post_type><![CDATA[whitepapers]]>' ) ) {
		continue;
	}
	if ( ! preg_match( '#<wp:post_name><!\[CDATA\[(.*?)\]\]>#', $item, $sm ) ) {
		continue;
	}
	$slug = $sm[1];

	$exc = '';
	if ( preg_match( '#<excerpt:encoded><!\[CDATA\[(.*?)\]\]></excerpt:encoded>#s', $item, $em ) ) {
		$exc = trim( $em[1] );
	}

	$excerpt_map[ $slug ] = $exc;
}

WP_CLI::log( sprintf(
	'Legacy WXR: %d whitepaper slugs loaded (%d with excerpt text).',
	count( $excerpt_map ),
	count( array_filter( $excerpt_map ) )
) );

/* ---- Query migrated whitepaper posts -------------------------------------- */

$query_args = [
	'post_type'      => 'whitepaper',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'meta_key'       => '_momentive_migration_run',
];
if ( '' !== $only ) {
	$query_args['name'] = $only;
}

$post_ids = get_posts( $query_args );
WP_CLI::log( sprintf( 'Found %d migrated whitepaper post(s) to check.', count( $post_ids ) ) . "\n" );

/* ---- Patch each post ------------------------------------------------------ */

$summary = [
	'processed'       => 0,
	'excerpt_written' => 0,
	'already_set'     => 0,
	'no_wxr_excerpt'  => 0,
	'slug_not_in_wxr' => 0,
];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$slug    = get_post_field( 'post_name', $post_id );
	$title   = get_the_title( $post_id );
	$summary['processed']++;

	WP_CLI::log( "[{$post_id}] {$title} ({$slug})" );

	if ( ! array_key_exists( $slug, $excerpt_map ) ) {
		WP_CLI::log( "  slug not in WXR — skipped" );
		$summary['slug_not_in_wxr']++;
		continue;
	}

	$wxr_excerpt     = $excerpt_map[ $slug ];
	$current_excerpt = get_post_field( 'post_excerpt', $post_id );

	if ( '' !== $current_excerpt ) {
		WP_CLI::log( "  excerpt already set — no action" );
		$summary['already_set']++;
	} elseif ( '' === $wxr_excerpt ) {
		WP_CLI::log( "  no excerpt in WXR — no action" );
		$summary['no_wxr_excerpt']++;
	} else {
		if ( $dry ) {
			WP_CLI::log( "  [dry] would write: " . mb_substr( $wxr_excerpt, 0, 100 ) . ( mb_strlen( $wxr_excerpt ) > 100 ? '…' : '' ) );
		} else {
			wp_update_post( [ 'ID' => $post_id, 'post_excerpt' => $wxr_excerpt ] );
			WP_CLI::log( "  wrote excerpt" );
		}
		$summary['excerpt_written']++;
	}
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-22s %d', $k, $v ) );
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to apply changes.' : 'Patch complete.' );
