<?php
/**
 * report-impact-stat-usage.php
 *
 * Read-only inventory of every post containing a `momentive/impact-stat`
 * block, across every registered post type — including the "Count up"
 * (`animate`) setting for each instance.
 *
 * Why this exists: `blocks/impact-stat/src/deprecated.js` documents that
 * every impact-stat block published before the `data-animate` attribute was
 * added has HTML frozen in post_content WITHOUT that attribute. Opening one
 * of those in the editor silently auto-recovers it via the registered v1
 * deprecation (attributes.animate falls back to the current schema's
 * default, `true`) — but nothing is actually written back to the database
 * unless the post is explicitly saved again. Until that one real resave
 * happens, the post keeps re-triggering the same silent recovery every time
 * it's opened, which is what makes the "Count up" setting look like it
 * keeps getting lost: it isn't being reset by anything on save, it just
 * never got durably written in the first place.
 *
 * This report flags exactly that: any instance where the stored HTML is
 * missing `data-animate` needs one resave (open the post, no changes even
 * required, just Update) to stop re-triggering recovery. Once that data
 * attribute is present, its value read from the *rendered HTML* is the
 * reliable source of truth for what will actually display — read that
 * value directly rather than trusting the block comment's JSON attrs alone,
 * since Gutenberg omits any attribute that matches its schema default
 * (`animate: true`) from the comment JSON to keep markup compact, so a
 * block set to the default value legitimately shows no `animate` key at
 * all in the comment even when everything is working correctly.
 *
 * Run from the theme's WP-CLI context (the rebuilt site):
 *
 *   wp eval-file migrations/report-impact-stat-usage.php
 *   wp eval-file migrations/report-impact-stat-usage.php out=impact-stat-usage.csv
 *   wp eval-file migrations/report-impact-stat-usage.php types=solutions,page
 *   wp eval-file migrations/report-impact-stat-usage.php domain=https://momentivesoftware.com
 *
 * Flags (positional — `wp eval-file` doesn't accept `--flags`):
 *
 *   out=<path>     → CSV output path (default: ./impact-stat-usage-{date}.csv)
 *   types=a,b,c    → limit to specific post type slugs (default: all public types)
 *   status=a,b,c   → limit to specific post statuses (default: publish,draft,pending,future)
 *   domain=<url>   → public domain to build live URLs against (default: https://momentivesoftware.com)
 *
 * Makes no writes. Safe to re-run any time.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file report-impact-stat-usage.php [out=path] [types=a,b] [status=a,b] [domain=https://...]' . PHP_EOL );
}

const MOMENTIVE_IMPACT_STAT_REPORT_EXCLUDE = [
	'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
	'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
	'wp_global_styles', 'wp_navigation',
];

const MOMENTIVE_IMPACT_STAT_DEFAULT_DOMAIN = 'https://momentivesoftware.com';

function momentive_impact_stat_report_get_flags( array $argv = [] ): array {
	$flags = [
		'out'    => '',
		'types'  => [],
		'status' => [ 'publish', 'draft', 'pending', 'future' ],
		'domain' => MOMENTIVE_IMPACT_STAT_DEFAULT_DOMAIN,
	];
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( str_starts_with( $tok, 'out=' ) ) {
			$flags['out'] = substr( $tok, 4 );
		} elseif ( str_starts_with( $tok, 'types=' ) ) {
			$flags['types'] = array_values( array_filter( array_map( 'trim', explode( ',', substr( $tok, 6 ) ) ) ) );
		} elseif ( str_starts_with( $tok, 'status=' ) ) {
			$flags['status'] = array_values( array_filter( array_map( 'trim', explode( ',', substr( $tok, 7 ) ) ) ) );
		} elseif ( str_starts_with( $tok, 'domain=' ) ) {
			$flags['domain'] = substr( $tok, 7 );
		}
	}
	return $flags;
}

/**
 * Find every momentive/impact-stat instance in a post's content.
 *
 * @return array<int,array{attrs:array,has_data_animate:bool,animate_rendered:?string}>
 */
function momentive_impact_stat_find_instances( string $content ): array {
	$instances = [];

	// Each block: comment (optionally with JSON attrs) + rendered <div>...</div>.
	if ( ! preg_match_all(
		'#<!--\s*wp:momentive/impact-stat(?:\s+(\{.*?\}))?\s*-->(.*?)<!--\s*/wp:momentive/impact-stat\s*-->#s',
		$content, $matches, PREG_SET_ORDER
	) ) {
		return $instances;
	}

	foreach ( $matches as $m ) {
		$attrs = [];
		if ( ! empty( $m[1] ) ) {
			$decoded = json_decode( $m[1], true );
			$attrs   = is_array( $decoded ) ? $decoded : [];
		}
		$rendered_html    = $m[2] ?? '';
		$has_data_animate = (bool) preg_match( '/data-animate="(true|false)"/', $rendered_html, $dm );
		$instances[]      = [
			'attrs'            => $attrs,
			'has_data_animate' => $has_data_animate,
			'animate_rendered' => $has_data_animate ? $dm[1] : null,
		];
	}

	return $instances;
}

function momentive_impact_stat_live_url( int $post_id, string $domain ): string {
	return rtrim( $domain, '/' ) . wp_make_link_relative( (string) get_permalink( $post_id ) );
}

function momentive_impact_stat_report_run( array $argv ): void {
	$flags = momentive_impact_stat_report_get_flags( $argv );

	$post_types = ! empty( $flags['types'] )
		? $flags['types']
		: array_values( array_diff( get_post_types( [ 'public' => true ], 'names' ), MOMENTIVE_IMPACT_STAT_REPORT_EXCLUDE ) );
	sort( $post_types );

	$run_at = gmdate( 'c' );
	$rows   = [];
	$needs_resave = [];

	foreach ( $post_types as $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}
		$post_ids = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => $flags['status'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		foreach ( $post_ids as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			if ( ! str_contains( $content, 'wp:momentive/impact-stat' ) ) {
				continue;
			}
			$instances = momentive_impact_stat_find_instances( $content );
			foreach ( $instances as $i => $inst ) {
				$row = [
					'post_type'        => $post_type,
					'post_id'          => $post_id,
					'title'            => get_the_title( $post_id ),
					'url'              => momentive_impact_stat_live_url( $post_id, $flags['domain'] ),
					'block_index'      => $i + 1,
					'animate_attr'     => array_key_exists( 'animate', $inst['attrs'] ) ? ( $inst['attrs']['animate'] ? 'true' : 'false' ) : '(default: true)',
					'stat_label'       => $inst['attrs']['statLabel'] ?? '',
					'has_data_animate' => $inst['has_data_animate'] ? 'yes' : 'NO — needs one resave',
					'animate_rendered' => $inst['animate_rendered'] ?? '(unknown — legacy format)',
				];
				$rows[] = $row;
				if ( ! $inst['has_data_animate'] ) {
					$needs_resave[] = $row;
				}
			}
		}
	}

	if ( empty( $rows ) ) {
		WP_CLI::success( 'No momentive/impact-stat blocks found anywhere.' );
		return;
	}

	$header   = [ 'post_type', 'post_id', 'title', 'url', 'block_index', 'animate_attr', 'stat_label', 'has_data_animate', 'animate_rendered' ];
	$out_path = $flags['out'] ?: ( 'impact-stat-usage-' . gmdate( 'Y-m-d' ) . '.csv' );

	$fh = fopen( $out_path, 'w' );
	fwrite( $fh, '# generated_at,' . $run_at . PHP_EOL );
	fputcsv( $fh, $header );
	foreach ( $rows as $row ) {
		fputcsv( $fh, array_map( static fn( $key ) => $row[ $key ] ?? '', $header ) );
	}
	fclose( $fh );

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Found %d momentive/impact-stat instance(s) across %d post(s).', count( $rows ), count( array_unique( array_column( $rows, 'post_id' ) ) ) ) );
	foreach ( $rows as $row ) {
		WP_CLI::log( sprintf(
			'  [%s %d] %s — block #%d: animate=%s (rendered: %s, stored-HTML has data-animate: %s) — "%s"',
			$row['post_type'], $row['post_id'], $row['title'], $row['block_index'],
			$row['animate_attr'], $row['animate_rendered'], $row['has_data_animate'], $row['stat_label']
		) );
	}

	WP_CLI::log( '' );
	if ( ! empty( $needs_resave ) ) {
		WP_CLI::warning( sprintf(
			'%d instance(s) across %d post(s) are still in the legacy format (no data-animate in stored HTML) — these will keep silently re-triggering the old-format recovery on every open until each post gets one real resave:',
			count( $needs_resave ), count( array_unique( array_column( $needs_resave, 'post_id' ) ) )
		) );
		foreach ( array_unique( array_column( $needs_resave, 'post_id' ) ) as $pid ) {
			$match = current( array_filter( $needs_resave, static fn( $r ) => $r['post_id'] === $pid ) );
			WP_CLI::log( "    {$match['post_type']} {$pid}: {$match['title']} — {$match['url']}" );
		}
	} else {
		WP_CLI::log( 'Every instance found already has data-animate in its stored HTML — none are stuck in the legacy format.' );
	}

	WP_CLI::success( sprintf( 'Wrote %d rows to %s (generated %s).', count( $rows ), $out_path, $run_at ) );
}

momentive_impact_stat_report_run( isset( $args ) && is_array( $args ) ? $args : [] );
