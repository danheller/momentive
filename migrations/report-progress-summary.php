<?php
/**
 * report-progress-summary.php
 *
 * Merges the two CSVs produced by:
 *   - migrations/legacy/count-legacy-content.php   (run against the legacy site)
 *   - migrations/report-rebuild-progress.php       (run against the rebuilt site)
 *
 * ...into one "what's left to rebuild" Markdown table, suitable for pasting
 * into a status update, a Teams post, or reading straight off a terminal
 * before a meeting. This is a PLAIN PHP script — no WordPress bootstrap, no
 * WP-CLI — because merging two CSVs doesn't need either site loaded:
 *
 *   php migrations/report-progress-summary.php legacy.csv rebuild.csv
 *   php migrations/report-progress-summary.php legacy.csv rebuild.csv out=progress.md
 *
 * Optional flags (appended after the two required CSV paths) add a "Sample
 * pages to spot-check" section — a handful of legacy/rebuilt URL pairs per
 * content type, matched by slug, for teammates to click through and compare:
 *
 *   legacy_urls=<path>   → from count-legacy-content.php's urls=1 output
 *   rebuild_urls=<path>  → from report-rebuild-progress.php's urls=1 output
 *   sample=<N>           → pairs per content type (default 3; 0 disables)
 *
 *   php migrations/report-progress-summary.php legacy.csv rebuild.csv \
 *     legacy_urls=legacy-urls.csv rebuild_urls=rebuild-urls.csv sample=3
 *
 * Both URL CSVs must be supplied to get pairing (only sampling one side isn't
 * that useful for a side-by-side compare). Pairing prefers rebuilt pages
 * classified "rebuilt" over "import_remnant" so the sample isn't just raw
 * imported content — see report-rebuild-progress.php's header for why that
 * classification exists. When a content type has legacy pages but no
 * matching rebuilt slug yet, one legacy URL is still shown so there's
 * something to look at, flagged as not migrated yet.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * MOMENTIVE_PROGRESS_MAPPING — reconciled against migrations/legacy/legacy.csv
 * (generated 2026-07-20T19:31:39+00:00). Every legacy slug below is a
 * confirmed post_type from that export, not a guess — see the reconciliation
 * notes next to anything that changed from an earlier guess. If you re-run
 * count-legacy-content.php later and get a different slug list (new CPT,
 * renamed CPT), the report will print an "unmapped post types" section
 * calling it out — add a row here when that happens.
 *
 * Two things flagged in an earlier pass, now resolved:
 *   - `authors` never appears in legacy.csv because it isn't a post type at
 *     all — legacy authorship is PublishPress Authors plugin data, not a
 *     CPT, so it was never going to show up in a post-type scan. It's
 *     already been migrated into the `people` CPT (CLAUDE.md's "Authors →
 *     People" pass), so there's nothing left to track for it — removed from
 *     the People row's legacy slugs below rather than left as a dead lookup.
 *   - `reviews` DOES exist as its own CPT on the legacy site (confirmed) —
 *     it's just registered non-public, which is why count-legacy-content.php's
 *     default public-types scan misses it. An export exists at
 *     migrations/momentivesoftware.reviews.current.2026-07-20.xml. Deliberately
 *     left out of MOMENTIVE_PROGRESS_MAPPING for now (per Daniel — not a
 *     priority yet); add a row + re-run count-legacy-content.php
 *     types=reviews when it's time to plan that migration.
 *
 * `status` values:
 *   migrating           → being rebuilt into an equivalent CPT; % is meaningful
 *   folding_into_pages  → legacy CPT becomes regular Pages, not a CPT count
 *   folding_into_pattern→ content goes into a block pattern, not its own CPT
 *   extending_existing  → folds into an already-rebuilt CPT as new fields, not its own CPT
 *   retiring            → no migration planned; legacy count shown for context only
 *   not_content         → plugin/builder-internal data, not real content (evaluate the plugin)
 *   tbd                 → scope/destination not yet decided
 */

const MOMENTIVE_PROGRESS_MAPPING = [
	[ 'label' => 'Pages',                    'legacy' => [ 'page' ],               'rebuilt' => [ 'page' ],        'status' => 'migrating' ],
	[ 'label' => 'Solutions',                'legacy' => [ 'solutions' ],          'rebuilt' => [ 'solutions' ],   'status' => 'migrating' ],
	[ 'label' => 'Products',                 'legacy' => [ 'branded-products' ],   'rebuilt' => [ 'product' ],     'status' => 'migrating', 'notes' => 'legacy slug is branded-products, not product — confirmed via legacy.csv' ],
	[ 'label' => 'Testimonials',             'legacy' => [ 'testimonials' ],       'rebuilt' => [ 'testimonials' ],'status' => 'migrating', 'notes' => 'legacy slug is plural, matching the rebuilt DB name' ],
	[ 'label' => 'FAQ',                      'legacy' => [ 'faq' ],                'rebuilt' => [ 'faq' ],         'status' => 'migrating' ],
	[ 'label' => 'Case Studies',             'legacy' => [ 'case_studies' ],       'rebuilt' => [ 'case-study' ],  'status' => 'migrating' ],
	[ 'label' => 'Webinars',                 'legacy' => [ 'webinars' ],           'rebuilt' => [ 'webinar' ],     'status' => 'migrating' ],
	[ 'label' => 'Whitepapers',              'legacy' => [ 'whitepapers' ],        'rebuilt' => [ 'whitepaper' ],  'status' => 'migrating' ],
	[ 'label' => 'Guides & Research',        'legacy' => [ 'guides' ],             'rebuilt' => [ 'guide' ],       'status' => 'migrating' ],
	[ 'label' => 'Toolkits',                 'legacy' => [ 'toolkits' ],           'rebuilt' => [ 'toolkit' ],     'status' => 'migrating' ],
	[ 'label' => 'Infographics',             'legacy' => [ 'infographics' ],       'rebuilt' => [ 'infographic' ], 'status' => 'migrating' ],
	[ 'label' => 'Blog',                     'legacy' => [ 'post' ],               'rebuilt' => [ 'post' ],        'status' => 'migrating' ],
	[ 'label' => 'Newsroom / Press',         'legacy' => [ 'press-article' ],      'rebuilt' => [ 'press-article' ], 'status' => 'migrating' ],
	[ 'label' => 'People (leaders+authors)', 'legacy' => [ 'team' ],               'rebuilt' => [ 'people' ],      'status' => 'migrating', 'notes' => 'authors came from PublishPress plugin data, not a CPT, and are already fully migrated — no legacy count applies; presenters fold in too, but come from a webinar repeater field, not a legacy CPT' ],
	[ 'label' => 'Industries',               'legacy' => [ 'who-we-serve' ],       'rebuilt' => [ 'page' ],        'status' => 'folding_into_pages', 'notes' => 'legacy slug is who-we-serve, not industries — confirmed via legacy.csv' ],
	[ 'label' => 'Award Recipients',         'legacy' => [ 'award-recipients' ],   'rebuilt' => [],                'status' => 'folding_into_pattern' ],
	[ 'label' => 'Product Overviews',        'legacy' => [ 'product-overviews' ],  'rebuilt' => [],                'status' => 'extending_existing', 'notes' => 'not a separate rebuilt CPT — extends the product CPT with upcoming/on-demand fields (mirrors Webinar Settings); see CLAUDE.md' ],
	[ 'label' => 'Videos',                   'legacy' => [ 'videos' ],             'rebuilt' => [],                'status' => 'tbd', 'notes' => 'likely folds into webinar as a video type — see CLAUDE.md' ],
	[ 'label' => 'Video Testimonials',       'legacy' => [ 'video-testimonials' ], 'rebuilt' => [],                'status' => 'tbd', 'notes' => 'candidate: fold into testimonials CPT with a video type — see CLAUDE.md' ],
	[ 'label' => 'Events',                   'legacy' => [ 'events' ],             'rebuilt' => [],                'status' => 'tbd' ],
	[ 'label' => 'Interactive Tools',        'legacy' => [ 'interactive-tools' ],  'rebuilt' => [],                'status' => 'tbd' ],
	[ 'label' => 'Landing Pages',            'legacy' => [ 'lp' ],                 'rebuilt' => [],                'status' => 'tbd' ],
	[ 'label' => 'Integrations',             'legacy' => [ 'integrations' ],       'rebuilt' => [],                'status' => 'tbd' ],
	[ 'label' => 'Donation Examples',        'legacy' => [ 'donation_examples' ],  'rebuilt' => [],                'status' => 'tbd' ],
	[ 'label' => 'Clients',                  'legacy' => [ 'clients' ],            'rebuilt' => [],                'status' => 'retiring' ],
	[ 'label' => 'Assets (legacy)',          'legacy' => [ 'assets' ],             'rebuilt' => [],                'status' => 'retiring', 'notes' => 'video_embed_code already folded into webinar/whitepaper migration' ],
	[ 'label' => 'JetPopup',                 'legacy' => [ 'jet-popup' ],          'rebuilt' => [],                'status' => 'not_content', 'notes' => 'Crocoblock/JetEngine popup builder templates, likely not real content — confirm before ignoring' ],
	[ 'label' => 'Maintenance Reports',      'legacy' => [ 'maintenance' ],        'rebuilt' => [],                'status' => 'not_content', 'notes' => 'see CLAUDE.md "Dashboard / plugin items to evaluate" — Maintenance Reports plugin' ],
];

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php report-progress-summary.php <legacy.csv> <rebuild.csv> [out=path.md] [legacy_urls=path] [rebuild_urls=path] [sample=N]\n" );
	exit( 1 );
}

/**
 * Read one of our CSVs (first line is a `# generated_at,<iso8601>` comment,
 * second line is the header row) into [ 'generated_at' => ..., 'rows' => [ post_type => row ] ].
 */
function momentive_progress_read_csv( string $path ): array {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Cannot read file: {$path}\n" );
		exit( 1 );
	}

	$fh          = fopen( $path, 'r' );
	$generated   = '';
	$header      = null;
	$rows        = [];

	$first_line = fgets( $fh );
	if ( $first_line !== false && str_starts_with( $first_line, '#' ) ) {
		[ , $generated ] = array_pad( explode( ',', trim( $first_line, "# \r\n" ), 2 ), 2, '' );
	} else {
		rewind( $fh );
	}

	while ( ( $data = fgetcsv( $fh ) ) !== false ) {
		if ( $header === null ) {
			$header = $data;
			continue;
		}
		$row = array_combine( $header, $data );
		if ( $row === false || empty( $row['post_type'] ) ) {
			continue;
		}
		$rows[ $row['post_type'] ] = $row;
	}
	fclose( $fh );

	return [ 'generated_at' => $generated, 'rows' => $rows ];
}

function momentive_progress_sum( array $rows, array $slugs, string $field ): int {
	$sum = 0;
	foreach ( $slugs as $slug ) {
		if ( isset( $rows[ $slug ][ $field ] ) ) {
			$sum += (int) $rows[ $slug ][ $field ];
		}
	}
	return $sum;
}

/**
 * Read a URL-list CSV from count-legacy-content.php or
 * report-rebuild-progress.php's `urls=1` output. First line is
 * `# generated_at,<iso8601>,domain,<url>`; returns
 * [ 'generated_at' => ..., 'domain' => ..., 'rows' => [ post_type => [ slug => row ] ] ].
 * Rows with no slug (e.g. drafts that never got one) are skipped — an empty
 * slug would spuriously "match" between unrelated posts on each side.
 */
function momentive_progress_read_url_csv( string $path ): array {
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "Cannot read file: {$path}\n" );
		exit( 1 );
	}

	$fh     = fopen( $path, 'r' );
	$meta   = [ 'generated_at' => '', 'domain' => '' ];
	$header = null;
	$rows   = [];

	$first_line = fgets( $fh );
	if ( $first_line !== false && str_starts_with( $first_line, '#' ) ) {
		$parts = array_map( 'trim', explode( ',', trim( $first_line, "# \r\n" ) ) );
		for ( $i = 0; $i + 1 < count( $parts ); $i += 2 ) {
			$meta[ $parts[ $i ] ] = $parts[ $i + 1 ];
		}
	} else {
		rewind( $fh );
	}

	while ( ( $data = fgetcsv( $fh ) ) !== false ) {
		if ( $header === null ) {
			$header = $data;
			continue;
		}
		$row = array_combine( $header, $data );
		if ( $row === false || empty( $row['post_type'] ) || empty( $row['slug'] ) ) {
			continue;
		}
		$rows[ $row['post_type'] ][ $row['slug'] ] = $row;
	}
	fclose( $fh );

	return array_merge( $meta, [ 'rows' => $rows ] );
}

/**
 * Flatten a URL CSV's rows across several post types into one slug => row
 * map. If $prefer is set and at least one row matches it, only those rows
 * are returned (used to prefer "rebuilt" over "import_remnant" pages).
 */
function momentive_progress_flatten_url_rows( array $url_data, array $post_types, ?string $prefer = null ): array {
	$combined = [];
	foreach ( $post_types as $post_type ) {
		foreach ( $url_data['rows'][ $post_type ] ?? [] as $slug => $row ) {
			$combined[ $slug ] = $row;
		}
	}

	if ( null !== $prefer ) {
		$preferred = array_filter( $combined, static fn( $row ) => ( $row['classification'] ?? '' ) === $prefer );
		if ( ! empty( $preferred ) ) {
			return $preferred;
		}
	}

	return $combined;
}

$out_path         = '';
$legacy_urls_path  = '';
$rebuild_urls_path = '';
$sample_n          = 3;

foreach ( array_slice( $argv, 3 ) as $tok ) {
	if ( str_starts_with( $tok, 'out=' ) ) {
		$out_path = substr( $tok, 4 );
	} elseif ( str_starts_with( $tok, 'legacy_urls=' ) ) {
		$legacy_urls_path = substr( $tok, 12 );
	} elseif ( str_starts_with( $tok, 'rebuild_urls=' ) ) {
		$rebuild_urls_path = substr( $tok, 13 );
	} elseif ( str_starts_with( $tok, 'sample=' ) ) {
		$sample_n = max( 0, (int) substr( $tok, 7 ) );
	}
}

$legacy  = momentive_progress_read_csv( $argv[1] );
$rebuild = momentive_progress_read_csv( $argv[2] );

$status_labels = [
	'migrating'           => 'Migrating',
	'folding_into_pages'  => 'Folding into Pages',
	'folding_into_pattern'=> 'Folding into pattern',
	'extending_existing'  => 'Extends existing CPT',
	'retiring'            => 'Retiring (no migration)',
	'not_content'         => 'Not content (plugin data)',
	'tbd'                 => 'TBD',
];

$lines = [];
$lines[] = '# Rebuild progress';
$lines[] = '';
$lines[] = sprintf(
	'_Legacy snapshot: %s · Rebuilt snapshot: %s_',
	$legacy['generated_at'] ?: 'unknown',
	$rebuild['generated_at'] ?: 'unknown'
);
$lines[] = '';
$lines[] = '| Content type | Status | Legacy count | Rebuilt | Import-only | % rebuilt | Notes |';
$lines[] = '|---|---|---|---|---|---|---|';

$mapped_legacy_slugs  = [];
$mapped_rebuilt_slugs = [];

foreach ( MOMENTIVE_PROGRESS_MAPPING as $entry ) {
	$mapped_legacy_slugs  = array_merge( $mapped_legacy_slugs, $entry['legacy'] );
	$mapped_rebuilt_slugs = array_merge( $mapped_rebuilt_slugs, $entry['rebuilt'] );

	$legacy_total    = momentive_progress_sum( $legacy['rows'], $entry['legacy'], 'total' );
	$rebuilt_count   = momentive_progress_sum( $rebuild['rows'], $entry['rebuilt'], 'rebuilt' );
	$remnant_count   = momentive_progress_sum( $rebuild['rows'], $entry['rebuilt'], 'import_remnant' );
	$rebuilt_total   = momentive_progress_sum( $rebuild['rows'], $entry['rebuilt'], 'total' );

	$status = $entry['status'];
	$pct    = '—';
	if ( 'migrating' === $status && $legacy_total > 0 ) {
		$pct = round( ( $rebuilt_count / $legacy_total ) * 100 ) . '%';
	} elseif ( 'migrating' === $status && $legacy_total === 0 && $rebuilt_total > 0 ) {
		$pct = 'n/a (no legacy count)';
	}

	$lines[] = sprintf(
		'| %s | %s | %s | %s | %s | %s | %s |',
		$entry['label'],
		$status_labels[ $status ] ?? $status,
		'retiring' === $status || 'tbd' === $status || $legacy_total > 0 ? $legacy_total : '—',
		in_array( $status, [ 'migrating' ], true ) ? $rebuilt_count : '—',
		in_array( $status, [ 'migrating' ], true ) && $remnant_count > 0 ? $remnant_count : ( 'migrating' === $status ? 0 : '—' ),
		$pct,
		$entry['notes'] ?? ''
	);
}

// Sample pages to spot-check — legacy/rebuilt URL pairs matched by slug, for
// teammates to click through and compare. Only meaningful for "migrating"
// rows (anything else has no rebuilt CPT to pair against).
if ( $sample_n > 0 && $legacy_urls_path && $rebuild_urls_path ) {
	$legacy_urls  = momentive_progress_read_url_csv( $legacy_urls_path );
	$rebuild_urls = momentive_progress_read_url_csv( $rebuild_urls_path );

	$lines[] = '';
	$lines[] = '## Sample pages to spot-check';
	$lines[] = '';
	$lines[] = sprintf(
		'_Legacy: %s · Rebuilt: %s. Pages are matched by slug — click through and compare, flag anything off._',
		$legacy_urls['domain'] ?: 'unknown',
		$rebuild_urls['domain'] ?: 'unknown'
	);
	$lines[] = '';
	$lines[] = '| Content type | Legacy | Rebuilt |';
	$lines[] = '|---|---|---|';

	$sample_rows_found = false;

	foreach ( MOMENTIVE_PROGRESS_MAPPING as $entry ) {
		if ( 'migrating' !== $entry['status'] || empty( $entry['legacy'] ) || empty( $entry['rebuilt'] ) ) {
			continue;
		}

		$legacy_by_slug  = momentive_progress_flatten_url_rows( $legacy_urls, $entry['legacy'] );
		$rebuilt_by_slug = momentive_progress_flatten_url_rows( $rebuild_urls, $entry['rebuilt'], 'rebuilt' );

		if ( empty( $legacy_by_slug ) ) {
			continue; // nothing on the legacy side at all for this type — nothing to show
		}

		$matched_slugs = array_intersect_key( $legacy_by_slug, $rebuilt_by_slug );

		if ( ! empty( $matched_slugs ) ) {
			$slugs = array_keys( $matched_slugs );
			shuffle( $slugs );
			foreach ( array_slice( $slugs, 0, $sample_n ) as $slug ) {
				$sample_rows_found = true;
				$lines[]           = sprintf(
					'| %s | [%s](%s) | [%s](%s) |',
					$entry['label'],
					$legacy_by_slug[ $slug ]['title'] ?: $slug,
					$legacy_by_slug[ $slug ]['url'],
					$rebuilt_by_slug[ $slug ]['title'] ?: $slug,
					$rebuilt_by_slug[ $slug ]['url']
				);
			}
		} else {
			// Legacy pages exist for this type but none matched a rebuilt slug
			// yet — still surface one legacy link so there's something to see.
			$slug = array_rand( $legacy_by_slug );
			$sample_rows_found = true;
			$lines[]           = sprintf(
				'| %s | [%s](%s) | _not migrated yet_ |',
				$entry['label'],
				$legacy_by_slug[ $slug ]['title'] ?: $slug,
				$legacy_by_slug[ $slug ]['url']
			);
		}
	}

	if ( ! $sample_rows_found ) {
		$lines[] = '| _(no sampleable pages found — check the URL CSVs)_ | | |';
	}
}

// Flag anything present in either CSV that the mapping doesn't cover, so new
// or renamed post types don't silently disappear from the report.
$unmapped_legacy  = array_diff( array_keys( $legacy['rows'] ), $mapped_legacy_slugs );
$unmapped_rebuilt = array_diff( array_keys( $rebuild['rows'] ), $mapped_rebuilt_slugs );

if ( $unmapped_legacy || $unmapped_rebuilt ) {
	$lines[] = '';
	$lines[] = '**Unmapped post types found — add these to MOMENTIVE_PROGRESS_MAPPING:**';
	foreach ( $unmapped_legacy as $slug ) {
		$lines[] = sprintf( '- legacy `%s` (%d posts) — not in the mapping', $slug, (int) $legacy['rows'][ $slug ]['total'] );
	}
	foreach ( $unmapped_rebuilt as $slug ) {
		$lines[] = sprintf( '- rebuilt `%s` (%d posts) — not in the mapping', $slug, (int) $rebuild['rows'][ $slug ]['total'] );
	}
}

$output = implode( "\n", $lines ) . "\n";

echo $output;

if ( $out_path ) {
	file_put_contents( $out_path, $output );
	fwrite( STDERR, "\nWrote {$out_path}\n" );
}
