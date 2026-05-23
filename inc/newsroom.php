<?php

/**
 * Custom Post Type: Newsroom
 */

function momentive_newsroom_setup() {
	$labels = array(
		'name'                  => _x( 'Newsroom', 'Post type general name', 'momentive' ),
		'singular_name'         => _x( 'News', 'Post type singular name', 'momentive' ),
		'menu_name'             => _x( 'Newsroom', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'        => _x( 'Newsroom', 'Add New on Toolbar', 'momentive' ),
		'add_new'               => __( 'Add News', 'momentive' ),
		'add_new_item'          => __( 'Add a News Item', 'momentive' ),
		'new_item'              => __( 'New News', 'momentive' ),
		'edit_item'             => __( 'Edit News', 'momentive' ),
		'view_item'             => __( 'View News Item', 'momentive' ),
		'all_items'             => __( 'All News Items', 'momentive' ),
		'search_items'          => __( 'Search Newsroom', 'momentive' ),
		'parent_item_colon'     => __( 'Parent News Items:', 'momentive' ),
		'not_found'             => __( 'No news found.', 'momentive' ),
		'not_found_in_trash'    => __( 'No news found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-media-document',
		'menu_position'      => 6, // right after Posts
		// Gutenberg + FSE friendly
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
			'slug'       => 'press-releases',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => true,
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,

		// Capabilities (page-like behavior)
		'capability_type'    => 'post',

		// Template support (important for FSE )
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'press-article', $args );
	
}
add_action( 'init', 'momentive_newsroom_setup' );


/* Give the single tempalte in either the "post" or "press-article" post type the 
 * "single-article" body class 
 */

add_filter( 'body_class', function( $classes ) {

	if ( is_singular( array( 'post', 'press-article' ) ) ) {
		$classes[] = 'single-article';
	}
	return $classes;

} );


/* Add a related posts / recommended for you block at the bottom of both newsroom posts 
 * and standard (blog) posts using a simple category query, which might be replaced with 
 * custom curation later */

add_filter( 'render_block', function ( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/columns' ) {
		return $block_content;
	}
	if ( ! is_singular( 'post' ) ) {
		return $block_content;
	}

	// Only target the specific columns block that has the post-layout class.
	$classes = $block['attrs']['className'] ?? '';
	if ( strpos( $classes, 'post-layout' ) === false ) {
		return $block_content;
	}

	ob_start();
	get_template_part( 'patterns/related-posts' );
	$related = ob_get_clean();

	return $block_content . $related;
}, 10, 2 );