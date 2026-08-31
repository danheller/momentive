<?php

/**
 * Custom Post Type: Interactive Tools
 *
 * CPT key:  interactive-tool  (singular)
 * URL slug: /interactive-tools/{slug}/
 *
 * A small collection of standalone interactive experiences — quizzes,
 * calculators, generators — that don't fit any other content type.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * No ACF fields defined upfront. These posts don't share a real schema —
 * each is bespoke block editor content. ACF field groups can be added later
 * via the ACF UI if a common pattern emerges across multiple posts.
 *
 * The quiz funnel (Find Your Ideal AMS Fit) is two posts: a marketing landing
 * page and a tunnel page that embeds the HubSpot quiz form. The tunnel page
 * uses a simplified header (no megamenu) via a dedicated FSE template.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy, identical to webinars,
 * whitepapers, case studies, etc. Included in
 * momentive_get_resource_post_types() so interactive tools appear in the
 * Resources archive filter and Solution-page resource grids.
 *
 * Note on "noindex" tunnel pages
 * ─────────────────────────────────────────────────────────────────────────────
 * Quiz tunnel pages (the actual embedded form, not the landing page) should
 * be set to noindex via Rank Math — they're reached by clicking through from
 * the landing page and aren't meant to be discovered via search.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_interactive_tools_setup' );

function momentive_interactive_tools_setup(): void {

	$labels = [
		'name'               => _x( 'Interactive Tools', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Interactive Tool', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Interactive Tools', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Interactive Tool', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Interactive Tool', 'momentive' ),
		'new_item'           => __( 'New Interactive Tool', 'momentive' ),
		'edit_item'          => __( 'Edit Interactive Tool', 'momentive' ),
		'view_item'          => __( 'View Interactive Tool', 'momentive' ),
		'all_items'          => __( 'All Interactive Tools', 'momentive' ),
		'search_items'       => __( 'Search Interactive Tools', 'momentive' ),
		'not_found'          => __( 'No interactive tools found.', 'momentive' ),
		'not_found_in_trash' => __( 'No interactive tools found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-hammer',
		'menu_position'      => 39,
		'show_in_rest'       => true,   // Block editor
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
			'page-attributes',  // Allows template assignment (needed for simplified-header tunnel pages)
		],
		'rewrite'            => [
			'slug'       => 'interactive-tools',
			'with_front' => false,
		],
		'has_archive'        => false,  // No archive — these surface via the Resources filter
		'show_in_nav_menus'  => false,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
	];

	register_post_type( 'interactive-tool', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-28.1';
	if ( get_option( 'momentive_interactive_tool_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_interactive_tool_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type (priority 10)
