<?php
/**
 * Patch: strip ChatGPT/OpenAI-added UTM tracking params out of post content —
 * and unwrap the invisible "citation" anchors that often ride along with them.
 *
 * Real example this fixes (Blog post, found 2026-07-27):
 *
 *   <li>Organizer check-in app for scanning and entry workflows<a
 *   href="https://www.eventbrite.ca/organizer/features/organizer-check-in-app/?utm_source=chatgpt.com">&nbsp;</a></li>
 *
 * When content written with ChatGPT is pasted into the editor, its inline
 * citation links come along as a trailing <a> whose only "text" is a
 * non-breaking space (or nothing at all) — an invisible link riding on the
 * end of a real sentence, tagged with utm_source=chatgpt.com. Two independent
 * problems, handled differently:
 *
 *   1. The tracking param itself (utm_source/utm_medium/utm_campaign/utm_term/
 *      utm_content whose VALUE signals chatgpt/openai) is stripped from the
 *      href — whether or not the anchor is one of the phantom ones below.
 *      Other, legitimate params on the same URL (a real utm_source from an
 *      actual campaign, say) are left alone; only params whose VALUE matches
 *      a chatgpt/openai signature are touched.
 *   2. The phantom anchor itself — when an anchor's visible text is empty, or
 *      collapses to nothing once tags/entities/whitespace are stripped (a
 *      bare &nbsp; is the observed case), the whole <a>...</a> wrapper is
 *      unwrapped rather than just having its href cleaned, since a link with
 *      no visible text serves no purpose and is itself the artifact.
 *
 * Usage (wp eval-file does NOT accept --flags; args are positional):
 *
 *   wp eval-file migrations/patch-remove-chatgpt-utm-links.php                  # dry run (default)
 *   wp eval-file migrations/patch-remove-chatgpt-utm-links.php live             # writes
 *   wp eval-file migrations/patch-remove-chatgpt-utm-links.php live only=1234   # single post ID
 *   wp eval-file migrations/patch-remove-chatgpt-utm-links.php live limit=20    # cap posts touched
 *
 * Flags (positional, same pattern as other migration/patch scripts):
 *   live / go   → write changes (default: dry run)
 *   only=<id>   → patch a single post by ID
 *   limit=<n>   → cap how many flagged posts get processed in one run
 *
 * Dry-run is the default (same convention as every other script in this
 * folder) — an explicit `live` token, or MOMENTIVE_LIVE=1, is required to
 * write anything.
 *
 * Idempotent: once a post's flagged params are stripped and its phantom
 * anchors unwrapped, nothing matches on a re-run — safe to run twice.
 *
 * Scope: post types to scan default to Blog only ('post'), since that's
 * where this was reported. Override MOMENTIVE_UTM_POST_TYPES (comma-separated)
 * before requiring this file, or edit the constant below, to also scan
 * 'case-study', 'webinar', 'whitepaper', 'press-article', 'page', etc.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-remove-chatgpt-utm-links.php [live] [only=id] [limit=n]' . PHP_EOL );
}

/* ---- Config (overridable) -------------------------------------------------- */

if ( ! defined( 'MOMENTIVE_UTM_POST_TYPES' ) ) {
	define( 'MOMENTIVE_UTM_POST_TYPES', 'post' ); // comma-separated post type slugs
}

// Signatures that mark a UTM param VALUE as ChatGPT/OpenAI-originated.
// Matched case-insensitively against the param value only — never against
// the rest of the URL — so a legitimate link to, say, openai.com's own site
// isn't touched unless it's the UTM value itself doing the flagging.
if ( ! defined( 'MOMENTIVE_UTM_SIGNATURES' ) ) {
	define( 'MOMENTIVE_UTM_SIGNATURES', 'chatgpt.com,chatgpt,openai.com,openai,chat.openai.com' );
}

/* ---- Flags (positional, wp eval-file has no --flag support) --------------- */

$_flags  = isset( $args ) && is_array( $args ) ? $args : [];
$dry     = true;
$only_id = 0;
$limit   = 0;

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )               { $dry = false; }
	if ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) { $dry = true;  }
	if ( str_starts_with( $tok, 'only=' ) )  { $only_id = (int) substr( $tok, 5 ); }
	if ( str_starts_with( $tok, 'limit=' ) ) { $limit   = (int) substr( $tok, 6 ); }
}

if ( getenv( 'MOMENTIVE_LIVE' ) === '1' ) {
	$dry = false;
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  Patch: remove ChatGPT UTM links' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
if ( $only_id ) { WP_CLI::log( "  only: #{$only_id}" ); }
if ( $limit )   { WP_CLI::log( "  limit: {$limit}" ); }
WP_CLI::log( '=====================================================' );

/* ---- Helpers ---------------------------------------------------------------- */

/**
 * True if $value (a UTM query param value) signals ChatGPT/OpenAI.
 */
function momentive_utm_is_chatgpt_signature( string $value ): bool {
	$value = strtolower( trim( $value ) );
	if ( '' === $value ) {
		return false;
	}
	foreach ( explode( ',', MOMENTIVE_UTM_SIGNATURES ) as $signature ) {
		if ( $value === strtolower( trim( $signature ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Strip only the ChatGPT/OpenAI-flagged UTM params from a URL's query string,
 * leaving every other param (including legitimate UTM params) untouched.
 *
 * Returns [ $new_url, $stripped_keys_map ].
 */
function momentive_utm_clean_url( string $url ): array {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['query'] ) ) {
		return [ $url, [] ];
	}

	parse_str( $parts['query'], $query_args );
	if ( empty( $query_args ) ) {
		return [ $url, [] ];
	}

	$utm_keys = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
	$stripped = [];

	foreach ( $utm_keys as $key ) {
		if ( isset( $query_args[ $key ] ) && momentive_utm_is_chatgpt_signature( (string) $query_args[ $key ] ) ) {
			$stripped[ $key ] = $query_args[ $key ];
			unset( $query_args[ $key ] );
		}
	}

	if ( empty( $stripped ) ) {
		return [ $url, [] ];
	}

	$new_query = http_build_query( $query_args );

	$new_url = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' )
		. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
		. ( $parts['path'] ?? '' )
		. ( $new_query ? '?' . $new_query : '' )
		. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );

	return [ $new_url, $stripped ];
}

/**
 * True if an anchor's inner HTML, once tags/entities/whitespace are
 * stripped, is empty — i.e. the anchor has no real visible text (a bare
 * &nbsp;, an empty string, or pure whitespace).
 */
function momentive_utm_anchor_text_is_empty( string $inner_html ): bool {
	$text = wp_strip_all_tags( $inner_html );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = str_replace( "\xC2\xA0", ' ', $text ); // literal U+00A0 (nbsp)
	return '' === trim( $text );
}

/**
 * Process one post's content: clean flagged UTM params, unwrap phantom
 * anchors. Returns [ $new_content, $stats ].
 */
function momentive_utm_process_content( string $content ): array {
	$stats = [
		'phantom_removed' => 0,
		'hrefs_cleaned'   => 0,
		'examples'        => [],
	];

	// Non-greedy match across a single <a ...>...</a>, DOTALL so multi-line
	// link text (rare, but possible inside list items/paragraphs) matches too.
	$pattern = '/<a\b([^>]*)\bhref=(["\'])(.*?)\2([^>]*)>(.*?)<\/a>/is';

	$new_content = preg_replace_callback( $pattern, function ( $m ) use ( &$stats ) {
		[ , $pre_attrs, $quote, $href, $post_attrs, $inner ] = $m;

		$decoded_href          = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
		[ $clean_href, $stripped ] = momentive_utm_clean_url( $decoded_href );

		if ( empty( $stripped ) ) {
			// No ChatGPT/OpenAI signature on this link at all — leave untouched.
			return $m[0];
		}

		if ( momentive_utm_anchor_text_is_empty( $inner ) ) {
			$stats['phantom_removed']++;
			$stats['examples'][] = "removed phantom anchor -> {$href}";
			// Unwrap entirely: drop the <a>...</a>, keep whatever was inside
			// (typically just a stray &nbsp;) so surrounding text is untouched.
			return $inner;
		}

		$stats['hrefs_cleaned']++;
		$stats['examples'][] = "cleaned href: {$href} -> {$clean_href}";

		$new_href = esc_attr( $clean_href );
		return "<a{$pre_attrs}href={$quote}{$new_href}{$quote}{$post_attrs}>{$inner}</a>";
	}, $content );

	return [ $new_content, $stats ];
}

/* ---- Query + run ------------------------------------------------------------ */

$post_types = array_map( 'trim', explode( ',', MOMENTIVE_UTM_POST_TYPES ) );

$query_args = [
	'post_type'      => $post_types,
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
];

if ( $only_id ) {
	$query_args['post_type']   = 'any';
	$query_args['p']           = $only_id;
}

$post_ids = get_posts( $query_args );

if ( $limit && count( $post_ids ) > $limit ) {
	$post_ids = array_slice( $post_ids, 0, $limit );
}

WP_CLI::log( sprintf(
	'Scanning %d post(s) across post type(s): %s',
	count( $post_ids ),
	$only_id ? '(single post by ID)' : implode( ', ', $post_types )
) . "\n" );

$summary = [
	'posts_scanned'    => 0,
	'posts_modified'   => 0,
	'phantom_removed'  => 0,
	'hrefs_cleaned'    => 0,
];
$warnings = [];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		continue;
	}

	$summary['posts_scanned']++;

	[ $new_content, $stats ] = momentive_utm_process_content( $post->post_content );

	if ( 0 === $stats['phantom_removed'] && 0 === $stats['hrefs_cleaned'] ) {
		continue; // nothing flagged on this post
	}

	$summary['posts_modified']++;
	$summary['phantom_removed'] += $stats['phantom_removed'];
	$summary['hrefs_cleaned']   += $stats['hrefs_cleaned'];

	WP_CLI::log( sprintf(
		'[%d] "%s" — %d phantom anchor(s), %d href(s) cleaned',
		$post_id,
		$post->post_title,
		$stats['phantom_removed'],
		$stats['hrefs_cleaned']
	) );
	foreach ( $stats['examples'] as $example ) {
		WP_CLI::log( "    {$example}" );
	}

	if ( $dry ) {
		WP_CLI::log( '    [dry] would write updated post_content' );
		continue;
	}

	// wp_update_post calls wp_unslash() internally on all post data; wp_slash()
	// here ensures any backslashes surviving from entity-decoded hrefs aren't
	// stripped (same gotcha documented for the HubSpot block patches).
	$res = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $new_content ] ), true );
	if ( is_wp_error( $res ) ) {
		WP_CLI::warning( "  wp_update_post failed: " . $res->get_error_message() );
		$warnings[] = "{$post->post_title} (#{$post_id}): wp_update_post failed — " . $res->get_error_message();
	} else {
		clean_post_cache( $post_id );
		WP_CLI::log( '    wrote updated post_content' );
	}
}

/* ---- Summary ------------------------------------------------------------ */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-18s %d', $k, $v ) );
}

if ( $warnings ) {
	WP_CLI::log( "\n== Warnings (" . count( $warnings ) . ") ==" );
	foreach ( $warnings as $line ) {
		WP_CLI::log( '  ' . $line );
	}
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to apply changes.' : 'Patch complete.' );
