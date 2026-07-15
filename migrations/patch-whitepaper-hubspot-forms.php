<?php
/**
 * Patch: fix malformed HubSpot form blocks in migrated whitepaper posts.
 *
 * The initial migration produced broken block comments for the acf/hubspot-form
 * block due to two bugs in the migration script:
 *
 *   1. Wrong data key format — the block used {hubspot_embed_code: "...",
 *      _hubspot_embed_code: "field_key"} (field-name + shadow-key format)
 *      instead of {field_6a2873ba3bf87: "..."} (field-key-direct format).
 *
 *   2. wp_slash() missing on wp_update_post — wp_update_post calls wp_unslash()
 *      internally on all post data. Without wp_slash() wrapping, every backslash
 *      in the JSON was stripped: \" became " (unescaped quotes, invalid JSON)
 *      and \r\n became rn (breaking line endings in the embed code).
 *
 * This patch:
 *   - Reads the legacy WXR to get the hubspot_form_code for each post.
 *   - Rebuilds the correct block comment using field-key-direct format and
 *     JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP encoding (same as migrate-whitepapers.php).
 *   - Replaces the broken block comment in post_content via wp_update_post()
 *     with wp_slash() wrapping.
 *
 * Usage (dry run, safe default):
 *   wp eval-file migrations/patch-whitepaper-hubspot-forms.php --user=<admin>
 *
 * Usage (live write):
 *   wp eval-file migrations/patch-whitepaper-hubspot-forms.php live --user=<admin>
 *
 * Optional:
 *   wp eval-file migrations/patch-whitepaper-hubspot-forms.php live only=build-vs-buy-ai-platform --user=<admin>
 *
 * Flags (positional, same pattern as other migration scripts):
 *   live / go      → write changes (default: dry run)
 *   only=<slug>    → patch one post by its rebuilt slug
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-whitepaper-hubspot-forms.php [live] [only=slug] --user=<admin>' . PHP_EOL );
}

/* ---- Field key constants -------------------------------------------------- */

const FK_PWHF_HS_EMBED    = 'field_6a2873ba3bf87';
const FK_PWHF_HS_TWO_STEP = 'field_6a35626f3a11b';

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
WP_CLI::log( '  Whitepaper patch: HubSpot form blocks' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
if ( '' !== $only ) { WP_CLI::log( '  only: "' . $only . '"' ); }
WP_CLI::log( '=====================================================' );

/* ---- Build slug → embed code map from WXR --------------------------------- */

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

$form_map = []; // slug → raw hubspot_form_code string

foreach ( $all_items as $item ) {
	if ( false === strpos( $item, 'post_type><![CDATA[whitepapers]]>' ) ) {
		continue;
	}
	if ( ! preg_match( '#<wp:post_name><!\[CDATA\[(.*?)\]\]>#', $item, $sm ) ) {
		continue;
	}
	$slug = $sm[1];

	$form_code = '';
	if ( preg_match_all(
		'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
		$item, $mm, PREG_SET_ORDER
	) ) {
		foreach ( $mm as $pair ) {
			if ( 'hubspot_form_code' === $pair[1] ) {
				$form_code = $pair[2];
				break;
			}
		}
	}

	$form_map[ $slug ] = $form_code;
}

WP_CLI::log( sprintf(
	'Legacy WXR: %d whitepaper slugs loaded (%d with form code).',
	count( $form_map ),
	count( array_filter( $form_map ) )
) );

/* ---- Block builder -------------------------------------------------------- */

/**
 * Build a correct acf/hubspot-form block comment.
 *
 * Uses field-key-direct format and JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP
 * to safely encode <, >, ", & in the embed code. Must be wrapped in wp_slash()
 * before passing to wp_update_post (see below).
 *
 * Auto-injects the hsforms loader <script> when only hbspt.forms.create()
 * is present (common legacy omission).
 */
function momentive_pwhf_build_block( string $embed_code ): string {
	$embed_code = trim( $embed_code );
	if (
		'' !== $embed_code
		&& str_contains( $embed_code, 'hbspt.forms.create' )
		&& ! str_contains( $embed_code, 'js.hsforms.net' )
	) {
		$embed_code = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $embed_code;
	}

	$attrs = wp_json_encode( [
		'name' => 'acf/hubspot-form',
		'data' => [
			FK_PWHF_HS_EMBED    => $embed_code,
			FK_PWHF_HS_TWO_STEP => '0',
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP );

	return "<!-- wp:acf/hubspot-form {$attrs} /-->";
}

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
	'processed'        => 0,
	'block_fixed'      => 0,
	'no_form_code'     => 0,  // WXR has no embed code for this slug
	'no_block_found'   => 0,  // post_content has no acf/hubspot-form block
	'slug_not_in_wxr'  => 0,
	'already_correct'  => 0,  // block already uses field-key-direct format
];
$warnings = [];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$slug    = get_post_field( 'post_name', $post_id );
	$title   = get_the_title( $post_id );
	$summary['processed']++;

	WP_CLI::log( "[{$post_id}] {$title} ({$slug})" );

	/* 1. Look up embed code ------------------------------------------------- */
	if ( ! array_key_exists( $slug, $form_map ) ) {
		WP_CLI::log( "  slug not in WXR — skipped" );
		$summary['slug_not_in_wxr']++;
		continue;
	}

	$embed_code = $form_map[ $slug ];
	if ( '' === $embed_code ) {
		WP_CLI::log( "  no hubspot_form_code in WXR — no HubSpot block to fix" );
		$summary['no_form_code']++;
		continue;
	}

	/* 2. Read post_content -------------------------------------------------- */
	$post_content = get_post_field( 'post_content', $post_id );

	if ( false === strpos( $post_content, 'wp:acf/hubspot-form' ) ) {
		WP_CLI::log( "  no acf/hubspot-form block in post_content — skipped" );
		$summary['no_block_found']++;
		continue;
	}

	/* 3. Skip posts that already use field-key-direct format ---------------- */
	// If the block already stores data with the field key as the primary key,
	// the content went through the fixed migration; no patch needed.
	$already_correct = (bool) preg_match(
		'#wp:acf/hubspot-form \{[^}]*"' . FK_PWHF_HS_EMBED . '"#',
		$post_content
	);
	if ( $already_correct ) {
		WP_CLI::log( "  block already uses field-key-direct format — no action" );
		$summary['already_correct']++;
		continue;
	}

	/* 4. Build the corrected block ------------------------------------------ */
	$new_block = momentive_pwhf_build_block( $embed_code );

	/* 5. Replace the broken block comment in post_content ------------------- */
	// The broken block's JSON is invalid (unescaped quotes), so we can't parse
	// it. Use a pattern that matches the whole self-closing block comment.
	$new_content = preg_replace(
		'#<!-- wp:acf/hubspot-form .*? /-->#s',
		$new_block,
		$post_content,
		1 // replace only the first occurrence
	);

	if ( $new_content === $post_content ) {
		WP_CLI::warning( "  regex replace produced no change — unexpected; check post manually" );
		$warnings[] = "{$title} ({$slug}): regex replace produced no change";
		continue;
	}

	if ( $dry ) {
		WP_CLI::log( "  [dry] would replace broken block with corrected block" );
		WP_CLI::log( "    portalId in new block: " . ( preg_match( '/portalId[^"]*"(\d+)"/', $embed_code, $pid ) ? $pid[1] : '?' ) );
	} else {
		// wp_update_post calls wp_unslash() internally on all post data.
		// wp_slash() here ensures the JSON escapes in the block comment survive
		// the unslash pass intact.
		$res = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $new_content ] ), true );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( "  wp_update_post failed: " . $res->get_error_message() );
			$warnings[] = "{$title}: wp_update_post failed — " . $res->get_error_message();
		} else {
			// Invalidate the object cache so the updated content is read back correctly.
			clean_post_cache( $post_id );
			WP_CLI::log( "  fixed HubSpot form block" );
		}
	}
	$summary['block_fixed']++;
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-22s %d', $k, $v ) );
}

if ( $warnings ) {
	WP_CLI::log( "\n== Warnings (" . count( $warnings ) . ") ==" );
	foreach ( $warnings as $line ) {
		WP_CLI::log( '  ' . $line );
	}
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to apply changes.' : 'Patch complete.' );
