<?php

/**
 * Custom Post Type: Authors
 */

function momentive_authors_setup() {
	$labels = array(
		'name'                  => _x( 'Authors', 'Post type general name', 'momentive' ),
		'singular_name'         => _x( 'Author', 'Post type singular name', 'momentive' ),
		'menu_name'             => _x( 'Authors', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'        => _x( 'Authors', 'Add New on Toolbar', 'momentive' ),
		'add_new'               => __( 'Add Author', 'momentive' ),
		'add_new_item'          => __( 'Add an Author', 'momentive' ),
		'new_item'              => __( 'New Author', 'momentive' ),
		'edit_item'             => __( 'Edit Author', 'momentive' ),
		'view_item'             => __( 'View Author', 'momentive' ),
		'all_items'             => __( 'All Authors', 'momentive' ),
		'search_items'          => __( 'Search Authors', 'momentive' ),
		'parent_item_colon'     => __( 'Parent Author:', 'momentive' ),
		'not_found'             => __( 'No authors found.', 'momentive' ),
		'not_found_in_trash'    => __( 'No authors found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-groups',
		'menu_position'      => 50,
		'show_in_rest'       => true,
		'taxonomies'   => array( 'category' ),
		'supports'           => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		),
		// URL structure
		'rewrite'            => array(
			'slug'       => 'authors',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => false,
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'authors', $args );
	
}
add_action( 'init', 'momentive_authors_setup' );


