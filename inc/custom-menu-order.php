<?php
/**
 * Reorganize the dashboard menu to make the order more logical
 */

/**
 * Move WP Activity Log to the end
 */

add_action( 'admin_menu', function() {
    global $menu;
    foreach ( $menu as $position => $item ) {
        if ( isset( $item[2] ) && $item[2] === 'wsal-auditlog' ) {
            $menu[999] = $item;
            unset( $menu[ $position ] );
            break;
        }
    }
}, 9999 );


// ─────────────────────────────────────────────────────────────────────────────
// Resources umbrella menu
//
// Moves resource-type CPTs (case-study, webinar, whitepaper, infographic,
// guide, video, event, product-overview) out of the top-level sidebar and
// nests them under a single "Resources" parent, each with their own
// "All [Type]" and "Add New" sublinks.
//
// Product Overviews is also added as a submenu under the Products CPT so it
// stays reachable from both places.
//
// Blog (edit.php) and Press Articles (press-article) are intentionally left
// at the top level — they're primary editorial workflows, not archive content.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function() {

	// Top-level "Resources" page. Clicking it redirects to Case Studies so the
	// URL is always meaningful; the callback is never rendered directly.
	add_menu_page(
		__( 'Resources', 'momentive' ),
		__( 'Resources', 'momentive' ),
		'edit_posts',
		'momentive-resources',
		function() {
			wp_safe_redirect( admin_url( 'edit.php?post_type=case-study' ) );
			exit;
		},
		'dashicons-archive',
		27
	);

	// WordPress auto-adds the parent as its own first submenu item — remove it
	// so we control exactly what appears in the dropdown.
	remove_submenu_page( 'momentive-resources', 'momentive-resources' );

	// Resource CPTs to nest, in display order.
	// Each entry: post_type => [ plural label, singular label ]
	$resources = [
		'case-study'       => [ 'Case Studies',      'Case Study' ],
		'webinar'          => [ 'Webinars',           'Webinar' ],
		'whitepaper'       => [ 'Whitepapers',        'Whitepaper' ],
		'infographic'      => [ 'Infographics',       'Infographic' ],
		'guide'            => [ 'Guides',             'Guide' ],
		'interactive-tool' => [ 'Interactive Tools',  'Interactive Tool' ],
		'toolkit'          => [ 'Toolkits',           'Toolkit' ],
		'event'            => [ 'Events',             'Event' ],
		'video'            => [ 'Videos',             'Video' ],
		'product-overview' => [ 'Product Overviews',  'Product Overview' ],
	];

	foreach ( $resources as $post_type => [ $plural ] ) {
		add_submenu_page(
			'momentive-resources',
			$plural,
			$plural,
			'edit_posts',
			"edit.php?post_type={$post_type}"
		);
		// Remove the original top-level menu item.
		remove_menu_page( "edit.php?post_type={$post_type}" );
	}

	// Product Overviews also appears under Products so it is reachable from
	// both locations without duplicating the actual posts.
	// Remove the auto-generated "All Product Overviews" and "Add New Product
	// Overview" submenus (added by show_in_menu in product-overviews.php)
	// before adding our single, cleanly-labelled entry.
	remove_submenu_page( 'edit.php?post_type=product', 'edit.php?post_type=product-overview' );
	remove_submenu_page( 'edit.php?post_type=product', 'post-new.php?post_type=product-overview' );
	add_submenu_page(
		'edit.php?post_type=product',
		__( 'Product Overviews', 'momentive' ),
		__( 'Product Overviews', 'momentive' ),
		'edit_posts',
		'edit.php?post_type=product-overview'
	);

}, 99 );


function momentive_custom_menu_order( $menu_ord ) {
	if ( ! $menu_ord ) return true;

	return array(
		'index.php',                          // Dashboard
		'wpengine-common',                    // WP Engine
		'separator1',                         // — separator —
		'edit.php',                           // Blog
		'edit.php?post_type=press-article',   // Newsroom
		'edit.php?post_type=page',            // Pages
		'edit.php?post_type=product',         // Products
		'edit.php?post_type=solutions',       // Solutions
		'momentive-resources',                // Resources (umbrella)
		'separator2',                         // — separator —
		'edit.php?post_type=people',          // People
		'edit.php?post_type=testimonial',     // Testimonials
		'edit.php?post_type=faq',             // FAQ
		'separator3',                         // — separator —
		'edit.php?post_type=wp_block',        // Patterns
		'upload.php',                         // Media
	);
}
add_filter( 'custom_menu_order', 'momentive_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'momentive_custom_menu_order', 10, 1 );

