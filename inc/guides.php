<?php

/**
 * Custom Post Type: Guides & Research
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  guide  (singular, matching the pattern of whitepaper, infographic)
 * URL slug: /guides/{slug}/
 *
 * "Guides & Research" is actually two structurally different content shapes
 * sharing one legacy CPT (`guide_type` meta: `guides` vs `research-study`),
 * confirmed against the full legacy export in notes/guide-reference-sheet.md.
 * Both stay on this one rebuilt CPT — same taxonomy, same archive, same
 * "resource" role — but they render very differently:
 *
 *   • `guides` (17 of 25 legacy posts) — the same two-column gated/ungated
 *     layout already built for whitepapers/infographics: description +
 *     optional checklist on the left, HubSpot form (or a direct download
 *     link when ungated) on the right. No new components needed.
 *
 *   • `research-study` (8 of 25 legacy posts) — a materially richer,
 *     standalone layout: custom hero + overline + rich subheader, a
 *     "download the full report" form, up to two animated-stat "insight"
 *     sections (each stat with its own accent color), an optional
 *     webinar-promo CTA band, and an optional "Explore Previous Studies"
 *     card grid. None of this exists yet in any built block/pattern — it's
 *     new template work, not a drop-in extension of the whitepaper shape.
 *     Two of the 8 legacy `research-study` posts actually have every
 *     research-only field empty and render as plain `guides`-shape pages
 *     anyway, so `guide_type` is a content-team label, not a reliable
 *     runtime layout switch — don't gate rendering logic on it directly.
 *
 * This file only registers the CPT shell (mirrors whitepapers.php /
 * infographics.php at the same stage of their build-out). The
 * `momentive/guide-content` pattern and the research-study-only blocks
 * (insight/stat callouts, previous-studies grid, webinar CTA band, custom
 * image section) are still to be built; `template` stays `[]` until then.
 *
 * Content architecture (once built)
 * ─────────────────────────────────────────────────────────────────────────────
 * The post body (block editor) will hold, for the `guides` shape:
 *   • Description copy ("resource_details" from legacy)
 *   • Optional closing CTA sentence ("details_cta")
 *   • Optional checklist with heading ("resource_checklist")
 *   • Optional insights list (title + description pairs, replaces checklist)
 *   • Optional additional download/anchor link button
 *   • Either: HubSpot form embed (gated) via acf/hubspot-form
 *   • Or:     direct download button (ungated)
 *
 * ACF fields (defined in inc/acf-groups.php, once added) will hold structured
 * metadata the theme PHP needs to read programmatically:
 *   • hero_image — page-hero image override (separate from _thumbnail_id),
 *     same pattern as whitepaper/infographic
 *   • enable_gated_content — when false, a direct download link replaces
 *     the form
 *   • hero_title (Guide Settings group, below) — optional H1 override so the
 *     on-page title can differ from the post title (legacy behavior), without
 *     resorting to a static Heading block in place of wp:post-title. See the
 *     "Header title override" filter below.
 *   • guide_type (Guide Settings group, below) — the one field that DOES
 *     need to exist on the rebuilt CPT even before the rest of the
 *     research-study layout is built, because it drives the permalink
 *     prefix (see "Permalinks" below), not just rendering.
 *
 * Fields confirmed dead across the full legacy corpus and NOT migrated:
 *   connected_products, enable_product_logos, statistics_sections,
 *   general_research_study_info, resource_details_tab — all unused
 *   Elementor-era fields. See notes/guide-reference-sheet.md for the full
 *   field → destination map, including the research-study-only fields.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions"
 * parent term), identical to whitepapers, infographics, case studies, etc.
 * One legacy post has no category at all — that's valid, same as the
 * infographic corpus; the native category panel is optional.
 *
 * Permalinks
 * ─────────────────────────────────────────────────────────────────────────────
 * The legacy site gives research-study posts a different permalink prefix
 * than guides posts, on the SAME post type — confirmed against the export's
 * <link> values, not assumed:
 *   • guide_type=guides                     → /guides/{slug}/
 *   • guide_type=research-study              → /research-study/{slug}/
 *   • guide_type=research-study-preview      → /research-study/{slug}/ (same prefix)
 * e.g. .../guides/donor-segmentation/ vs .../research-study/small-staff-associations/
 *
 * register_post_type()'s `rewrite` only supports one static prefix, so
 * matching this requires the standard "one CPT, two permalink prefixes"
 * pattern on top of it: a `post_type_link` filter rewrites the generated
 * permalink for research-study posts, and a hand-added rewrite rule routes
 * incoming /research-study/{slug}/ requests back to this CPT. Both are
 * below, gated on the `guide_type` ACF field (Guide Settings group).
 * Getting this wrong would silently break existing inbound links/SEO/
 * HubSpot campaign links built against the legacy /research-study/ URLs
 * once real research-study content is migrated.
 *
 * There is only ONE archive though — /guides/ — not a second one at
 * /research-study/. The export only shows per-post permalinks differing;
 * there's no evidence of a separate research-study archive/listing page on
 * the legacy site, and CLAUDE.md's existing "Guides & Research" collection
 * is documented as a single archive. `has_archive` stays 'guides' for all
 * three guide_type values; only individual post permalinks branch.
 *
 * Visual chrome: preview vs. fully-launched research studies
 * ─────────────────────────────────────────────────────────────────────────────
 * `guide_type` has a third value — `research-study-preview` — for a report's
 * teaser page published before the full study is built out (see
 * notes/guide-reference-sheet.md's resolved "preview → full-launch
 * lifecycle" note; posts #10/nonprofit-trends-report-2026 is a live example).
 * A preview post keeps the /research-study/ permalink (above) but renders
 * with the PLAIN guides-shape chrome — white page background, superlight-
 * tinted sidebar — because visually it doesn't have the fully-built layout
 * yet. Only `research-study` (the fully-launched value) gets the
 * gradient-hero / white-sidebar treatment.
 *
 * This is deliberately a third value on the SAME field rather than a second
 * boolean field: one flip (Preview → Research Study) correctly updates both
 * the permalink prefix AND the visual chrome together, no second field to
 * keep in sync. See the `is-research-study` body class below and its CSS in
 * assets/sass/gate.scss.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_guides_setup' );

// Front-end styles for the single-guide view.
// Reuses the same gate.css as whitepapers/infographics (two-column gated
// layout). The research-study shape will likely need its own stylesheet
// once that layout is built — add it here, conditionally enqueued, at
// that point rather than loading it for every guide up front.
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'guide' ) ) {
		wp_enqueue_style( 'momentive-gate' );
	}
} );

function momentive_guides_setup(): void {

	$labels = [
		'name'               => _x( 'Guides & Research', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Guides & Research', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Guides/Research', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Guide', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Guide', 'momentive' ),
		'new_item'           => __( 'New Guide', 'momentive' ),
		'edit_item'          => __( 'Edit Guide', 'momentive' ),
		'view_item'          => __( 'View Guide', 'momentive' ),
		'all_items'          => __( 'All Guides & Research', 'momentive' ),
		'search_items'       => __( 'Search Guides & Research', 'momentive' ),
		'not_found'          => __( 'No guides found.', 'momentive' ),
		'not_found_in_trash' => __( 'No guides found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-analytics',
		'menu_position'      => 38,
		'show_in_rest'       => true,        // Block editor
		'supports'           => [
			'title',      // Guide title
			'editor',     // Body: description, checklist, form block, etc.
			'excerpt',    // Used on archive/query loop cards
			'thumbnail',  // Archive card image (separate from hero_image ACF field)
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'guides',
			'with_front' => false,
		],
		'has_archive'        => 'guides',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
		'template'           => [],   // Populated below once the pattern exists
		'template_lock'      => false,
	];

	register_post_type( 'guide', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Dual permalink prefix: /guides/{slug}/ vs /research-study/{slug}/
// ─────────────────────────────────────────────────────────────────────────────
//
// See the "Permalinks" design note at the top of this file. The CPT's own
// `rewrite` above only understands the 'guides' prefix; this rule adds the
// second prefix so incoming /research-study/{slug}/ requests still resolve
// to a `guide` post. Both prefixes route to the same query var ('guide',
// the CPT key) — WordPress doesn't care which prefix was used to get there.

add_action( 'init', function() {
	add_rewrite_rule(
		'^research-study/([^/]+)/?$',
		'index.php?guide=$matches[1]',
		'top'
	);
}, 10 );

// Rewrites the GENERATED permalink (get_permalink(), the_permalink(), etc.)
// for research-study posts so links point at /research-study/{slug}/ rather
// than the CPT's default /guides/{slug}/. Falls through unchanged for
// anything that isn't a `guide` post, and for guide posts where guide_type
// is empty/unset/'guides' — i.e. this only ever removes the default prefix
// in favor of the alternate one, never the reverse.
//
// Matches BOTH research-study values (full launch and preview) — a preview
// post keeps the /research-study/ permalink even though it renders with the
// plain chrome (see the is-research-study body class below, which checks
// for the full-launch value only).
add_filter( 'post_type_link', function( string $link, WP_Post $post ): string {
	if ( 'guide' !== $post->post_type ) {
		return $link;
	}
	if ( str_starts_with( (string) get_field( 'guide_type', $post->ID ), 'research-study' ) ) {
		$link = str_replace( '/guides/', '/research-study/', $link );
	}
	return $link;
}, 10, 2 );

// Body class for the fully-launched research-study chrome (gradient hero,
// white sidebar — see assets/sass/gate.scss). Deliberately checks for the
// EXACT 'research-study' value, not a str_starts_with() match like the
// permalink filter above — a 'research-study-preview' post keeps the
// /research-study/ URL but should NOT get this class, since it still uses
// the plain guides-shape chrome until the full study is built out.
add_filter( 'body_class', function( array $classes ): array {
	if ( is_singular( 'guide' ) && 'research-study' === get_field( 'guide_type', get_the_ID() ) ) {
		$classes[] = 'is-research-study';
	}
	return $classes;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Header title override (hero_title)
// ─────────────────────────────────────────────────────────────────────────────
// The legacy site let editors show a different (usually shorter) H1 on the
// page than the post title used for SEO/the permalink. The first pass at
// rebuilding this swapped wp:post-title out for a plain Heading block on a
// per-post basis wherever the two needed to differ — it works, but silently
// disconnects the H1 from the post title field, so a later Quick Edit/title
// change never reaches the page. That actually happened on 3 of the first
// 10 rebuilt posts (caught by comparing the post_title to the hardcoded H1
// text in the export).
//
// Fixed here the same way as the query-title top label above: override the
// rendered output of the SAME shared block via render_block, driven by one
// clearly-labeled ACF field (hero_title, Guide Settings group), rather than
// a second per-post block choice that isn't self-documenting in the editor.
// Every guide post can now use the ordinary wp:post-title block
// unconditionally; leaving hero_title blank (the default) changes nothing.
add_filter( 'render_block', function( string $content, array $block ): string {
	if ( 'core/post-title' !== $block['blockName'] || ! is_singular( 'guide' ) ) {
		return $content;
	}
	$override = trim( (string) get_field( 'hero_title', get_the_ID() ) );
	if ( '' === $override ) {
		return $content;
	}
	return preg_replace( '/(<h[1-6][^>]*>).*?(<\/h[1-6]>)/s', '$1' . esc_html( $override ) . '$2', $content );
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────
//
// WordPress needs its rewrite table rebuilt after a new CPT — or a new
// rewrite rule, as added above — is registered. A version-stamped option
// triggers this exactly once (bump the stamp to re-trigger, e.g. after a
// slug change). Same pattern as whitepapers.php, infographics.php, and
// people.php. Stamp bumped for the research-study rewrite rule added above.

add_action( 'init', function() {
	$stamp = '2026-07-17.1';
	if ( get_option( 'momentive_guide_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_guide_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type + the rewrite rule above (both priority 10)


// ─────────────────────────────────────────────────────────────────────────────
// Shared solution categories — scoped ACF field filter
// ─────────────────────────────────────────────────────────────────────────────
//
// If an ACF taxonomy field named `guide_solution` is added (e.g. for a
// curated single "primary" solution), this filter scopes its options to the
// Solutions children. The native multi-select category panel handles the
// general case and is left unfiltered (see case-studies.php for rationale).

add_filter( 'acf/fields/taxonomy/query/name=guide_solution', function( array $args ): array {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );


// ─────────────────────────────────────────────────────────────────────────────
// guide_type labels — single source of truth
// ─────────────────────────────────────────────────────────────────────────────
// Shared by the admin "Type" column, the "Type" list-table filter dropdown,
// and anywhere else in the admin that needs a human label for the field's
// three values. Keeping this in one place means the column and the filter
// dropdown can't silently drift apart the way two independent copies could.

function momentive_guide_type_labels(): array {
	return [
		'guides'                 => __( 'Guide', 'momentive' ),
		'research-study'         => __( 'Research Study', 'momentive' ),
		'research-study-preview' => __( 'Research Study — Preview', 'momentive' ),
	];
}


// ─────────────────────────────────────────────────────────────────────────────
// guide_type labels — front-end top label
// ─────────────────────────────────────────────────────────────────────────────
// Deliberately separate from momentive_guide_type_labels() above. That one is
// admin-only copy ("Guide", the em-dash "— Preview" suffix) meant to read
// clearly in a list-table column; a site visitor shouldn't see either. The
// front-end top label needs to match the legacy site's reader-facing text
// exactly instead: "Guides & Research" (the CPT's own singular_name — this
// IS the plain guides-shape default, not an abbreviation of it), "Research
// Study", and "Research Study Preview" (no punctuation between the words).
//
// Consumed by the render_block filter on core/query-title in functions.php,
// which swaps this in for the singular guide view's `.top-label` element —
// the same "override one shared block's output based on post state" pattern
// already used there for the h1→p tag swap, rather than a second dedicated
// block like acf/webinar-status. A dedicated block made sense for webinar
// status because that label is reused across multiple contexts (its own
// hero AND story-card.php archive cards); the guide top label, as asked for
// here, is only the singular-page hero, so overriding the existing
// query-title output is the smaller, equally clear fix. If a future request
// wants research-study cards to carry this same distinction in archive
// grids (story-card.php's "everything else" branch currently just shows the
// post type's singular label for every guide post), that's the point where
// promoting this to a shared template-part/small block — mirroring
// webinar-status — would earn its keep.
function momentive_guide_type_front_label( string $guide_type ): string {
	$labels = [
		'research-study'         => __( 'Research Study', 'momentive' ),
		'research-study-preview' => __( 'Research Study Preview', 'momentive' ),
	];
	return $labels[ $guide_type ] ?? __( 'Guides & Research', 'momentive' );
}


// ─────────────────────────────────────────────────────────────────────────────
// Admin columns: Type + Gated badges
// ─────────────────────────────────────────────────────────────────────────────
// "Type" reads the guide_type ACF field added above (Guide Settings group) —
// now that it exists as a real field (needed for the permalink prefix, see
// "Permalinks" above), it doubles as a cheap, reliable admin-list signal for
// which layout a post uses, no post_content sniffing required.
//
// "Gated" is the same convention as whitepapers.php / infographics.php.
// Gating applies within both the `guides` and `research-study` shapes (a
// research-study post either embeds a HubSpot form or points straight at an
// external report), so this one badge covers the whole CPT.

add_filter( 'manage_guide_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['guide_type']  = __( 'Type', 'momentive' );
			$new['guide_gated'] = __( 'Gated', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_guide_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column === 'guide_type' ) {
		$type   = (string) get_field( 'guide_type', $post_id );
		$labels = momentive_guide_type_labels();
		echo esc_html( $labels[ $type ] ?? $labels['guides'] );
		return;
	}

	if ( $column !== 'guide_gated' ) return;

	$gated = str_contains( get_post_field( 'post_content', $post_id ), '<!-- wp:acf/hubspot-form' );
	if ( $gated ) {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#00a32a;color:#fff;">Gated</span>';
	} else {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#787c82;color:#fff;">Ungated</span>';
	}
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// Admin list-table filter: Type
// ─────────────────────────────────────────────────────────────────────────────
// A "Filter by type" dropdown above the Guides & Research list table, same
// restrict_manage_posts + parse_query convention people.php uses for its
// "Filter by role" dropdown — except this filters on the guide_type ACF
// field (plain post meta), not a taxonomy, so it's a meta_query rather than
// a tax_query on the query side.

add_action( 'restrict_manage_posts', function ( string $post_type ): void {
	if ( 'guide' !== $post_type ) {
		return;
	}

	$current = isset( $_GET['guide_type'] ) ? sanitize_text_field( wp_unslash( $_GET['guide_type'] ) ) : '';

	printf( '<select name="%s" id="%s">', esc_attr( 'guide_type' ), esc_attr( 'guide_type' ) );
	printf( '<option value="">%s</option>', esc_html__( 'All types', 'momentive' ) );
	foreach ( momentive_guide_type_labels() as $value => $label ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
} );

add_filter( 'parse_query', function ( $query ) {
	global $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow ) {
		return $query;
	}
	if ( empty( $query->query_vars['post_type'] ) || 'guide' !== $query->query_vars['post_type'] ) {
		return $query;
	}

	if ( ! empty( $_GET['guide_type'] ) ) {
		$value = sanitize_text_field( wp_unslash( $_GET['guide_type'] ) );
		$query->query_vars['meta_query'] = [
			[
				'key'   => 'guide_type',
				'value' => $value,
			],
		];
	}

	return $query;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Default block pattern as the new-post template
// ─────────────────────────────────────────────────────────────────────────────
//
// Once `momentive/guide-content` is registered as a block pattern, this
// hook sets it as the default editor template for new guide posts. Mirrors
// the approach in whitepapers.php, infographics.php, and webinars.php.
//
// Because this CPT covers two layouts, the pattern registered here should
// be the `guides`-shape scaffold (the common case, ~2/3 of the corpus) —
// research-study posts are enough of a bespoke build that hand-assembling
// from individual blocks is expected, the same way case-study posts with
// unusual sidebars deviate from case-study-content.php.

add_action( 'init', function() {
	$cpt = get_post_type_object( 'guide' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/guide-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );
