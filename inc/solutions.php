<?php

/**
 * Custom Post Type: Solutions
 */

function momentive_solutions_setup() {
	$labels = array(
		'name'                  => _x( 'Solutions', 'Post type general name', 'momentive' ),
		'singular_name'         => _x( 'Solution', 'Post type singular name', 'momentive' ),
		'menu_name'             => _x( 'Solutions', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'        => _x( 'Solution', 'Add New on Toolbar', 'momentive' ),
		'add_new'               => __( 'Add New', 'momentive' ),
		'add_new_item'          => __( 'Add New Solution', 'momentive' ),
		'new_item'              => __( 'New Solution', 'momentive' ),
		'edit_item'             => __( 'Edit Solution', 'momentive' ),
		'view_item'             => __( 'View Solution', 'momentive' ),
		'all_items'             => __( 'All Solutions', 'momentive' ),
		'search_items'          => __( 'Search Solutions', 'momentive' ),
		'parent_item_colon'     => __( 'Parent Solutions:', 'momentive' ),
		'not_found'             => __( 'No solutions found.', 'momentive' ),
		'not_found_in_trash'    => __( 'No solutions found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => true, // Makes it behave like pages
		'menu_icon'          => 'dashicons-portfolio',
		// Gutenberg + FSE friendly
		'show_in_rest'       => true,
		// Supports similar to Pages
		'supports'           => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'page-attributes', // Enables parent/child + order
			'revisions',
		),
		// URL structure
		'rewrite'            => array(
			'slug'       => 'solutions',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => false,
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,

		// Capabilities (page-like behavior)
		'capability_type'    => 'page',

		// Template support (important for FSE )
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'solutions', $args );
	
	// Register Custom Taxonomy: Solution Tags
	$labels = array(
		'name'                       => _x( 'Solution Tags', 'taxonomy general name', 'momentive' ),
		'singular_name'              => _x( 'Solution Tag', 'taxonomy singular name', 'momentive' ),
		'search_items'               => __( 'Search Solution Tags', 'momentive' ),
		'popular_items'              => __( 'Popular Solution Tags', 'momentive' ),
		'all_items'                  => __( 'All Solution Tags', 'momentive' ),
		'edit_item'                  => __( 'Edit Solution Tag', 'momentive' ),
		'update_item'                => __( 'Update Solution Tag', 'momentive' ),
		'add_new_item'               => __( 'Add New Solution Tag', 'momentive' ),
		'new_item_name'              => __( 'New Solution Tag Name', 'momentive' ),
		'separate_items_with_commas' => __( 'Separate tags with commas', 'momentive' ),
		'add_or_remove_items'        => __( 'Add or remove tags', 'momentive' ),
		'choose_from_most_used'      => __( 'Choose from the most used tags', 'momentive' ),
		'menu_name'                  => __( 'Solution Tags', 'momentive' ),
	);

	$args = array(
		'hierarchical'          => false, // This makes it tag-like
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_rest'          => true, // Required for block editor / FSE
		'public'                => true,
		// URL structure
		'rewrite'               => array(
			'slug' => 'solution-tag',
		),
	);
	register_taxonomy( 'solution_tag', array( 'solutions' ), $args );
}
add_action( 'init', 'momentive_solutions_setup' );


/*
 * Slider card block
 */
 
add_action( 'init', function() {
	if ( ! function_exists( 'acf_register_block_type' ) ) return;
    	
	acf_register_block_type([
		'name'              => 'solution-slide',
		'title'             => 'Solution Slide',
		'description'       => 'A single solution card for use in the query loop.',
		'render_template'   => get_template_directory() . '/blocks/solution-slide/solution-slide.php',
		'category'          => 'theme',
		'icon'              => 'cover-image',
		'keywords'          => ['solution', 'slide', 'card'],
		'post_types'        => ['post','page'],
		'mode'              => 'preview',
		'supports'          => [
			'align'  => false,
			'mode'   => false,
			'jsx'    => false,
		],
	]);
});

/*
 * ACF fields for card block
 */
 
add_action( 'acf/init', function() {
	acf_add_local_field_group([
		'key'      => 'group_solution_slider_card',
		'title'    => 'Solution Slider Card',
		'fields'   => [
			[
				'key'   => 'field_background_color',
				'label' => 'Background Color',
				'name'  => 'background_color',
				'type'  => 'color_picker',
			],
			[
				'key'          => 'field_icon',
				'label'        => 'Icon',
				'name'         => 'icon',
				'type'         => 'image',
				'return_format' => 'url',
			],
			[
				'key'          => 'field_background_image',
				'label'        => 'Background Image',
				'name'         => 'background_image',
				'type'         => 'image',
				'return_format' => 'url',
			],
		],
		'location' => [[
			['param' => 'post_type', 'operator' => '==', 'value' => 'solutions'],
		]],
	]);
});
