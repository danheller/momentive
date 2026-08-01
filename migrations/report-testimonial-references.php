<?php
/**
 * report-testimonial-references.php
 *
 * Read-only inventory of every place a `testimonials` post is referenced by
 * a hardcoded ID, ahead of any manual cleanup of duplicate testimonial posts
 * (see migrations/momentive.testimonials.rebuild.2026-07-27.xml — 11 groups /
 * 23 posts share byte-identical quote text with a slightly different post
 * title, the kind of thing a case-study/webinar/whitepaper migration's
 * create-and-reference dedup logic can produce when the same testimonial
 * gets imported twice under a different name).
 *
 * Why this matters: `momentive/testimonial` (blocks/testimonial/block.php)
 * is a JS-registered dynamic block with `save() => null` — every attribute,
 * including a hand-picked `testimonialId`, lives in the block comment's JSON
 * inside `post_content`, not in a queryable ACF field. There is exactly one
 * other way the block resolves a testimonial: from Query Loop context
 * (`postType === 'testimonials'`), which pulls whatever the loop's query
 * returns and needs no hardcoded ID — those usages are unaffected by
 * deleting a specific testimonial post, since the loop simply won't return
 * it anymore. The risk is entirely in the *other* mode: a product, solution,
 * case study, webinar, or whitepaper page where an editor (or a migration
 * script) picked one specific testimonial by ID outside a Query Loop. Delete
 * that post and the block silently renders nothing (see the block's own
 * `if ( ! $testimonial_id ) return '';` early-out) — no error, just a missing
 * testimonial on a live page.
 *
 * This script parses every public post type's post_content with
 * parse_blocks(), recursively walks child blocks, and collects every
 * `momentive/testimonial` block that carries a non-zero `testimonialId`
 * attribute. It has no write path at all — safe to re-run anytime, including
 * after edits, to re-check before deleting anything.
 *
 * Run:
 *
 *   wp eval-file migrations/report-testimonial-references.php
 *   wp eval-file migrations/report-testimonial-references.php out=refs.csv
 *   wp eval-file migrations/report-testimonial-references.php only=2387,11152,3222
 *
 * Flags (positional — `wp eval-file` doesn't accept --flags):
 *
 *   out=<path>   → CSV of every reference found (default: ./testimonial-references-{date}.csv)
 *   only=<ids>   → after the full scan, print a focused safe/unsafe summary
 *                  for just this comma-separated list of testimonial post IDs
 *                  (default: the 23 duplicate-quote IDs found in the
 *                  2026-07-27 export — see MOMENTIVE_TESTIMONIAL_DUPE_IDS)
 *
 * Makes no writes. Safe to re-run any time.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/report-testimonial-references.php [out=path] [only=id,id,...]' . PHP_EOL );
}

/**
 * The 23 testimonial post IDs across 11 exact-quote-text duplicate groups,
 * found by diffing migrations/momentive.testimonials.rebuild.2026-07-27.xml
 * (250 published testimonial posts) on normalized quote content. Grouped
 * here only for the `only=` default — re-derive this list yourself if the
 * export is regenerated later and the numbers may have shifted.
 */
const MOMENTIVE_TESTIMONIAL_DUPE_GROUPS = [
	[ 2387, 11152 ],                 // Shannon Reed / Shannon R., Senior Director of Engagement
	[ 3222, 11161, 12150 ],          // American Therapeutic Recreation Association / Maggie Bayerl / Maggie B.
	[ 5317, 12119 ],                 // Jasamine Riel / Jasamine R.
	[ 6053, 12120 ],                 // Lee Veid-Norstern / Lee N.
	[ 7217, 12121 ],                 // Kerry Lennon / Kerry L.
	[ 7218, 12122 ],                 // Christina Bova / Christina B.
	[ 7220, 12123 ],                 // Shelley O'Brien / Shelley O.
	[ 10049, 12148 ],                // Megan Sandidge / Megan S.
	[ 10145, 11151 ],                // Steve D. / Steve Davis
	[ 11159, 12149 ],                // Allison Walsh (x2)
	[ 11149, 12124 ],                // Scott G. (x2)
];

/**
 * Post types to scan for references. Deliberately excludes `testimonials`
 * itself (the source, not a consumer) and non-content types.
 */
const MOMENTIVE_TESTIMONIAL_REF_EXCLUDE = [
	'testimonials',
	'attachment',
	'revision',
	'nav_menu_item',
	'custom_css',
	'customize_changeset',
	'oembed_cache',
	'user_request',
	'wp_block',
	'wp_template',
	'wp_template_part',
	'wp_global_styles',
	'wp_navigation',
];

function momentive_testimonial_ref_get_flags( array $argv = [] ): array {
	$flags = [
		'out'  => '',
		'only' => array_values( array_unique( array_merge( ...MOMENTIVE_TESTIMONIAL_DUPE_GROUPS ) ) ),
	];

	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( str_starts_with( $tok, 'out=' ) ) {
			$flags['out'] = substr( $tok, 4 );
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = array_values( array_filter( array_map( 'intval', explode( ',', substr( $tok, 5 ) ) ) ) );
		}
	}

	return $flags;
}

/**
 * Recursively walk parsed blocks, collecting testimonialId references.
 */
function momentive_testimonial_ref_walk_blocks( array $blocks, array &$found ): void {
	foreach ( $blocks as $block ) {
		if ( ( $block['blockName'] ?? '' ) === 'momentive/testimonial' ) {
			$id = (int) ( $block['attrs']['testimonialId'] ?? 0 );
			if ( $id > 0 ) {
				$found[] = $id;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			momentive_testimonial_ref_walk_blocks( $block['innerBlocks'], $found );
		}
	}
}

function momentive_testimonial_ref_run( array $argv ): void {
	$flags = momentive_testimonial_ref_get_flags( $argv );

	$post_types = array_values( array_diff(
		get_post_types( [ 'public' => true ], 'names' ),
		MOMENTIVE_TESTIMONIAL_REF_EXCLUDE
	) );
	sort( $post_types );

	WP_CLI::log( sprintf( 'Scanning post types: %s', implode( ', ', $post_types ) ) );

	$post_ids = get_posts( [
		'post_type'      => $post_types,
		'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	WP_CLI::log( sprintf( 'Scanning %d posts for hardcoded momentive/testimonial references...', count( $post_ids ) ) );

	// testimonial_id => [ [ post_id, post_type, title ], ... ]
	$refs = [];

	foreach ( $post_ids as $post_id ) {
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( ! str_contains( $content, 'momentive/testimonial' ) ) {
			continue;
		}

		$blocks = parse_blocks( $content );
		$found  = [];
		momentive_testimonial_ref_walk_blocks( $blocks, $found );

		foreach ( array_unique( $found ) as $testimonial_id ) {
			$refs[ $testimonial_id ][] = [
				'post_id'   => $post_id,
				'post_type' => get_post_type( $post_id ),
				'title'     => get_the_title( $post_id ),
			];
		}
	}

	// ── CSV of every reference found ──────────────────────────────────────────

	$out_path = $flags['out'] ?: ( 'testimonial-references-' . gmdate( 'Y-m-d' ) . '.csv' );
	$fh       = fopen( $out_path, 'w' );
	fputcsv( $fh, [ 'testimonial_id', 'testimonial_title', 'referencing_post_id', 'referencing_post_type', 'referencing_post_title' ] );

	foreach ( $refs as $testimonial_id => $rows ) {
		$testimonial_title = get_the_title( $testimonial_id );
		foreach ( $rows as $row ) {
			fputcsv( $fh, [ $testimonial_id, $testimonial_title, $row['post_id'], $row['post_type'], $row['title'] ] );
		}
	}
	fclose( $fh );

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'Found %d distinct testimonial posts referenced by hardcoded ID, across %d referencing blocks. Wrote %s.',
		count( $refs ),
		array_sum( array_map( 'count', $refs ) ),
		$out_path
	) );

	// ── Focused summary for the requested ID list (defaults to the 23 dupes) ──

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Reference check for %d requested testimonial ID(s):', count( $flags['only'] ) ) );
	WP_CLI::log( str_pad( 'ID', 8 ) . str_pad( 'Title', 55 ) . 'Referenced by' );

	foreach ( $flags['only'] as $testimonial_id ) {
		$post = get_post( $testimonial_id );
		if ( ! $post || $post->post_type !== 'testimonials' ) {
			WP_CLI::warning( sprintf( '[%d] Not found as a testimonials post — skipped.', $testimonial_id ) );
			continue;
		}

		if ( empty( $refs[ $testimonial_id ] ) ) {
			$referenced_by = '(no hardcoded references found — still check any Query Loop by author/context manually)';
		} else {
			$referenced_by = implode( '; ', array_map(
				static fn( $r ) => sprintf( '%s #%d (%s)', $r['post_type'], $r['post_id'], $r['title'] ),
				$refs[ $testimonial_id ]
			) );
		}

		WP_CLI::log(
			str_pad( (string) $testimonial_id, 8 ) .
			str_pad( mb_substr( $post->post_title, 0, 52 ), 55 ) .
			$referenced_by
		);
	}

	WP_CLI::log( '' );
	WP_CLI::log( 'Note: "no hardcoded references found" means no post embeds this exact testimonial' );
	WP_CLI::log( 'by ID outside a Query Loop. It can still appear dynamically inside any' );
	WP_CLI::log( 'postType=testimonials Query Loop (e.g. the default product-content testimonials' );
	WP_CLI::log( 'slider) — deleting it just removes it from that rotation, which is safe.' );
}

momentive_testimonial_ref_run( isset( $args ) && is_array( $args ) ? $args : [] );
