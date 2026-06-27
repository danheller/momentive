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


/* Give the single template in either the "post" or "press-article" post type the 
 * "single-article" body class 
 */

add_filter( 'body_class', function( $classes ) {

	if ( is_singular( array( 'post', 'press-article' ) ) ) {
		$classes[] = 'single-article';
	}
	return $classes;

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
	if ( ! is_singular( [ 'post', 'press-article', 'webinar' ] ) ) return $block_content;

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


/* When a query template has the "order-by-modified" class, adjust the order accordingly.
 * Note: make sure the class is added to the template block inside the query, not to the 
 * query itself.
 */

add_filter( 'query_loop_block_query_vars', function( $query, $block, $page ) {
    $class_list = $block->attributes['className'] ?? '';
    error_log( 'Query vars filter fired. classList: ' . $class_list );

    if ( strpos( $class_list, 'order-by-modified' ) !== false ) {
        $query['orderby'] = 'modified';
        $query['order']   = $query['order'] ?? 'DESC';
    }

    return $query;
}, 10, 3 );

/* When showing the modified date with
 * <!-- wp:post-date {"displayType":"modified"} /-->
 * show a publish date if it matches the modified date
 */
 
add_filter( 'render_block', function ( string $html, array $block, WP_Block $instance ): string {
	if ( 'core/post-date' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	if ( 'modified' !== ( $block['attrs']['displayType'] ?? '' ) ) {
		return $html;
	}
	// Core already rendered something (modified != published) — leave it.
	if ( '' !== trim( $html ) ) {
		return $html;
	}

	$post_id = $instance->context['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return $html;
	}

	$format    = $block['attrs']['format'] ?? get_option( 'date_format' );
	$datetime  = get_the_date( 'c', $post_id );
	$formatted = get_the_date( $format, $post_id );

	$class = 'wp-block-post-date';
	if ( ! empty( $block['attrs']['textAlign'] ) ) {
		$class .= ' has-text-align-' . $block['attrs']['textAlign'];
	}

	return sprintf(
		'<div class="%s"><time datetime="%s">%s</time></div>',
		esc_attr( $class ),
		esc_attr( $datetime ),
		esc_html( $formatted )
	);
}, 10, 3 );

/**
 * Shared renderer: output the post_author_ref byline (People) for a post.
 */
function msw_render_author_ref_column( $post_id ) {
	$people = get_field( 'post_author_ref', $post_id );

	if ( empty( $people ) ) {
		echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
			esc_html__( 'No byline set', 'momentive' ) . '</span>';
		return;
	}

	// Relationship field returns an array (of IDs, per our pinned return format).
	$links = array();
	foreach ( (array) $people as $person ) {
		$pid = is_object( $person ) ? $person->ID : (int) $person;
		if ( ! $pid ) {
			continue;
		}
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $pid ) ),
			esc_html( get_the_title( $pid ) )
		);
	}

	echo $links ? wp_kses_post( implode( ', ', $links ) ) : '—';
}

/**
 * Column header swap: remove native Author, add People byline in its place.
 * Applied to both 'post' and 'press-article'.
 */
function msw_swap_author_column( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'author' === $key ) {
			// Replace the native author column position with ours.
			$new['author_ref'] = __( 'Byline', 'momentive' );
			continue;
		}
		$new[ $key ] = $label;
	}
	// If there was no native author column, append ours.
	if ( ! isset( $new['author_ref'] ) ) {
		$new['author_ref'] = __( 'Byline', 'momentive' );
	}
	return $new;
}

add_filter( 'manage_posts_columns', function ( $columns, $post_type ) {
	if ( in_array( $post_type, array( 'post', 'press-article' ), true ) ) {
		return msw_swap_author_column( $columns );
	}
	return $columns;
}, 10, 2 );

add_action( 'manage_posts_custom_column', function ( $column, $post_id ) {
	if ( 'author_ref' === $column ) {
		msw_render_author_ref_column( $post_id );
	}
}, 10, 2 );