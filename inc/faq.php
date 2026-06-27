<?php

/**
 * Custom Post Type: FAQ
 *
 * Data structure (from site export):
 *  - post_title   — the question text (used as accordion trigger label)
 *  - post_content — the long answer (Gutenberg blocks)
 *  - post_excerpt - a brief HTML summary, used where a
 *    condensed answer is needed (e.g. rich snippets, cards)
 *  - category taxonomy — solution-scoped categories (same parent "solutions"
 *    term used by Testimonials), used for accordion filtering
 *  - menu_order   — manual sort order within a category or page
 */

function momentive_faq_setup() {
	$labels = array(
		'name'               => _x( 'FAQ', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'FAQ', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'FAQ', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'FAQ', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Question', 'momentive' ),
		'new_item'           => __( 'New Question', 'momentive' ),
		'edit_item'          => __( 'Edit Question', 'momentive' ),
		'view_item'          => __( 'View Question', 'momentive' ),
		'all_items'          => __( 'All Questions', 'momentive' ),
		'search_items'       => __( 'Search Questions', 'momentive' ),
		'not_found'          => __( 'No Questions found.', 'momentive' ),
		'not_found_in_trash' => __( 'No Questions found in Trash.', 'momentive' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,         // Permalinks are live on the old site
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,         // Block editor + REST API (accordion block load-more)
		'rest_base'          => 'faq',       // /wp-json/wp/v2/faq
		'menu_icon'          => 'dashicons-editor-help',
		'menu_position'      => 45,
		'supports'           => array(
			'title',        // The question
			'excerpt',      // The short answer
			'editor',       // The long answer (Gutenberg blocks)
			'revisions',
			'page-attributes', // Exposes menu_order for manual sort control
		),
		'taxonomies'         => array( 'category' ),
		'has_archive'        => 'faq',
		'rewrite'            => array(
			'slug'       => 'faq',
			'with_front' => false,
		),
		'capability_type'    => 'post',
	);

	register_post_type( 'faq', $args );
}
add_action( 'init', 'momentive_faq_setup' );


/**
 * Restrict the category selector to solution-scoped terms only,
 * mirroring the testimonial_solution ACF field filter in testimonials.php.
 *
 * The ACF field should be named "faq_solution" and use the taxonomy field
 * type pointing at "category".
 */
add_filter( 'acf/fields/taxonomy/query/name=faq_solution', function( $args ) {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );


/**
 * Hide the default category panel in the block editor for FAQ posts.
 * The ACF "faq_solution" field provides a filtered, purpose-built replacement.
 */
add_action( 'enqueue_block_editor_assets', function() {
	if ( get_post_type() !== 'faq' ) return;

	wp_add_inline_script( 'wp-blocks', "
		wp.domReady( function() {
			wp.data.dispatch( 'core/edit-post' )
				.removeEditorPanel( 'taxonomy-panel-category' );
		} );
	" );
} );

