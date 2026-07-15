<?php
/**
 * migrate-solutions.php
 *
 * WP-CLI migration: legacy `solutions` CPT (Elementor/Crocoblock) -> rebuilt
 * `solutions` CPT (native blocks, patterns/solution-content.php family).
 *
 * ---------------------------------------------------------------------------
 * WHY THIS SCRIPT LOOKS THE WAY IT DOES
 * ---------------------------------------------------------------------------
 * The legacy site is Elementor + Crocoblock. content:encoded and
 * _elementor_data in the WXR export are SHARED TEMPLATE SHELLS, not per-post
 * content — a child post's rendered HTML shows its PARENT's hero, not its
 * own. Neither field is used as a content source here.
 *
 * The real per-post content lives in ~220 consistently-named postmeta keys
 * shared across every one of the 87 true child posts:
 *   - event_sub_hero_-_*                 hero (title in 3 parts, description, image)
 *   - approach_-_* + accordion_items     "benefits" selling-points accordion
 *   - event_sub_features_-_* + repeater  image+text feature pairs
 *   - request_a_demo_-_*                 demo-form section
 *   - ~10 further sections, each behind its own boolean "enable" flag
 *     (statistics, faqs, benefits media collage, testimonials, image+text
 *     2-col, related-solutions grid, whitepaper promo, purple CTA, a plain
 *     bulleted list, and a content-free "resources" toggle).
 *
 * This is structured data, not prose — see migrations/solutions-migration-
 * coverage.xlsx for the full field -> block mapping and per-post enabled-
 * section inventory this script was built from.
 *
 * SCOPE (confirmed with Daniel, 2026-07-14):
 *   - Full post_content block generation for the ~87 CHILD pages (including
 *     legacy post 4428 "Accounting Software Implementation", which is
 *     structurally parent=0 in the legacy tree but carries child-shaped
 *     content — see MOMENTIVE_SOL_FORCE_PARENT below).
 *   - ACF FIELD BACKFILL ONLY for the 21 HUB-TIER posts (12 top-level
 *     families + 9 "(Split Test B)" variants). Hub content is bespoke and
 *     hand-built, same decision already made for the Products CPT
 *     (migrate-products.php) — this script does not touch their post_content.
 *   - 6 legacy "(OLD)" / "(DUPLICATE)" drafts are skipped entirely — dead
 *     content, no rebuild target.
 *   - Testimonials: BLOCKED on a legacy testimonials WXR export. Until
 *     MOMENTIVE_SOL_TESTIMONIALS_WXR exists on disk, the script logs which
 *     posts had a testimonials section enabled and skips the block. Once the
 *     export is added, it reuses migrate-case-studies.php's normalized-
 *     quote-text matching against the rebuilt `testimonials` CPT.
 *
 * KNOWN GAPS (see coverage sheet for detail — these are not bugs, there is
 * no source data to resolve them from):
 *   - connected_products (CCT CSV) is unusable — Crocoblock exported every
 *     row as the literal string "Array", not real product IDs.
 *   - solution_order has no source for child pages outside the Accounting
 *     family (the CCT only tracks Accounting's 7 children) — set the rest
 *     by hand after migration.
 *   - The "resources" section (enabled on roughly half of children) carries
 *     NO content fields in the export at all. It renders a heading-only
 *     placeholder; the theme has no cross-CPT resources block yet (see
 *     CLAUDE.md "Known limitations: Resource filters").
 *   - 3 very-low-frequency sections (event_sub_list_w_heading,
 *     event_sub_accordion, sol_sub_features_accordion — 1 post each) are not
 *     scripted; the run log names the post(s) so they can be hand-built.
 *
 * USAGE (flags are POSITIONAL — `wp eval-file` rejects --flags):
 *   wp eval-file migrate-solutions.php                  # LIVE (writes posts)
 *   wp eval-file migrate-solutions.php dry-run           # no writes
 *   wp eval-file migrate-solutions.php dry-run limit=6   # dry run, first 6
 *   wp eval-file migrate-solutions.php only=4406         # single legacy ID
 *   wp eval-file migrate-solutions.php hubs-only         # hub-tier field backfill only
 *   wp eval-file migrate-solutions.php children-only      # child content build only
 *   MOMENTIVE_LIVE=1 wp eval-file migrate-solutions.php  # dry run via env
 *
 * Must run with --user=<admin-login-or-id> (media sideload capability gate,
 * same requirement as the other *-wxr migrations on this theme).
 *
 * SOURCES (override via constants below or place beside this script):
 *   MOMENTIVE_SOL_LEGACY_WXR          momentivesoftware.solutions.current.2026-07-14.xml
 *   MOMENTIVE_SOL_CCT_CSV             solution-settings-cct.csv
 *   MOMENTIVE_SOL_TESTIMONIALS_WXR    legacy testimonials export (optional; see above)
 *   MOMENTIVE_SOL_UPLOADS_BASE        media host for sideloading
 *   MOMENTIVE_ICON_DIR                assets/icons manifest directory
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

const MOMENTIVE_SOL_TYPE          = 'solutions';
const MOMENTIVE_SOL_TESTIMONIAL_TYPE = 'testimonials';
const MOMENTIVE_SOL_RUN_META      = '_momentive_migration_run';
const MOMENTIVE_SOL_SOURCE_META   = '_momentive_source_url';

// ACF field keys (Solution Settings group, group_6a1e022de5da6).
const MOMENTIVE_SOL_FK_BREADCRUMB_TITLE = 'field_6a225b0ed6b08';
const MOMENTIVE_SOL_FK_ACCENT_COLOR     = 'field_6a1e0271ae913';
const MOMENTIVE_SOL_FK_ICON             = 'field_6a1e02ccae914';
const MOMENTIVE_SOL_FK_ORDER            = 'field_6a32e3f929c42';
const MOMENTIVE_SOL_FK_BACKGROUND_IMAGE = 'field_6a1e02ffae915';
const MOMENTIVE_SOL_FK_CARD_LABEL       = 'field_6a20b62df882b';

// acf/hubspot-form block field key (block-level embed override).
const MOMENTIVE_SOL_FK_HUBSPOT_EMBED = 'field_6a2873ba3bf87';

/**
 * Legacy IDs to skip entirely — "(OLD)" / "(DUPLICATE)" drafts with no
 * rebuild target. Confirmed with Daniel 2026-07-14.
 */
const MOMENTIVE_SOL_EXCLUDE_IDS = array( 2372, 2935, 3203, 4312, 6856, 10152 );

/**
 * Legacy IDs of the 12 top-level hub families + the 9 "(Split Test B)"
 * variants. Both are HUB-TIER: fields-only backfill, no post_content touch.
 */
const MOMENTIVE_SOL_HUB_IDS = array(
	// 12 top-level families (+ standalone MomentiveIQ, + orphan draft Data & Analytics)
	263, 264, 265, 267, 268, 5309, 6000, 6383, 7299, 7582,
	// (association-management and career-center hubs are counted via 6000/6540 below)
	6540,
	// 9 Split Test B variants
	11018, 11054, 11062, 11063, 11065, 11066, 11068, 11071, 11072,
);

/**
 * Legacy post 4428 ("Accounting Software Implementation") is the one
 * exception: structurally parent=0 in the legacy WP tree, but its postmeta
 * is shaped exactly like a true child (event_sub_hero_-_* etc. populated).
 * Content-build it as a child of the rebuilt Accounting hub.
 */
const MOMENTIVE_SOL_FORCE_PARENT = array(
	4428 => 263,
);

/**
 * Legacy hub-family parent post ID -> rebuilt category slug (children of the
 * "Solutions" parent category). Used to assign the category taxonomy and to
 * resolve sibling child posts for the "related solutions" grid. Mirrors the
 * family map already established in migrate-products.php.
 */
const MOMENTIVE_SOL_FAMILY_TO_CAT_SLUG = array(
	263  => 'accounting',
	264  => 'learning-management',
	265  => 'fundraising',
	267  => 'event-management',
	268  => 'data-analytics',
	5309 => 'certification-management',
	6000 => 'association-management',
	6383 => 'volunteer-management',
	6540 => 'career-centers',
	7582 => 'donor-management',
);

/* -------------------------------------------------------------------------
 * Run identity / small helpers
 * ---------------------------------------------------------------------- */

function momentive_sol_run_id(): string {
	static $id = null;
	if ( null === $id ) {
		$id = gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	return $id;
}

/** Strip the legacy "box-" icon prefix. No bxs->bx fallback — write faithfully. */
function momentive_sol_normalize_icon( string $icon ): string {
	$icon = trim( $icon );
	if ( 0 === strpos( $icon, 'box-' ) ) {
		$icon = substr( $icon, 4 );
	}
	return $icon;
}

/** Icon slugs actually available in assets/icons/*.svg. */
function momentive_sol_icon_manifest(): array {
	static $manifest = null;
	if ( null !== $manifest ) {
		return $manifest;
	}
	$manifest = array();
	$dir = defined( 'MOMENTIVE_ICON_DIR' ) ? MOMENTIVE_ICON_DIR : get_stylesheet_directory() . '/assets/icons';
	if ( is_dir( $dir ) ) {
		foreach ( glob( $dir . '/*.svg' ) ?: array() as $file ) {
			$manifest[ basename( $file, '.svg' ) ] = true;
		}
	}
	return $manifest;
}

/** wp_json_encode with the slash/unicode flags every block-attr call here wants. */
function momentive_sol_json( array $data ): string {
	return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/**
 * Normalize a quote string for fuzzy matching against the testimonials CPT.
 * Mirrors migrate-case-studies.php's momentive_cs_norm_quote().
 */
function momentive_sol_norm_quote( string $q ): string {
	if ( '' === trim( $q ) ) {
		return '';
	}
	$q = html_entity_decode( $q, ENT_QUOTES, 'UTF-8' );
	$q = wp_strip_all_tags( $q );
	$map = array(
		"\xE2\x80\x99" => "'", "\xE2\x80\x98" => "'",
		"\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
		"\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
		"\xC2\xA0"     => ' ',
	);
	$q = strtr( $q, $map );
	$q = preg_replace( '#\[[^\]]*\]#', ' ', $q );
	$q = strtolower( $q );
	$q = preg_replace( '#[^a-z0-9 ]#', ' ', $q );
	$q = preg_replace( '#\s+#', ' ', $q );
	return trim( (string) $q );
}

/* -------------------------------------------------------------------------
 * Legacy source: parse `solutions` posts from the WXR export
 * ---------------------------------------------------------------------- */

/** Extract a single CDATA/plain child tag value from an <item> block. */
function momentive_sol_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Parse all `solutions` items from the legacy WXR into structured arrays.
 *
 * @return array<int,array{id:int,title:string,slug:string,status:string,parent:int,meta:array<string,string>}>
 */
function momentive_sol_load_legacy_posts(): array {
	$path = defined( 'MOMENTIVE_SOL_LEGACY_WXR' )
		? MOMENTIVE_SOL_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.solutions.current.2026-07-14.xml';

	$out = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Legacy WXR not found at {$path}. Set MOMENTIVE_SOL_LEGACY_WXR or place the export beside the script." );
		return $out;
	}
	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::error( 'Could not read legacy solutions WXR.' );
		return $out;
	}

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		return $out;
	}

	foreach ( $items[1] as $item ) {
		if ( false === strpos( $item, '<wp:post_type><![CDATA[solutions]]>' ) ) {
			continue;
		}

		$meta = array();
		if ( preg_match_all(
			'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $pair ) {
				if ( ! array_key_exists( $pair[1], $meta ) ) {
					$meta[ $pair[1] ] = $pair[2];
				}
			}
		}

		$out[] = array(
			'id'     => (int) momentive_sol_xml_tag( $item, 'wp:post_id' ),
			'title'  => momentive_sol_xml_tag( $item, 'title' ),
			'slug'   => momentive_sol_xml_tag( $item, 'wp:post_name' ),
			'status' => momentive_sol_xml_tag( $item, 'wp:status' ) ?: 'publish',
			'parent' => (int) momentive_sol_xml_tag( $item, 'wp:post_parent' ),
			'meta'   => $meta,
		);
	}

	usort( $out, static function ( $a, $b ) {
		return $a['id'] <=> $b['id'];
	} );

	return $out;
}

/** Read a legacy meta value, unserializing if it's a serialized array. */
function momentive_sol_meta( array $legacy, string $key ) {
	$raw = $legacy['meta'][ $key ] ?? '';
	if ( '' === $raw ) {
		return '';
	}
	return maybe_unserialize( $raw );
}

/** Shorthand: string meta value, trimmed. */
function momentive_sol_str( array $legacy, string $key ): string {
	$v = momentive_sol_meta( $legacy, $key );
	return is_string( $v ) ? trim( $v ) : '';
}

/** Shorthand: boolean "true"/"false" string meta value. */
function momentive_sol_bool( array $legacy, string $key ): bool {
	return 'true' === momentive_sol_str( $legacy, $key );
}

/** Shorthand: repeater meta value as a list, or []. */
function momentive_sol_repeater( array $legacy, string $key ): array {
	$v = momentive_sol_meta( $legacy, $key );
	return is_array( $v ) ? array_values( $v ) : array();
}

/* -------------------------------------------------------------------------
 * Attachment map (legacy attachment ID -> URL), for sideloading
 * ---------------------------------------------------------------------- */

function momentive_sol_build_attachment_map(): array {
	$path = defined( 'MOMENTIVE_SOL_LEGACY_WXR' )
		? MOMENTIVE_SOL_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.solutions.current.2026-07-14.xml';
	$base = defined( 'MOMENTIVE_SOL_UPLOADS_BASE' )
		? MOMENTIVE_SOL_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/';
	$base = rtrim( $base, '/' ) . '/';

	$map = array();
	if ( ! file_exists( $path ) ) {
		return $map;
	}
	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		return $map;
	}

	if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		foreach ( $items[1] as $item ) {
			if ( false === strpos( $item, '<wp:post_type><![CDATA[attachment]]>' ) ) {
				continue;
			}
			if ( ! preg_match( '#<wp:post_id>(\d+)</wp:post_id>#', $item, $pm ) ) {
				continue;
			}
			// Prefer the canonical attachment URL if present; else derive from _wp_attached_file.
			if ( preg_match( '#<wp:attachment_url><!\[CDATA\[(.*?)\]\]></wp:attachment_url>#s', $item, $um ) ) {
				$map[ (int) $pm[1] ] = $um[1];
				continue;
			}
			if ( preg_match(
				'#<wp:meta_key><!\[CDATA\[_wp_attached_file\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]>#s',
				$item, $fm ) ) {
				$map[ (int) $pm[1] ] = $base . ltrim( $fm[1], '/' );
			}
		}
	}

	WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs resolved to URLs.', count( $map ) ) );
	return $map;
}

/**
 * Sideload a legacy attachment ID into the rebuilt media library, deduped by
 * source URL. Returns the rebuilt attachment ID, or 0 if unresolved/failed.
 */
function momentive_sol_sideload_id( int $legacy_attachment_id, array $attach_map, int $post_id, bool $dry ): int {
	if ( $legacy_attachment_id <= 0 ) {
		return 0;
	}
	$url = $attach_map[ $legacy_attachment_id ] ?? '';
	if ( '' === $url ) {
		return 0;
	}
	return momentive_sol_sideload_url( $url, $post_id, $dry );
}

function momentive_sol_sideload_url( string $url, int $post_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}

	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => MOMENTIVE_SOL_SOURCE_META,
		'meta_value'     => $url,
		'no_found_rows'  => true,
	) );
	if ( $existing ) {
		return (int) $existing[0];
	}

	if ( $dry ) {
		WP_CLI::log( sprintf( '    [dry-run] would sideload %s', basename( wp_parse_url( $url, PHP_URL_PATH ) ?? $url ) ) );
		return -1;
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( sprintf( '    sideload failed (download): %s — %s', $url, $tmp->get_error_message() ) );
		return 0;
	}
	$file_array = array(
		'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ?? 'file' ),
		'tmp_name' => $tmp,
	);
	$id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( sprintf( '    sideload failed (handle): %s — %s', $url, $id->get_error_message() ) );
		return 0;
	}
	update_post_meta( (int) $id, MOMENTIVE_SOL_SOURCE_META, $url );
	return (int) $id;
}

/* -------------------------------------------------------------------------
 * Solution Settings CCT (solution-settings-cct.csv) — order lookup only.
 * Only covers the 12 hub families + Accounting's 7 children (19 of 114
 * posts) — see coverage sheet. connected_products in this file is broken
 * (exported as the literal string "Array") and is not read here.
 * ---------------------------------------------------------------------- */

function momentive_sol_load_cct(): array {
	$path = defined( 'MOMENTIVE_SOL_CCT_CSV' ) ? MOMENTIVE_SOL_CCT_CSV : __DIR__ . '/solution-settings-cct.csv';
	$by_linked_page = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Solution Settings CCT not found at {$path} — solution_order will be left unset." );
		return $by_linked_page;
	}
	if ( ( $fh = fopen( $path, 'r' ) ) === false ) {
		return $by_linked_page;
	}
	$header = fgetcsv( $fh );
	if ( $header ) {
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
	}
	while ( ( $line = fgetcsv( $fh ) ) !== false ) {
		if ( count( $line ) === 1 && trim( (string) $line[0] ) === '' ) {
			continue;
		}
		$row = array_combine( $header, $line );
		if ( ! empty( $row['linked_solution_page'] ) ) {
			$by_linked_page[ (int) $row['linked_solution_page'] ] = $row;
		}
	}
	fclose( $fh );
	return $by_linked_page;
}

/* -------------------------------------------------------------------------
 * Testimonial resolution (BLOCKED on legacy testimonials WXR)
 * ---------------------------------------------------------------------- */

/**
 * Load legacy testimonial posts (id -> quote text) from an optional legacy
 * testimonials WXR, if present. Returns [] if the file doesn't exist yet —
 * callers must treat that as "can't resolve, log and skip", not an error.
 */
function momentive_sol_load_legacy_testimonials(): array {
	$path = defined( 'MOMENTIVE_SOL_TESTIMONIALS_WXR' ) ? MOMENTIVE_SOL_TESTIMONIALS_WXR : null;
	if ( ! $path ) {
		// Try a couple of likely filenames before giving up.
		foreach ( glob( __DIR__ . '/*testimonial*.xml' ) ?: array() as $candidate ) {
			$path = $candidate;
			break;
		}
	}
	if ( ! $path || ! file_exists( $path ) ) {
		return array();
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		return array();
	}
	$out = array();
	if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		foreach ( $items[1] as $item ) {
			if ( false === strpos( $item, '<wp:post_type><![CDATA[testimonials]]>' )
				&& false === strpos( $item, '<wp:post_type><![CDATA[testimonial]]>' ) ) {
				continue;
			}
			$id = (int) momentive_sol_xml_tag( $item, 'wp:post_id' );
			// Try the common legacy field names for the quote text.
			$quote = '';
			foreach ( array( 'testimonial_content', 'quote', 'testimonial_quote' ) as $key ) {
				if ( preg_match(
					'#<wp:meta_key><!\[CDATA\[' . preg_quote( $key, '#' ) . '\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]>#s',
					$item, $qm ) ) {
					$quote = $qm[1];
					break;
				}
			}
			if ( $id && '' !== trim( $quote ) ) {
				$out[ $id ] = $quote;
			}
		}
	}
	return $out;
}

/** Build a normalized-quote index of the rebuilt `testimonials` CPT. */
function momentive_sol_build_testimonial_index(): array {
	$index = array( 'by_norm' => array() );
	$q = new WP_Query( array(
		'post_type'      => MOMENTIVE_SOL_TESTIMONIAL_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $q->posts as $tid ) {
		$content = (string) get_field( 'testimonial_content', $tid );
		$norm    = momentive_sol_norm_quote( $content );
		if ( '' !== $norm && ! isset( $index['by_norm'][ $norm ] ) ) {
			$index['by_norm'][ $norm ] = $tid;
		}
	}
	return $index;
}

/**
 * Resolve a legacy testimonial ID to a rebuilt testimonial post ID.
 * Returns 0 if the legacy testimonials WXR hasn't been provided, or if no
 * match is found (both are logged by the caller).
 */
function momentive_sol_resolve_testimonial( int $legacy_id, array $legacy_testimonials, array $t_index ): int {
	if ( ! isset( $legacy_testimonials[ $legacy_id ] ) ) {
		return 0;
	}
	$nq = momentive_sol_norm_quote( $legacy_testimonials[ $legacy_id ] );
	if ( '' === $nq ) {
		return 0;
	}
	if ( isset( $t_index['by_norm'][ $nq ] ) ) {
		return (int) $t_index['by_norm'][ $nq ];
	}
	// Conservative substring fallback (>=40 char overlap), mirrors migrate-case-studies.php.
	foreach ( $t_index['by_norm'] as $norm => $tid ) {
		if ( ( false !== strpos( $norm, $nq ) || false !== strpos( $nq, $norm ) )
			&& min( strlen( $nq ), strlen( $norm ) ) >= 40 ) {
			return (int) $tid;
		}
	}
	return 0;
}

/* -------------------------------------------------------------------------
 * Block builders — child page sections
 *
 * Order mirrors patterns/solution-content.php, extended with the sections
 * the legacy data actually carries (see coverage sheet, "Section to Block
 * Mapping" tab). Every builder returns '' when its section has no content,
 * so assemble_child_content() can simply concatenate non-empty pieces.
 * ---------------------------------------------------------------------- */

function momentive_sol_breadcrumb_block(): string {
	return '<!-- wp:group {"className":"breadcrumb-bar","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group breadcrumb-bar"><!-- wp:momentive/breadcrumbs /--></div>'
		. '<!-- /wp:group -->';
}

/**
 * Hero. Legacy headline is 3 parts (preanimated / animated / postanimated)
 * reassembled as "{pre} <strong>{animated}</strong> {post}" into the
 * existing "is-style-has-swoop tucked" heading. The H1 uses the post's own
 * title (not the legacy hero fields, which are mostly reused sitewide
 * boilerplate copy).
 */
function momentive_sol_hero_block( array $legacy, string $post_title, array $attach_map, int $post_id, bool $dry ): string {
	$kicker  = momentive_sol_str( $legacy, 'solutions_sub_hero_title_kicker_text' );
	$pre     = trim( str_ireplace( '<br>', ' ', momentive_sol_str( $legacy, 'event_sub_hero_-_title_preanimated' ) ) );
	$mid     = momentive_sol_str( $legacy, 'event_sub_hero_-_title_animated' );
	$post    = momentive_sol_str( $legacy, 'event_sub_hero_-_title_postanimated' );
	$desc    = momentive_sol_str( $legacy, 'event_sub_hero_-_description' );
	$btn_lbl = momentive_sol_str( $legacy, 'solutions_sub_hero_-_button_label' ) ?: 'Talk to an expert';
	$btn_url = momentive_sol_str( $legacy, 'solutions_sub_hero_-_button_url' ) ?: '#form';
	$img_id  = (int) momentive_sol_str( $legacy, 'event_sub_hero_-_image' );

	$swoop_parts = array_filter( array( esc_html( $pre ), $mid ? '<strong>' . esc_html( $mid ) . '</strong>' : '', esc_html( $post ) ) );
	$swoop = trim( implode( ' ', $swoop_parts ) );

	$kicker_block = '';
	if ( '' !== $kicker ) {
		$kicker_block = '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">'
			. esc_html( $kicker ) . '</p><!-- /wp:paragraph -->';
	}

	$rebuilt_img_id = momentive_sol_sideload_id( $img_id, $attach_map, $post_id, $dry );
	$image_block = '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="" alt="" /></figure><!-- /wp:image -->';
	if ( $rebuilt_img_id > 0 ) {
		$image_block = sprintf(
			'<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="%2$s" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
			$rebuilt_img_id,
			esc_url( wp_get_attachment_url( $rebuilt_img_id ) ?: '' )
		);
	}

	return '<!-- wp:group {"className":"is-style-bg-dots hero-background","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group is-style-bg-dots hero-background"><!-- wp:group {"className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group hero" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->'
		. '<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->'
		. '<div class="wp-block-column is-vertically-aligned-center">'
		. $kicker_block
		. '<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"left"}}} --><h1 class="wp-block-heading has-text-align-left">' . esc_html( $post_title ) . '</h1><!-- /wp:heading -->'
		. '<!-- wp:heading {"className":"is-style-has-swoop tucked","style":{"typography":{"textAlign":"left"}},"fontSize":"xxx-large"} --><h2 class="wp-block-heading has-text-align-left is-style-has-swoop tucked has-xxx-large-font-size">' . $swoop . '</h2><!-- /wp:heading -->'
		. ( '' !== $desc ? '<!-- wp:paragraph {"className":"narrow balance","style":{"typography":{"textAlign":"left"}}} --><p class="has-text-align-left narrow balance">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '<!-- wp:buttons {"layout":{"type":"flex"}} --><div class="wp-block-buttons"><!-- wp:button {"style":{"typography":{"textAlign":"center"}}} --><div class="wp-block-button"><a class="wp-block-button__link has-text-align-center wp-element-button" href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_lbl ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
		. '</div><!-- /wp:column -->'
		. '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">' . $image_block . '</div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group --></div><!-- /wp:group -->';
}

/** "Benefits" selling-points accordion (approach_-_* + accordion_items). */
function momentive_sol_approach_block( array $legacy, array &$unresolved_icons, string $title ): string {
	if ( ! momentive_sol_bool( $legacy, 'approach_-_enable_approach_section' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'approach_-_kicker_text' );
	$heading = momentive_sol_str( $legacy, 'approach_-_title' );
	$desc = momentive_sol_str( $legacy, 'approach_-_description' );
	$items_raw = momentive_sol_repeater( $legacy, 'accordion_items' );
	if ( empty( $items_raw ) ) {
		return '';
	}
	$manifest = momentive_sol_icon_manifest();
	$items = array();
	foreach ( $items_raw as $row ) {
		$slug = momentive_sol_normalize_icon( (string) ( $row['icon'] ?? '' ) );
		if ( '' !== $slug && ! isset( $manifest[ $slug ] ) ) {
			$unresolved_icons[] = sprintf( '%s: accordion icon "%s"', $title, $slug );
		}
		$items[] = array(
			'_key'     => 'item' . wp_generate_password( 6, false ),
			'question' => trim( (string) ( $row['title'] ?? '' ) ),
			'answer'   => trim( (string) ( $row['description'] ?? '' ) ),
			'iconSlug' => $slug,
			'category' => '',
		);
	}
	$accordion_attrs = momentive_sol_json( array( 'style' => 'icon', 'items' => $items ) );

	return '<!-- wp:group {"className":"selling-points","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group selling-points"><!-- wp:columns -->'
		. '<div class="wp-block-columns"><!-- wp:column -->'
		. '<div class="wp-block-column">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $heading ? '<!-- wp:paragraph {"className":"h2","fontSize":"xxx-large"} --><p class="h2 has-xxx-large-font-size">' . wp_kses_post( $heading ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:column -->'
		. '<!-- wp:column --><div class="wp-block-column"><!-- wp:momentive/accordion ' . $accordion_attrs . ' /--></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group -->';
}

/**
 * Features: repeating image+text pairs (event_sub_features_-_* + repeater).
 * Always present on children — not currently represented in
 * patterns/solution-content.php; this is new section coverage.
 */
function momentive_sol_features_block( array $legacy, array $attach_map, int $post_id, bool $dry ): string {
	$kicker = momentive_sol_str( $legacy, 'event_sub_features_-_kicker_text' );
	$heading = momentive_sol_str( $legacy, 'event_sub_features_-_title' );
	$desc = momentive_sol_str( $legacy, 'event_sub_features_-_description' );
	$rows = momentive_sol_repeater( $legacy, 'event_sub_features_-_repeater' );
	if ( empty( $rows ) && '' === $heading ) {
		return '';
	}

	$intro = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--small)">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $heading ? '<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><h2 class="wp-block-heading has-text-align-center balance">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center balance">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:group -->';

	$pairs = '';
	foreach ( $rows as $row ) {
		$r_kicker = trim( (string) ( $row['event_sub_feat_rep_-_kicker'] ?? '' ) );
		$r_title  = trim( (string) ( $row['event_sub_feat_rep_-_title'] ?? '' ) );
		$r_desc   = trim( (string) ( $row['event_sub_feat_rep_-_desc'] ?? '' ) );
		$r_img    = (int) ( $row['event_sub_feat_rep_-_image'] ?? 0 );
		$position = (string) ( $row['event_sub_feat_rep_-_image_position'] ?? 'left' );
		if ( '' === $r_title && '' === $r_desc && ! $r_img ) {
			continue;
		}
		$att_id = momentive_sol_sideload_id( $r_img, $attach_map, $post_id, $dry );
		$media_attrs = array(
			'linkDestination' => 'none',
			'mediaType'       => 'image',
			'className'       => 'is-style-stacked',
		);
		if ( $att_id > 0 ) {
			$media_attrs['mediaId'] = $att_id;
		}
		$is_left = 'right' !== $position;
		$img_tag = $att_id > 0
			? '<img src="' . esc_url( wp_get_attachment_url( $att_id ) ?: '' ) . '" alt="" class="wp-image-' . $att_id . '"/>'
			: '<img src="" alt="" />';

		$pairs .= '<!-- wp:media-text ' . momentive_sol_json( $media_attrs ) . ' -->'
			. '<div class="wp-block-media-text is-stacked-on-mobile is-style-stacked' . ( $is_left ? '' : ' has-media-on-the-right' ) . '">'
			. '<figure class="wp-block-media-text__media">' . $img_tag . '</figure>'
			. '<div class="wp-block-media-text__content">'
			. ( '' !== $r_kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">' . esc_html( $r_kicker ) . '</p><!-- /wp:paragraph -->' : '' )
			. ( '' !== $r_title ? '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $r_title ) . '</h3><!-- /wp:heading -->' : '' )
			. ( '' !== $r_desc ? '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">' . esc_html( $r_desc ) . '</p><!-- /wp:paragraph -->' : '' )
			. '</div></div><!-- /wp:media-text -->';
	}

	if ( '' === $pairs ) {
		return $intro; // heading-only, no usable rows
	}

	return '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">' . $intro . $pairs . '</div><!-- /wp:group -->';
}

/** Benefits media collage (benefits_-_* — 19/87). */
function momentive_sol_benefits_media_block( array $legacy, array $attach_map, int $post_id, bool $dry ): string {
	if ( ! momentive_sol_bool( $legacy, 'benefits_-_enable_benefits_media_section' ) ) {
		return '';
	}
	$heading = momentive_sol_str( $legacy, 'benefits_-_title' );
	$desc = momentive_sol_str( $legacy, 'benefits_-_description' );
	$main = momentive_sol_sideload_id( (int) momentive_sol_str( $legacy, 'benefits_-_main_image' ), $attach_map, $post_id, $dry );
	$f1 = momentive_sol_sideload_id( (int) momentive_sol_str( $legacy, 'benefits_-_floating_image_1' ), $attach_map, $post_id, $dry );
	$f2 = momentive_sol_sideload_id( (int) momentive_sol_str( $legacy, 'benefits_-_floating_image_2' ), $attach_map, $post_id, $dry );

	$img_block = static function ( int $id, string $class ) {
		if ( $id <= 0 ) {
			return '';
		}
		return sprintf(
			'<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none","className":"%2$s"} --><figure class="wp-block-image size-large %2$s"><img src="%3$s" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
			$id, esc_attr( $class ), esc_url( wp_get_attachment_url( $id ) ?: '' )
		);
	};

	$images = $img_block( $main, 'horizontal' ) . $img_block( $f1, 'circle circle-left' ) . $img_block( $f2, 'circle circle-right' );
	if ( '' === $heading && '' === $desc && '' === $images ) {
		return '';
	}

	return '<!-- wp:group {"className":"content-collage wide is-style-bg-dots is-style-ellipse-top","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group content-collage wide is-style-bg-dots is-style-ellipse-top"><!-- wp:group {"className":"content-collage-inner","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group content-collage-inner">'
		. ( '' !== $heading ? '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph --><p>' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $images ? '<!-- wp:group {"className":"content-collage-images","layout":{"type":"constrained"}} --><div class="wp-block-group content-collage-images">' . $images . '</div><!-- /wp:group -->' : '' )
		. '</div><!-- /wp:group --></div><!-- /wp:group -->';
}

/** Stats (statistics_-_* + repeater — 19/87). */
function momentive_sol_stats_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'statistics_-_enable_statistics_section' ) ) {
		return '';
	}
	$heading = momentive_sol_str( $legacy, 'statistics_-_title' );
	$stats = momentive_sol_repeater( $legacy, 'statistics_-_stats' );
	if ( empty( $stats ) ) {
		return '';
	}
	$cols = '';
	$width = (int) floor( 50 / max( 1, count( $stats ) ) ) ?: 25;
	foreach ( $stats as $row ) {
		$attrs = array(
			'statPrefix' => (string) ( $row['number_prefix'] ?? '' ),
			'statNumber' => is_numeric( $row['number'] ?? null ) ? 0 + $row['number'] : 0,
			'statSuffix' => (string) ( $row['number_suffix'] ?? '' ),
			'statLabel'  => (string) ( $row['description'] ?? '' ),
		);
		if ( ! empty( $row['accent_color'] ) ) {
			$attrs['accentColor'] = $row['accent_color'];
		}
		$cols .= '<!-- wp:column {"width":"' . $width . '%"} --><div class="wp-block-column" style="flex-basis:' . $width . '%"><!-- wp:momentive/impact-stat ' . momentive_sol_json( $attrs ) . ' /--></div><!-- /wp:column -->';
	}

	return '<!-- wp:group {"className":"impact-stat-wrapper wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group impact-stat-wrapper wide" style="margin-top:var(--wp--preset--spacing--large);margin-bottom:var(--wp--preset--spacing--large)"><!-- wp:group {"className":"impact-stats-inner","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group impact-stats-inner">'
		. ( '' !== $heading ? '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. '<!-- wp:columns --><div class="wp-block-columns">' . $cols . '</div><!-- /wp:columns -->'
		. '</div><!-- /wp:group --></div><!-- /wp:group -->';
}

/** FAQs — page-specific static Q&A (faqs_-_* + faq_item — 17/87). NOT query mode. */
function momentive_sol_faqs_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'faqs_-_enable_faqs_section' ) ) {
		return '';
	}
	$heading = momentive_sol_str( $legacy, 'faqs_-_title' ) ?: 'FAQ';
	$desc = momentive_sol_str( $legacy, 'faqs_-_description' );
	$rows = momentive_sol_repeater( $legacy, 'faq_item' );
	if ( empty( $rows ) ) {
		return '';
	}
	$items = array();
	foreach ( $rows as $row ) {
		$items[] = array(
			'_key'     => 'item' . wp_generate_password( 6, false ),
			'question' => trim( (string) ( $row['question'] ?? '' ) ),
			'answer'   => trim( (string) ( $row['answer'] ?? '' ) ),
			'iconSlug' => '',
			'category' => '',
		);
	}
	$attrs = momentive_sol_json( array( 'style' => 'default', 'items' => $items ) );

	$cta_title = momentive_sol_str( $legacy, 'faq_cta_title' );
	$cta_lbl   = momentive_sol_str( $legacy, 'faq_cta_button_text' );
	$cta_url   = momentive_sol_str( $legacy, 'faq_cta_button_link' );
	$cta = '';
	if ( '' !== $cta_title && '' !== $cta_lbl ) {
		$cta = '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. '<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center">' . esc_html( $cta_title ) . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $cta_url ?: '/request-a-demo/' ) . '">' . esc_html( $cta_lbl ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
			. '</div><!-- /wp:group -->';
	}

	return '<!-- wp:group {"className":"faq-wrapper","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|large"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group faq-wrapper" style="padding-bottom:var(--wp--preset--spacing--large)">'
		. '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->'
		. ( '' !== $desc ? '<!-- wp:paragraph --><p>' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '<!-- wp:momentive/accordion ' . $attrs . ' /-->'
		. '</div><!-- /wp:group -->'
		. $cta;
}

/** Testimonial reference (18-19/87). Logs + skips if unresolved. */
function momentive_sol_testimonial_block( array $legacy, array $legacy_testimonials, array $t_index, array &$log, string $title ): string {
	$enabled = momentive_sol_bool( $legacy, 'testimonials_-_enable_testimonials_section_83' )
		|| momentive_sol_bool( $legacy, 'solutions_sub_testimonials_-_enable_section' );
	if ( ! $enabled ) {
		return '';
	}
	$ids = momentive_sol_repeater( $legacy, 'testimonials' );
	if ( empty( $ids ) ) {
		$ids = momentive_sol_repeater( $legacy, 'case_study_testimonials' );
	}
	if ( empty( $ids ) ) {
		return '';
	}
	if ( empty( $legacy_testimonials ) ) {
		$log[] = sprintf( '%s: testimonial section enabled (legacy IDs: %s) — no legacy testimonials WXR yet, skipped.', $title, implode( ',', $ids ) );
		return '';
	}
	$legacy_id = (int) $ids[0]; // one testimonial per section in the rebuilt block.
	$tid = momentive_sol_resolve_testimonial( $legacy_id, $legacy_testimonials, $t_index );
	if ( $tid <= 0 ) {
		$log[] = sprintf( '%s: testimonial legacy ID %d could not be matched to a rebuilt testimonial post.', $title, $legacy_id );
		return '';
	}
	$attrs = momentive_sol_json( array( 'testimonialId' => $tid, 'showCaseStudyButton' => false ) );
	return '<!-- wp:group {"className":"alignfull no-margin","gradient":"vertical"} --><div class="wp-block-group alignfull no-margin has-vertical-gradient-background has-background">'
		. '<!-- wp:columns {"className":"content-width","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} --><div class="wp-block-columns content-width" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)">'
		. '<!-- wp:column {"width":"66.66%","className":"no-padding"} --><div class="wp-block-column no-padding" style="flex-basis:66.66%"><!-- wp:momentive/testimonial ' . $attrs . ' /--></div><!-- /wp:column -->'
		. '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%"></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group -->';
}

/** Image + text, 2 columns (image__text_2_cols_-_* — 18/87). */
function momentive_sol_image_text_2cols_block( array $legacy, array $attach_map, int $post_id, bool $dry ): string {
	if ( ! momentive_sol_bool( $legacy, 'image__text_2_cols_-_enable_section' ) ) {
		return '';
	}
	$heading = momentive_sol_str( $legacy, 'image__text_2_cols_-_title' );
	$desc = momentive_sol_str( $legacy, 'image__text_2_cols_-_description' );
	$right_rows = momentive_sol_repeater( $legacy, 'image__text_2_cols_-_right_col' );
	$right = reset( $right_rows ) ?: array();
	$r_img = (int) ( $right['image_text_2_cols_rightcol_-_image'] ?? 0 );
	$r_title = trim( (string) ( $right['image_text_2_cols_rightcol_-_title'] ?? '' ) );
	$r_desc = trim( (string) ( $right['image_text_2_cols_rightcol_-_description'] ?? '' ) );

	if ( '' === $heading && '' === $desc && '' === $r_title ) {
		return '';
	}
	$att_id = momentive_sol_sideload_id( $r_img, $attach_map, $post_id, $dry );
	$img_tag = $att_id > 0
		? '<img src="' . esc_url( wp_get_attachment_url( $att_id ) ?: '' ) . '" alt="" class="wp-image-' . $att_id . ' size-full"/>'
		: '<img src="" alt="" />';

	return '<!-- wp:group {"className":"alignfull no-margin"} --><div class="wp-block-group alignfull no-margin">'
		. '<!-- wp:columns {"className":"content-width","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} --><div class="wp-block-columns content-width" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)">'
		. '<!-- wp:column {"verticalAlignment":"center","width":"50%","className":"no-padding"} --><div class="wp-block-column is-vertically-aligned-center no-padding" style="flex-basis:50%">'
		. ( '' !== $heading ? '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:column -->'
		. '<!-- wp:column {"width":"50%"} --><div class="wp-block-column" style="flex-basis:50%"><!-- wp:media-text {"linkDestination":"none","mediaType":"image","className":"is-style-stacked"} -->'
		. '<div class="wp-block-media-text is-stacked-on-mobile is-style-stacked"><figure class="wp-block-media-text__media">' . $img_tag . '</figure><div class="wp-block-media-text__content">'
		. ( '' !== $r_title ? '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $r_title ) . '</h3><!-- /wp:heading -->' : '' )
		. ( '' !== $r_desc ? '<!-- wp:paragraph {"fontSize":"medium"} --><p class="has-medium-font-size">' . esc_html( $r_desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div></div><!-- /wp:media-text --></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group -->';
}

/**
 * Related-solutions grid (solutions_-_enable_solutions_section — 28/87).
 * Siblings are resolved from the rebuilt post hierarchy (same parent), NOT
 * from the broken connected_products field.
 */
function momentive_sol_related_solutions_block( array $legacy, int $rebuilt_parent_id ): string {
	if ( ! momentive_sol_bool( $legacy, 'solutions_-_enable_solutions_section' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'solutions_-_kicker_text' );
	$heading = momentive_sol_str( $legacy, 'solutions_-_title' );
	$desc = momentive_sol_str( $legacy, 'solutions_-_description' );

	if ( $rebuilt_parent_id <= 0 ) {
		return '';
	}
	$siblings = get_posts( array(
		'post_type'      => MOMENTIVE_SOL_TYPE,
		'post_status'    => 'publish',
		'post_parent'    => $rebuilt_parent_id,
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
	if ( empty( $siblings ) ) {
		return '';
	}

	// Build each sibling's column, then chunk into rows of 3 (matching the
	// "is-style-boxed" 3-across grid already used on the rebuilt Fundraising
	// hub page), one wp:columns block per row.
	$card_htmls = array();
	foreach ( $siblings as $sib ) {
		$icon = momentive_sol_normalize_icon( (string) get_field( 'solution_icon', $sib->ID ) );
		$icon_block = $icon ? '<!-- wp:momentive/icon-block {"iconId":"' . esc_attr( $icon ) . '"} /-->' : '';
		$summary = get_field( 'card_label', $sib->ID ) ?: get_the_excerpt( $sib );
		$card_htmls[] = '<!-- wp:column --><div class="wp-block-column">' . $icon_block
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><a href="' . esc_url( get_permalink( $sib ) ) . '">' . esc_html( get_the_title( $sib ) ) . '</a></h3><!-- /wp:heading -->'
			. ( $summary ? '<!-- wp:paragraph --><p>' . esc_html( wp_strip_all_tags( $summary ) ) . '</p><!-- /wp:paragraph -->' : '' )
			. '<!-- wp:paragraph {"className":"read-more has-arrow"} --><p class="read-more has-arrow"><a href="' . esc_url( get_permalink( $sib ) ) . '">Learn more</a></p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:column -->';
	}
	$cards = '';
	foreach ( array_chunk( $card_htmls, 3 ) as $row ) {
		$cards .= '<!-- wp:columns {"className":"is-style-boxed"} --><div class="wp-block-columns is-style-boxed">' . implode( '', $row ) . '</div><!-- /wp:columns -->';
	}

	return '<!-- wp:group {"className":"to-edge","style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group to-edge" style="padding-top:0;padding-bottom:var(--wp--preset--spacing--small)"><!-- wp:group {"className":"narrow","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group narrow" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--small)">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $heading ? '<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><h2 class="wp-block-heading has-text-align-center balance">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center balance">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:group -->'
		. $cards
		. '</div><!-- /wp:group -->';
}

/** Whitepaper promo card (whitepaper_-_* — 6/87). Links out; no CPT relation attempted. */
function momentive_sol_whitepaper_block( array $legacy, array $attach_map, int $post_id, bool $dry ): string {
	if ( ! momentive_sol_bool( $legacy, 'whitepaper_-_enable_section' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'whitepaper_kicker_text' );
	$title = momentive_sol_str( $legacy, 'whitepaper_title' );
	$desc = momentive_sol_str( $legacy, 'whitepaper_description' );
	$btn_lbl = momentive_sol_str( $legacy, 'whitepaper_button_label' ) ?: 'Download now';
	$btn_url = momentive_sol_str( $legacy, 'whitepaper_button_url' ) ?: momentive_sol_str( $legacy, 'whitepaper_link' );
	$img_id = momentive_sol_sideload_id( (int) momentive_sol_str( $legacy, 'whitepaper_image' ), $attach_map, $post_id, $dry );
	if ( '' === $title ) {
		return '';
	}
	$img_tag = $img_id > 0
		? '<img src="' . esc_url( wp_get_attachment_url( $img_id ) ?: '' ) . '" alt="" class="wp-image-' . $img_id . ' size-large"/>'
		: '<img src="" alt="" />';

	return '<!-- wp:group {"className":"content-collage wide","layout":{"type":"constrained"}} --><div class="wp-block-group content-collage wide"><!-- wp:columns --><div class="wp-block-columns">'
		. '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading -->'
		. ( '' !== $desc ? '<!-- wp:paragraph --><p>' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( $btn_url ? '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_lbl ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' : '' )
		. '</div><!-- /wp:column -->'
		. '<!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-rounder"} --><figure class="wp-block-image size-large is-style-rounder">' . $img_tag . '</figure><!-- /wp:image --></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group -->';
}

/** "Purple CTA" (solutions_sub_purple_cta_-_* — 4/87). Low frequency; verify visually. */
function momentive_sol_purple_cta_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'solutions_sub_purple_cta_-_enable' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'solutions_sub_purple_cta_-_kicker_text' );
	$title = momentive_sol_str( $legacy, 'solutions_sub_purple_cta_-_title' );
	$desc = momentive_sol_str( $legacy, 'solutions_sub_purple_cta_-_desc' );
	$btn_lbl = momentive_sol_str( $legacy, 'solutions_sub_purple_cta_-_btn_label' );
	$btn_url = momentive_sol_str( $legacy, 'solutions_sub_purple_cta_-_btn_url' );
	if ( '' === $title ) {
		return '';
	}
	return '<!-- wp:group {"className":"is-style-bg-dark alignfull","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group is-style-bg-dark alignfull" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. '<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} --><h2 class="wp-block-heading has-text-align-center">' . esc_html( $title ) . '</h2><!-- /wp:heading -->'
		. ( '' !== $desc ? '<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( $btn_url ? '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_lbl ?: 'Learn more' ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' : '' )
		. '</div><!-- /wp:group -->';
}

/**
 * "Resources" placeholder (event_sub_resources_-_enable_section — 44/87).
 * The legacy export carries NO content fields for this section at all — it
 * appears to auto-pull related content by category, which this theme has no
 * cross-CPT block for yet (CLAUDE.md "Known limitations: Resource filters").
 * Heading-only placeholder so the section's presence isn't silently dropped.
 */
function momentive_sol_resources_placeholder_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'event_sub_resources_-_enable_section' ) ) {
		return '';
	}
	$title = momentive_sol_str( $legacy, 'event_sub_resources_-_custom_title' ) ?: 'Related Resources';
	return '<!-- wp:group {"className":"featured-blog-wrapper space-around","layout":{"type":"constrained"}} --><div class="wp-block-group featured-blog-wrapper space-around">'
		. '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p><em>TODO: no cross-CPT resources block exists yet — wire up manually (see CLAUDE.md "Known limitations: Resource filters").</em></p><!-- /wp:paragraph -->'
		. '</div><!-- /wp:group -->';
}

/**
 * "Features Overview" — CHECKLIST layout (event_sub_list_-_* — 19/87).
 *
 * event_sub_list_-_* and event_sub_benefits_-_* are two layout variants of
 * the SAME conceptual section (both carry a "Features Overview"/"Features"/
 * "Benefits" kicker) — not two different sections. Confirmed against the
 * legacy front end 2026-07-15 (Daniel diffed posts 6308 vs 6295): 19 posts
 * use this checklist variant, 18 use the icon-grid variant below
 * (momentive_sol_features_overview_grid_block), 49 use neither, and exactly
 * 1 (legacy 2485, "CE Credit Claiming") has BOTH enabled with distinct real
 * content in each — assemble_child_content() renders both for that post
 * rather than guessing which one to drop.
 */
function momentive_sol_list_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'event_sub_list_-_enable_section' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'event_sub_list_-_kicker_text' );
	$title = momentive_sol_str( $legacy, 'event_sub_list_-_title' );
	$content = momentive_sol_str( $legacy, 'event_sub_list_-_list_content' );
	$footnote = momentive_sol_str( $legacy, 'event_sub_list_-_list_footnote' );

	$list_block = '';
	if ( preg_match( '#<(ul|ol)\b[^>]*>(.*?)</\1>#is', $content, $lm ) ) {
		$tag = strtolower( $lm[1] );
		$ordered = 'ol' === $tag ? ' {"ordered":true}' : '';
		$lis = '';
		if ( preg_match_all( '#<li\b[^>]*>(.*?)</li>#is', $lm[2], $lim ) ) {
			foreach ( $lim[1] as $li ) {
				$li = trim( $li );
				if ( '' === $li ) {
					continue;
				}
				$lis .= '<!-- wp:list-item --><li>' . $li . '</li><!-- /wp:list-item -->';
			}
		}
		if ( '' !== $lis ) {
			$list_block = '<!-- wp:list' . $ordered . ' --><' . $tag . ' class="wp-block-list">' . $lis . '</' . $tag . '><!-- /wp:list -->';
		}
	}
	if ( '' === $list_block && '' === $title ) {
		return '';
	}

	return '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $title ? '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading -->' : '' )
		. $list_block
		. ( '' !== $footnote ? '<!-- wp:paragraph {"style":{"typography":{"fontStyle":"italic"}}} --><p style="font-style:italic">' . esc_html( $footnote ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:group -->';
}

/**
 * "Features Overview" — ICON-GRID layout (event_sub_benefits_-_* — 18/87).
 *
 * The other layout variant of the same section as momentive_sol_list_block()
 * above — see that function's docblock. Source: event_sub_benefits_-_enable
 * (gate) + _kicker_text/_title/_description (intro copy) +
 * event_sub_benefits_-_repeater (items: icon [box-prefixed], title,
 * description). Some posts carry a populated-looking repeater with this flag
 * set to false — checked across the full corpus, every one of those has
 * EMPTY item descriptions (leftover/unused stub content, e.g. a generic
 * "AI Chat assistant" placeholder never filled in) — so gating strictly on
 * the enable flag, not on "does the repeater have rows," is correct and
 * matches the legacy front end exactly.
 *
 * NOTE: the rebuilt reference example (Example 2, "Association Event
 * Management") links each card's heading to a related solution page — but
 * the legacy repeater has NO url field for these items at all, so those
 * links were added by hand during rebuild, not sourced from legacy data.
 * This builder renders plain (unlinked) headings; add links manually if
 * wanted, same as the rebuilt example did.
 */
function momentive_sol_features_overview_grid_block( array $legacy, array &$unresolved_icons, string $title ): string {
	if ( ! momentive_sol_bool( $legacy, 'event_sub_benefits_-_enable' ) ) {
		return '';
	}
	$kicker = momentive_sol_str( $legacy, 'event_sub_benefits_-_kicker_text' );
	$heading = momentive_sol_str( $legacy, 'event_sub_benefits_-_title' );
	$desc = momentive_sol_str( $legacy, 'event_sub_benefits_-_description' );
	$rows = momentive_sol_repeater( $legacy, 'event_sub_benefits_-_repeater' );
	if ( empty( $rows ) ) {
		return '';
	}

	$manifest = momentive_sol_icon_manifest();
	$card_htmls = array();
	foreach ( $rows as $row ) {
		$item_title = trim( (string) ( $row['event_sub_benefit_rep_title'] ?? '' ) );
		$item_desc  = trim( (string) ( $row['event_sub_benefit_rep_description'] ?? '' ) );
		$slug       = momentive_sol_normalize_icon( (string) ( $row['event_sub_benefit_rep_icon'] ?? '' ) );
		if ( '' === $item_title && '' === $item_desc ) {
			continue; // unused stub row (see docblock).
		}
		if ( '' !== $slug && ! isset( $manifest[ $slug ] ) ) {
			$unresolved_icons[] = sprintf( '%s: features-overview grid icon "%s"', $title, $slug );
		}
		$icon_block = $slug ? '<!-- wp:momentive/icon-block {"iconId":"' . esc_attr( $slug ) . '"} /-->' : '';
		$card_htmls[] = '<!-- wp:column --><div class="wp-block-column">' . $icon_block
			. ( '' !== $item_title ? '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html( $item_title ) . '</h3><!-- /wp:heading -->' : '' )
			. ( '' !== $item_desc ? '<!-- wp:paragraph --><p>' . esc_html( $item_desc ) . '</p><!-- /wp:paragraph -->' : '' )
			. '</div><!-- /wp:column -->';
	}
	if ( empty( $card_htmls ) ) {
		return '';
	}
	$grid = '';
	foreach ( array_chunk( $card_htmls, 3 ) as $row_chunk ) {
		$grid .= '<!-- wp:columns {"className":"is-style-boxed"} --><div class="wp-block-columns is-style-boxed">' . implode( '', $row_chunk ) . '</div><!-- /wp:columns -->';
	}

	return '<!-- wp:group {"className":"to-edge is-style-motion-blur top","backgroundColor":"neutral","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group to-edge is-style-motion-blur top has-neutral-background-color has-background"><!-- wp:group {"className":"narrow","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group narrow" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--small)">'
		. ( '' !== $kicker ? '<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( '' !== $heading ? '<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><h2 class="wp-block-heading has-text-align-center balance">' . esc_html( $heading ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph {"className":"balance","style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center balance">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '</div><!-- /wp:group -->'
		. $grid
		. '</div><!-- /wp:group -->';
}

/** CTA block (cta_-_* — 6/87). Reuses the alignfull gradient CTA already used elsewhere. */
function momentive_sol_cta_block( array $legacy ): string {
	if ( ! momentive_sol_bool( $legacy, 'cta_-_enable_cta_section' ) ) {
		return '';
	}
	$title = momentive_sol_str( $legacy, 'cta_-_title' );
	$desc = momentive_sol_str( $legacy, 'cta_-_description' );
	$btn1_txt = momentive_sol_str( $legacy, 'cta_-_button_1_text' );
	$btn1_url = momentive_sol_str( $legacy, 'cta_-_button_1_link' );
	$btn2_txt = momentive_sol_str( $legacy, 'cta_-_button_2_text' );
	$btn2_url = momentive_sol_str( $legacy, 'cta_-_button_2_link' );
	if ( '' === $title ) {
		return '';
	}
	$buttons = '';
	if ( $btn1_txt ) {
		$buttons .= '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn1_url ?: '/request-a-demo/' ) . '">' . esc_html( $btn1_txt ) . '</a></div><!-- /wp:button -->';
	}
	if ( $btn2_txt ) {
		$buttons .= '<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn2_url ?: '/contact-us/' ) . '">' . esc_html( $btn2_txt ) . '</a></div><!-- /wp:button -->';
	}
	return '<!-- wp:group {"className":"alignfull is-style-bg-gradient","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group alignfull is-style-bg-gradient" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)">'
		. '<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} --><h2 class="wp-block-heading has-text-align-center">' . esc_html( $title ) . '</h2><!-- /wp:heading -->'
		. ( '' !== $desc ? '<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} --><p class="has-text-align-center">' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. ( $buttons ? '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">' . $buttons . '</div><!-- /wp:buttons -->' : '' )
		. '</div><!-- /wp:group -->';
}

/** Demo form (request_a_demo_-_* — always on). Uses this page's own copy + HubSpot embed. */
function momentive_sol_demo_form_block( array $legacy, array $attach_map, int $post_id, bool $dry ): string {
	$kicker = momentive_sol_str( $legacy, 'request_a_demo_-_kicker_text' ) ?: 'Request a Demo';
	$title = momentive_sol_str( $legacy, 'request_a_demo_-_title' );
	$desc = momentive_sol_str( $legacy, 'request_a_demo_-_description' );
	$img_id = momentive_sol_sideload_id( (int) momentive_sol_str( $legacy, 'request_a_demo_-_image' ), $attach_map, $post_id, $dry );
	$embed = momentive_sol_str( $legacy, 'request_a_demo_-_hubspot_form_script' );

	$img_tag = $img_id > 0
		? '<img src="' . esc_url( wp_get_attachment_url( $img_id ) ?: '' ) . '" alt="" class="wp-image-' . $img_id . '"/>'
		: '<img src="" alt="" />';

	$hs_data = array(
		'hubspot_embed_code'  => $embed,
		'_hubspot_embed_code' => MOMENTIVE_SOL_FK_HUBSPOT_EMBED,
	);
	$hs_attrs = momentive_sol_json( array( 'name' => 'acf/hubspot-form', 'data' => $hs_data, 'mode' => 'preview' ) );

	return '<!-- wp:group {"className":"demo-form is-style-ellipse-bottom","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|medium","top":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group demo-form is-style-ellipse-bottom" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->'
		. '<div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column">'
		. '<!-- wp:paragraph {"className":"is-style-eyebrow"} --><p class="is-style-eyebrow">' . esc_html( $kicker ) . '</p><!-- /wp:paragraph -->'
		. ( '' !== $title ? '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading -->' : '' )
		. ( '' !== $desc ? '<!-- wp:paragraph --><p>' . esc_html( $desc ) . '</p><!-- /wp:paragraph -->' : '' )
		. '<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-rounder"} --><figure class="wp-block-image size-large is-style-rounder">' . $img_tag . '</figure><!-- /wp:image -->'
		. '</div><!-- /wp:column -->'
		. '<!-- wp:column --><div class="wp-block-column"><!-- wp:acf/hubspot-form ' . $hs_attrs . ' /--></div><!-- /wp:column -->'
		. '</div><!-- /wp:columns --></div><!-- /wp:group -->';
}

/* -------------------------------------------------------------------------
 * Flags & main run
 * ---------------------------------------------------------------------- */

function momentive_sol_get_flags( array $argv = array() ): array {
	$flags = array(
		'dry_run'       => true, // DRY-RUN BY DEFAULT — 'live' token required to write.
		'only'          => 0,
		'limit'         => 0,
		'hubs_only'     => false,
		'children_only' => false,
	);
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( 'live' === $tok || 'go' === $tok ) {
			$flags['dry_run'] = false;
		} elseif ( 'dry-run' === $tok || 'dry_run' === $tok || 'dry' === $tok ) {
			$flags['dry_run'] = true;
		} elseif ( 0 === strpos( $tok, 'only=' ) ) {
			$flags['only'] = (int) substr( $tok, 5 );
		} elseif ( 0 === strpos( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		} elseif ( 'hubs-only' === $tok || 'hubs_only' === $tok ) {
			$flags['hubs_only'] = true;
		} elseif ( 'children-only' === $tok || 'children_only' === $tok ) {
			$flags['children_only'] = true;
		}
	}
	if ( getenv( 'MOMENTIVE_LIVE' ) ) { $flags['dry_run'] = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )  { $flags['dry_run'] = true; }
	return $flags;
}

/** Assemble the full post_content for a child page from its section builders. */
function momentive_sol_assemble_child_content(
	array $legacy, string $post_title, array $attach_map, int $post_id, bool $dry,
	array &$unresolved_icons, array &$testimonial_log, array $legacy_testimonials, array $t_index,
	int $rebuilt_parent_id
): string {
	$parts = array(
		momentive_sol_breadcrumb_block(),
		momentive_sol_hero_block( $legacy, $post_title, $attach_map, $post_id, $dry ),
		momentive_sol_approach_block( $legacy, $unresolved_icons, $post_title ),
		momentive_sol_features_block( $legacy, $attach_map, $post_id, $dry ),
		momentive_sol_benefits_media_block( $legacy, $attach_map, $post_id, $dry ),
		momentive_sol_stats_block( $legacy ),
		momentive_sol_image_text_2cols_block( $legacy, $attach_map, $post_id, $dry ),
		momentive_sol_list_block( $legacy ),
		momentive_sol_features_overview_grid_block( $legacy, $unresolved_icons, $post_title ),
		momentive_sol_testimonial_block( $legacy, $legacy_testimonials, $t_index, $testimonial_log, $post_title ),
		momentive_sol_related_solutions_block( $legacy, $rebuilt_parent_id ),
		momentive_sol_whitepaper_block( $legacy, $attach_map, $post_id, $dry ),
		momentive_sol_purple_cta_block( $legacy ),
		momentive_sol_resources_placeholder_block( $legacy ),
		momentive_sol_faqs_block( $legacy ),
		momentive_sol_cta_block( $legacy ),
		momentive_sol_demo_form_block( $legacy, $attach_map, $post_id, $dry ),
	);
	return implode( "\n\n", array_filter( $parts ) );
}

/** Find (or, live-mode, create) the rebuilt post for a legacy slug/title. */
function momentive_sol_find_or_create_post( string $slug, string $title, string $status, int $parent_id, bool $dry ): int {
	$existing = get_page_by_path( $slug, OBJECT, MOMENTIVE_SOL_TYPE );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	if ( $dry ) {
		return 0; // nothing to update against in dry-run if it doesn't exist yet.
	}
	$id = wp_insert_post( array(
		'post_type'   => MOMENTIVE_SOL_TYPE,
		'post_title'  => $title,
		'post_name'   => $slug,
		'post_status' => 'publish' === $status ? 'publish' : 'draft',
		'post_parent' => $parent_id,
	), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "    failed to create post for slug {$slug}: " . $id->get_error_message() );
		return 0;
	}
	return (int) $id;
}

function momentive_sol_run( array $argv = array() ): void {
	$flags = momentive_sol_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  Solutions CPT migration' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( $flags['only'] )  { WP_CLI::log( '  only:  legacy ID ' . $flags['only'] ); }
	if ( $flags['limit'] ) { WP_CLI::log( '  limit: ' . $flags['limit'] . ' posts' ); }
	if ( $flags['hubs_only'] )     { WP_CLI::log( '  hubs-only: field backfill for hub-tier posts only' ); }
	if ( $flags['children_only'] ) { WP_CLI::log( '  children-only: content build for child posts only' ); }
	WP_CLI::log( '====================================================' );

	$legacy_all  = momentive_sol_load_legacy_posts();
	WP_CLI::log( sprintf( 'Legacy WXR: %d solutions items parsed.', count( $legacy_all ) ) );
	$attach_map  = momentive_sol_build_attachment_map();
	$cct         = momentive_sol_load_cct();
	$legacy_testimonials = momentive_sol_load_legacy_testimonials();
	$t_index     = empty( $legacy_testimonials ) ? array( 'by_norm' => array() ) : momentive_sol_build_testimonial_index();
	if ( empty( $legacy_testimonials ) ) {
		WP_CLI::warning( 'No legacy testimonials WXR found — testimonial sections will be logged and skipped. Set MOMENTIVE_SOL_TESTIMONIALS_WXR once the export is added.' );
	} else {
		WP_CLI::log( sprintf( 'Legacy testimonials: %d parsed. Rebuilt testimonial corpus: %d indexed.', count( $legacy_testimonials ), count( $t_index['by_norm'] ) ) );
	}

	if ( $flags['only'] > 0 ) {
		$legacy_all = array_values( array_filter( $legacy_all, static function ( $p ) use ( $flags ) {
			return $p['id'] === $flags['only'];
		} ) );
	}
	if ( $flags['limit'] > 0 ) {
		$legacy_all = array_slice( $legacy_all, 0, $flags['limit'] );
	}

	// Legacy parent ID -> rebuilt post ID, resolved as hub-tier posts are processed.
	$rebuilt_parent_map = array();

	$summary = array(
		'excluded'        => 0,
		'hub_updated'     => 0,
		'hub_missing'     => 0,
		'child_created'   => 0,
		'child_updated'   => 0,
	);
	$unresolved_icons  = array();
	$testimonial_log   = array();
	$order_unresolved  = array();
	$hand_build_needed = array();

	// ---- Pass 1: hub-tier posts (fields only) -----------------------------
	if ( ! $flags['children_only'] ) {
		foreach ( $legacy_all as $legacy ) {
			$id = $legacy['id'];
			if ( in_array( $id, MOMENTIVE_SOL_EXCLUDE_IDS, true ) ) {
				continue;
			}
			if ( ! in_array( $id, MOMENTIVE_SOL_HUB_IDS, true ) ) {
				continue;
			}
			WP_CLI::log( sprintf( "\n[hub %d] %s", $id, $legacy['title'] ) );

			$post = get_page_by_path( $legacy['slug'], OBJECT, MOMENTIVE_SOL_TYPE );
			if ( ! $post ) {
				WP_CLI::warning( "    no rebuilt post at slug '{$legacy['slug']}' — create it by hand first, then re-run with only={$id}." );
				$summary['hub_missing']++;
				continue;
			}
			$post_id = (int) $post->ID;
			$rebuilt_parent_map[ $id ] = $post_id;

			$accent = momentive_sol_str( $legacy, 'accent_color' ) ?: momentive_sol_str( $legacy, 'solution_styling_settings_primary_color' );
			$icon   = momentive_sol_normalize_icon( momentive_sol_str( $legacy, 'solution_icon' ) );
			$manifest = momentive_sol_icon_manifest();
			if ( $icon && ! isset( $manifest[ $icon ] ) ) {
				$unresolved_icons[] = sprintf( '%s (hub): icon "%s"', $legacy['title'], $icon );
			}
			$order = $cct[ $id ]['solution_menu_order'] ?? '';

			WP_CLI::log( sprintf( '    accent_color=%s icon=%s order=%s', $accent ?: '(none)', $icon ?: '(none)', $order !== '' ? $order : '(none — set by hand)' ) );

			if ( ! $dry ) {
				if ( $accent ) {
					update_field( MOMENTIVE_SOL_FK_ACCENT_COLOR, $accent, $post_id );
				}
				if ( $icon ) {
					update_field( MOMENTIVE_SOL_FK_ICON, $icon, $post_id );
				}
				if ( '' !== $order ) {
					update_field( MOMENTIVE_SOL_FK_ORDER, (int) $order, $post_id );
				}
				// Category taxonomy, from the family map.
				$cat_slug = MOMENTIVE_SOL_FAMILY_TO_CAT_SLUG[ $id ] ?? '';
				if ( $cat_slug ) {
					$term = get_term_by( 'slug', $cat_slug, 'category' );
					if ( $term ) {
						wp_set_post_terms( $post_id, array( $term->term_id ), 'category', false );
					} else {
						$order_unresolved[] = sprintf( '%s: rebuilt category slug "%s" not found.', $legacy['title'], $cat_slug );
					}
				}
				update_post_meta( $post_id, MOMENTIVE_SOL_RUN_META, momentive_sol_run_id() );
			}
			$summary['hub_updated']++;
		}
	}

	// ---- Pass 2: child posts (full content build) -------------------------
	if ( ! $flags['hubs_only'] ) {
		foreach ( $legacy_all as $legacy ) {
			$id = $legacy['id'];
			if ( in_array( $id, MOMENTIVE_SOL_EXCLUDE_IDS, true ) || in_array( $id, MOMENTIVE_SOL_HUB_IDS, true ) ) {
				continue;
			}
			$legacy_parent = MOMENTIVE_SOL_FORCE_PARENT[ $id ] ?? $legacy['parent'];
			if ( $legacy_parent <= 0 ) {
				// Shouldn't happen given the hub/exclude lists above, but skip defensively.
				continue;
			}

			WP_CLI::log( sprintf( "\n[child %d] %s", $id, $legacy['title'] ) );

			// A handful of rare sections (~1 post each) are intentionally not
			// scripted — flag them so they're not silently dropped.
			foreach ( array(
				'event_sub_list_w_heading_-_enable_section' => 'list-with-heading',
				'event_sub_accordion_-_enable_section'      => 'extra accordion',
				'sol_sub_features_accordion_enable'         => 'features accordion',
			) as $flag_key => $label ) {
				if ( momentive_sol_bool( $legacy, $flag_key ) ) {
					$hand_build_needed[] = sprintf( '%s: "%s" section enabled — not scripted (low frequency), build by hand.', $legacy['title'], $label );
				}
			}

			$rebuilt_parent_id = $rebuilt_parent_map[ $legacy_parent ] ?? 0;
			if ( ! $rebuilt_parent_id ) {
				$parent_legacy = null;
				foreach ( $legacy_all as $lp ) {
					if ( $lp['id'] === $legacy_parent ) { $parent_legacy = $lp; break; }
				}
				if ( $parent_legacy ) {
					$parent_post = get_page_by_path( $parent_legacy['slug'], OBJECT, MOMENTIVE_SOL_TYPE );
					if ( $parent_post ) {
						$rebuilt_parent_id = (int) $parent_post->ID;
						$rebuilt_parent_map[ $legacy_parent ] = $rebuilt_parent_id;
					}
				}
			}
			if ( ! $rebuilt_parent_id ) {
				WP_CLI::warning( "    can't resolve rebuilt parent (legacy parent {$legacy_parent}) — run hub-tier pass first, or create the hub post. Skipping." );
				continue;
			}

			$post_id = momentive_sol_find_or_create_post( $legacy['slug'], $legacy['title'], $legacy['status'], $rebuilt_parent_id, $dry );
			if ( ! $post_id && ! $dry ) {
				continue;
			}
			$content = momentive_sol_assemble_child_content(
				$legacy, $legacy['title'], $attach_map, $post_id ?: -1, $dry,
				$unresolved_icons, $testimonial_log, $legacy_testimonials, $t_index,
				$rebuilt_parent_id
			);

			$breadcrumb_title = momentive_sol_str( $legacy, 'event_sub_-_breadcrumb_title' );
			$card_icon = momentive_sol_normalize_icon( momentive_sol_str( $legacy, 'event_sub_card_icon' ) );
			$manifest = momentive_sol_icon_manifest();
			if ( $card_icon && ! isset( $manifest[ $card_icon ] ) ) {
				$unresolved_icons[] = sprintf( '%s: card icon "%s"', $legacy['title'], $card_icon );
			}

			if ( $dry ) {
				WP_CLI::log( sprintf( '    [dry-run] would write %d bytes of content, parent=%d, breadcrumb_title="%s", icon="%s"',
					strlen( $content ), $rebuilt_parent_id, $breadcrumb_title, $card_icon ) );
				$summary['child_updated']++;
				continue;
			}

			wp_update_post( wp_slash( array(
				'ID'           => $post_id,
				'post_parent'  => $rebuilt_parent_id,
				'post_content' => $content,
			) ), true );

			if ( $breadcrumb_title ) {
				update_field( MOMENTIVE_SOL_FK_BREADCRUMB_TITLE, $breadcrumb_title, $post_id );
			}
			if ( $card_icon ) {
				update_field( MOMENTIVE_SOL_FK_ICON, $card_icon, $post_id );
			}
			update_post_meta( $post_id, MOMENTIVE_SOL_RUN_META, momentive_sol_run_id() );

			$summary['child_updated']++;
		}
	}

	WP_CLI::success( sprintf(
		'Done. Hub posts updated: %d | Hub posts missing: %d | Child posts written: %d',
		$summary['hub_updated'], $summary['hub_missing'], $summary['child_updated']
	) );

	WP_CLI::log( '' );
	if ( $unresolved_icons ) {
		WP_CLI::log( 'Unresolved icon slugs (written as-is, verify against assets/icons/):' );
		foreach ( array_unique( $unresolved_icons ) as $line ) { WP_CLI::log( "  - {$line}" ); }
	}
	if ( $testimonial_log ) {
		WP_CLI::log( 'Testimonial sections needing attention:' );
		foreach ( $testimonial_log as $line ) { WP_CLI::log( "  - {$line}" ); }
	}
	if ( $order_unresolved ) {
		WP_CLI::log( 'Category assignment issues:' );
		foreach ( $order_unresolved as $line ) { WP_CLI::log( "  - {$line}" ); }
	}
	if ( $hand_build_needed ) {
		WP_CLI::log( 'Rare sections not scripted — build by hand:' );
		foreach ( $hand_build_needed as $line ) { WP_CLI::log( "  - {$line}" ); }
	}
	WP_CLI::log( '' );
	WP_CLI::log( 'Not handled by this script (see migrations/solutions-migration-coverage.xlsx):' );
	WP_CLI::log( '  • solution_order for child pages outside the Accounting family — no legacy source, set by hand.' );
	WP_CLI::log( '  • connected_products — unrecoverable (CCT export bug), source manually per page if needed.' );
	WP_CLI::log( '  • "Resources" sections — heading-only placeholder written; needs a real cross-CPT block.' );
	WP_CLI::log( '  • Hub-tier (12 top-level + 9 Split Test B) post_content — hand-build, this script only backfilled fields.' );
}

// `wp eval-file` provides positional args as a script-scope $args variable.
momentive_sol_run( isset( $args ) && is_array( $args ) ? $args : array() );
