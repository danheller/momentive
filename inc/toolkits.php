<?php

/**
 * Custom Post Type: Toolkits
 *
 * CPT key:  toolkit  (singular, matching the pattern of webinar, whitepaper, etc.)
 * URL slug: /toolkits/{slug}/
 *
 * A toolkit is a gated resource-collection page — one HubSpot form, but
 * instead of gating a single PDF or video, it gates a bundle: a curated set
 * of webinars/templates or a long buyer's-guide with accordion sections.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * Two layout variants, distinguished by a `toolkit_type` ACF field:
 *
 *   standard     — Gated shell + a card grid of bundled webinars/templates.
 *                  Cards are Post Object references into the `webinar` CPT
 *                  (not hand-typed labels), so card titles and thumbnails stay
 *                  in sync if the referenced webinar is updated.
 *
 *   buyers-guide — Gated shell + a series of accordion sections (each backed
 *                  by a `momentive/accordion` block), a dark CTA box (a synced
 *                  block pattern), and an optional two-button CTA band.
 *
 * All published toolkits are gated — no ungated variant exists in this corpus,
 * unlike infographics. The `enable_gated_content` flag can be respected if a
 * future ungated toolkit is ever created.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions"
 * parent term), identical to webinars, whitepapers, case studies, etc.
 * Included in momentive_get_resource_post_types() so toolkits appear in the
 * Resources archive filter and Solution-page resource grids.
 *
 * Note on buyer's-guide posts
 * ─────────────────────────────────────────────────────────────────────────────
 * The two buyer's-guide posts each have 7 accordion sections × 3–5 rows of
 * HTML content. A targeted WP-CLI migration script may be worth writing for
 * those two posts specifically to handle the Word-artifact cleanup and
 * accordion assembly — assess before hand-building them.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_toolkits_setup' );

function momentive_toolkits_setup(): void {

	$labels = [
		'name'               => _x( 'Toolkits', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Toolkit', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Toolkits', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Toolkit', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Toolkit', 'momentive' ),
		'new_item'           => __( 'New Toolkit', 'momentive' ),
		'edit_item'          => __( 'Edit Toolkit', 'momentive' ),
		'view_item'          => __( 'View Toolkit', 'momentive' ),
		'all_items'          => __( 'All Toolkits', 'momentive' ),
		'search_items'       => __( 'Search Toolkits', 'momentive' ),
		'not_found'          => __( 'No toolkits found.', 'momentive' ),
		'not_found_in_trash' => __( 'No toolkits found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-archive',
		'menu_position'      => 40,
		'show_in_rest'       => true,   // Block editor
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'toolkits',
			'with_front' => false,
		],
		'has_archive'        => false,  // No archive — surfaces via the Resources filter
		'show_in_nav_menus'  => false,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
	];

	register_post_type( 'toolkit', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-28.1';
	if ( get_option( 'momentive_toolkit_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_toolkit_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type (priority 10)
