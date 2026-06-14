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

		'core/group' => [
			'bg-dots'          => __( 'Dots Background',       'momentive' ),
			'bg-rings'         => __( 'Rings Background',      'momentive' ),
			'bg-dark'          => __( 'Dark Background',       'momentive' ),
			'bg-light'         => __( 'Light Background',      'momentive' ),
			'bg-gradient'      => __( 'Gradient Background',   'momentive' ),
			'bg-ellipse'       => __( 'Ellipse',               'momentive' ),
			'ellipse-bottom'   => __( 'Ellipse Bottom',        'momentive' ),
			'ellipse-top'      => __( 'Ellipse Top',           'momentive' ),
		],

		'core/list' => [
			'no-disc' => __( 'No Disc', 'momentive' ),
			'column-checks' => __( 'Check Marks', 'momentive' ),
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
require get_template_directory() . '/blocks/impact-stat/block.php';
require get_template_directory() . '/blocks/testimonial/block.php';
require get_template_directory() . '/blocks/accordion/block.php';
require get_template_directory() . '/blocks/hubspot-form/block.php';
require get_template_directory() . '/blocks/megamenu-panel/block.php';


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
require get_template_directory() . '/inc/testimonials.php';
require get_template_directory() . '/inc/faq.php';


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


/**
 * Resolve the accent color for a category term by hopping through
 * its related Solution post (ACF relationship field).
 *
 * Falls back to the term's own tag_color field if no solution is linked,
 * so existing data continues to work during any transition period.
 */
function get_solution_color_for_term( int $term_id ): string {
	static $cache = [];
	if ( isset( $cache[ $term_id ] ) ) return $cache[ $term_id ];

	$solution = get_field( 'solution_relationship', 'category_' . $term_id );
	$post     = is_array( $solution ) ? ( $solution[0] ?? null ) : $solution;
	$color    = $post ? (string) get_field( 'accent_color', $post->ID ) : '';

	// Fallback: read tag_color directly off the term (remove once all terms have a linked solution).
	if ( ! $color ) {
		$color = (string) get_field( 'tag_color', 'category_' . $term_id );
	}

	$cache[ $term_id ] = $color;
	return $cache[ $term_id ];
}

/**
 * Inject per-term --solution CSS variable onto each <a> in
 * the core/post-terms block (category taxonomy only).
 *
 * Requires: ACF field "tag_color" registered on the category taxonomy.
 */
add_filter( 'render_block', function ( string $html, array $block, WP_Block $instance ): string {

	// Only touch post-terms blocks showing the category taxonomy.
	if ( 'core/post-terms' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	if ( 'category' !== ( $block['attrs']['term'] ?? '' ) ) {
		return $html;
	}

	// Resolve the post ID from block context (works inside Query Loop).
	$post_id = $instance->context['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return $html;
	}

	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $html;
	}

	foreach ( $terms as $term ) {
		// ACF stores term meta with the "category_" prefix on term IDs.
		$color = get_solution_color_for_term( $term->term_id );
		if ( ! $color ) {
			continue;
		}

		$color = esc_attr( sanitize_hex_color( $color ) );
		$slug  = preg_quote( $term->slug, '/' );

		// Match the <a> whose href contains this category's slug segment.
		// The slug always appears as /slug/ in WP category permalinks.
		$html = preg_replace(
			'/(<a\b)([^>]*href=["\'][^"\']*\/' . $slug . '\/["\'][^>]*)(>)/',
			'$1$2 style="--solution:' . $color . '"$3',
			$html
		);
	}

	return $html;
}, 10, 3 );

add_action( 'rest_api_init', function () {
	register_rest_field( 'category', 'tag_color', [
		'get_callback' => function ( $term ) {
			$color = get_solution_color_for_term( (int) $term['id'] );
			return $color ? sanitize_hex_color( $color ) : null;
		},
		'schema' => [
			'description' => 'Hex color for category tag.',
			'type'        => [ 'string', 'null' ],
			'context'     => [ 'view', 'embed' ],
		],
	] );
} );

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

// Renames "Posts" to "Blog" throughout the WordPress admin.
require get_template_directory() . '/inc/rename-posts-to-blog.php';

// Adds a "Patterns" item to the dashboard left menu with submenu links
// to synced patterns, theme patterns, and "Add New".
require get_template_directory() . '/inc/show-patterns-in-menu.php';

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