<?php
/**
 * Functions and definitions for the Momentive theme.
 *
 * Built on the Frost FSE theme base. Uses Full Site Editing with
 * block templates, template parts, and custom blocks.
 *
 * @package momentive
 * @license GNU General Public License v3
 * @link    https://momentivesoftware.com/
 *
 * TABLE OF CONTENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * 1.0  Theme Setup
 * 2.0  Asset Enqueuing
 * 3.0  Block System
 *      3.1  Block Styles
 *      3.2  Block Pattern Categories
 *      3.3  Custom Blocks (required from /blocks/)
 * 4.0  Post Types & Taxonomies (required from /inc/)
 * 5.0  Query & Content Filters
 * 6.0  Front-End Features
 *      6.1  Announcement Bar
 *      6.2  Reading Progress Bar
 * 7.0  Custom Fields
 * 8.0  Developer Experience (required from /inc/)
 * ─────────────────────────────────────────────────────────────────────────────
 */


/*==============================================================================
  1.0 - Theme Setup
==============================================================================*/

if ( ! function_exists( 'momentive_setup' ) ) {
	function momentive_setup() {

		// Make theme available for translation.
		load_theme_textdomain( 'momentive', get_template_directory() . '/languages' );

		// Load theme CSS into the block editor so the editor
		// preview matches the front end as closely as possible.
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/momentive.css' );

		// Load default block styles (e.g. quote, separator, etc.).
		add_theme_support( 'wp-block-styles' );

		// Allow embedded content (YouTube, etc.) to resize responsively.
		add_theme_support( 'responsive-embeds' );

		// Enqueue editor stylesheet.
		add_editor_style( 'assets/css/editor-blocks.css' );

	}
}
add_action( 'after_setup_theme', 'momentive_setup' );


/*==============================================================================
  2.0 - Asset Enqueuing
==============================================================================*/

add_action( 'wp_enqueue_scripts', 'momentive_enqueue' );

function momentive_enqueue() {

	$ver = wp_get_theme()->get( 'Version' );

	// Main stylesheet — compiled from /assets/scss/momentive.scss.
	wp_enqueue_style(
		'momentive',
		get_template_directory_uri() . '/assets/css/momentive.css',
		[],
		$ver
	);

	// Splide slider — loaded globally because sliders appear on multiple
	// page types. Consider conditional loading if performance becomes a concern.
	wp_enqueue_style(
		'splide',
		get_template_directory_uri() . '/assets/css/splide.css',
		[],
		$ver
	);

	wp_enqueue_script(
		'site-utils',
		get_stylesheet_directory_uri() . '/assets/js/site-utils.js',
		[],
		$ver,
		true
	);

	// Main JS — initialises sliders, swoop animations, announcement bar, etc.
	wp_enqueue_script(
		'momentive',
		get_template_directory_uri() . '/assets/js/momentive.js',
		[ 'site-utils' ],
		$ver,
		true // load in footer
	);

	wp_register_script(
		'sliders',
		get_stylesheet_directory_uri() . '/assets/js/sliders.js',
		[],
		$ver,
		true // footer
	);

}

// conditionally enqueue slider javascript based on slider classes

add_filter( 'render_block', function ( $content, $block ) {
	static $enqueued = false;
	if ( $enqueued ) return $content;

	$classes = $block['attrs']['className'] ?? '';
	if ( ! $classes ) return $content;

	$markers = [ 'autoslider', 'solutions-slider', 'testimonials-slider', 'news-slider' ];
	foreach ( $markers as $marker ) {
		if ( false !== strpos( $classes, $marker ) ) {
			wp_enqueue_script( 'sliders' );
			$enqueued = true;
			break;
		}
	}

	return $content;
}, 10, 2 );

/*==============================================================================
  3.0 - Block System
==============================================================================*/

/*------------------------------------------------------------------------------
  3.1 - Block Styles
  These are registered style variations that appear in the block editor's
  "Styles" panel for each block. They add an `is-style-{name}` CSS class
  to the block wrapper, which is targeted in momentive.scss.
------------------------------------------------------------------------------*/

add_action( 'init', 'momentive_register_block_styles' );

function momentive_register_block_styles() {

	$block_styles = [

		'core/columns' => [
			'columns-reverse' => __( 'Reverse',  'momentive' ),
			'outline'         => __( 'Outline',  'momentive' ), // bordered card columns
		],

		'core/column' => [
			'outline'         => __( 'Outline',  'momentive' ), // bordered card columns
		],

		'core/group' => [
			'bg-dots'              => __( 'Dots Background',       'momentive' ),
			'bg-rings'             => __( 'Rings Background',      'momentive' ),
			'bg-dark'              => __( 'Dark Background',       'momentive' ),
			'bg-light'             => __( 'Light Background',      'momentive' ),
			'bg-gradient'          => __( 'Gradient Background',   'momentive' ),
			'bg-ellipse'           => __( 'Ellipse',               'momentive' ),
			'ellipse-bottom'       => __( 'Ellipse Bottom',        'momentive' ),
			'ellipse-top'          => __( 'Ellipse Top',           'momentive' ),
			'purple-seafoam-wash'  => __( 'Purple Seafoam Wash',   'momentive' ),
			'cloudy-sunset'        => __( 'Cloudy Sunset',         'momentive' ),
		],

		'core/list' => [
			'no-disc'              => __( 'No Disc',               'momentive' ),
			'column-checks'        => __( 'Orange Checks',         'momentive' ),
			'circle-checks'        => __( 'Circle Checks',         'momentive' ),
		],

		'core/media-text' => [
			'stacked'   => __( 'Stacked',         'momentive' ),
		],

		'core/paragraph' => [
			'eyebrow'   => __( 'Eyebrow',         'momentive' ),
			'uppercase' => __( 'Uppercase Label', 'momentive' ),
		],

		'core/quote' => [
			'shadow-light' => __( 'Shadow',          'momentive' ),
			'shadow-solid' => __( 'Solid Shadow',    'momentive' ),
			'quote'        => __( 'Large Pull Quote', 'momentive' ),
		],

		'core/heading' => [
			'eyebrow'   => __( 'Eyebrow',         'momentive' ),
			'has-swoop' => __( 'Swoop Underline', 'momentive' ),
		],

		'core/image' => [
			'shadow' => __( 'Shadow',  'momentive' ),
			'round'  => __( 'Round', 'momentive' ),
			'rounder'  => __( 'Rounder', 'momentive' ),

		],

		'core/button' => [
			'superlight' => __( 'Superlight',  'momentive' ), // blue pill
		],

		'core/social-links' => [
			'outline' => __( 'Outline', 'momentive' ),
		],

		// Adds an `is-style-button` option to individual nav items,
		// used for the "Get Your Demo" CTA in the header navigation.
		'core/navigation-link' => [
			'button' => __( 'Button', 'momentive' ),
		],

	];

	foreach ( $block_styles as $block => $styles ) {
		foreach ( $styles as $style_name => $style_label ) {
			register_block_style( $block, [
				'name'  => $style_name,
				'label' => $style_label,
			] );
		}
	}
	

}

// rename "rounded" image block style
add_action( 'enqueue_block_editor_assets', function() {

	wp_add_inline_script(
		'wp-dom-ready',
		<<<JS
		wp.domReady( function() {
			setTimeout( function() {
				wp.blocks.unregisterBlockStyle(
					'core/image',
					'rounded'
				);
			}, 2000 );
		} );
		JS
	);

} );
/*------------------------------------------------------------------------------
  3.2 - Block Pattern Categories
  These appear as filter tabs in the block inserter's Patterns panel.
  Patterns themselves are registered via PHP files in /patterns/ or via
  the Synced Patterns editor (stored as wp_block posts).
------------------------------------------------------------------------------*/

add_action( 'init', 'momentive_register_block_pattern_categories' );

function momentive_register_block_pattern_categories() {

	register_block_pattern_category( 'momentive-page', [
		'label'       => __( 'Page',    'momentive' ),
		'description' => __( 'Full-page layout patterns.', 'momentive' ),
	] );

	register_block_pattern_category( 'momentive-pricing', [
		'label'       => __( 'Pricing', 'momentive' ),
		'description' => __( 'Feature comparison and pricing table patterns.', 'momentive' ),
	] );

}


/*------------------------------------------------------------------------------
  3.3 - Custom Blocks
  Each block lives in its own directory under /blocks/ with a block.json,
  block.php (registration + render callback), and editor.js.
  The front-end script and stylesheet are registered inside each block.php
  and enqueued automatically by WordPress only on pages that use the block.
------------------------------------------------------------------------------*/

require get_template_directory() . '/blocks/breadcrumbs/block.php';
require get_template_directory() . '/blocks/icon-shuffle/block.php';
require get_template_directory() . '/blocks/resource-filters/block.php';
require get_template_directory() . '/blocks/table-of-contents/block.php';
require get_template_directory() . '/blocks/social-share/block.php';
require get_template_directory() . '/blocks/post-byline/block.php';
require get_template_directory() . '/blocks/post-cta-button/block.php';
require get_template_directory() . '/blocks/related-posts/block.php';
require get_template_directory() . '/blocks/impact-stat/block.php';
require get_template_directory() . '/blocks/testimonial/block.php';
require get_template_directory() . '/blocks/accordion/block.php';
require get_template_directory() . '/blocks/hubspot-form/block.php';
require get_template_directory() . '/blocks/megamenu-panel/block.php';
require get_template_directory() . '/blocks/product-marquee/block.php';
require get_template_directory() . '/blocks/product-solution-tabs/block.php';
require get_template_directory() . '/blocks/webinar-cta/block.php';
require get_template_directory() . '/blocks/webinar-schedule/block.php';
require get_template_directory() . '/blocks/webinar-status/block.php';
require get_template_directory() . '/blocks/recording/block.php';


/*==============================================================================
  4.0 - Post Types & Taxonomies
  Each post type is registered and configured in its own file under /inc/.
==============================================================================*/

require get_template_directory() . '/inc/icons.php';
require get_template_directory() . '/inc/solutions.php';

// Press articles — includes shared body class with blog posts (.single-article)
// and the render_block filter that injects related posts below the post layout columns.
require get_template_directory() . '/inc/newsroom.php';

require get_template_directory() . '/inc/authors.php';
require get_template_directory() . '/inc/people.php';

require get_template_directory() . '/inc/testimonials.php';
require get_template_directory() . '/inc/faq.php';
require get_template_directory() . '/inc/products.php';
require get_template_directory() . '/inc/webinars.php';
require get_template_directory() . '/inc/recordings.php'; // not a post type, but a passthrough to what were formerly "assets"


/*==============================================================================
  5.0 - Query & Content Filters
==============================================================================*/

// Hide blank post excerpts rather than falling back to the full post content.
// This keeps archive cards and story cards clean when no excerpt is set.
add_filter( 'get_the_excerpt', function ( $excerpt, $post ) {
	if ( empty( $post->post_excerpt ) ) return '';
	return $excerpt;
}, 10, 2 );


// Query Loop blocks with the class `has-featured-images-only` will only
// show posts that have a featured image set. Add this class in the block
// editor's Advanced panel to use this behavior on any Query Loop.
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	$class = $block->parsed_block['attrs']['className'] ?? '';
	if ( strpos( $class, 'has-featured-images-only' ) !== false ) {
		$meta_query   = $query['meta_query'] ?? [];
		$meta_query[] = [
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		];
		$query['meta_query'] = $meta_query;
	}
	return $query;
}, 10, 2 );


/*==============================================================================
  6.0 - Front-End Features
==============================================================================*/

/*------------------------------------------------------------------------------
  6.1 - Announcement Bar
  The bar is rendered via a pattern file (patterns/announcement-bar.php)
  injected immediately after <body> opens. Cookie-based dismissal is handled
  in the pattern's inline JS (sitewide path=/ cookie).

  To disable the bar: comment out the add_action line below.
  To customise content: use the momentive_announcement_bar_args filter
  (see patterns/announcement-bar.php for available args).
------------------------------------------------------------------------------*/

add_action( 'wp_body_open', 'momentive_render_announcement_bar', 5 );

function momentive_render_announcement_bar() {
	get_template_part( 'patterns/announcement-bar' );
}

/*
// Example: override bar content without editing the pattern file.
add_filter( 'momentive_announcement_bar_args', function ( $args ) {
	$args['text']        = 'New announcement text here.';
	$args['link_url']    = 'https://momentivesoftware.com/your-page/';
	$args['link_label']  = 'Learn More';
	$args['cookie_days'] = 7;
	return $args;
} );
*/


/*------------------------------------------------------------------------------
  6.2 - Reading Progress Bar
  A thin accent-colored bar fixed below the sticky header that fills as the
  reader scrolls through the post content. Only loaded on singular posts.
  Styles are in momentive.scss (#reading-progress). JS is in reading-progress.js.

  Currently loads on all singular post types (is_singular('post') targets
  standard blog posts only; change to is_single() to include all CPTs).
------------------------------------------------------------------------------*/

add_action( 'wp_footer', function () {
	if ( ! is_singular( 'post' ) ) return;
	echo '<div id="reading-progress" aria-hidden="true"></div>';
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'post' ) ) return;
	wp_enqueue_script(
		'momentive-reading-progress',
		get_template_directory_uri() . '/assets/js/reading-progress.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);
} );

/*==============================================================================
  7.0 - Custom Fields
==============================================================================*/

require get_template_directory() . '/inc/acf-groups.php';

/*==============================================================================
  8.0 - Developer Experience
==============================================================================*/

// "Edit Header" and "Edit Footer" hover buttons visible to logged-in editors.
require get_template_directory() . '/inc/header-footer-edit-buttons.php';

// Rename "Posts" to "Blog" throughout the WordPress admin.
require get_template_directory() . '/inc/rename-posts-to-blog.php';

// Customize the dashboard sidebar menu order.
require get_template_directory() . '/inc/custom-menu-order.php';

// Pattern setup
require get_template_directory() . '/inc/patterns.php';

// Check for a block recursively within content (including within patterns)
require get_template_directory() . '/inc/check-content-for-block.php';

// Removes all comment-related UI, menus, and dashboard widgets.
require get_template_directory() . '/inc/disable-comments.php';



/**
 * Hide the standard WordPress accordion and icon blocks to avoid ambiguity.
 */

add_filter( 'allowed_block_types_all', function( $allowed, $context ) {

	if ( ! is_array( $allowed ) ) {
		$allowed = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$allowed = array_keys( $allowed );
	}
	$blocked = [
		'core/details',
		'core/accordion',
		'core/accordion-item',
		'core/accordion-heading',
		'core/accordion-panel',
		'core/icon',
	];
	return array_diff( $allowed, $blocked );

}, 10, 2 );

add_filter( 'block_editor_settings_all', function( $settings ) {
	if ( isset( $settings['__unstableBlockDefinitions'] ) ) {
		foreach ( [ 'core/accordion', 'core/accordion-item', 'core/accordion-heading', 'core/accordion-panel', 'core/details', 'core/icon' ] as $name ) {
			unset( $settings['__unstableBlockDefinitions'][ $name ] );
		}
	}
	return $settings;
} );

wp_add_inline_script( 'wp-edit-post', "
wp.domReady(function() {

	const targets = [
		'core/details',
		'core/accordion',
		'core/accordion-item',
		'core/accordion-heading',
		'core/accordion-panel',
		'core/icon'
	];
	const unsubscribe = wp.data.subscribe(() => {
		const ready = targets.every(
			name => wp.blocks.getBlockType(name)
		);
		if ( ! ready ) {
			return;
		}
		targets.forEach(name => {
			wp.blocks.unregisterBlockType(name);
		});
		unsubscribe();
	});

});
" );