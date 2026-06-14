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
		'has_archive'        => 'solutions',
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
 * For solution singular posts, apply the accent color as a root-level variable.
 * If the solution is a child page, use the parent solution's accent color.
 */

add_action( 'wp_head', function() {
	if ( ! is_singular( 'solutions' ) ) return;

	$post_id = get_the_ID();
	$parent_id = wp_get_post_parent_id( $post_id );

	// If this is a child post, use the parent's accent color
	$source_id = $parent_id ? $parent_id : $post_id;
	$color = get_field( 'accent_color', $source_id );

	if ( ! $color ) return;
	echo '<style>:root { --solution: ' . esc_attr( $color ) . '; }</style>';
} );

add_filter( 'acf/prepare_field/name=accent_color', function( $field ) {
	// Only run in the admin editor context
	if ( ! is_admin() ) return $field;

	$post_id = acf_get_valid_post_id();
	if ( ! $post_id ) return $field;

	$post = get_post( $post_id );
	if ( $post && $post->post_parent ) {
		return false; // Returning false hides the field entirely
	}

	return $field;
} );

/* 
 * Add editor javascript to show/hide accent color field based on whether there is a parent post.
 */

add_action( 'enqueue_block_editor_assets', function() {
	$screen = get_current_screen();
	if ( ! $screen ) return;

	$post_type = $screen->post_type;

	if ( 'solutions' != $post_type ) return;

	wp_enqueue_script(
		'momentive-solutions-editor',
		get_theme_file_uri( 'assets/js/solutions-editor.js' ),
		[ 'wp-data' ],
		filemtime( get_theme_file_path( 'assets/js/solutions-editor.js' ) ),
		true
	);
} );


/* 
 * For new 'solution' posts, use "patterns/solution-content.php" pattern. 
 */

add_action( 'init', function() {
	$cpt = get_post_type_object( 'solutions' );
	if ( ! $cpt ) return;
	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/solution-content' );
	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );

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
		'mode'              => 'preview',
		'supports'          => [
			'align'  => false,
			'mode'   => false,
			'jsx'    => false,
		],
	]);
});

/*
 * Enqueue solutions CSS conditionally
 */
 
add_action( 'enqueue_block_assets', function() {
	if ( ! has_block( 'acf/solution-slide' ) ) return;

	wp_enqueue_style(
		'momentive-solutions',
		get_template_directory_uri() . '/assets/css/solutions.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );

/*
 * Populate "icons" select list with options from the icon system
 */

add_action( 'acf/render_field/name=solution_icon', function( $field ) {
	$slug = $field['value'];
	// Only render preview for slug-like values, not legacy attachment IDs
	if ( ! $slug || is_numeric( $slug ) ) return;
	echo '<div style="margin-top:8px; width:48px; height:48px;">';
	momentive_output_svg_symbols( [ $slug ] );
	echo '<svg style="width:100%;height:100%;"><use href="#icon-' . esc_attr( $slug ) . '"></use></svg>';
	echo '</div>';
} );

add_filter( 'acf/load_field/name=solution_icon', function( $field ) {
	$field['choices'] = array_merge(
		[ '' => '— None —' ],
		momentive_get_available_icons()
	);
	return $field;
} );

