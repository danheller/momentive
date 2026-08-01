<?php
/**
 * backfill-resource-relevance.php
 *
 * One-time (and re-runnable) CLI backfill for AI-assisted Solution relevance
 * tagging (see inc/resource-relevance.php). The live site only tags a
 * resource post when it's saved (save_post → a deferred WP-Cron pass) or
 * when an editor uses the "Re-tag Solution relevance (AI)" bulk action one
 * post at a time. Neither of those covers the ~87+ resource posts that
 * already existed before this feature was built. This script calls the
 * exact same tagging function those paths use — momentive_tag_resource_
 * relevance_now() — directly and synchronously, in a loop, across every
 * resource post type (momentive_get_resource_post_types()).
 *
 * This is deliberately NOT a different implementation of the tagging logic,
 * just a third caller of the one that already exists. It skips the
 * wp_schedule_single_event() deferral the live save_post path uses — that
 * exists only to keep a slow API call from blocking an editor's browser,
 * which doesn't apply here, and WP-Cron may not fire promptly outside a
 * real HTTP hit anyway.
 *
 * Requires an Anthropic API key to actually tag anything — see
 * MOMENTIVE_ANTHROPIC_API_KEY / the momentive_anthropic_api_key filter in
 * inc/resource-relevance.php. Until IT approves API access, `live` mode
 * will refuse to run (see the early check below) rather than silently
 * loop and no-op on every post; `dry-run` mode works today and exercises
 * every other part of this script (querying, candidate-list building,
 * gating, logging) without needing a key at all.
 *
 * Usage (flags are POSITIONAL — `wp eval-file` rejects --flags):
 *   wp eval-file migrations/backfill-resource-relevance.php               # dry run (default)
 *   wp eval-file migrations/backfill-resource-relevance.php live          # writes
 *   wp eval-file migrations/backfill-resource-relevance.php live limit=5  # first 5 only
 *   wp eval-file migrations/backfill-resource-relevance.php live only=1234 # single post ID
 *   wp eval-file migrations/backfill-resource-relevance.php live types=post,case-study # restrict post types
 *   wp eval-file migrations/backfill-resource-relevance.php live force    # re-tag even if already tagged / manually overridden
 *
 * Flags:
 *   live / go        → write changes (default: dry run, no API calls, no writes)
 *   only=<post_id>   → a single resource post, by ID (not slug — slugs aren't
 *                       guaranteed unique across the 6 resource post types)
 *   limit=<n>        → stop after N posts
 *   types=a,b,c       → restrict to specific post type slugs (default: all of
 *                       momentive_get_resource_post_types())
 *   force             → bypass the "manual override" flag and the unchanged-
 *                       content hash check, and re-tag regardless (mirrors
 *                       momentive_maybe_schedule_relevance_tagging()'s $force
 *                       semantics, reimplemented here since this script calls
 *                       the tagging function directly rather than scheduling it)
 *
 * Safe to re-run: posts already tagged with an unchanged content hash are
 * skipped by default (no wasted API calls), same idempotency convention as
 * every other migration script in this folder.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/backfill-resource-relevance.php [live] [only=<post_id>] [limit=n] [types=a,b,c] [force]' . PHP_EOL );
}

/* ---- Flags ---------------------------------------------------------------- */

$_flags = isset( $args ) && is_array( $args ) ? $args : [];
$dry     = true;
$only    = 0;
$limit   = 0;
$types   = [];
$force   = false;

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )                { $dry   = false; }
	elseif ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) { $dry   = true; }
	elseif ( str_starts_with( $tok, 'only=' ) )                     { $only  = (int) substr( $tok, 5 ); }
	elseif ( str_starts_with( $tok, 'limit=' ) )                    { $limit = (int) substr( $tok, 6 ); }
	elseif ( str_starts_with( $tok, 'types=' ) )                    { $types = array_values( array_filter( array_map( 'trim', explode( ',', substr( $tok, 6 ) ) ) ) ); }
	elseif ( 'force' === $tok )                                     { $force = true; }
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  Resource relevance backfill (AI Solution tagging)' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no API calls, no writes)' : '*** LIVE — CALLING THE ANTHROPIC API ***' ) );
if ( $only )   { WP_CLI::log( '  only:  post ' . $only ); }
if ( $limit )  { WP_CLI::log( '  limit: ' . $limit . ' posts' ); }
if ( $types )  { WP_CLI::log( '  types: ' . implode( ', ', $types ) ); }
if ( $force )  { WP_CLI::log( '  force: re-tagging even if already tagged / manually overridden' ); }
WP_CLI::log( '=====================================================' );

/* ---- Refuse to run live without an API key --------------------------------- */

if ( ! $dry && ! momentive_anthropic_api_key() ) {
	WP_CLI::error(
		'No Anthropic API key configured — define MOMENTIVE_ANTHROPIC_API_KEY in wp-config.php ' .
		'(or add a momentive_anthropic_api_key filter) before running live. ' .
		'Dry run works without a key: wp eval-file migrations/backfill-resource-relevance.php'
	);
	return;
}

/* ---- Candidate solutions sanity check --------------------------------------- */

$candidate_count = count( momentive_get_taggable_child_solutions() );
WP_CLI::log( sprintf( 'Taggable child Solutions available as candidates: %d', $candidate_count ) . "\n" );
if ( 0 === $candidate_count ) {
	WP_CLI::warning( 'No published child Solutions found — every post will be skipped. Run this after the Solutions migration, not before.' );
}

/* ---- Query resource posts --------------------------------------------------- */

$post_types = $types ?: momentive_get_resource_post_types();

$query_args = [
	'post_type'      => $post_types,
	'post_status'    => 'publish',
	'posts_per_page' => $limit > 0 ? $limit : -1,
	'orderby'        => 'ID',
	'order'          => 'ASC',
	'fields'         => 'ids',
	'no_found_rows'  => true,
];
if ( $only ) {
	$query_args['p'] = $only;
	unset( $query_args['posts_per_page'] );
}

$post_ids = get_posts( $query_args );
WP_CLI::log( sprintf( 'Found %d resource post(s) to process.', count( $post_ids ) ) . "\n" );

/* ---- Backfill each post ------------------------------------------------------ */

$summary = [
	'processed'              => 0,
	'tagged'                 => 0,
	'skipped_manual_override' => 0,
	'skipped_unchanged'      => 0,
	'not_tagged'             => 0, // API call failed, no key, or zero candidates
];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		continue;
	}
	$summary['processed']++;

	$label = sprintf( '[%d/%s] %s', $post_id, $post->post_type, get_the_title( $post_id ) );
	WP_CLI::log( $label );

	// Same gating momentive_maybe_schedule_relevance_tagging() applies —
	// reimplemented here since this script calls the tagging function
	// directly rather than scheduling it through that helper.
	if ( ! $force ) {
		if ( get_post_meta( $post_id, '_momentive_relevance_manual_override', true ) ) {
			WP_CLI::log( '  manual override set — skipped (pass `force` to re-tag anyway)' );
			$summary['skipped_manual_override']++;
			continue;
		}
		$hash          = momentive_resource_content_hash( $post );
		$existing_hash = get_post_meta( $post_id, '_momentive_relevance_hash', true );
		if ( '' !== $existing_hash && $existing_hash === $hash ) {
			WP_CLI::log( '  already tagged, content unchanged — skipped (pass `force` to re-tag anyway)' );
			$summary['skipped_unchanged']++;
			continue;
		}
	} elseif ( get_post_meta( $post_id, '_momentive_relevance_manual_override', true ) ) {
		delete_post_meta( $post_id, '_momentive_relevance_manual_override' );
	}

	if ( $dry ) {
		WP_CLI::log( '  [dry-run] would call the Anthropic API and write relevant_solutions' );
		continue;
	}

	$before_tagged_at = get_post_meta( $post_id, '_momentive_relevance_tagged_at', true );
	momentive_tag_resource_relevance_now( $post_id );
	$after_tagged_at = get_post_meta( $post_id, '_momentive_relevance_tagged_at', true );

	if ( $after_tagged_at !== $before_tagged_at ) {
		$matched = get_field( 'relevant_solutions', $post_id ) ?: [];
		$names   = array_map( 'get_the_title', is_array( $matched ) ? $matched : [] );
		WP_CLI::log( sprintf( '  tagged: %s', $names ? implode( ', ', $names ) : '(no solutions matched)' ) );
		$summary['tagged']++;
	} else {
		WP_CLI::warning( '  not tagged — check the PHP error log (missing/invalid API key, request failure, or zero candidates)' );
		$summary['not_tagged']++;
	}

	// Be polite to the API across a batch of 87+ posts.
	sleep( 1 );
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-26s %d', $k, $v ) );
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to actually tag (requires an API key).' : 'Backfill complete.' );
