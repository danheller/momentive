<?php
/**
 * report-rebuild-progress.php
 *
 * Read-only inventory of the REBUILT site: counts posts per post type AND
 * classifies each post as actually-rebuilt vs. still-raw-imported-content.
 *
 * Why the classification step exists: some CPTs (solutions, posts,
 * testimonials) were bulk-loaded early on via WP Import from the legacy
 * Elementor export. Posts/testimonials have since been rebuilt by hand, but
 * some Solutions posts still only have whatever the import brought over — a
 * plain post-type count would report those as "done" when they aren't. The
 * WordPress Importer carries legacy postmeta along with the content, so an
 * imported-but-untouched post still has non-empty `_elementor_data` /
 * `_elementor_edit_mode` meta. That meta is the signal this script uses:
 *
 *   - `_elementor_data` (or `_elementor_edit_mode`) present & non-empty
 *       → "import_remnant" (still raw Elementor content, not rebuilt)
 *   - post_content contains a `<!-- wp: ... -->` block comment
 *       → "rebuilt" (someone has written it in the block editor)
 *   - post_content is empty
 *       → "empty" (post shell exists, nothing written yet)
 *   - anything else (has content, no blocks, no Elementor meta)
 *       → "needs_review" (hand-pasted HTML, classic-editor content, etc.)
 *
 * This is a heuristic, not a guarantee — spot check a few "rebuilt" rows
 * per CPT the first time you run this against a given post type.
 *
 * Run from the theme's WP-CLI context (the rebuilt site):
 *
 *   wp eval-file migrations/report-rebuild-progress.php
 *   wp eval-file migrations/report-rebuild-progress.php out=rebuild-counts.csv
 *   wp eval-file migrations/report-rebuild-progress.php types=solutions,product
 *   wp eval-file migrations/report-rebuild-progress.php urls=1
 *
 * Flags (positional — see migrate-whitepapers.php header for why):
 *
 *   out=<path>        → counts CSV output path (default: ./rebuild-progress-{date}.csv)
 *   types=a,b,c       → limit to specific post type slugs (default: all public types)
 *   status=a,b,c      → limit to specific post statuses (default: publish,draft,pending,future)
 *   urls=1            → ALSO write a per-post URL list (see below) — off by default
 *   urls_out=<path>   → URL list output path (default: ./rebuild-urls-{date}.csv)
 *   domain=<url>      → public domain to build live URLs against (default: https://unmomento.wpenginepowered.com)
 *
 * The `urls=1` output includes each post's classification bucket alongside
 * its live URL, so migrations/report-progress-summary.php can preferentially
 * sample "rebuilt" pages (not import remnants) when pairing them with a
 * matching legacy URL by slug. Local (`wp eval-file`) permalinks aren't the
 * public URLs, so this strips the scheme+host from get_permalink() via
 * wp_make_link_relative() and rebuilds the URL against `domain` instead.
 *
 * Deliberately does NOT hardcode a list of "the rebuilt CPTs" — it walks
 * whatever post types are actually registered right now, so it stays
 * accurate as CPTs get added (this theme has picked up migrate-guides.php
 * and migrate-infographics.php since CLAUDE.md's "pending" list was last
 * written, for example) without needing this script edited to match.
 *
 * Makes no writes. Safe to re-run any time.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file report-rebuild-progress.php [out=path] [types=a,b] [status=a,b] [urls=1] [domain=https://...]' . PHP_EOL );
}

const MOMENTIVE_REBUILD_REPORT_EXCLUDE = [
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

const MOMENTIVE_REBUILD_DEFAULT_DOMAIN = 'https://unmomento.wpenginepowered.com';

/**
 * Parse positional flags from `wp eval-file` $args.
 */
function momentive_rebuild_report_get_flags( array $argv = [] ): array {
	$flags = [
		'out'      => '',
		'types'    => [],
		'status'   => [ 'publish', 'draft', 'pending', 'future' ],
		'urls'     => false,
		'urls_out' => '',
		'domain'   => MOMENTIVE_REBUILD_DEFAULT_DOMAIN,
	];

	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( str_starts_with( $tok, 'out=' ) ) {
			$flags['out'] = substr( $tok, 4 );
		} elseif ( str_starts_with( $tok, 'types=' ) ) {
			$flags['types'] = array_values( array_filter( array_map( 'trim', explode( ',', substr( $tok, 6 ) ) ) ) );
		} elseif ( str_starts_with( $tok, 'status=' ) ) {
			$flags['status'] = array_values( array_filter( array_map( 'trim', explode( ',', substr( $tok, 7 ) ) ) ) );
		} elseif ( str_starts_with( $tok, 'urls_out=' ) ) {
			$flags['urls_out'] = substr( $tok, 9 );
		} elseif ( str_starts_with( $tok, 'urls=' ) ) {
			$flags['urls'] = ! in_array( substr( $tok, 5 ), [ '0', 'false', '' ], true );
		} elseif ( 'urls' === $tok ) {
			$flags['urls'] = true;
		} elseif ( str_starts_with( $tok, 'domain=' ) ) {
			$flags['domain'] = substr( $tok, 7 );
		}
	}

	return $flags;
}

/**
 * Classify a single post as rebuilt / import_remnant / empty / needs_review.
 */
/**
 * A single empty paragraph block — the block editor adds this by default
 * whenever a post with no blocks yet is opened and saved. Present on its own
 * (with no other block markup) it's not evidence of rebuilding, just an
 * artifact of the post having been opened once — strip it before checking
 * for "real" block content below.
 */
const MOMENTIVE_REBUILD_TRIVIAL_EMPTY_BLOCK = '#<!--\s*wp:paragraph\s*-->\s*<p>\s*</p>\s*<!--\s*/wp:paragraph\s*-->\s*#i';

function momentive_rebuild_report_classify( int $post_id, string $content ): string {
	// Check for real block content FIRST. Rebuilding a post in the block
	// editor does not automatically clear the Elementor meta an earlier bulk
	// WP Import left behind — several genuinely-rebuilt Solutions posts
	// still carry stale `_elementor_data`/`_elementor_edit_mode`, which
	// previously misclassified them as import_remnant despite having real
	// `<!-- wp: -->` content. A post with actual block markup is
	// unambiguously rebuilt regardless of what legacy meta lingers.
	//
	// One caveat found afterward: a post that is still raw pasted-HTML
	// (never actually rebuilt) but was opened once in the block editor can
	// pick up a single trailing empty `wp:paragraph` block with no edits
	// otherwise made. Strip that specific trivial pattern before testing,
	// so a raw-HTML post isn't misclassified as "rebuilt" on the strength of
	// an accidental empty block alone.
	$significant_content = preg_replace( MOMENTIVE_REBUILD_TRIVIAL_EMPTY_BLOCK, '', $content );
	if ( str_contains( $significant_content, '<!-- wp:' ) ) {
		return 'rebuilt';
	}

	$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
	$elementor_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );

	if ( ( is_string( $elementor_data ) && trim( $elementor_data ) !== '' && trim( $elementor_data ) !== '[]' )
		|| ( is_string( $elementor_mode ) && trim( $elementor_mode ) !== '' ) ) {
		return 'import_remnant';
	}

	if ( trim( $content ) === '' ) {
		return 'empty';
	}

	return 'needs_review';
}

/**
 * Build a live URL for a post by stripping the local scheme+host from its
 * permalink and rebuilding against the public domain.
 */
function momentive_rebuild_live_url( int $post_id, string $domain ): string {
	return rtrim( $domain, '/' ) . wp_make_link_relative( (string) get_permalink( $post_id ) );
}

function momentive_rebuild_report_run( array $argv ): void {
	$flags = momentive_rebuild_report_get_flags( $argv );

	$post_types = ! empty( $flags['types'] )
		? $flags['types']
		: array_values( array_diff(
			get_post_types( [ 'public' => true ], 'names' ),
			MOMENTIVE_REBUILD_REPORT_EXCLUDE
		) );

	sort( $post_types );

	$run_at    = gmdate( 'c' );
	$rows      = [];
	$url_rows  = [];
	$buckets   = [ 'rebuilt', 'import_remnant', 'empty', 'needs_review' ];

	foreach ( $post_types as $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::warning( sprintf( 'Post type "%s" is not registered on this site — skipped.', $post_type ) );
			continue;
		}

		$obj   = get_post_type_object( $post_type );
		$label = $obj ? $obj->label : $post_type;

		$post_ids = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => $flags['status'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$row = [
			'post_type' => $post_type,
			'label'     => $label,
			'total'     => count( $post_ids ),
		];
		foreach ( $buckets as $bucket ) {
			$row[ $bucket ] = 0;
		}

		foreach ( $post_ids as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			$bucket  = momentive_rebuild_report_classify( $post_id, $content );
			$row[ $bucket ]++;

			if ( $flags['urls'] ) {
				$url_rows[] = [
					'post_type'      => $post_type,
					'label'          => $label,
					'post_id'        => $post_id,
					'slug'           => (string) get_post_field( 'post_name', $post_id ),
					'title'          => (string) get_the_title( $post_id ),
					'url'            => momentive_rebuild_live_url( $post_id, $flags['domain'] ),
					'classification' => $bucket,
				];
			}
		}

		$rows[] = $row;
	}

	if ( empty( $rows ) ) {
		WP_CLI::error( 'No matching post types found — nothing to report.' );
		return;
	}

	$header   = array_merge( [ 'post_type', 'label', 'total' ], $buckets );
	$out_path = $flags['out'] ?: ( 'rebuild-progress-' . gmdate( 'Y-m-d' ) . '.csv' );

	$fh = fopen( $out_path, 'w' );
	fwrite( $fh, '# generated_at,' . $run_at . PHP_EOL );
	fputcsv( $fh, $header );
	foreach ( $rows as $row ) {
		fputcsv( $fh, array_map( static fn( $key ) => $row[ $key ] ?? '', $header ) );
	}
	fclose( $fh );

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Rebuilt site — snapshot as of %s', $run_at ) );
	WP_CLI::log(
		str_pad( 'Post type', 22 ) . str_pad( 'Total', 7 ) .
		implode( '  ', array_map( static fn( $b ) => str_pad( $b, 15 ), $buckets ) )
	);
	foreach ( $rows as $row ) {
		WP_CLI::log(
			str_pad( $row['post_type'], 22 ) . str_pad( (string) $row['total'], 7 ) .
			implode( '  ', array_map( static fn( $b ) => str_pad( (string) $row[ $b ], 15 ), $buckets ) )
		);
	}

	WP_CLI::log( '' );
	WP_CLI::log( 'Note: "import_remnant" rows are posts with leftover Elementor meta from an' );
	WP_CLI::log( 'early WP Import — they exist, but are not actually rebuilt yet.' );

	WP_CLI::success( sprintf( 'Wrote %d post types to %s (generated %s).', count( $rows ), $out_path, $run_at ) );

	if ( $flags['urls'] ) {
		$urls_out_path = $flags['urls_out'] ?: ( 'rebuild-urls-' . gmdate( 'Y-m-d' ) . '.csv' );
		$url_header    = [ 'post_type', 'label', 'post_id', 'slug', 'title', 'url', 'classification' ];

		$fh = fopen( $urls_out_path, 'w' );
		fwrite( $fh, '# generated_at,' . $run_at . ',domain,' . $flags['domain'] . PHP_EOL );
		fputcsv( $fh, $url_header );
		foreach ( $url_rows as $row ) {
			fputcsv( $fh, array_map( static fn( $key ) => $row[ $key ] ?? '', $url_header ) );
		}
		fclose( $fh );

		WP_CLI::success( sprintf( 'Wrote %d post URLs to %s (domain: %s).', count( $url_rows ), $urls_out_path, $flags['domain'] ) );
	}
}

// `wp eval-file` delivers positional args as a script-scope $args variable.
momentive_rebuild_report_run( isset( $args ) && is_array( $args ) ? $args : [] );
