<?php

/**
 * Custom Post Type: Testimonials
 */

function momentive_testimonials_setup() {
	$labels = array(
		'name'               => _x( 'Testimonials', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Testimonial', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Testimonials', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Testimonial', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Testimonial', 'momentive' ),
		'new_item'           => __( 'New Testimonial', 'momentive' ),
		'edit_item'          => __( 'Edit Testimonial', 'momentive' ),
		'view_item'          => __( 'View Testimonial', 'momentive' ),
		'all_items'          => __( 'All Testimonials', 'momentive' ),
		'search_items'       => __( 'Search Testimonials', 'momentive' ),
		'not_found'          => __( 'No testimonials found.', 'momentive' ),
		'not_found_in_trash' => __( 'No testimonials found in Trash.', 'momentive' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => false,       // No public-facing URLs needed
		'publicly_queryable' => true,
		'show_ui'            => true,        // Visible in admin
		'show_in_menu'       => true,
		'show_in_rest'       => true,        // Block editor support
		'menu_icon'          => 'dashicons-format-quote',
		'supports'           => array(
			'title',    // Author name as post title (makes admin list readable)
			'editor',   // Quote text lives here
			'thumbnail', // Author photo fallback if not using ACF
			'revisions',
		),
		'taxonomies'   => array( 'category' ),
		'has_archive'        => false,
		'rewrite'            => false,
		'capability_type'    => 'post',
	);

	register_post_type( 'testimonials', $args );
}
add_action( 'init', 'momentive_testimonials_setup' );

$args = array(
	'hierarchical'      => false,
	'labels'            => array(
		'name'          => _x( 'Testimonial Types', 'taxonomy general name', 'momentive' ),
		'singular_name' => _x( 'Testimonial Type', 'taxonomy singular name', 'momentive' ),
		'all_items'     => __( 'All Types', 'momentive' ),
		'edit_item'     => __( 'Edit Type', 'momentive' ),
		'add_new_item'  => __( 'Add New Type', 'momentive' ),
		'new_item_name' => __( 'New Type Name', 'momentive' ),
		'menu_name'     => __( 'Types', 'momentive' ),
	),
	'show_ui'           => true,
	'show_admin_column' => true,
	'show_in_rest'      => true,   // Required for Query Loop filtering
	'publicly_queryable' => true,  // Same reason as the CPT itself
	'public'            => false,
	'rewrite'           => false,
);
register_taxonomy( 'testimonial_type', array( 'testimonials' ), $args );

// add solution categories as options for the testimonial solution select field

add_filter( 'acf/fields/taxonomy/query/name=testimonial_solution', function( $args ) {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );


// hide the default category panel

add_action( 'enqueue_block_editor_assets', function() {
	if ( get_post_type() !== 'testimonials' ) return;

	wp_add_inline_script( 'wp-blocks', "
		wp.domReady( function() {
			wp.data.dispatch( 'core/edit-post' )
				.removeEditorPanel( 'taxonomy-panel-category' );
		} );
	" );
} );