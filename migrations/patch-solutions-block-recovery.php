<?php
/**
 * Patch: fix migrated Solutions posts that show blocks as "corrupted" in the
 * editor (fine on the live front end) needing "Attempt Block Recovery".
 *
 * Two distinct bugs in migrate-solutions.php produced post_content that
 * doesn't match what the block editor's own save() functions would
 * regenerate from the stored attributes — see CLAUDE.md's "Two 'block
 * recovery' bugs found via before/after export comparison (2026-07-28)"
 * writeup for the full diagnosis. Both are purely editor-validation issues;
 * neither affects front-end rendering, which is why they went unnoticed
 * until a post was actually opened for editing.
 *
 *   1. Right-positioned core/media-text rows (momentive_sol_features_block())
 *      — emitted figure-then-content unconditionally with "has-media-on-
 *      the-right" appended last in the class list. core/media-text's real
 *      save() swaps to content-then-figure and sorts that class first when
 *      mediaPosition is "right".
 *
 *   2. momentive/impact-stat (momentive_sol_stats_block()) — emitted as a
 *      self-closing `<!-- wp:momentive/impact-stat {...} /-->` with no
 *      inner HTML. This block's save.js actually serializes real markup
 *      (border div, content div, prefix/number/suffix spans, label), so a
 *      self-closing instance can never validate.
 *
 * This patch fixes both via scoped regex replacement directly on
 * post_content — the same "narrow, targeted regex" approach already
 * established for inc/swoop-heading-cleanup.php, and deliberately NOT a
 * full parse_blocks()/serialize_blocks() round-trip (which risks
 * "unexpected or invalid content" errors on unrelated blocks elsewhere in
 * the same post — see that file's docblock for the prior incident this is
 * avoiding). Both regexes are idempotent: once fixed, a post's markup no
 * longer matches the "broken" pattern, so re-running this script is safe.
 *
 * Usage (dry run, safe default):
 *   wp eval-file migrations/patch-solutions-block-recovery.php
 *
 * Usage (live write):
 *   wp eval-file migrations/patch-solutions-block-recovery.php live
 *
 * Optional:
 *   wp eval-file migrations/patch-solutions-block-recovery.php live only=budget-software
 *   wp eval-file migrations/patch-solutions-block-recovery.php live limit=5
 *
 * Flags (positional, same pattern as other migration scripts):
 *   live / go     → write changes (default: dry run, no writes)
 *   only=<slug>   → patch a single post by its rebuilt slug
 *   limit=<n>     → stop after N posts
 *
 * No --user=<admin> needed — this only rewrites post_content strings,
 * no media sideloading or Safe SVG capability is involved.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-solutions-block-recovery.php [live] [only=slug] [limit=n]' . PHP_EOL );
}

/* ---- Flags ------------------------------------------------------------ */

$_flags = isset( $args ) && is_array( $args ) ? $args : [];
$dry    = true;
$only   = '';
$limit  = 0;

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )                    { $dry   = false; }
	elseif ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) )  { $dry   = true;  }
	elseif ( str_starts_with( $tok, 'only=' ) )                         { $only  = substr( $tok, 5 ); }
	elseif ( str_starts_with( $tok, 'limit=' ) )                        { $limit = (int) substr( $tok, 6 ); }
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  Solutions patch: block-recovery fixes' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
if ( '' !== $only ) { WP_CLI::log( '  only:  "' . $only . '"' ); }
if ( $limit )        { WP_CLI::log( '  limit: ' . $limit . ' posts' ); }
WP_CLI::log( '=====================================================' );

/* ---- Fix 1: right-positioned media-text row order/class order -------- */

/**
 * Matches the broken div wrapper produced by the old
 * momentive_sol_features_block() for mediaPosition:"right" rows:
 * figure first, then the content div, with "has-media-on-the-right"
 * appended last to the class list. Once fixed, the class order/child
 * order no longer matches this pattern, so re-matching naturally stops —
 * idempotent by construction, no separate "already fixed" check needed.
 */
const MOMENTIVE_PSBR_MEDIA_TEXT_PATTERN =
	'#<div class="wp-block-media-text is-stacked-on-mobile no-shadow has-media-on-the-right"([^>]*)>'
	. '<figure class="wp-block-media-text__media">(.*?)</figure>'
	. '(<div class="wp-block-media-text__content">.*?</div>)'
	. '</div>#s';

function momentive_psbr_fix_media_text( string $content, int &$count ): string {
	return preg_replace_callback(
		MOMENTIVE_PSBR_MEDIA_TEXT_PATTERN,
		static function ( array $m ): string {
			return '<div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile no-shadow"' . $m[1] . '>'
				. $m[3]
				. '<figure class="wp-block-media-text__media">' . $m[2] . '</figure>'
				. '</div>';
		},
		$content,
		-1,
		$count
	);
}

/* ---- Fix 2: self-closing momentive/impact-stat -> full save() markup - */

/** Matches only the broken self-closing form; already-fixed instances use
 * an opening/closing comment pair instead and won't match. */
const MOMENTIVE_PSBR_IMPACT_STAT_PATTERN = '#<!-- wp:momentive/impact-stat (\{.*?\}) /-->#s';

/**
 * Rebuilds the full rendered markup for one momentive/impact-stat instance
 * from its (already-valid) attributes JSON, mirroring
 * blocks/impact-stat/src/save.js and block.json's defaults exactly — same
 * logic as momentive_sol_impact_stat_html() in migrate-solutions.php,
 * duplicated here since this is a standalone wp eval-file script.
 */
function momentive_psbr_impact_stat_html( array $attrs, string $original_comment_json ): string {
	$prefix   = (string) ( $attrs['statPrefix'] ?? '' );
	$number   = $attrs['statNumber'] ?? 0;
	$suffix   = (string) ( $attrs['statSuffix'] ?? '' );
	$label    = (string) ( $attrs['statLabel'] ?? '' );
	$accent   = (string) ( $attrs['accentColor'] ?? '#E8611A' );
	$duration = $attrs['animationDuration'] ?? 1800;
	$animate  = array_key_exists( 'animate', $attrs ) ? (bool) $attrs['animate'] : true;

	// Number.isInteger() equivalent — checks the numeric value, not the PHP
	// type (json_decode may hand back "5000.0"-style values as float even
	// when the value is a whole number).
	$is_integer = ( (float) $number === (float) (int) $number );
	$final = $is_integer ? number_format( (int) $number ) : (string) $number;

	return '<!-- wp:momentive/impact-stat ' . $original_comment_json . ' -->'
		. '<div class="wp-block-momentive-impact-stat impact-stat" style="--accent-color:' . esc_attr( $accent ) . '"'
		. ' data-stat-number="' . esc_attr( $number ) . '"'
		. ' data-stat-prefix="' . esc_attr( $prefix ) . '"'
		. ' data-stat-suffix="' . esc_attr( $suffix ) . '"'
		. ' data-stat-integer="' . ( $is_integer ? 'true' : 'false' ) . '"'
		. ' data-animation-duration="' . esc_attr( $duration ) . '"'
		. ' data-animate="' . ( $animate ? 'true' : 'false' ) . '">'
		. '<div class="impact-stat__border"></div>'
		. '<div class="impact-stat__content">'
		. '<p class="impact-stat__value" aria-label="' . esc_attr( $prefix . $number . $suffix ) . '">'
		. ( '' !== $prefix ? '<span class="impact-stat__prefix" aria-hidden="true">' . esc_html( $prefix ) . '</span>' : '' )
		. '<span class="impact-stat__number" aria-hidden="true" data-final="' . esc_attr( $final ) . '">0</span>'
		. ( '' !== $suffix ? '<span class="impact-stat__suffix" aria-hidden="true">' . esc_html( $suffix ) . '</span>' : '' )
		. '</p>'
		. ( '' !== $label ? '<p class="impact-stat__label">' . esc_html( $label ) . '</p>' : '' )
		. '</div></div>'
		. '<!-- /wp:momentive/impact-stat -->';
}

function momentive_psbr_fix_impact_stat( string $content, int &$count, array &$unparsed ): string {
	return preg_replace_callback(
		MOMENTIVE_PSBR_IMPACT_STAT_PATTERN,
		static function ( array $m ) use ( &$unparsed ): string {
			$attrs = json_decode( $m[1], true );
			if ( ! is_array( $attrs ) ) {
				// Malformed JSON we can't safely parse — leave untouched and
				// flag for manual review rather than guessing.
				$unparsed[] = $m[0];
				return $m[0];
			}
			return momentive_psbr_impact_stat_html( $attrs, $m[1] );
		},
		$content,
		-1,
		$count
	);
}

/* ---- Query migrated Solutions posts ------------------------------------ */

$query_args = [
	'post_type'      => 'solutions',
	'post_status'    => 'any',
	'posts_per_page' => $limit > 0 ? $limit : -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'meta_key'       => '_momentive_migration_run',
];
if ( '' !== $only ) {
	$query_args['name'] = $only;
	unset( $query_args['posts_per_page'] );
}

$post_ids = get_posts( $query_args );
WP_CLI::log( sprintf( 'Found %d migrated Solutions post(s) to check.', count( $post_ids ) ) . "\n" );

/* ---- Patch each post ---------------------------------------------------- */

$summary = [
	'processed'                => 0,
	'media_text_rows_fixed'    => 0, // instance count, not post count
	'impact_stats_fixed'       => 0, // instance count, not post count
	'posts_changed'            => 0,
	'posts_unchanged'          => 0,
];
$warnings = [];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$slug    = get_post_field( 'post_name', $post_id );
	$title   = get_the_title( $post_id );
	$summary['processed']++;

	$post_content = get_post_field( 'post_content', $post_id );

	$media_count = 0;
	$stat_count  = 0;
	$unparsed    = [];

	$new_content = momentive_psbr_fix_media_text( $post_content, $media_count );
	$new_content = momentive_psbr_fix_impact_stat( $new_content, $stat_count, $unparsed );

	if ( $unparsed ) {
		foreach ( $unparsed as $bad ) {
			$warnings[] = "{$title} ({$slug}): couldn't parse impact-stat attrs JSON — left untouched: " . substr( $bad, 0, 120 ) . '...';
		}
	}

	if ( 0 === $media_count && 0 === $stat_count ) {
		$summary['posts_unchanged']++;
		continue;
	}

	WP_CLI::log( sprintf(
		'[%d] %s (%s) — %d media-text row(s), %d impact-stat(s)',
		$post_id, $title, $slug, $media_count, $stat_count
	) );

	$summary['media_text_rows_fixed'] += $media_count;
	$summary['impact_stats_fixed']    += $stat_count;
	$summary['posts_changed']++;

	if ( $dry ) {
		WP_CLI::log( '  [dry-run] would update post_content' );
		continue;
	}

	// wp_update_post calls wp_unslash() internally on all post data —
	// wp_slash() here ensures the JSON escapes already present in the
	// existing block comments (e.g. the impact-stat attrs) survive the
	// unslash pass intact. Same convention as every other patch script in
	// this folder that rewrites block markup containing JSON.
	$res = wp_update_post( wp_slash( [ 'ID' => $post_id, 'post_content' => $new_content ] ), true );
	if ( is_wp_error( $res ) ) {
		WP_CLI::warning( "  wp_update_post failed: " . $res->get_error_message() );
		$warnings[] = "{$title} ({$slug}): wp_update_post failed — " . $res->get_error_message();
		continue;
	}
	clean_post_cache( $post_id );
	WP_CLI::log( '  fixed' );
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-26s %d', $k, $v ) );
}

if ( $warnings ) {
	WP_CLI::log( "\n== Warnings (" . count( $warnings ) . ") ==" );
	foreach ( $warnings as $line ) {
		WP_CLI::log( '  ' . $line );
	}
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to apply changes.' : 'Patch complete.' );
