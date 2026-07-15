<?php

/**
 * Custom Post Type: Whitepapers
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  whitepaper  (one word, matching the brand's public-facing spelling
 *           on momentivesoftware.com; the two-word form "white paper" is more
 *           standard but would create a discrepancy with the site's own labels)
 * URL slug: /whitepapers/{slug}/
 *
 * Whitepapers are "gated content": a two-column layout with a description and
 * optional checklist on the left, and a HubSpot registration form on the right.
 * Submitting the form delivers a PDF hosted by HubSpot — the rebuilt CPT just
 * renders the embed; HubSpot handles delivery.
 *
 * Unlike webinars, whitepapers have no date-based status transitions, no
 * upcoming/on-demand lifecycle, and no recording layer. The structure is close
 * to case-studies.php: registration, category taxonomy wiring, a solution admin
 * column, and the new-post pattern template hook.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * The post body (block editor) holds:
 *   • Description copy ("resource_details" from legacy)
 *   • Optional "you'll learn" checklist
 *   • Optional insights list (title + description pairs — 2 legacy posts only)
 *   • Optional additional download/anchor link button
 *   • HubSpot form embed (via acf/hubspot-form block)
 *
 * ACF fields (defined in inc/acf-groups.php) hold structured metadata the theme
 * PHP needs to read programmatically:
 *   • hero_image — page-hero image override (separate from _thumbnail_id)
 *   • enable_gated_content — when false, a direct download link replaces
 *                            the form (1 legacy post; keep for edge cases)
 *
 * The form heading ("Download Now", "Get your free copy now", etc.) is NOT an
 * ACF field — it lives as a paragraph/heading block in the right column of the
 * post body, directly above the acf/hubspot-form block. It varies per post and
 * PHP never needs to read it, so a sidebar field would only add cognitive load.
 *
 * Fields that are always false/empty in the legacy corpus and are NOT migrated:
 *   quote box, CAE credits, video module, related resources, CTA box, series
 *   section, popup forms — all dead Elementor-era fields.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions"
 * parent term), identical to products, testimonials, and case studies. Uses
 * the native multi-select category panel — whitepapers can span multiple
 * solution areas (the legacy corpus has posts with up to 5 categories).
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_whitepapers_setup' );

// Front-end styles for the single-whitepaper view.
// Registered always, enqueued only on singular whitepaper posts.
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);


	if ( is_singular( 'whitepaper' ) ) {
		wp_enqueue_style( 'momentive-gate' );
	}
} );

function momentive_whitepapers_setup(): void {

	$labels = [
		'name'               => _x( 'Whitepapers', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Whitepaper', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Whitepapers', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Whitepaper', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Whitepaper', 'momentive' ),
		'new_item'           => __( 'New Whitepaper', 'momentive' ),
		'edit_item'          => __( 'Edit Whitepaper', 'momentive' ),
		'view_item'          => __( 'View Whitepaper', 'momentive' ),
		'all_items'          => __( 'All Whitepapers', 'momentive' ),
		'search_items'       => __( 'Search Whitepapers', 'momentive' ),
		'not_found'          => __( 'No whitepapers found.', 'momentive' ),
		'not_found_in_trash' => __( 'No whitepapers found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-media-document',
		'menu_position'      => 36,
		'show_in_rest'       => true,        // Block editor
		'supports'           => [
			'title',      // Whitepaper title
			'editor',     // Body: description, checklist, form block, etc.
			'excerpt',    // Used on archive/query loop cards
			'thumbnail',  // Archive card image (separate from hero_image ACF field)
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'whitepapers',
			'with_front' => false,
		],
		'has_archive'        => 'whitepapers',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
		'template'           => [],   // Populated below once the pattern exists
		'template_lock'      => false,
	];

	register_post_type( 'whitepaper', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────
//
// WordPress needs its rewrite table rebuilt after a new CPT is added.
// A version-stamped option triggers this exactly once (bump the stamp to
// re-trigger, e.g. after a slug change). The same pattern is used in people.php.

add_action( 'init', function() {
	$stamp = '2026-07-02.1';
	if ( get_option( 'momentive_whitepaper_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_whitepaper_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type (priority 10)


// ─────────────────────────────────────────────────────────────────────────────
// Shared solution categories — scoped ACF field filter
// ─────────────────────────────────────────────────────────────────────────────
//
// If an ACF taxonomy field named `whitepaper_solution` is added (e.g. for a
// curated single "primary" solution), this filter scopes its options to the
// Solutions children. The native multi-select category panel handles the
// general case and is left unfiltered (see case-studies.php for rationale).

add_filter( 'acf/fields/taxonomy/query/name=whitepaper_solution', function( array $args ): array {
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
// Categories column. The gated/ungated status is not visible anywhere else
// in the list and is immediately useful for spotting the one ungated post.

add_filter( 'manage_whitepaper_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['wp_gated'] = __( 'Gated', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_whitepaper_posts_custom_column', function( string $column, int $post_id ): void {
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
// Once `momentive/whitepaper-content` is registered as a block pattern, this
// hook sets it as the default editor template for new whitepaper posts.
// Mirrors the approach in webinars.php and case-studies.php.

add_action( 'init', function() {
	$cpt = get_post_type_object( 'whitepaper' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/whitepaper-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );

