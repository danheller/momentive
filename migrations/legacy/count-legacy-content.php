<?php
/**
 * count-legacy-content.php
 *
 * Read-only inventory of the LEGACY (Elementor) site: counts posts per post
 * type and status. This is the "how much is there to rebuild" half of the
 * progress report — the companion script, migrations/report-rebuild-progress.php,
 * does the same job on the REBUILT site and additionally flags whether each
 * post has actually been rebuilt or is just raw imported content. Merge the
 * two CSVs with migrations/report-progress-summary.php.
 *
 * This file lives under the rebuilt theme's migrations/legacy/ folder so it's
 * version-controlled alongside the rest of the migration tooling, but it runs
 * against the LEGACY site's WP-CLI context, not this theme — point --path at
 * the legacy Local site:
 *
 *   wp eval-file "/path/to/momentive-theme/migrations/legacy/count-legacy-content.php" \
 *     --path="/path/to/Local Sites/<legacy-site>/app/public"
 *
 * Flags (positional — `wp eval-file` does not accept --flags; see the header
 * comment in migrate-whitepapers.php for why these arrive as a script-scope
 * $args array):
 *
 *   out=<path>        → counts CSV output path (default: ./legacy-content-counts-{date}.csv)
 *   types=a,b,c       → limit to specific post type slugs (default: all public types)
 *   status=a,b,c      → limit to specific post statuses (default: publish,draft,pending,future)
 *   urls=1            → ALSO write a per-post URL list (see below) — off by default
 *   urls_out=<path>   → URL list output path (default: ./legacy-urls-{date}.csv)
 *   domain=<url>      → public domain to build live URLs against (default: https://momentivesoftware.com)
 *
 * Example:
 *   wp eval-file migrations/legacy/count-legacy-content.php out=legacy-counts.csv --path=...
 *   wp eval-file migrations/legacy/count-legacy-content.php urls=1 --path=...
 *
 * The `urls=1` output exists so migrations/report-progress-summary.php can
 * sample a few live URLs per content type — pairing a legacy page with its
 * rebuilt counterpart (by matching slug) so a teammate can click both and
 * compare. Local (`wp eval-file`) permalinks aren't the public URLs, so this
 * strips the scheme+host from get_permalink() via wp_make_link_relative()
 * and rebuilds the URL against `domain` instead — the path structure is the
 * same locally and live, only the host differs.
 *
 * Excludes WP core / Elementor internal post types automatically (attachment,
 * revisions, nav menu items, Elementor library items, etc.) — edit
 * MOMENTIVE_LEGACY_COUNT_EXCLUDE below if the legacy site has others (some
 * Elementor installs also register things like `elementor-hf`, page builder
 * template CPTs from other plugins, etc. — check the printed list against
 * what you expect and adjust).
 *
 * Makes no writes. Safe to re-run any time; each run is a fresh snapshot,
 * not a diff, so there's nothing to worry about re-running.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file count-legacy-content.php [out=path] [types=a,b] [status=a,b] [urls=1] [domain=https://...] --path=<legacy-site-path>' . PHP_EOL );
}

const MOMENTIVE_LEGACY_COUNT_EXCLUDE = [
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
	'elementor_library',
	'e-floating-buttons',
	'e-landing-page',
];

const MOMENTIVE_LEGACY_DEFAULT_DOMAIN = 'https://momentivesoftware.com';

/**
 * Parse positional flags from `wp eval-file` $args.
 */
function momentive_legacy_count_get_flags( array $argv = [] ): array {
	$flags = [
		'out'      => '',
		'types'    => [],
		'status'   => [ 'publish', 'draft', 'pending', 'future' ],
		'urls'     => false,
		'urls_out' => '',
		'domain'   => MOMENTIVE_LEGACY_DEFAULT_DOMAIN,
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
 * Count posts of a given type/status without loading the full result set.
 */
function momentive_legacy_count_for( string $post_type, string $status ): int {
	$query = new WP_Query( [
		'post_type'      => $post_type,
		'post_status'    => $status,
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
	] );

	return (int) $query->found_posts;
}

/**
 * Build a live URL for a post by stripping the local scheme+host from its
 * permalink and rebuilding against the public domain.
 */
function momentive_legacy_live_url( int $post_id, string $domain ): string {
	return rtrim( $domain, '/' ) . wp_make_link_relative( (string) get_permalink( $post_id ) );
}

function momentive_legacy_count_run( array $argv ): void {
	$flags = momentive_legacy_count_get_flags( $argv );

	$post_types = ! empty( $flags['types'] )
		? $flags['types']
		: array_values( array_diff(
			get_post_types( [ 'public' => true ], 'names' ),
			MOMENTIVE_LEGACY_COUNT_EXCLUDE
		) );

	sort( $post_types );

	$run_at    = gmdate( 'c' );
	$rows      = [];
	$url_rows  = [];

	foreach ( $post_types as $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			WP_CLI::warning( sprintf( 'Post type "%s" is not registered on this site — skipped.', $post_type ) );
			continue;
		}

		$obj   = get_post_type_object( $post_type );
		$label = $obj ? $obj->label : $post_type;
		$row   = [ 'post_type' => $post_type, 'label' => $label, 'total' => 0 ];

		foreach ( $flags['status'] as $status ) {
			$count           = momentive_legacy_count_for( $post_type, $status );
			$row[ $status ]  = $count;
			$row['total']   += $count;
		}

		$rows[] = $row;

		if ( $flags['urls'] ) {
			$post_ids = get_posts( [
				'post_type'      => $post_type,
				'post_status'    => $flags['status'],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			] );

			foreach ( $post_ids as $post_id ) {
				$url_rows[] = [
					'post_type' => $post_type,
					'label'     => $label,
					'post_id'   => $post_id,
					'slug'      => (string) get_post_field( 'post_name', $post_id ),
					'title'     => (string) get_the_title( $post_id ),
					'url'       => momentive_legacy_live_url( $post_id, $flags['domain'] ),
				];
			}
		}
	}

	if ( empty( $rows ) ) {
		WP_CLI::error( 'No matching post types found — nothing to report.' );
		return;
	}

	$statuses = $flags['status'];
	$header   = array_merge( [ 'post_type', 'label' ], $statuses, [ 'total' ] );
	$out_path = $flags['out'] ?: ( 'legacy-content-counts-' . gmdate( 'Y-m-d' ) . '.csv' );

	$fh = fopen( $out_path, 'w' );
	fwrite( $fh, '# generated_at,' . $run_at . PHP_EOL );
	fputcsv( $fh, $header );
	foreach ( $rows as $row ) {
		fputcsv( $fh, array_map( static fn( $key ) => $row[ $key ] ?? '', $header ) );
	}
	fclose( $fh );

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Legacy site — snapshot as of %s', $run_at ) );
	WP_CLI::log( str_pad( 'Post type', 26 ) . str_pad( 'Total', 8 ) . implode( '  ', $statuses ) );
	foreach ( $rows as $row ) {
		$status_cells = array_map(
			static fn( $s ) => str_pad( (string) $row[ $s ], max( strlen( $s ), 6 ) ),
			$statuses
		);
		WP_CLI::log(
			str_pad( $row['post_type'], 26 ) .
			str_pad( (string) $row['total'], 8 ) .
			implode( '  ', $status_cells )
		);
	}

	WP_CLI::success( sprintf( 'Wrote %d post types to %s (generated %s).', count( $rows ), $out_path, $run_at ) );

	if ( $flags['urls'] ) {
		$urls_out_path = $flags['urls_out'] ?: ( 'legacy-urls-' . gmdate( 'Y-m-d' ) . '.csv' );
		$url_header    = [ 'post_type', 'label', 'post_id', 'slug', 'title', 'url' ];

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
momentive_legacy_count_run( isset( $args ) && is_array( $args ) ? $args : [] );
