<?php
/**
 * One-time migration: Products CPT structured-field backfill
 * ---------------------------------------------------------------------------
 * Populates the *structured* fields of the rebuilt `product` CPT from the
 * legacy JetEngine "Product Settings" CCT export (CSV). Does NOT touch
 * post_content — the page body is built by hand from the
 * `momentive/product-content` block pattern, whose sections are
 * templateLock:contentOnly. This script only fills the meta/field layer
 * that the pattern and the products archive read from.
 *
 * What it sets per product:
 *   - product_summary        (from CCT product_description)
 *   - product_category       (term_id, via solution_family -> category map)
 *   - product_type taxonomy  (active-product / orphan-product)
 *   - logo image fields      (sideloaded from CCT URLs -> attachment IDs)
 *       product_logo_unendorsed, white_product_logo_unendorsed,
 *       product_logo_endorsed,  white_product_logo_endorsed
 *
 * What it deliberately leaves alone (decide/handle separately — see notes):
 *   - product_icon       : your rebuilt field is a SPRITE SLUG (e.g. "a2z"),
 *                          not an image. CCT only has icon image URLs, which
 *                          don't map to a slug. Left for manual entry.
 *   - accent_color / tint_color : CCT has no per-product hex that maps
 *                          cleanly; the legacy color lived on Solution Settings
 *                          (primary_color) per family, not per product. A
 *                          family-color fallback is available but OFF by
 *                          default (see $apply_family_accent).
 *   - background_image, breadcrumb_title, product_order : no clean source.
 *   - post_content       : never touched.
 *
 * IMPORTANT — the rebuilt stubs contain COPY-PASTE BLEED. Several stub
 * products (Aptify, Nucleus, etc.) currently hold A2Z's values (same logo
 * IDs, icon "a2z", same summary). So this script OVERWRITES by default
 * rather than skip-if-present. Use --only / --skip-populated to control that.
 *
 * Usage:
 *   wp eval-file migrate-products.php --path=/path/to/wp
 *   wp eval-file migrate-products.php --dry-run
 *   wp eval-file migrate-products.php --only=aptify,nucleus
 *   wp eval-file migrate-products.php --skip-populated      (skip if logo already a real, non-bleed ID)
 *   wp eval-file migrate-products.php --apply-family-accent (also set accent colors from family map)
 *
 * The CSV path defaults to ./export-product_settings.csv next to this file.
 * Override with --csv=/abs/path.csv
 *
 * Place in theme `migrations/` folder. Delete after confirming.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "This script must be run via WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

// ── Flags ────────────────────────────────────────────────────────────────
$assoc = ! empty( $args_assoc ) && is_array( $args_assoc ) ? $args_assoc : array();
// WP-CLI passes named args via $args_assoc when using eval-file in newer
// versions; fall back to parsing $args for older ones.
$flags = array(
	'dry-run'             => false,
	'skip-populated'      => false,
	'apply-family-accent' => false,
	'only'                => '',
	'csv'                 => __DIR__ . '/export-product_settings.csv',
);
foreach ( (array) $args as $raw ) {
	if ( strpos( $raw, '--' ) !== 0 ) { continue; }
	$raw = substr( $raw, 2 );
	if ( strpos( $raw, '=' ) !== false ) {
		list( $k, $v ) = explode( '=', $raw, 2 );
		$flags[ $k ] = $v;
	} else {
		$flags[ $raw ] = true;
	}
}
$dry_run = ! empty( $flags['dry-run'] );
$only    = array_filter( array_map( 'trim', explode( ',', (string) $flags['only'] ) ) );

if ( $dry_run ) {
	WP_CLI::log( '--- DRY RUN — no changes will be written ---' );
}

// ───────────────────────────────────────────────────────────────────────────
// MAP 1: rebuilt product SLUG  =>  legacy CCT _ID
//
// Keyed by the rebuilt post slug (unambiguous). Only the 19 products that
// currently exist on the rebuilt site are listed. The other 12 CCT rows
// (MomentiveIQ, Path eClinical, MIP Millenium/Fundraising50, GiveSmart
// sub-products, Configio/Freestone/Core-Apps/TripBuilder/ExpoLogic/
// Attendee Interactive/Crowd Wisdom variants) have no rebuilt post yet —
// add a line here once you create the post.
//
// Verify these against your install before running. Slugs come straight
// from the rebuilt export; _IDs from the Product Settings CCT.
// ───────────────────────────────────────────────────────────────────────────
$slug_to_cct = array(
	'givesmart'                           => 9,
	'nimbleams'                           => 20,
	'path-lms'                            => 22,
	'volunteermatters'                    => 25,
	'crowdwisdom'                         => 6,   // CCT "Crowd Wisdom"
	'a2z-events'                          => 29,
	'aptify'                              => 1,
	'nucleus'                             => 21,  // CCT "Nucleus Analytics"
	'three-sixty'                         => 30,  // CCT "ThreeSixty"
	'yourmembership-ams'                  => 28,
	'accounting'                          => 13,  // CCT "MIP Accounting"
	'configio'                            => 4,
	'wild-apricot'                        => 31,
	'ymc-network'                         => 27,  // CCT "YM Careers Network" (draft)
	'cobaltams'                           => 3,
	'netforumams'                         => 19,
	'ym-careers'                          => 26,
	'momentive-certification-management'  => 16,
	'momentive-event-management-software' => 17,
);

// ───────────────────────────────────────────────────────────────────────────
// MAP 2: legacy solution_family (== WP category term_id on the LEGACY site)
//        =>  rebuilt category term SLUG
//
// The CCT solution_family value is the legacy category term_id. We resolve it
// to a rebuilt category by slug (term_ids differ between sites). Confirm these
// rebuilt slugs exist as children of the "solutions" parent category.
// ───────────────────────────────────────────────────────────────────────────
$family_to_cat_slug = array(
	'12'  => 'association-management',
	'13'  => 'fundraising',
	'14'  => 'event-management',
	'15'  => 'learning-management',
	'16'  => 'career-centers',
	'17'  => 'data-analytics',
	'18'  => 'accounting',
	'88'  => 'volunteer-management',
	'163' => 'certification-management',
	'195' => 'donor-management',
);

// Optional family => accent hex (from Solution Settings primary_color).
// Only used with --apply-family-accent.
$family_to_accent = array(
	'12'  => '#0078ff',
	'13'  => '#6a4ed8',
	'14'  => '#f26522',
	'15'  => '#43afbc',
	'16'  => '#d73f5d',
	'17'  => '#646b85',
	'18'  => '#3ba3d8',
	'88'  => '#0cbca0',
	'163' => '#5281ba',
	'195' => '#9b59b6',
);

// ───────────────────────────────────────────────────────────────────────────
// MAP 3: rebuilt ACF field keys (required so update_field writes the _key ref)
// Pulled from the rebuilt export. update_field() with the key is most robust.
// ───────────────────────────────────────────────────────────────────────────
$field_keys = array(
	'product_category'              => 'field_6a31522708095',
	'product_summary'               => 'field_6a32e41fcff4c',
	'product_logo_unendorsed'       => 'field_6a32163fecf51',
	'white_product_logo_unendorsed' => 'field_6a3216e0ecf54',
	'product_logo_endorsed'         => 'field_6a32169eecf52',
	'white_product_logo_endorsed'   => 'field_6a3216b8ecf53',
	'accent_color'                  => 'field_6a315103d2413',
	'tint_color'                    => 'field_6a371ffc1c6c6',
);

// CCT column => rebuilt logo field name
$logo_columns = array(
	'product_logo'               => 'product_logo_unendorsed',
	'white_product_logo'         => 'white_product_logo_unendorsed',
	'product_logo_endorsed'      => 'product_logo_endorsed',
	'white_product_logo_endorsed'=> 'white_product_logo_endorsed',
);

// ── Load CSV ───────────────────────────────────────────────────────────────
$csv_path = $flags['csv'];
if ( ! file_exists( $csv_path ) ) {
	WP_CLI::error( "CCT CSV not found at: $csv_path  (pass --csv=/abs/path.csv)" );
}
$rows = array();
if ( ( $fh = fopen( $csv_path, 'r' ) ) !== false ) {
	$header = fgetcsv( $fh );
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] ); // strip BOM
	while ( ( $line = fgetcsv( $fh ) ) !== false ) {
		if ( count( $line ) === 1 && trim( $line[0] ) === '' ) { continue; }
		$rows[] = array_combine( $header, $line );
	}
	fclose( $fh );
}
$cct_by_id = array();
foreach ( $rows as $r ) {
	$cct_by_id[ (string) $r['_ID'] ] = $r;
}
WP_CLI::log( sprintf( 'Loaded %d CCT product rows.', count( $cct_by_id ) ) );

// ── Sideload cache so a logo URL shared across products imports once ────────
$sideload_cache = array();

/**
 * Sideload a remote file into the media library, return attachment ID.
 * Caches by URL. Returns 0 on failure (logged).
 */
function mom_sideload( $url, &$cache, $post_id, $dry_run ) {
	$url = trim( $url );
	if ( $url === '' ) { return 0; }
	if ( isset( $cache[ $url ] ) ) { return $cache[ $url ]; }
	if ( $dry_run ) {
		WP_CLI::log( sprintf( '    [dry-run] would sideload %s', basename( $url ) ) );
		$cache[ $url ] = -1; // sentinel so dry-run doesn't re-log
		return -1;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( sprintf( '    sideload failed (download): %s — %s', basename( $url ), $tmp->get_error_message() ) );
		return 0;
	}
	$file_array = array(
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);
	$id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( sprintf( '    sideload failed (handle): %s — %s', basename( $url ), $id->get_error_message() ) );
		return 0;
	}
	$cache[ $url ] = (int) $id;
	return (int) $id;
}

// Heuristic to detect copy-paste bleed: stub products share A2Z's logo IDs.
// If --skip-populated is set we only skip a product whose logo field points
// at an attachment whose source filename actually matches THIS product.
// Simpler + safe: treat the known bleed IDs as "not real".
$bleed_logo_ids = array( 11838, 11839, 11840, 11841 ); // A2Z's four logo attachment IDs

$updated = 0; $skipped = 0; $missing = 0;

foreach ( $slug_to_cct as $slug => $cct_id ) {
	if ( $only && ! in_array( $slug, $only, true ) ) { continue; }

	$post = get_page_by_path( $slug, OBJECT, 'product' );
	if ( ! $post ) {
		WP_CLI::warning( sprintf( '[%s] No rebuilt product post with this slug — skipping.', $slug ) );
		$missing++;
		continue;
	}
	$post_id = $post->ID;
	$cct = isset( $cct_by_id[ (string) $cct_id ] ) ? $cct_by_id[ (string) $cct_id ] : null;
	if ( ! $cct ) {
		WP_CLI::warning( sprintf( '[%s] CCT _ID %d not found in CSV — skipping.', $slug, $cct_id ) );
		$missing++;
		continue;
	}

	WP_CLI::log( sprintf( '── [%s] (post %d  <-  CCT %d "%s") ──', $slug, $post_id, $cct_id, $cct['product_name'] ) );

	// Optional skip if this product already has a real (non-bleed) logo set.
	if ( $flags['skip-populated'] ) {
		$existing = (int) get_post_meta( $post_id, 'product_logo_endorsed', true );
		if ( $existing && ! in_array( $existing, $bleed_logo_ids, true ) ) {
			WP_CLI::log( '    skip-populated: real logo already set.' );
			$skipped++;
			continue;
		}
	}

	// 1) product_summary
	$summary = trim( (string) $cct['product_description'] );
	if ( $summary !== '' ) {
		if ( ! $dry_run ) { update_field( $field_keys['product_summary'], $summary, $post_id ); }
		WP_CLI::log( sprintf( '    summary: "%s…"', mb_substr( $summary, 0, 50 ) ) );
	}

	// 2) product_category (term_id via family -> slug)
	$family = (string) $cct['solution_family'];
	if ( isset( $family_to_cat_slug[ $family ] ) ) {
		$term = get_term_by( 'slug', $family_to_cat_slug[ $family ], 'category' );
		if ( $term ) {
			if ( ! $dry_run ) {
				update_field( $field_keys['product_category'], $term->term_id, $post_id );
				// Also assign the actual category term so archive/Query Loop work.
				wp_set_post_terms( $post_id, array( $term->term_id ), 'category', false );
			}
			WP_CLI::log( sprintf( '    category: %s (term %d)', $term->name, $term->term_id ) );
		} else {
			WP_CLI::warning( sprintf( '    category slug "%s" not found on rebuilt site.', $family_to_cat_slug[ $family ] ) );
		}
	} elseif ( $family !== '' ) {
		WP_CLI::warning( sprintf( '    unmapped solution_family "%s".', $family ) );
	}

	// 3) product_type taxonomy
	$ptype = strtolower( trim( (string) $cct['product_type'] ) );
	$type_term = null;
	if ( $ptype === 'active' )      { $type_term = 'active-product'; }
	elseif ( $ptype === 'orphaned') { $type_term = 'orphan-product'; }
	// sub-product: no rebuilt term — leave unset, log it.
	if ( $type_term ) {
		if ( ! $dry_run ) {
			wp_set_post_terms( $post_id, array( $type_term ), 'product_type', false );
		}
		WP_CLI::log( sprintf( '    product_type: %s', $type_term ) );
	} else {
		WP_CLI::log( sprintf( '    product_type: "%s" (no rebuilt term — left unset)', $ptype ) );
	}

	// 4) Logos — sideload each present URL, set the matching field.
	foreach ( $logo_columns as $csv_col => $field_name ) {
		$url = isset( $cct[ $csv_col ] ) ? trim( $cct[ $csv_col ] ) : '';
		if ( $url === '' ) { continue; }
		$att = mom_sideload( $url, $sideload_cache, $post_id, $dry_run );
		if ( $att > 0 ) {
			if ( ! $dry_run ) { update_field( $field_keys[ $field_name ], $att, $post_id ); }
			WP_CLI::log( sprintf( '    %s -> attachment %d', $field_name, $att ) );
		} elseif ( $att === -1 ) {
			WP_CLI::log( sprintf( '    %s -> [dry-run sideload]', $field_name ) );
		}
	}

	// 5) Optional family accent color
	if ( $flags['apply-family-accent'] && isset( $family_to_accent[ $family ] ) ) {
		if ( ! $dry_run ) {
			update_field( $field_keys['accent_color'], $family_to_accent[ $family ], $post_id );
		}
		WP_CLI::log( sprintf( '    accent_color (family fallback): %s', $family_to_accent[ $family ] ) );
	}

	$updated++;
}

WP_CLI::success( sprintf(
	'Done. Updated: %d | Skipped: %d | Missing post/CCT: %d',
	$updated, $skipped, $missing
) );

// ── Post-run reminders (printed, not actions) ──────────────────────────────
WP_CLI::log( '' );
WP_CLI::log( 'Not handled by this script (do manually / decide):' );
WP_CLI::log( '  • product_icon  — sprite slug, set per product (CCT only had image URLs).' );
WP_CLI::log( '  • accent_color / tint_color — unless --apply-family-accent was used,' );
WP_CLI::log( '    these are per-product design choices with no clean legacy source.' );
WP_CLI::log( '  • background_image, breadcrumb_title, product_order — no source field.' );
WP_CLI::log( '  • 12 CCT products have no rebuilt post yet — create the post, add a slug' );
WP_CLI::log( '    line to $slug_to_cct, and re-run with --only=<slug>.' );
WP_CLI::log( '  • sub-product CCT rows (GiveSmart Events/Fundraise, etc.) — decide whether' );
WP_CLI::log( '    they become their own posts or collapse into the parent product.' );