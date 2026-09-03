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
 * 7.0  Developer Experience (required from /inc/)
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

	$markers = [ 'autoslider', 'solutions-slider', 'testimonials-slider', 'news-slider', 'draggable-image-slider' ];
	foreach ( $markers as $marker ) {
		if ( false !== strpos( $classes, $marker ) ) {
			wp_enqueue_script( 'sliders' );
			$enqueued = true;
			break;
		}
	}

	return $content;
}, 10, 2 );

// top labels for preview

add_filter( 'render_block', function( $content, $block ) {
	if (
		$block['blockName'] === 'core/query-title'
		&& ! empty( $block['attrs']['className'] )
		&& str_contains( $block['attrs']['className'], 'top-label' )
	) {
		$content = preg_replace( '/^<h1([^>]*)>/', '<p$1>', $content );
		$content = preg_replace( '/<\/h1>$/', '</p>', $content );

		// Guides & Research: swap the generic post-type label for a
		// guide_type-driven one ("Guides & Research" / "Research Study" /
		// "Research Study Preview"), matching the legacy site's per-subtype
		// top label. See momentive_guide_type_front_label() in inc/guides.php
		// for why this overrides here rather than using a dedicated block.
		if ( ( is_singular( 'guide' ) || is_post_type_archive( 'guide' ) ) && function_exists( 'momentive_guide_type_front_label' ) ) {
			$label   = momentive_guide_type_front_label( (string) get_field( 'guide_type', get_the_ID() ) );
			$content = preg_replace( '/(<p[^>]*>).*?(<\/p>\s*)$/s', '$1' . esc_html( $label ) . '$2', $content );
		}
	}
	return $content;
}, 10, 2 );

// Defer non-critical, below-the-fold stylesheets (testimonial cards, solution
// sliders, gated whitepaper layout) via preload + swap-on-load. See the file
// for the handle list and why this rewrites style_loader_tag instead of a
// hand-rolled wp_head() output.
require get_template_directory() . '/inc/defer-styles.php';

// Auto-discovered, per-page CSS for one-off Elementor-relic styling that
// doesn't belong in the global stylesheet. See the file for the convention.
require get_template_directory() . '/inc/page-styles.php';

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
			'boxed'           => __( 'Boxed',    'momentive' ), // border all cards
			'outline'         => __( 'Outline',  'momentive' ), // bordered wrapper
		],

		'core/column' => [
			'outline'         => __( 'Outline',  'momentive' ), // bordered card
		],

		'core/group' => [
			'bg-dots'              => __( 'Dots Background',       'momentive' ),
			'bg-rings'             => __( 'Rings Background',      'momentive' ),
			'bg-dark'              => __( 'Dark Background',       'momentive' ),
			'bg-navy'              => __( 'Navy Background',       'momentive' ),
			'bg-light'             => __( 'Light Background',      'momentive' ),
			'bg-gradient'          => __( 'Gradient Background',   'momentive' ),
			'bg-ellipse'           => __( 'Ellipse',               'momentive' ),
			'ellipse-bottom'       => __( 'Ellipse Bottom',        'momentive' ),
			'ellipse-top'          => __( 'Ellipse Top',           'momentive' ),
			'seafoam-wash'         => __( 'Seafoam Wash',          'momentive' ),
			'motion-blur'          => __( 'Motion Blur',           'momentive' ),
			// Two blurred glow blobs (top-left/top-right) — CSS replacement
			// for the MIQ-Hero-Lights.webp background image on dark-mode
			// Solution pages (MomentiveIQ family). Styled in
			// solutions-dark.scss, scoped under body.solution-dark-mode —
			// applying this class outside a dark-mode page currently does
			// nothing, since no light-mode version of this treatment exists.
			'bg-glow-lights'       => __( 'Glow Lights (dark mode)', 'momentive' ),
		],

		'core/list' => [
			'no-disc'              => __( 'No Disc',               'momentive' ),
			'column-checks'        => __( 'Orange Checks',         'momentive' ),
			'circle-checks'        => __( 'Circle Checks',         'momentive' ),
			'simple-checks'        => __( 'Simple Checks',         'momentive' ),
			'checkboxes'           => __( 'Checkboxes',            'momentive' ),
		],

		'core/media-text' => [
			'stacked'   => __( 'Stacked',         'momentive' ),
		],

		'core/paragraph' => [
			'eyebrow'   => __( 'Eyebrow',         'momentive' ),
			'uppercase' => __( 'Uppercase Label', 'momentive' ),
		],

		'core/table' => [
			'shaded'    => __( 'Shaded',         'momentive' ),
		],

		'core/quote' => [
			'shadow-light' => __( 'Shadow',          'momentive' ),
			'shadow-solid' => __( 'Solid Shadow',    'momentive' ),
			'quote'        => __( 'Large Pull Quote', 'momentive' ),
		],

		'core/heading' => [
			'eyebrow'          => __( 'Eyebrow',         'momentive' ),
			'has-swoop'        => __( 'Swoop Underline', 'momentive' ),
			// Gradient text-clip headline — dark-mode Solution pages only so
			// far. Styled in solutions-dark.scss, scoped under
			// body.solution-dark-mode, same as bg-glow-lights above.
			'gradient-heading' => __( 'Gradient Heading (dark mode)', 'momentive' ),
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

		// Hides the sidebar and truncates the grid to ~4 rows (12 cards at 3-col).
		// Used on the /solutions/integrations/ page where no filters are shown.
		'momentive/integration-list' => [
			'truncated' => __( 'Truncated (no filters)', 'momentive' ),
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
require get_template_directory() . '/blocks/stat-columns/block.php';
require get_template_directory() . '/blocks/testimonial/block.php';
require get_template_directory() . '/blocks/accordion/block.php';
require get_template_directory() . '/blocks/hubspot-form/block.php';
require get_template_directory() . '/blocks/megamenu-panel/block.php';
require get_template_directory() . '/blocks/solution-slide/block.php';
require get_template_directory() . '/blocks/product-marquee/block.php';
require get_template_directory() . '/blocks/product-solution-tabs/block.php';
require get_template_directory() . '/blocks/back-link/block.php';
require get_template_directory() . '/blocks/webinar-cta/block.php';
require get_template_directory() . '/blocks/webinar-form-heading/block.php';
require get_template_directory() . '/blocks/webinar-presenters/block.php';
require get_template_directory() . '/blocks/webinar-schedule/block.php';
require get_template_directory() . '/blocks/webinar-status/block.php';
require get_template_directory() . '/blocks/recording/block.php';
require get_template_directory() . '/blocks/person/block.php';
require get_template_directory() . '/blocks/person-metadata/block.php';
require get_template_directory() . '/blocks/linked-products/block.php';
require get_template_directory() . '/blocks/icon-list/block.php';
require get_template_directory() . '/blocks/solution-resources/block.php';
require get_template_directory() . '/blocks/fundraiser-list/block.php';
require get_template_directory() . '/blocks/previous-studies/block.php';
require get_template_directory() . '/blocks/wistia-popover/block.php';
require get_template_directory() . '/blocks/reviews/block.php';
require get_template_directory() . '/blocks/integration-list/block.php';
require get_template_directory() . '/blocks/client-marquee/block.php';


/*==============================================================================
  4.0 - Post Types & Taxonomies
  Each post type is registered and configured in its own file under /inc/.
==============================================================================*/

require get_template_directory() . '/inc/icons.php';
require get_template_directory() . '/inc/solutions.php';
require get_template_directory() . '/inc/blog-and-newsroom.php';
require get_template_directory() . '/inc/people.php';
require get_template_directory() . '/inc/testimonials.php';
require get_template_directory() . '/inc/faq.php';
require get_template_directory() . '/inc/products.php';
require get_template_directory() . '/inc/webinars.php';
require get_template_directory() . '/inc/recordings.php'; // not a post type, but a passthrough to what were formerly "assets"
require get_template_directory() . '/inc/case-studies.php';
require get_template_directory() . '/inc/whitepapers.php';
require get_template_directory() . '/inc/videos.php';
require get_template_directory() . '/inc/infographics.php';
require get_template_directory() . '/inc/events.php';
require get_template_directory() . '/inc/interactive-tools.php';
require get_template_directory() . '/inc/toolkits.php';
require get_template_directory() . '/inc/guides.php';
require get_template_directory() . '/inc/integrations.php';
require get_template_directory() . '/inc/fundraisers.php';
require get_template_directory() . '/inc/product-overviews.php';
require get_template_directory() . '/inc/award-recipients.php';
require get_template_directory() . '/inc/who-we-serve.php';
require get_template_directory() . '/inc/clients.php';
require get_template_directory() . '/inc/resources.php'; // cross-CPT "Resources" query layer + REST endpoint
require get_template_directory() . '/inc/archive-visibility.php'; // hide_from_archives / hide_from_resource_center ACF toggles
require get_template_directory() . '/inc/resource-relevance.php'; // AI-assisted per-child-Solution relevance tagging
require get_template_directory() . '/inc/default-card-image.php'; // fallback image for posts with no featured image

/*==============================================================================
  5.0 - Query & Content Filters
==============================================================================*/

// Hide blank post excerpts rather than falling back to the full post content.
// This keeps archive cards and story cards clean when no excerpt is set.
add_filter( 'get_the_excerpt', function ( $excerpt, $post ) {
	if ( empty( $post->post_excerpt ) ) return '';
	return $excerpt;
}, 10, 2 );


/**
 * Whether a Query Loop block instance carries a given CSS class in its
 * Advanced-panel className. Shared by the query_loop_block_query_vars
 * filters below so each one stays a single `if`, not a repeated strpos().
 */
function momentive_query_block_has_class( WP_Block $block, string $needle ): bool {
	$class = $block->parsed_block['attrs']['className'] ?? '';
	return false !== strpos( $class, $needle );
}

// Query Loop blocks with the class `has-featured-images-only` will only
// show posts that have a featured image set. Add this class in the block
// editor's Advanced panel to use this behavior on any Query Loop.
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	if ( momentive_query_block_has_class( $block, 'has-featured-images-only' ) ) {
		$meta_query   = $query['meta_query'] ?? [];
		$meta_query[] = [
			'key'     => '_thumbnail_id',
			'compare' => 'EXISTS',
		];
		$query['meta_query'] = $meta_query;
	}
	return $query;
}, 10, 2 );

/* When a query template has the "order-by-modified" class, adjust the order accordingly.
 * Note: make sure the class is added to the template block inside the query, not to the
 * query itself.
 */
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	if ( momentive_query_block_has_class( $block, 'order-by-modified' ) ) {
		$query['orderby'] = 'modified';
		$query['order']   = $query['order'] ?? 'DESC';
	}
	return $query;
}, 10, 2 );

// Query Loop blocks with the class `upcoming-webinars-only` restrict the
// webinar query to future events only (webinar_date >= today, sorted soonest
// first). The webinar_date ACF field is stored as Ymd (e.g. 20261022), so
// lexicographic string comparison is correct — no NUMERIC cast needed.
add_filter( 'query_loop_block_query_vars', function ( array $query, WP_Block $block ): array {
	if ( ! momentive_query_block_has_class( $block, 'upcoming-webinars-only' ) ) {
		return $query;
	}
	$today        = date( 'Ymd' );
	$meta_query   = $query['meta_query'] ?? [];
	$meta_query[] = [
		'key'     => 'webinar_date',
		'value'   => $today,
		'compare' => '>=',
	];
	$query['meta_query'] = $meta_query;
	// Sort soonest-first so the grid reads like a calendar.
	$query['meta_key'] = 'webinar_date';
	$query['orderby']  = 'meta_value';
	$query['order']    = 'ASC';
	return $query;
}, 10, 2 );

// Query Loop blocks with the class `siblings` show the current page's
// siblings (children of the same parent) instead of depending on a 
// manually-applied term filter.
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	if ( ! momentive_query_block_has_class( $block, 'siblings' ) ) {
		return $query;
	}
	// get_queried_object_id(), not get_the_ID() — more reliable here since
	// this filter can run outside the main loop context where get_the_ID()
	// is unreliable (the same FSE gotcha documented in CLAUDE.md for ACF
	// render templates and the solutions-sibling-slider variant of this
	// pattern).
	$current_id = get_queried_object_id();
	if ( ! $current_id ) {
		return $query;
	}
	$parent_id = wp_get_post_parent_id( $current_id ) ?: $current_id;

	$query['post_parent']  = $parent_id;
	$query['post__not_in'] = [ $current_id ];
	return $query;
}, 10, 2 );

// Query Loop blocks with the class `children` show the current page's
// children.
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	if ( ! momentive_query_block_has_class( $block, 'children' ) ) {
		return $query;
	}
	$current_id = get_the_ID();
	if ( ! $current_id ) {
		return $query;
	}
	$query['post_parent']  = $current_id;
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
	if ( ! momentive_announcement_bar_is_enabled() ) return;
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
  7.0 - Developer Experience
==============================================================================*/

// "Edit Header" and "Edit Footer" hover buttons visible to logged-in editors.
require get_template_directory() . '/inc/announcement-bar-settings.php';
require get_template_directory() . '/inc/header-footer-edit-buttons.php';

// Landing page: simplified header / footer swap via ACF fields.
require get_template_directory() . '/inc/landing-page.php';

// Customize the dashboard sidebar menu order.
require get_template_directory() . '/inc/custom-menu-order.php';

// Pattern setup
require get_template_directory() . '/inc/patterns.php';

// Check for a block recursively within content (including within patterns)
require get_template_directory() . '/inc/check-content-for-block.php';

// Normalize stray &nbsp; inside is-style-has-swoop headings at save time
// (prevents pasted-in nbsp from breaking the swoop underline's word wrap).
require get_template_directory() . '/inc/swoop-heading-cleanup.php';
require get_template_directory() . '/inc/bg-rings-animation.php';

// Removes all comment-related UI, menus, and dashboard widgets.
require get_template_directory() . '/inc/disable-comments.php';

// Admin list table customizations (e.g. Last Modified column).
require get_template_directory() . '/inc/admin-columns.php';

// Dashboard widget showing rebuilt vs. empty post counts across all CPTs.
require get_template_directory() . '/inc/rebuild-progress.php';



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