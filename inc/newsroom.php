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
		'has_archive'        => 'newsroom' , // the archive slug
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,

		// Capabilities (page-like behavior)
		'capability_type'    => 'post',

		// Template support (important for FSE )
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'press-article', $args ); // individual post slug
	
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

/* Register the related posts pattern as a reusable block. */

add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/related-posts', [
		'render_callback' => function() {
			if ( ! is_singular( [ 'post', 'press-article' ] ) ) return '';
			ob_start();
			get_template_part( 'patterns/related-posts' );
			return ob_get_clean();
		},
	] );
} );

function momentive_blocks_to_cpt_template( array $blocks ): array {
	$template = [];
	foreach ( $blocks as $block ) {
		if ( empty( $block['blockName'] ) ) {
			continue; // skip whitespace nodes between blocks
		}
		$template[] = [
			$block['blockName'],
			$block['attrs'] ?? [],
			momentive_blocks_to_cpt_template( $block['innerBlocks'] ?? [] ),
		];
	}
	return $template;
}

/* For new press-article posts, use "patterns/press-article-content.php" pattern. */

add_action( 'init', function() {

	$cpt = get_post_type_object( 'press-article' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/press-article-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}

	$cpt->template_lock = false;

}, 30 ); // priority 30 ensures theme file patterns are registered first


/* For new 'post' posts, use "patterns/blog-article-content.php" pattern. */

add_action( 'init', function() {

	$pt = get_post_type_object( 'post' );
	if ( ! $pt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/blog-article-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$pt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}

	$pt->template_lock = false;

}, 30 ); // priority 30 ensures theme file patterns are registered first


/* Block editor JS to handle interactions with ACF. */


add_action( 'enqueue_block_editor_assets', function() {
	$screen = get_current_screen();
	if ( ! $screen ) return;

	$post_type = $screen->post_type;

	$pattern_name = match( $post_type ) {
		'press-article' => 'momentive/press-article-content',
		'post'          => 'momentive/blog-article-content',
		default         => null,
	};

	if ( ! $pattern_name ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( $pattern_name );
	if ( ! $pattern ) return;

	wp_enqueue_script(
		'momentive-press-article-editor',
		get_theme_file_uri( 'assets/js/press-article-editor.js' ),
		[ 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks' ],
		filemtime( get_theme_file_path( 'assets/js/press-article-editor.js' ) ),
		true
	);

	wp_localize_script( 'momentive-press-article-editor', 'momentivePressArticle', [
		'patternContent' => $pattern['content'],
		'resetLabel'     => $post_type === 'press-article'
								? 'Reset to default press article layout'
								: 'Reset to default article layout',
	] );
} );

/* Swap featured image if it's changed using ACF hero_image field. */

add_filter( 'render_block', function( $block_content, $block ) {

	if ( $block['blockName'] !== 'core/post-featured-image' ) return $block_content;
	if ( ! is_singular( [ 'post', 'press-article' ] ) ) return $block_content;

	$hero_image = get_field( 'hero_image' );
	if ( ! $hero_image ) return $block_content;

	$replacement = wp_get_attachment_image(
		$hero_image['ID'],
		'full',
		false,
		[ 'style' => 'width:100%;height:100%;object-fit:cover;' ]
	);

	return preg_replace( '/<img[^>]+>/', $replacement, $block_content, 1 );

}, 10, 2 );