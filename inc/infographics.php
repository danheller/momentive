<?php

/**
 * Custom Post Type: Infographics
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  infographic  (singular, matching the pattern of whitepaper, webinar)
 * URL slug: /infographics/{slug}/
 *
 * Infographics are visual assets — statistics, tips, or data presented as a
 * designed image (usually a PDF or image hosted on HubSpot). Unlike whitepapers,
 * the majority of infographics are NOT gated (8 of 14 legacy posts): the primary
 * content is a direct download link to the externally-hosted file.
 *
 * The 6 gated infographics follow the same two-column layout as whitepapers:
 * description + optional checklist on the left, HubSpot form on the right.
 * The 8 ungated infographics show a description + optional checklist + a
 * prominent download/view button; no form column.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * The post body (block editor) holds:
 *   • Description copy ("resource_details" from legacy)
 *   • Optional closing CTA sentence ("details_cta" — 2 legacy posts)
 *   • Optional checklist with heading ("resource_checklist" — 10/14 posts)
 *     Note: checklist items may contain HTML anchor tags (1 post uses them as
 *     a "related resources" list rather than plain text bullet points).
 *   • Optional additional paragraphs after checklist ("resource_details_after_checklist" — 4 posts)
 *   • Either: HubSpot form embed (gated, 6 posts) via acf/hubspot-form block
 *   • Or:     direct download button pointing to resource_link (ungated, 8 posts)
 *
 * ACF fields (defined in inc/acf-groups.php) hold structured metadata:
 *   • hero_image — page-hero image override (separate from _thumbnail_id)
 *   • enable_gated_content — when true, the right column shows the HubSpot form;
 *                            when false, a direct download link is the CTA instead
 *
 * No date-based lifecycle (unlike webinars). No video (the legacy
 * "hero_video_source: wistia" field is a dead Elementor default — both
 * hero_library_video and hero_link_video are empty on all 14 legacy posts).
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions"
 * parent term), identical to whitepapers, case studies, etc. One legacy post
 * ("5 Stats for #GivingTuesday Growth") has no category at all — that's valid;
 * the native category panel is optional.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_infographics_setup' );

// Front-end styles for the single-infographic view.
// Reuses the same gate.css as whitepapers (two-column gated layout).
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'infographic' ) ) {
		wp_enqueue_style( 'momentive-gate' );
	}
} );

function momentive_infographics_setup(): void {

	$labels = [
		'name'               => _x( 'Infographics', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Infographic', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Infographics', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Infographic', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Infographic', 'momentive' ),
		'new_item'           => __( 'New Infographic', 'momentive' ),
		'edit_item'          => __( 'Edit Infographic', 'momentive' ),
		'view_item'          => __( 'View Infographic', 'momentive' ),
		'all_items'          => __( 'All Infographics', 'momentive' ),
		'search_items'       => __( 'Search Infographics', 'momentive' ),
		'not_found'          => __( 'No infographics found.', 'momentive' ),
		'not_found_in_trash' => __( 'No infographics found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-format-image',
		'menu_position'      => 37,
		'show_in_rest'       => true,        // Block editor
		'supports'           => [
			'title',      // Infographic title
			'editor',     // Body: description, checklist, form/download button, etc.
			'excerpt',    // Used on archive/query loop cards
			'thumbnail',  // Archive card image (separate from hero_image ACF field)
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'infographics',
			'with_front' => false,
		],
		'has_archive'        => 'infographics',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
		'template'           => [],   // Populated below once the pattern exists
		'template_lock'      => false,
	];

	register_post_type( 'infographic', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────
//
// WordPress needs its rewrite table rebuilt after a new CPT is added.
// Bump the stamp to re-trigger (e.g. after a slug change).

add_action( 'init', function() {
	$stamp = '2026-07-02.1';
	if ( get_option( 'momentive_infographic_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_infographic_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type (priority 10)


// ─────────────────────────────────────────────────────────────────────────────
// Shared solution categories — scoped ACF field filter
// ─────────────────────────────────────────────────────────────────────────────
//
// Scopes any ACF taxonomy field named `infographic_solution` to the Solutions
// children. The native multi-select category panel handles the general case.

add_filter( 'acf/fields/taxonomy/query/name=infographic_solution', function( array $args ): array {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Admin column: Gated badge
// ─────────────────────────────────────────────────────────────────────────────
// Replaces the former "Solutions" column, which duplicated the native
// Categories column. Gated/ungated is the most useful per-post signal on
// this CPT — the split is roughly even (6 gated, 8 ungated in the corpus).

add_filter( 'manage_infographic_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['wp_gated'] = __( 'Gated', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_infographic_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column !== 'wp_gated' ) return;

	$gated = str_contains( get_post_field( 'post_content', $post_id ), '<!-- wp:acf/hubspot-form' );
	if ( $gated ) {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#00a32a;color:#fff;">Gated</span>';
	} else {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#787c82;color:#fff;">Ungated</span>';
	}
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// Default block pattern as the new-post template
// ─────────────────────────────────────────────────────────────────────────────
//
// Once `momentive/infographic-content` is registered as a block pattern, this
// hook sets it as the default editor template for new infographic posts.
// Mirrors the approach in whitepapers.php and webinars.php.

add_action( 'init', function() {
	$cpt = get_post_type_object( 'infographic' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/infographic-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );
