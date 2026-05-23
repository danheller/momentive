<?php
/**
 * This file adds functions to the Momentive WordPress theme.
 *
 * @package momentive
 * @author  Momentive
 * @license GNU General Public License v3
 * @link    https://momentivesoftware.com/
 */

if ( ! function_exists( 'momentive_setup' ) ) {

	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function momentive_setup() {

		// Make theme available for translation.
		load_theme_textdomain( 'momentive', get_template_directory() . '/languages' );

		// Load regular editor styles into the block-based editor.
		add_theme_support( 'editor-styles' );
	
		// Load default block styles.
		add_theme_support( 'wp-block-styles' );
	
		// Add support for responsive embeds.
		add_theme_support( 'responsive-embeds' );

		// Enqueue editor stylesheet.
		add_editor_style( get_template_directory_uri() . '/assets/css/editor-blocks.css' );

		// Remove core block patterns.
		// remove_theme_support( 'core-block-patterns' );


	}
}
add_action( 'after_setup_theme', 'momentive_setup' );

// Enqueue stylesheet.
add_action( 'wp_enqueue_scripts', 'momentive_enqueue' );
function momentive_enqueue() {
	wp_enqueue_style( 'momentive', get_template_directory_uri() . '/assets/css/momentive.css', array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_style( 'slider', get_template_directory_uri() . '/assets/css/splide.css', array(), wp_get_theme()->get( 'Version' ) );
	wp_enqueue_script( 'momentive', get_template_directory_uri() . '/assets/js/momentive.js', false, wp_get_theme()->get( 'Version' ), 1 );

}




/**
 * Register block styles.
 *
 * @since 0.9.2
 */
function momentive_register_block_styles() {

	$block_styles = array(
		// Columns
		'core/columns' => array(
			'columns-reverse' => __( 'Reverse', 'momentive' ),
			'outline'         => __( 'Outline', 'momentive' ), // bordered card columns
		),

		// Group
		'core/group' => array(
			'bg-dots'         => __( 'Dots Background', 'momentive' ),
			'bg-rings'        => __( 'Rings Background', 'momentive' ),
			'bg-dark'         => __( 'Dark Background', 'momentive' ),
			'bg-light-blue'   => __( 'Light Blue Background', 'momentive' ),
			'bg-blue-ellipse' => __( 'Blue Ellipse Background', 'momentive' ),
			'bg-gradient-blue' => __( 'Blue Gradient', 'momentive' ),
		),

		// List
		'core/list' => array(
			'no-disc' => __( 'No Disc', 'momentive' ),
		),

		// Quote
		'core/quote' => array(
			'shadow-light' => __( 'Shadow', 'momentive' ),
			'shadow-solid' => __( 'Solid Shadow', 'momentive' ),
			'quote'        => __( 'Large Pull Quote', 'momentive' ), // .is-style-quote
		),

		// Paragraph
		'core/paragraph' => array(
			'eyebrow'    => __( 'Eyebrow', 'momentive' ),
			'uppercase'  => __( 'Uppercase Label', 'momentive' ),
		),

		// Heading
		'core/heading' => array(
			'eyebrow'   => __( 'Eyebrow', 'momentive' ),
			'has-swoop' => __( 'Swoop Underline', 'momentive' ),
		),

		// Image / Figure
		'core/image' => array(
			'shadow' => __( 'Shadow', 'momentive' ),
			'round'  => __( 'Rounded', 'momentive' ),
		),
	
		// Social Links
		'core/social-links' => array(
			'outline' => __( 'Outline', 'momentive' ),
		),

		// Navigation Link
		'core/navigation-link' => array(
			'button' => __( 'Button', 'momentive' ),
		),
	);

	foreach ( $block_styles as $block => $styles ) {
		foreach ( $styles as $style_name => $style_label ) {
			register_block_style(
				$block,
				array(
					'name'  => $style_name,
					'label' => $style_label,
				)
			);
		}
	}
}
add_action( 'init', 'momentive_register_block_styles' );

/**
 * Register block pattern categories.
 *
 * @since 1.0.4
 */
function momentive_register_block_pattern_categories() {

	register_block_pattern_category(
		'momentive-page',
		array(
			'label'       => __( 'Page', 'momentive' ),
			'description' => __( 'Create a full page with multiple patterns that are grouped together.', 'momentive' ),
		)
	);
	register_block_pattern_category(
		'momentive-pricing',
		array(
			'label'       => __( 'Pricing', 'momentive' ),
			'description' => __( 'Compare features for your digital products or service plans.', 'momentive' ),
		)
	);

}

add_action( 'init', 'momentive_register_block_pattern_categories' );



/**
 * Set up solutions post type
 */

require get_template_directory() . '/inc/solutions.php';


/**
 * Set up newsroom/press article post type
 * - give these posts a common body class (.single-article) with standard (blog) posts to 
 *   simplify styling
 * - add related posts at the bottom of both newsroom posts and standard (blog) posts 
 *   using a simple category query, which can be replaced with custom curation later
 */

require get_template_directory() . '/inc/newsroom.php';


/**
 * Set up authors post type
 */

require get_template_directory() . '/inc/authors.php';



/**
 * Set up icons post type
 */

require get_template_directory() . '/inc/icons.php';



/**
 * Set up breadcrumbs custom block
 */

require get_template_directory() . '/blocks/breadcrumbs/block.php';



/**
 * Set up icon shuffle custom block
 */

require get_template_directory() . '/blocks/icon-shuffle/block.php';


/**
 * Set up resource filters custom block
 */

require get_template_directory() . '/blocks/resource-filters/block.php';


/**
 * Set up table of contents custom block for single and newsroom posts
 */

require get_template_directory() . '/blocks/table-of-contents/block.php';


/**
 * Set up social sharing links custom block for single and newsroom posts
 */

require get_template_directory() . '/blocks/social-share/block.php';


/**
 * Set up byline custom block for single and newsroom posts
 */

require get_template_directory() . '/blocks/post-byline/block.php';


/**
 * Set up CTA button custom block to add a link from an ACF field to single post headers
 */

require get_template_directory() . '/blocks/post-cta-button/block.php';


/**
 * Developer experience improvements
 */

/**
 * Header and footer edit buttons appear on hover
 */ 
 
require get_template_directory() . '/inc/header-footer-edit-buttons.php';

/**
 * Use the name "Blog" for Posts in admin screens
 */ 
 
require get_template_directory() . '/inc/rename-posts-to-blog.php';



/**
 * Add block patterns to the WP dashboard menu
 */ 
 
require get_template_directory() . '/inc/show-patterns-in-menu.php';




/**
 * Disable all comment features
 */ 
 
require get_template_directory() . '/inc/disable-comments.php';

/**
 * Hide blank excerpts rather than showing post content as a fallback
 */
 
add_filter( 'get_the_excerpt', function( $excerpt, $post ) {

	if ( empty( $post->post_excerpt ) ) {
		return '';
	}
	return $excerpt;

}, 10, 2 );


/**
 * Query loops with "has-featured-images-only" require a featured image
 */

add_filter( 'query_loop_block_query_vars', function( $query, $block ) {

	$class = $block->parsed_block['attrs']['className'] ?? '';
	if ( strpos( $class, 'has-featured-images-only' ) !== false ) {
		$meta_query = $query['meta_query'] ?? array();
		$meta_query[] = array(
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		);
		$query['meta_query'] = $meta_query;
	}
	return $query;

}, 10, 2 );


/**
 * Announcement bar
 */
 
// add_action( 'wp_enqueue_scripts', 'momentive_enqueue_announcement_bar' );

function momentive_enqueue_announcement_bar() {
	// Don't load styles if the bar has already been dismissed for this visitor.
	if ( ! empty( $_COOKIE['momentive_announcement_dismissed'] ) ) {
		return;
	}
/*
	wp_enqueue_style(
		'momentive-announcement-bar',
		get_template_directory_uri() . '/assets/css/announcement-bar.css',
		array(),          // no dependencies
		wp_get_theme()->get( 'Version' )
	);
*/
}

// ── 2. Render the bar via wp_body_open ────────────────────────────────────
//
// wp_body_open fires immediately after <body> opens, which places the bar
// before the FSE header template part – exactly what we want.

add_action( 'wp_body_open', 'momentive_render_announcement_bar', 5 );

function momentive_render_announcement_bar() {
	get_template_part( 'patterns/announcement-bar' );
}

/* ── 3. Optional: customise bar content without editing the template ────────

// Uncomment and adjust to override any default values:

add_filter( 'momentive_announcement_bar_args', function ( $args ) {
	$args['text']        = 'New announcement text goes here.';
	$args['link_url']    = 'https://momentivesoftware.com/your-page/';
	$args['link_label']  = 'Learn More';
	$args['cookie_days'] = 7;   // Re-show after 7 days instead of 30
	return $args;
} );
*/


/**
 * Reading progress bar
 */

add_action( 'wp_footer', function () {
	if ( ! is_single() ) return;
	echo '<div id="reading-progress" aria-hidden="true"></div>';
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_single() ) return;
	wp_enqueue_script(
		'momentive-reading-progress',
		get_template_directory_uri() . '/assets/js/reading-progress.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);
} );

