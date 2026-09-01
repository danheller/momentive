<?php
/**
 * One-time migration: Products CPT post_content generation
 * ---------------------------------------------------------------------------
 * Companion to migrate-products.php. That script fills structured/meta
 * fields (summary, category, product_type, logos) and DELIBERATELY never
 * touches post_content, because when it was written the page body was still
 * being hand-built from the momentive/product-content pattern.
 *
 * This script does the other half: it builds full post_content for the
 * `product` CPT from the legacy "branded-products" WXR export, the same
 * WP-CLI-from-WXR pattern already established by migrate-solutions.php /
 * migrate-case-studies.php / migrate-webinars.php / migrate-whitepapers.php.
 * It exists because Daniel hand-rebuilt four product pages first (Wild
 * Apricot, A2Z Events, Path LMS, Careers/YM Careers) to prove out the
 * section-by-section pattern, and that pattern held up well enough against
 * the legacy postmeta to script the rest.
 *
 * Data sources (place next to this script):
 *   - momentivesoftware.branded-products.current.2026-09-01.xml
 *       24 legacy `branded-products` posts. Each carries ~200 structured
 *       postmeta keys (hero, feature repeaters, CTA, FAQ, testimonials,
 *       related-products cross-sell, request-a-demo). This is the content
 *       source. It is NOT the same thing as the CCT CSV below.
 *   - export-product_settings-25-07-2026.csv
 *       The legacy "Product Settings" CCT export (same file migrate-
 *       products.php reads). Its `linked_product_page` column is the join
 *       key back to a branded-products post ID for a given product.
 *
 * Resolution per rebuilt product post (driven by $slug_to_cct below, the
 * same map migrate-products.php uses — keep the two in sync):
 *   1. Look up the CCT row for that slug, read `linked_product_page`.
 *   2. Try to find a branded-products WXR post with that exact ID.
 *   3. If not found (several `linked_product_page` values are stale/shared
 *      placeholder IDs, e.g. 264/267/7299 reused across multiple unrelated
 *      products), fall back to a normalized-title match against the 24
 *      branded-products posts.
 *   4. If still not found, this product has no legacy page content at all.
 *      Confirmed cases among the 19 active rebuilt slugs: `nucleus`,
 *      `ymc-network`, `momentive-certification-management`. These are
 *      logged at the end of the run rather than guessed at — some of these
 *      are legacy redirects-to-Solution (Daniel already hand-assigns
 *      `redirect_to_solution` for that pattern; not attempted here since
 *      there's no reliable signal for WHICH solution from this data alone).
 *
 * Sections built, in the order the reference pages use them:
 *   hero -> trust logos -> feature media-text rows -> "Also included"
 *   buttons -> boxed dashboard CTA -> testimonials slider -> related
 *   products/solutions cross-sell -> info-box (e.g. "Bootcamp") -> demo
 *   form -> FAQ accordion -> stat-columns (only if legacy stats present;
 *   none of the 24 legacy posts currently have any, so this is unexercised
 *   — verify placement once a product actually has stats).
 *
 * Known deliberate simplifications (verify against a rebuild or two before
 * trusting the full batch, same as the Solutions migration's QA passes):
 *   - Trust logos: only legacy post 8192 (Careers) has
 *     `product_hero_social_proof_custom_gallery = true` with its own logo
 *     gallery + title. Every other post has it off/blank and shows the same
 *     fixed "Trusted by over 37,000 nonprofits..." logo set Wild Apricot
 *     uses. That set now exists as a real synced pattern (post 18089,
 *     MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID below) — the default case just
 *     emits `wp:block {"ref":18089}`, so any future edit to the pattern
 *     (new client logo, reworded headline) propagates everywhere it's used
 *     without re-running this script.
 *   - Demo form section is gated on `request_a_demo_-_enable_section`
 *     rather than "is the embed code blank," because the two track each
 *     other exactly across all 24 legacy posts (see the run's start-of-log
 *     summary) — including Wild Apricot, whose legacy post has since had
 *     its demo form removed and intro copy moved into a prefooter block,
 *     which is why the original reference build (11766) looks different
 *     from what this script would generate today. That's expected drift,
 *     not a bug — the live legacy post is newer than the hand-rebuilt page.
 *   - Testimonials are filtered by the `solution_family` taxonomy on the
 *     `testimonials` CPT (an existing relationship — see Testimonial
 *     Settings in CLAUDE.md), resolved via the same family -> category-slug
 *     map as migrate-products.php, rather than the legacy `testimonial_type`
 *     / `post_tag` term IDs baked into the reference pages (those numeric
 *     IDs are legacy-site-specific and won't resolve on the rebuilt site).
 *   - Related-products cross-sell cards use the legacy
 *     `card_link_url_custom` / `card_link_text_custom` /
 *     `card_description_custom` values directly — they already point at
 *     rebuilt-site-shaped relative URLs (e.g. "/products/givesmart/"), not
 *     legacy ones, so no product/solution name-matching is needed here.
 *   - Feature media-text rows and the boxed CTA reuse the visual
 *     conventions already established for Solutions (no-shadow class,
 *     alternating mediaPosition, "to-edge" wrapper groups) — per-post
 *     padding/background tweaks are still a hand-adjustment, same known
 *     limitation documented for the Solutions migration.
 *
 * Run modes (positional args, NOT --flags — `wp eval-file` doesn't accept
 * flags; see the gotcha documented across every other *-wxr migration in
 * this folder):
 *
 *   wp eval-file migrate-products-content.php --user=<admin>
 *       Dry run (default). No writes. Logs every section it would build.
 *   wp eval-file migrate-products-content.php --user=<admin> live
 *       Writes.
 *   wp eval-file migrate-products-content.php --user=<admin> live only=wild-apricot
 *       Single product by rebuilt slug.
 *   wp eval-file migrate-products-content.php --user=<admin> live force
 *       Override the already-rebuilt guard (see below) — re-generates even
 *       hand-built pages. Use with `only=` in practice; don't run this
 *       against the full batch without a reason.
 *
 * --user=<admin> is required for the same reason as the other migrations:
 * Safe SVG gates SVG sideloads on user capability, and several legacy logo
 * URLs are .svg files.
 *
 * Already-rebuilt guard: a product post is skipped unless it's empty (or
 * only holds the single trivial empty-paragraph block every fresh post
 * gets on first save) OR it was stamped by a PREVIOUS run of this exact
 * script (`_momentive_migration_run` postmeta) — same convention as
 * migrate-solutions.php, so this script can be safely re-run to pick up
 * fixes without clobbering the four hand-built reference pages.
 *
 * Place in theme `migrations/` folder. Delete after confirming.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "This script must be run via WP-CLI (wp eval-file).\n" );
	exit( 1 );
}

// ── Positional args ─────────────────────────────────────────────────────────
$momentive_pc_args = isset( $args ) && is_array( $args ) ? $args : array();
$live   = in_array( 'live', $momentive_pc_args, true ) || getenv( 'MOMENTIVE_LIVE' ) === '1';
$force  = in_array( 'force', $momentive_pc_args, true );
$only   = null;
foreach ( $momentive_pc_args as $a ) {
	if ( strpos( $a, 'only=' ) === 0 ) {
		$only = substr( $a, 5 );
	}
}
$dry_run = ! $live;

if ( $dry_run ) {
	WP_CLI::log( '--- DRY RUN — no changes will be written (pass "live" to write) ---' );
}
if ( get_current_user_id() === 0 && ! $dry_run ) {
	WP_CLI::warning( 'No current user set — pass --user=<admin> or SVG logo sideloads will fail (Safe SVG capability gate).' );
}

// ── Config (override via constants defined before this file runs) ──────────
if ( ! defined( 'MOMENTIVE_PC_LEGACY_WXR' ) ) {
	define( 'MOMENTIVE_PC_LEGACY_WXR', __DIR__ . '/exports/momentivesoftware.branded-products.current.2026-09-01.xml' );
}
if ( ! defined( 'MOMENTIVE_PC_CSV' ) ) {
	define( 'MOMENTIVE_PC_CSV', __DIR__ . '/exports/export-product_settings-25-07-2026.csv' );
}
if ( ! defined( 'MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID' ) ) {
	// Synced pattern "trust logos" — headline + fixed logo autoslider,
	// reused as-is on every product page that doesn't have its own custom
	// social-proof gallery (see momentive_pc_trust_logos_block() below).
	define( 'MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID', 18089 );
}
define( 'MOMENTIVE_PC_RUN_META', '_momentive_migration_run' );

// ───────────────────────────────────────────────────────────────────────────
// MAP: rebuilt product SLUG => legacy CCT _ID
// Copied from migrate-products.php — KEEP IN SYNC. This is the scope of
// products this script will touch; anything not listed here has no rebuilt
// post yet and is out of scope for content generation.
// ───────────────────────────────────────────────────────────────────────────
$slug_to_cct = array(
	'givesmart'                           => 9,
	'nimbleams'                           => 20,
	'path-lms'                            => 22,
	'volunteermatters'                    => 25,
	'crowdwisdom'                         => 6,
	'a2z-events'                          => 29,
	'aptify'                              => 1,
	'nucleus'                             => 21,
	'three-sixty'                         => 30,
	'yourmembership-ams'                  => 28,
	'accounting'                          => 13,
	'configio'                            => 4,
	'wild-apricot'                        => 31,
	'ymc-network'                         => 27,
	'cobaltams'                           => 3,
	'netforumams'                         => 19,
	'ym-careers'                          => 26,
	'momentive-certification-management'  => 16,
	'momentive-event-management-software' => 17,
);

// Legacy solution_family (category term_id on the LEGACY site) => rebuilt
// category term SLUG. Same map as migrate-products.php; used here to
// resolve the testimonials query.
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

// ── Load CCT CSV ─────────────────────────────────────────────────────────────
if ( ! file_exists( MOMENTIVE_PC_CSV ) ) {
	WP_CLI::error( 'CCT CSV not found at: ' . MOMENTIVE_PC_CSV );
}
$cct_by_id = array();
if ( ( $fh = fopen( MOMENTIVE_PC_CSV, 'r' ) ) !== false ) {
	$header = fgetcsv( $fh );
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
	while ( ( $line = fgetcsv( $fh ) ) !== false ) {
		if ( count( $line ) === 1 && trim( (string) $line[0] ) === '' ) { continue; }
		$row = array_combine( $header, $line );
		$cct_by_id[ (string) $row['_ID'] ] = $row;
	}
	fclose( $fh );
}
WP_CLI::log( sprintf( 'Loaded %d CCT product rows.', count( $cct_by_id ) ) );

// ── Load legacy branded-products WXR ────────────────────────────────────────
if ( ! file_exists( MOMENTIVE_PC_LEGACY_WXR ) ) {
	WP_CLI::error( 'Legacy WXR not found at: ' . MOMENTIVE_PC_LEGACY_WXR );
}
libxml_use_internal_errors( true );
$xml = simplexml_load_file( MOMENTIVE_PC_LEGACY_WXR );
if ( ! $xml ) {
	WP_CLI::error( 'Failed to parse legacy WXR.' );
}
$wp_ns = 'http://wordpress.org/export/1.2/';

function momentive_pc_norm( $s ) {
	return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) );
}

$branded_by_id = array();
$branded_by_norm_title = array();
foreach ( $xml->channel->item as $item ) {
	$wp = $item->children( $wp_ns );
	$post_type = (string) $wp->post_type;
	if ( $post_type !== 'branded-products' ) { continue; }
	$post_id = (string) $wp->post_id;
	$title   = (string) $item->title;
	$meta    = array();
	foreach ( $wp->postmeta as $pm ) {
		$pm_wp = $pm->children( $wp_ns );
		$meta[ (string) $pm_wp->meta_key ] = (string) $pm_wp->meta_value;
	}
	$branded_by_id[ $post_id ] = array( 'title' => $title, 'meta' => $meta );
	$branded_by_norm_title[ momentive_pc_norm( $title ) ][] = $post_id;
}
WP_CLI::log( sprintf( 'Loaded %d legacy branded-products posts.', count( $branded_by_id ) ) );

/**
 * Resolve the legacy content source for a rebuilt product slug.
 * Returns the branded-products meta array, or null with a logged reason.
 */
function momentive_pc_resolve_source( $slug, $cct_row, $branded_by_id, $branded_by_norm_title ) {
	$linked = (string) $cct_row['linked_product_page'];
	if ( isset( $branded_by_id[ $linked ] ) ) {
		return $branded_by_id[ $linked ];
	}
	$norm = momentive_pc_norm( $cct_row['product_name'] );
	if ( isset( $branded_by_norm_title[ $norm ] ) && count( $branded_by_norm_title[ $norm ] ) === 1 ) {
		$pid = $branded_by_norm_title[ $norm ][0];
		WP_CLI::log( sprintf( '    [%s] linked_product_page=%s not found — resolved by title match to legacy post %s instead.', $slug, $linked, $pid ) );
		return $branded_by_id[ $pid ];
	}
	return null;
}

/** Safely unserialize an ACF repeater's raw postmeta value. */
function momentive_pc_repeater( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' || strpos( $raw, 'a:' ) !== 0 ) { return array(); }
	$val = @unserialize( $raw, array( 'allowed_classes' => false ) );
	return is_array( $val ) ? $val : array();
}

function momentive_pc_esc( $s ) {
	return esc_html( html_entity_decode( (string) $s, ENT_QUOTES ) );
}

// ── Sideload cache (URL -> attachment ID), shared across all products ──────
$sideload_cache = array();
function momentive_pc_sideload( $url, &$cache, $post_id, $dry_run ) {
	$url = trim( (string) $url );
	if ( $url === '' ) { return 0; }
	if ( isset( $cache[ $url ] ) ) { return $cache[ $url ]; }
	if ( $dry_run ) {
		$cache[ $url ] = -1;
		return -1;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// Dedupe by source URL across runs, same convention as the other migrations.
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'meta_key'       => '_momentive_source_url',
		'meta_value'     => $url,
		'fields'         => 'ids',
	) );
	if ( ! empty( $existing ) ) {
		$cache[ $url ] = (int) $existing[0];
		return (int) $existing[0];
	}

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
	update_post_meta( $id, '_momentive_source_url', $url );
	$cache[ $url ] = (int) $id;
	return (int) $id;
}

function momentive_pc_already_rebuilt( $post_id ) {
	$content = (string) get_post_field( 'post_content', $post_id );
	// Strip the single default empty-paragraph block a fresh post gets on
	// first open/save, same convention as the Solutions rebuild-progress
	// report's MOMENTIVE_REBUILD_TRIVIAL_EMPTY_BLOCK check.
	$stripped = trim( preg_replace( '#<!--\s*wp:paragraph\s*-->\s*<p>\s*</p>\s*<!--\s*/wp:paragraph\s*-->#', '', $content ) );
	if ( $stripped === '' ) { return false; }
	if ( get_post_meta( $post_id, MOMENTIVE_PC_RUN_META, true ) ) { return false; }
	return true;
}

// ───────────────────────────────────────────────────────────────────────────
// Section builders. Each returns a block-markup string, or '' when the
// legacy data doesn't support that section (rendering nothing is the
// established convention — see hubspot-form/back-link/solution-resources).
// ───────────────────────────────────────────────────────────────────────────

function momentive_pc_hero_block( $meta, $post_id, &$cache, $dry_run ) {
	$overline    = trim( $meta['product_hero_overline'] ?? '' );
	$overline_h1 = ( $meta['product_hero_overline_h1'] ?? '' ) === 'true';
	$headline    = trim( $meta['product_hero_headline'] ?? '' );
	$description = trim( $meta['product_hero_description'] ?? '' );
	$logo_url    = trim( $meta['hero_product_logo'] ?? '' );
	$hero_image  = trim( $meta['product_hero_image'] ?? '' );
	$btn_url     = trim( $meta['product_hero_button_url'] ?? '' );
	$btn_label   = trim( $meta['hero_button_text'] ?? '' );
	$new_tab     = ( $meta['product_hero_button_new_tab'] ?? '' ) === 'true';
	$checklist   = momentive_pc_repeater( $meta['product_hero_checklist'] ?? '' );

	$heading = '';
	if ( $overline_h1 && $overline !== '' ) {
		$heading = sprintf( '<!-- wp:heading {"level":1,"className":"is-style-eyebrow"} -->' . "\n" . '<h1 class="wp-block-heading is-style-eyebrow">%s</h1>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $overline ) );
	} else {
		if ( $overline !== '' ) {
			$heading .= sprintf( '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n" . '<p class="is-style-eyebrow">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $overline ) );
		}
		if ( $headline !== '' ) {
			$heading .= sprintf( '<!-- wp:heading {"level":1} -->' . "\n" . '<h1 class="wp-block-heading">%s</h1>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $headline ) );
		}
	}

	$desc_block = '';
	if ( $description !== '' ) {
		$desc_block = sprintf( '<!-- wp:paragraph {"fontSize":"medium"} -->' . "\n" . '<p class="has-medium-font-size">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $description ) );
	}

	$logo_block = '';
	if ( $logo_url !== '' ) {
		$logo_id = momentive_pc_sideload( $logo_url, $cache, $post_id, $dry_run );
		$logo_id_out = $logo_id > 0 ? $logo_id : 0;
		$logo_block = sprintf(
			'<!-- wp:image {"id":%d,"sizeSlug":"large","linkDestination":"none"} -->' . "\n" .
			'<figure class="wp-block-image size-large"><img src="%s" class="wp-image-%d"/></figure>' . "\n" .
			'<!-- /wp:image -->' . "\n\n",
			$logo_id_out, esc_url( $logo_url ), $logo_id_out
		);
	}

	$list_block = '';
	if ( ! empty( $checklist ) ) {
		$items = '';
		foreach ( $checklist as $row ) {
			$text = trim( (string) ( $row['product_checklist_item'] ?? '' ) );
			if ( $text === '' ) { continue; }
			$items .= sprintf( '<!-- wp:list-item -->' . "\n" . '<li>%s</li>' . "\n" . '<!-- /wp:list-item -->' . "\n\n", momentive_pc_esc( $text ) );
		}
		if ( $items !== '' ) {
			$list_block = '<!-- wp:list {"className":"is-style-blue-checks is-style-circle-checks"} -->' . "\n" .
				'<ul class="wp-block-list is-style-blue-checks is-style-circle-checks">' . $items . '</ul>' . "\n" .
				'<!-- /wp:list -->' . "\n\n";
		}
	}

	$button_block = '';
	if ( $btn_url !== '' && $btn_label !== '' ) {
		$target = $new_tab ? ' target="_blank" rel="noopener"' : '';
		$button_block = '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button {"className":"has-arrow"} -->' . "\n" .
			sprintf( '<div class="wp-block-button has-arrow"><a class="wp-block-button__link wp-element-button" href="%s"%s>%s</a></div>', esc_url( $btn_url ), $target, momentive_pc_esc( $btn_label ) ) . "\n" .
			'<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons -->' . "\n\n";
	}

	$image_block = '';
	if ( $hero_image !== '' ) {
		$img_id = momentive_pc_sideload( $hero_image, $cache, $post_id, $dry_run );
		$img_id_out = $img_id > 0 ? $img_id : 0;
		$image_block = sprintf(
			'<!-- wp:image {"id":%d,"sizeSlug":"full","linkDestination":"none"} -->' . "\n" .
			'<figure class="wp-block-image size-full"><img src="%s" class="wp-image-%d"/></figure>' . "\n" .
			'<!-- /wp:image -->',
			$img_id_out, esc_url( $hero_image ), $img_id_out
		);
	}

	$col1 = $heading . $logo_block . $desc_block . $list_block . $button_block;

	return '<!-- wp:group {"className":"hero-background is-style-bg-dots","style":{"spacing":{"padding":{"bottom":"0"}}},"gradient":"vertical","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group hero-background is-style-bg-dots has-vertical-gradient-background has-background" style="padding-bottom:0"><!-- wp:group {"className":"breadcrumb-bar","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group breadcrumb-bar"><!-- wp:momentive/breadcrumbs /--></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n" .
		'<!-- wp:group {"align":"full","className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"0"}}},"layout":{"type":"constrained","wideSize":"","contentSize":""}} -->' . "\n" .
		'<div class="wp-block-group alignfull hero" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:0"><!-- wp:columns -->' . "\n" .
		'<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center">' . $col1 . '</div>' . "\n" .
		'<!-- /wp:column -->' . "\n\n" .
		'<!-- wp:column {"verticalAlignment":"center"} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center">' . $image_block . '</div>' . "\n" .
		'<!-- /wp:column --></div>' . "\n" .
		'<!-- /wp:columns --></div>' . "\n" .
		'<!-- /wp:group --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_trust_logos_block( $meta ) {
	$custom = ( $meta['product_hero_social_proof_custom_gallery'] ?? '' ) === 'true';

	if ( ! $custom ) {
		// Default case: reuse the real synced pattern (headline + fixed logo
		// autoslider) instead of building it inline — a single source of
		// truth that stays in sync everywhere it's used, and picks up any
		// future edits (new client logos, reworded headline) automatically.
		if ( ! MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID ) {
			WP_CLI::warning( '    trust logos: MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID is unset — section left empty.' );
			return '';
		}
		return sprintf( '<!-- wp:block {"ref":%d} /-->' . "\n\n", (int) MOMENTIVE_PC_TRUST_LOGOS_PATTERN_ID );
	}

	// Custom gallery: comma-separated legacy attachment IDs — these won't
	// resolve to real URLs from the WXR alone, so this branch can only be
	// scripted once a per-product image-URL source is added; for now, log
	// and skip so it doesn't silently render broken images.
	WP_CLI::warning( '    trust logos: custom gallery is set but this script has no URL source for legacy gallery IDs — section left empty, add by hand.' );
	return '';
}

function momentive_pc_features_block( $meta, $post_id, &$cache, $dry_run ) {
	$rows = momentive_pc_repeater( $meta['event_sub_features_-_repeater'] ?? '' );
	$out  = '';
	$i    = 0;
	foreach ( $rows as $row ) {
		$img_url = trim( (string) ( $row['event_sub_feat_rep_-_image'] ?? '' ) );
		$pos     = trim( (string) ( $row['event_sub_feat_rep_-_image_position'] ?? ( $i % 2 === 0 ? 'left' : 'right' ) ) );
		$kicker  = trim( (string) ( $row['event_sub_feat_rep_-_kicker'] ?? '' ) );
		$title   = trim( (string) ( $row['event_sub_feat_rep_-_title'] ?? '' ) );
		$desc    = trim( (string) ( $row['event_sub_feat_rep_-_desc'] ?? '' ) );
		$btn_lbl = trim( (string) ( $row['event_sub_feat_rep_-_btn_label'] ?? '' ) );
		$btn_url = trim( (string) ( $row['event_sub_feat_rep_-_btn_url'] ?? '' ) );

		$img_id = $img_url !== '' ? momentive_pc_sideload( $img_url, $cache, $post_id, $dry_run ) : 0;
		$img_id_out = $img_id > 0 ? $img_id : 0;
		$right = $pos === 'right';

		$content  = '';
		if ( $kicker !== '' ) {
			$content .= sprintf( '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n" . '<p class="is-style-eyebrow">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $kicker ) );
		}
		if ( $title !== '' ) {
			$content .= sprintf( '<!-- wp:heading {"level":3,"fontSize":"xx-large"} -->' . "\n" . '<h3 class="wp-block-heading has-xx-large-font-size">%s</h3>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $title ) );
		}
		if ( $desc !== '' ) {
			$content .= sprintf( '<!-- wp:paragraph {"fontSize":"medium"} -->' . "\n" . '<p class="has-medium-font-size">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) );
		}
		if ( $btn_url !== '' && $btn_lbl !== '' ) {
			$content .= sprintf( '<!-- wp:paragraph {"className":"read-more has-arrow upward","fontSize":"medium"} -->' . "\n" . '<p class="read-more has-arrow upward has-medium-font-size"><a href="%s">%s</a></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", esc_url( $btn_url ), momentive_pc_esc( $btn_lbl ) );
		}

		$figure = sprintf( '<figure class="wp-block-media-text__media"><img src="%s" class="wp-image-%d size-full"/></figure>', esc_url( $img_url ), $img_id_out );
		$attrs  = $right
			? sprintf( '{"mediaPosition":"right","mediaId":%d,"mediaType":"image","className":"no-shadow","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}}', $img_id_out )
			: sprintf( '{"mediaId":%d,"mediaType":"image","className":"no-shadow","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}}', $img_id_out );
		$class  = 'wp-block-media-text is-stacked-on-mobile no-shadow' . ( $right ? ' has-media-on-the-right' : '' );

		if ( $right ) {
			$out .= sprintf(
				'<!-- wp:media-text %s -->' . "\n" .
				'<div class="%s" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small)"><div class="wp-block-media-text__content">%s</div>%s</div>' . "\n" .
				'<!-- /wp:media-text -->' . "\n\n",
				$attrs, $class, $content, $figure
			);
		} else {
			$out .= sprintf(
				'<!-- wp:media-text %s -->' . "\n" .
				'<div class="%s" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small)">%s<div class="wp-block-media-text__content">%s</div></div>' . "\n" .
				'<!-- /wp:media-text -->' . "\n\n",
				$attrs, $class, $figure, $content
			);
		}
		$i++;
	}
	return $out;
}

function momentive_pc_also_included_block( $meta ) {
	$rows = momentive_pc_repeater( $meta['additional_features_repeater'] ?? '' );
	if ( empty( $rows ) ) { return ''; }
	$buttons = '';
	foreach ( $rows as $row ) {
		$name = trim( (string) ( $row['additional_feature_name'] ?? '' ) );
		$url  = trim( (string) ( $row['feature_url'] ?? '' ) );
		if ( $name === '' ) { continue; }
		$buttons .= sprintf(
			'<!-- wp:button {"className":"is-style-superlight"} -->' . "\n" .
			'<div class="wp-block-button is-style-superlight"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div>' . "\n" .
			'<!-- /wp:button -->' . "\n\n",
			esc_url( $url ), momentive_pc_esc( $name )
		);
	}
	if ( $buttons === '' ) { return ''; }
	return '<!-- wp:group {"className":"feature-buttons","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group feature-buttons"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->' . "\n" .
		'<h2 class="wp-block-heading has-text-align-center">Also included</h2>' . "\n" .
		'<!-- /wp:heading -->' . "\n\n" .
		'<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->' . "\n" .
		'<div class="wp-block-buttons">' . $buttons . '</div>' . "\n" .
		'<!-- /wp:buttons --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_boxed_cta_block( $meta, $post_id, &$cache, $dry_run ) {
	$title    = trim( $meta['boxed_custom_cta_title'] ?? '' );
	if ( $title === '' ) { return ''; }
	$desc     = trim( $meta['boxed_custom_cta_description'] ?? '' );
	$btn_text = trim( $meta['boxed_custom_cta_button_text'] ?? '' );
	$btn_url  = trim( $meta['boxed_custom_cta_url'] ?? '' );
	$logo_url = trim( $meta['boxed_custom_cta_product_logo'] ?? '' );
	$img_url  = trim( $meta['boxed_custom_cta_image'] ?? '' );

	$logo_id = $logo_url !== '' ? momentive_pc_sideload( $logo_url, $cache, $post_id, $dry_run ) : 0;
	$img_id  = $img_url !== '' ? momentive_pc_sideload( $img_url, $cache, $post_id, $dry_run ) : 0;

	$logo_block = $logo_url !== '' ? sprintf(
		'<!-- wp:image {"id":%1$d,"width":"200px","sizeSlug":"large","className":"product"} -->' . "\n" .
		'<figure class="wp-block-image size-large is-resized product"><img src="%2$s" class="wp-image-%1$d" style="width:200px"/></figure>' . "\n" .
		'<!-- /wp:image -->' . "\n\n",
		max( $logo_id, 0 ), esc_url( $logo_url )
	) : '';

	$desc_block = $desc !== '' ? sprintf( '<!-- wp:paragraph {"fontSize":"large"} -->' . "\n" . '<p class="has-large-font-size"><strong>%s</strong></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) ) : '';

	$btn_block = '';
	if ( $btn_text !== '' && $btn_url !== '' ) {
		$btn_block = '<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->' . "\n" .
			'<div class="wp-block-buttons" style="margin-top:0;margin-bottom:0"><!-- wp:button -->' . "\n" .
			sprintf( '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div>', esc_url( $btn_url ), momentive_pc_esc( $btn_text ) ) . "\n" .
			'<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons -->' . "\n\n";
	}

	$img_block = $img_url !== '' ? sprintf(
		'<!-- wp:image {"id":%1$d,"sizeSlug":"full","linkDestination":"none"} -->' . "\n" .
		'<figure class="wp-block-image size-full"><img src="%2$s" class="wp-image-%1$d"/></figure>' . "\n" .
		'<!-- /wp:image -->',
		max( $img_id, 0 ), esc_url( $img_url )
	) : '';

	return '<!-- wp:group {"className":"featured-item space-around product-gradient","style":{"spacing":{"padding":{"right":"0","left":"0"},"margin":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},"layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group featured-item space-around product-gradient" style="margin-top:var(--wp--preset--spacing--large);margin-bottom:var(--wp--preset--spacing--large);padding-right:0;padding-left:0"><!-- wp:columns {"className":"is-style-columns-reverse","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small","top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}} -->' . "\n" .
		'<div class="wp-block-columns is-style-columns-reverse" style="padding-top:var(--wp--preset--spacing--small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)"><!-- wp:column {"verticalAlignment":"center"} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center">' . $logo_block . $desc_block . $btn_block . '</div>' . "\n" .
		'<!-- /wp:column -->' . "\n\n" .
		'<!-- wp:column {"verticalAlignment":"center"} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center">' . $img_block . '</div>' . "\n" .
		'<!-- /wp:column --></div>' . "\n" .
		'<!-- /wp:columns --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_testimonials_block( $family, $family_to_cat_slug ) {
	if ( ! isset( $family_to_cat_slug[ $family ] ) ) { return ''; }
	$term = get_term_by( 'slug', $family_to_cat_slug[ $family ], 'category' );
	if ( ! $term ) { return ''; }
	return '<!-- wp:group {"align":"full","className":"alignfull","gradient":"white-light-white","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group alignfull has-white-light-white-gradient-background has-background"><!-- wp:group {"metadata":{"categories":["testimonials"],"name":"Testimonials slider, one slide visible at a time"},"className":"testimonials-wrapper alignfull"} -->' . "\n" .
		'<div class="wp-block-group testimonials-wrapper alignfull"><!-- wp:group {"align":"full","className":"testimonials-slider single-slide is-style-outline has-pagination has-side-arrows"} -->' . "\n" .
		'<div class="wp-block-group alignfull testimonials-slider single-slide is-style-outline has-pagination has-side-arrows"><!-- wp:query ' .
		sprintf( '{"queryId":21,"query":{"perPage":10,"pages":0,"offset":0,"postType":"testimonials","order":"desc","orderBy":"date","inherit":false,"taxQuery":{"solution_family":[%d]}}}', (int) $term->term_id ) .
		' -->' . "\n" .
		'<div class="wp-block-query"><!-- wp:post-template -->' . "\n" .
		'<!-- wp:momentive/testimonial {"showCaseStudyButton":false} /-->' . "\n" .
		'<!-- /wp:post-template --></div>' . "\n" .
		'<!-- /wp:query --></div>' . "\n" .
		'<!-- /wp:group --></div>' . "\n" .
		'<!-- /wp:group --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_related_products_block( $meta, $post_id, &$cache, $dry_run ) {
	$overline = trim( $meta['related_products_and_solutions_overline'] ?? '' );
	$title    = trim( $meta['related_products_and_solutions_title'] ?? '' );
	$desc     = trim( $meta['related_products_and_solutions_description'] ?? '' );
	$rows     = momentive_pc_repeater( $meta['related_products_and_solutions_list'] ?? '' );
	if ( empty( $rows ) ) { return ''; }

	$cards = '';
	$pair  = '';
	$i     = 0;
	foreach ( $rows as $row ) {
		$logo_url = trim( (string) ( $row['related_product_logo'] ?? '' ) );
		$link_url = trim( (string) ( $row['card_link_url_custom'] ?? '' ) );
		$link_txt = trim( (string) ( $row['card_link_text_custom'] ?? '' ) );
		$desc_txt = trim( (string) ( $row['card_description_custom'] ?? '' ) );

		$logo_id = $logo_url !== '' ? momentive_pc_sideload( $logo_url, $cache, $post_id, $dry_run ) : 0;
		$logo_block = $logo_url !== '' ? sprintf(
			'<!-- wp:image {"id":%1$d,"width":"auto","height":"48px","sizeSlug":"large"} -->' . "\n" .
			'<figure class="wp-block-image size-large is-resized"><img src="%2$s" class="wp-image-%1$d" style="width:auto;height:48px"/></figure>' . "\n" .
			'<!-- /wp:image -->' . "\n\n",
			max( $logo_id, 0 ), esc_url( $logo_url )
		) : '';
		$desc_block = $desc_txt !== '' ? sprintf( '<!-- wp:paragraph -->' . "\n" . '<p>%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc_txt ) ) : '';
		$link_block = ( $link_url !== '' && $link_txt !== '' ) ? sprintf( '<!-- wp:paragraph {"className":"read-more has-arrow upward"} -->' . "\n" . '<p class="read-more has-arrow upward"><a href="%s">%s</a></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", esc_url( $link_url ), momentive_pc_esc( $link_txt ) ) : '';

		$pair .= '<!-- wp:column -->' . "\n" . '<div class="wp-block-column">' . $logo_block . $desc_block . $link_block . '</div>' . "\n" . '<!-- /wp:column -->' . "\n\n";
		$i++;
		if ( $i % 2 === 0 ) {
			$cards .= '<!-- wp:columns {"className":"is-style-boxed small-gap","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|x-small"}}}} -->' . "\n" .
				'<div class="wp-block-columns is-style-boxed small-gap">' . $pair . '</div>' . "\n" .
				'<!-- /wp:columns -->' . "\n\n";
			$pair = '';
		}
	}
	if ( $pair !== '' ) {
		$cards .= '<!-- wp:columns {"className":"is-style-boxed small-gap","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|x-small"}}}} -->' . "\n" .
			'<div class="wp-block-columns is-style-boxed small-gap">' . $pair . '</div>' . "\n" .
			'<!-- /wp:columns -->' . "\n\n";
	}

	$left  = '';
	if ( $overline !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"left"}}} -->' . "\n" . '<p class="has-text-align-left is-style-eyebrow">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $overline ) );
	}
	if ( $title !== '' ) {
		$left .= sprintf( '<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"left"}}} -->' . "\n" . '<h2 class="wp-block-heading has-text-align-left balance">%s</h2>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $title ) );
	}
	if ( $desc !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph {"className":"balance","style":{"typography":{"textAlign":"left"}},"fontSize":"medium"} -->' . "\n" . '<p class="has-text-align-left balance has-medium-font-size">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) );
	}

	return '<!-- wp:group {"className":"no-margin to-edge","backgroundColor":"neutral","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group no-margin to-edge has-neutral-background-color has-background"><!-- wp:columns {"style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} -->' . "\n" .
		'<div class="wp-block-columns" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)"><!-- wp:column {"width":"35%","className":" no-padding"} -->' . "\n" .
		'<div class="wp-block-column  no-padding" style="flex-basis:35%">' . $left . '</div>' . "\n" .
		'<!-- /wp:column -->' . "\n\n" .
		'<!-- wp:column {"width":"65%","className":" no-padding","style":{"spacing":{"blockGap":"0"}}} -->' . "\n" .
		'<div class="wp-block-column no-padding" style="flex-basis:65%">' . $cards . '</div>' . "\n" .
		'<!-- /wp:column --></div>' . "\n" .
		'<!-- /wp:columns --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_info_box_block( $meta, $post_id, &$cache, $dry_run ) {
	$heading  = trim( $meta['info_box_heading'] ?? '' );
	if ( $heading === '' ) { return ''; }
	$kicker   = trim( $meta['info_box_kicker_text'] ?? '' );
	$desc     = trim( $meta['info_box_description'] ?? '' );
	$btn_text = trim( $meta['info_box_button_text'] ?? '' );
	$btn_url  = trim( $meta['info_box_button_url'] ?? '' );
	$img_url  = trim( $meta['info_box_image'] ?? '' );

	$img_id  = $img_url !== '' ? momentive_pc_sideload( $img_url, $cache, $post_id, $dry_run ) : 0;

	$left  = '';
	if ( $kicker !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n" . '<p class="is-style-eyebrow">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $kicker ) );
	}
	$left .= sprintf( '<!-- wp:heading {"level":3,"fontSize":"xx-large"} -->' . "\n" . '<h3 class="wp-block-heading has-xx-large-font-size">%s%s</h3>' . "\n" . '<!-- /wp:heading -->' . "\n\n",
		$btn_url !== '' ? sprintf( '<a href="%s">', esc_url( $btn_url ) ) : '',
		momentive_pc_esc( $heading ) . ( $btn_url !== '' ? '</a>' : '' )
	);
	if ( $desc !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph -->' . "\n" . '<p>%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) );
	}
	if ( $btn_text !== '' && $btn_url !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph {"className":"read-more chevron"} -->' . "\n" . '<p class="read-more chevron"><a href="%s">%s</a></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", esc_url( $btn_url ), momentive_pc_esc( $btn_text ) );
	}

	$right = $img_url !== '' ? sprintf(
		'<!-- wp:image {"id":%1$d,"sizeSlug":"full","linkDestination":"none"} -->' . "\n" .
		'<figure class="wp-block-image size-full"><img src="%2$s" class="wp-image-%1$d"/></figure>' . "\n" .
		'<!-- /wp:image -->',
		max( $img_id, 0 ), esc_url( $img_url )
	) : '';

	return '<!-- wp:group {"className":"featured-item space-around is-style-bg-dark","style":{"spacing":{"padding":{"right":"0","left":"0"},"margin":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},"gradient":"dark-navy","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group featured-item space-around is-style-bg-dark has-dark-navy-gradient-background has-background" style="margin-top:var(--wp--preset--spacing--large);margin-bottom:var(--wp--preset--spacing--large);padding-right:0;padding-left:0"><!-- wp:columns {"className":"is-style-outline is-style-columns-reverse ","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small","left":"var:preset|spacing|small","right":"var:preset|spacing|small"}}}} -->' . "\n" .
		'<div class="wp-block-columns is-style-outline is-style-columns-reverse" style="padding-top:var(--wp--preset--spacing--small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)"><!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small"}}}} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center" style="padding-right:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)">' . $left . '</div>' . "\n" .
		'<!-- /wp:column -->' . "\n\n" .
		'<!-- wp:column {"verticalAlignment":"center","className":"no-padding","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small"}}}} -->' . "\n" .
		'<div class="wp-block-column is-vertically-aligned-center no-padding" style="padding-right:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)">' . $right . '</div>' . "\n" .
		'<!-- /wp:column --></div>' . "\n" .
		'<!-- /wp:columns --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

/**
 * Demo form section. Gated on request_a_demo_-_enable_section, which
 * tracks the presence of request_a_demo_-_hubspot_form_script exactly
 * across the current export (verified for all 24 legacy posts before
 * writing this script) — so "disabled" and "no embed code" are the same
 * signal here, not two things to reconcile.
 */
function momentive_pc_demo_form_block( $meta ) {
	$enabled = ( $meta['request_a_demo_-_enable_section'] ?? '' ) === 'true';
	if ( ! $enabled ) { return ''; }

	$title   = trim( $meta['request_a_demo_-_title'] ?? '' );
	$desc    = trim( $meta['request_a_demo_-_description'] ?? '' );
	$embed   = trim( $meta['request_a_demo_-_hubspot_form_script'] ?? '' );
	if ( $embed === '' ) {
		WP_CLI::warning( '    request_a_demo enabled but hubspot_form_script is blank — section skipped, needs a manual embed.' );
		return '';
	}

	$left = '';
	$left .= '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n" . '<p class="is-style-eyebrow">Request a Demo</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n";
	if ( $title !== '' ) {
		$left .= sprintf( '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">%s</h2>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $title ) );
	}
	if ( $desc !== '' ) {
		$left .= sprintf( '<!-- wp:paragraph {"fontSize":"medium"} -->' . "\n" . '<p class="has-medium-font-size">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) );
	}

	// Field-key-direct ACF block data format — see the wp_slash() gotcha in
	// CLAUDE.md's whitepaper/solutions migration notes. Same field keys the
	// acf/hubspot-form block group uses everywhere else on the site.
	$block_data = array(
		'hubspot_embed_code'  => $embed,
		'_hubspot_embed_code' => 'field_6a2873ba3bf87',
		'two_step'            => '0',
		'_two_step'           => 'field_6a35626f3a11b',
	);
	$comment = sprintf(
		'<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":%s,"mode":"preview"} /-->',
		wp_json_encode( $block_data )
	);

	return '<!-- wp:group {"metadata":{"categories":[34],"name":"Demo request form"},"className":"demo-form alignfull is-style-ellipse-bottom fade-to-white","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|medium","top":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group demo-form alignfull is-style-ellipse-bottom fade-to-white" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->' . "\n" .
		'<div class="wp-block-columns"><!-- wp:column -->' . "\n" .
		'<div class="wp-block-column">' . $left . '</div>' . "\n" .
		'<!-- /wp:column -->' . "\n\n" .
		'<!-- wp:column -->' . "\n" .
		'<div class="wp-block-column">' . $comment . '</div>' . "\n" .
		'<!-- /wp:column --></div>' . "\n" .
		'<!-- /wp:columns --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_faq_block( $meta ) {
	$enabled = ( $meta['faqs_-_enable_faqs_section'] ?? '' ) === 'true';
	if ( ! $enabled ) { return ''; }
	$kicker = trim( $meta['faqs_-_kicker_text'] ?? '' );
	$title  = trim( $meta['faqs_-_title'] ?? 'FAQ' );
	$desc   = trim( $meta['faqs_-_description'] ?? '' );
	$rows   = momentive_pc_repeater( $meta['faq_item'] ?? '' );
	if ( empty( $rows ) ) { return ''; }

	$items = array();
	foreach ( $rows as $row ) {
		$q = trim( (string) ( $row['question'] ?? '' ) );
		$a = trim( wp_strip_all_tags( (string) ( $row['answer'] ?? '' ) ) );
		if ( $q === '' || $a === '' ) { continue; }
		$items[] = array(
			'_key'     => substr( md5( $q ), 0, 7 ),
			'question' => $q,
			'answer'   => $a,
			'iconSlug' => '',
			'category' => '',
		);
	}
	if ( empty( $items ) ) { return ''; }

	$header = '';
	if ( $kicker !== '' ) {
		$header .= sprintf( '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n" . '<p class="is-style-eyebrow">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $kicker ) );
	}
	$header .= sprintf( '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">%s</h2>' . "\n" . '<!-- /wp:heading -->' . "\n\n", momentive_pc_esc( $title ) );
	if ( $desc !== '' ) {
		$header .= sprintf( '<!-- wp:paragraph {"fontSize":"medium"} -->' . "\n" . '<p class="has-medium-font-size">%s</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n\n", momentive_pc_esc( $desc ) );
	}

	$accordion = sprintf( '<!-- wp:momentive/accordion {"items":%s,"queryPostsPerPage":15} /-->', wp_json_encode( $items ) );

	return '<!-- wp:group {"metadata":{"categories":[],"name":"Basic FAQ accordion"},"className":"faq-wrapper alignfull","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|large"}}},"gradient":"white-to-superlight","layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group faq-wrapper alignfull has-white-to-superlight-gradient-background has-background" style="padding-bottom:var(--wp--preset--spacing--large)"><!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n" .
		'<div class="wp-block-group">' . $header . $accordion . '</div>' . "\n" .
		'<!-- /wp:group --></div>' . "\n" .
		'<!-- /wp:group -->' . "\n\n";
}

function momentive_pc_stats_block( $meta ) {
	$rows = momentive_pc_repeater( $meta['statistics_-_stats'] ?? '' );
	if ( empty( $rows ) ) { return ''; }
	$stats = array();
	foreach ( $rows as $row ) {
		$value = trim( (string) ( $row['stat_value'] ?? '' ) );
		$desc  = trim( (string) ( $row['stat_description'] ?? '' ) );
		if ( $value === '' ) { continue; }
		$stats[] = array( 'stat_value' => $value, 'stat_description' => $desc );
	}
	if ( empty( $stats ) ) { return ''; }
	$data = array(
		'stats'   => $stats,
		'_stats'  => 'field_6a42c667b17bc',
	);
	// NOTE: unexercised — no legacy product currently has statistics data.
	// Verify field-key format and placement against a real example before
	// trusting this in a live run.
	return sprintf( '<!-- wp:acf/stat-columns {"name":"acf/stat-columns","data":%s,"mode":"preview"} /-->' . "\n\n", wp_json_encode( $data ) );
}

// ───────────────────────────────────────────────────────────────────────────
// Main loop
// ───────────────────────────────────────────────────────────────────────────
$updated = 0; $skipped_guard = 0; $skipped_missing = 0; $no_source = array(); $blank_demo = array();

foreach ( $slug_to_cct as $slug => $cct_id ) {
	if ( $only && $slug !== $only ) { continue; }

	WP_CLI::log( "── [$slug] ──" );

	$post = get_page_by_path( $slug, OBJECT, 'product' );
	if ( ! $post ) {
		WP_CLI::warning( "    no rebuilt product post at this slug — create the stub first." );
		$skipped_missing++;
		continue;
	}
	$post_id = $post->ID;

	if ( ! isset( $cct_by_id[ (string) $cct_id ] ) ) {
		WP_CLI::warning( "    CCT _ID $cct_id not found in CSV — skipping." );
		$skipped_missing++;
		continue;
	}
	$cct_row = $cct_by_id[ (string) $cct_id ];

	$source = momentive_pc_resolve_source( $slug, $cct_row, $branded_by_id, $branded_by_norm_title );
	if ( ! $source ) {
		WP_CLI::warning( "    no legacy branded-products content found (linked_product_page={$cct_row['linked_product_page']}) — likely a redirect-to-Solution alias; verify and set redirect_to_solution by hand if so." );
		$no_source[] = $slug;
		continue;
	}
	$meta = $source['meta'];

	if ( ! $force && $only !== $slug && momentive_pc_already_rebuilt( $post_id ) ) {
		WP_CLI::log( '    already has real content and is not stamped by this script — skipping (use only=' . $slug . ' force to override).' );
		$skipped_guard++;
		continue;
	}

	$family = (string) ( $cct_row['solution_family'] ?? '' );

	$demo_enabled = ( $meta['request_a_demo_-_enable_section'] ?? '' ) === 'true';
	$demo_embed_blank = $demo_enabled && trim( $meta['request_a_demo_-_hubspot_form_script'] ?? '' ) === '';
	if ( $demo_embed_blank ) { $blank_demo[] = $slug; }

	$sections = array(
		momentive_pc_hero_block( $meta, $post_id, $sideload_cache, $dry_run ),
		momentive_pc_trust_logos_block( $meta ),
		momentive_pc_features_block( $meta, $post_id, $sideload_cache, $dry_run ),
		momentive_pc_also_included_block( $meta ),
		momentive_pc_boxed_cta_block( $meta, $post_id, $sideload_cache, $dry_run ),
		momentive_pc_testimonials_block( $family, $family_to_cat_slug ),
		momentive_pc_related_products_block( $meta, $post_id, $sideload_cache, $dry_run ),
		momentive_pc_info_box_block( $meta, $post_id, $sideload_cache, $dry_run ),
		momentive_pc_demo_form_block( $meta ),
		momentive_pc_faq_block( $meta ),
		momentive_pc_stats_block( $meta ),
	);
	$post_content = implode( '', array_filter( $sections ) );

	WP_CLI::log( sprintf( '    built %d bytes of content across %d non-empty sections.', strlen( $post_content ), count( array_filter( $sections ) ) ) );

	if ( ! $dry_run ) {
		// wp_slash() gotcha: wp_update_post() calls wp_unslash() internally,
		// which strips backslashes from the JSON in the hubspot-form block
		// comment unless the post data is pre-slashed. See CLAUDE.md.
		wp_update_post( wp_slash( array(
			'ID'           => $post_id,
			'post_content' => $post_content,
		) ), true );
		update_post_meta( $post_id, MOMENTIVE_PC_RUN_META, current_time( 'mysql' ) );
	}

	$updated++;
}

WP_CLI::success( sprintf( 'Done. Updated: %d | Skipped (already rebuilt): %d | Skipped (missing post/CCT): %d | No legacy source: %d', $updated, $skipped_guard, $skipped_missing, count( $no_source ) ) );

WP_CLI::log( '' );
if ( ! empty( $no_source ) ) {
	WP_CLI::log( 'No legacy content source found (verify redirect-to-Solution candidates by hand): ' . implode( ', ', $no_source ) );
}
if ( ! empty( $blank_demo ) ) {
	WP_CLI::log( 'Demo form enabled but embed code blank (section skipped, needs manual embed): ' . implode( ', ', $blank_demo ) );
}
WP_CLI::log( '' );
WP_CLI::log( 'Not handled by this script (do manually / decide):' );
WP_CLI::log( '  • Trust logos custom-gallery branch (only Careers uses it) — logged, not built (no URL source for legacy gallery IDs).' );
WP_CLI::log( '  • Per-post padding/background hand-tweaks, same known limitation as the Solutions migration.' );
WP_CLI::log( '  • Statistics section is unexercised — no current product has legacy stats data.' );
WP_CLI::log( '  • Structured fields (summary, category, product_type, logos) are migrate-products.php\'s job, not this script\'s.' );
