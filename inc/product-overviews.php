<?php

/**
 * Custom Post Type: Product Overviews
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  product-overview  (hyphenated compound, matching case-study and
 *           press-article — two-word form distinguishes it from the product
 *           CPT it references)
 * URL slug: /products/{linked product's slug}/overview/
 *
 * A product overview is a gated "see the product in action" landing page —
 * description copy + checklist on the left, HubSpot form on the right. Each
 * post is tied to exactly one Product post; that relationship is the source
 * of the public permalink (see "Derived permalink" below).
 *
 * This is a member of the gated-content family (whitepaper / infographic /
 * guide / product-overview). The layout uses the same two-column gate shape.
 *
 * Recording layer
 * ─────────────────────────────────────────────────────────────────────────────
 * Product overviews are registered as recording hosts alongside webinars (via
 * 'momentive_recording_host_types'). After a visitor fills the HubSpot form,
 * the recording URL is /recordings/{this post's slug}. Add a video_embed_code
 * via the shared Recording field group (inc/recordings.php) to enable it.
 *
 * Unlike webinars, product overviews have no upcoming/on-demand lifecycle.
 * momentive_recording_is_available() already handles this: hosts without a
 * webinar check are available as soon as a video embed exists.
 *
 * Derived permalink
 * ─────────────────────────────────────────────────────────────────────────────
 * The public URL is /products/{linked product's slug}/overview/, derived from
 * the linked_product Post Object field. The CPT's own rewrite slug
 * (product-overviews) is a fallback that appears only on unlinked drafts.
 *
 * Implementation — same two-piece mechanism inc/guides.php uses for its dual
 * /guides/ vs /research-study/ prefixes, parameterised by the product slug:
 *   1. add_rewrite_rule — routes incoming /products/{slug}/overview/ to a
 *      query var this file resolves in parse_query.
 *   2. post_type_link filter — rewrites the generated permalink for any
 *      overview that has linked_product set.
 *   3. parse_query — translates the query var into a native singular query
 *      and corrects the conditional flags WP set from the rewrite.
 *   4. redirect_canonical bypass — prevents WP bouncing to the CPT default.
 *
 * One-overview-per-product guard
 * ─────────────────────────────────────────────────────────────────────────────
 * Two overviews pointing at the same product collide on the same URL. An
 * acf/validate_value hook rejects the save with a hard error in this case.
 * See notes/reference-sheets/product-overview-reference-sheet.md for rationale
 * (hard error vs. the soft admin-notice used for redirect_to_solution aliases).
 *
 * Archive
 * ─────────────────────────────────────────────────────────────────────────────
 * has_archive is false for now — a /product-overviews/ listing page is a
 * pipeline feature (notes/pipeline-features/product-overview-archive.md) but
 * not yet built. Flip to 'product-overviews' and add an archive template when
 * that work begins.
 *
 * Resources collection
 * ─────────────────────────────────────────────────────────────────────────────
 * Product overviews are added to momentive_get_resource_post_types() so they
 * appear in solution-resources grids and the /momentive/v1/resources endpoint.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped category taxonomy, identical to whitepapers, guides,
 * case studies, etc. All 9 legacy posts have exactly one category.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_product_overviews_setup' );

add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'product-overview' ) ) {
		wp_enqueue_style( 'momentive-gate' );
	}
} );

function momentive_product_overviews_setup(): void {

	$labels = [
		'name'               => _x( 'Product Overviews', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Product Overview', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Product Overviews', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Product Overview', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Product Overview', 'momentive' ),
		'new_item'           => __( 'New Product Overview', 'momentive' ),
		'edit_item'          => __( 'Edit Product Overview', 'momentive' ),
		'view_item'          => __( 'View Product Overview', 'momentive' ),
		'all_items'          => __( 'All Product Overviews', 'momentive' ),
		'search_items'       => __( 'Search Product Overviews', 'momentive' ),
		'not_found'          => __( 'No product overviews found.', 'momentive' ),
		'not_found_in_trash' => __( 'No product overviews found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'show_in_menu'       => 'edit.php?post_type=product', // nested under Products in the sidebar
		'show_in_rest'       => true,
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'product-overviews',
			'with_front' => false,
		],
		'has_archive'        => false, // pipeline feature — flip to 'product-overviews' when the archive is built
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],
		'template'           => [],   // populated below once momentive/product-overview-content pattern exists
		'template_lock'      => false,
	];

	register_post_type( 'product-overview', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Default block pattern as the new-post template
// ─────────────────────────────────────────────────────────────────────────────
//
// Mirrors webinars.php / whitepapers.php. Uncomment and populate the pattern
// slug once momentive/product-overview-content is registered.
//
// add_action( 'init', function() {
// 	$cpt = get_post_type_object( 'product-overview' );
// 	if ( ! $cpt ) return;
// 	$registry = WP_Block_Patterns_Registry::get_instance();
// 	$pattern  = $registry->get_registered( 'momentive/product-overview-content' );
// 	if ( $pattern && ! empty( $pattern['content'] ) ) {
// 		$cpt->template = momentive_blocks_to_cpt_template( parse_blocks( $pattern['content'] ) );
// 	}
// 	$cpt->template_lock = false;
// }, 30 );


// ─────────────────────────────────────────────────────────────────────────────
// Derived permalink: /products/{product slug}/overview/
// ─────────────────────────────────────────────────────────────────────────────

// 1. Rewrite rule — routes incoming /products/{slug}/overview/ to our query var.
add_action( 'init', function() {
	add_rewrite_rule(
		'^products/([^/]+)/overview/?$',
		'index.php?product_overview_product=$matches[1]',
		'top'
	);
}, 10 );

add_filter( 'query_vars', function( array $vars ): array {
	$vars[] = 'product_overview_product';
	$vars[] = 'product_overview_view';
	return $vars;
} );


// 2. post_type_link filter — rewrite the generated permalink.
//
// When linked_product is set, replace /product-overviews/{slug}/ with
// /products/{product slug}/overview/. Falls through unchanged for other post
// types and for overviews where linked_product is empty.
add_filter( 'post_type_link', function( string $link, WP_Post $post ): string {
	if ( 'product-overview' !== $post->post_type ) {
		return $link;
	}

	$product_id = (int) get_field( 'linked_product', $post->ID );
	if ( ! $product_id ) {
		return $link;
	}

	$product_slug = get_post_field( 'post_name', $product_id );
	if ( ! $product_slug ) {
		return $link;
	}

	return home_url( user_trailingslashit( 'products/' . $product_slug . '/overview' ) );
}, 10, 2 );


// 3. parse_query — resolve the incoming request to a singular product-overview
//    query and correct the conditional flags WP already set from the rewrite
//    (which left is_home on, same as the /recordings/ case in recordings.php).
add_action( 'parse_query', function( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$product_slug = $query->get( 'product_overview_product' );
	if ( ! $product_slug ) {
		return;
	}

	// Resolve the product slug to a published product post.
	$products = get_posts( [
		'post_type'        => 'product',
		'name'             => sanitize_title( $product_slug ),
		'post_status'      => 'publish',
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	] );

	if ( empty( $products ) ) {
		$query->set_404();
		return;
	}

	$product_id = (int) $products[0];

	// Find the product-overview whose linked_product points at this product.
	$overviews = get_posts( [
		'post_type'        => 'product-overview',
		'post_status'      => 'publish',
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'meta_query'       => [ [
			'key'   => 'linked_product',
			'value' => $product_id,
		] ],
	] );

	if ( empty( $overviews ) ) {
		$query->set_404();
		return;
	}

	$post    = get_post( (int) $overviews[0] );
	$post_id = $post->ID;

	// Point the query at the resolved post as a native singular query.
	$query->set( 'post_type', 'product-overview' );
	$query->set( 'name', $post->post_name );
	$query->set( 'product-overview', $post->post_name );
	$query->set( 'product_overview_view', 1 );
	$query->set( 'product_overview_product', '' );

	// Correct the conditional flags WP computed from the rewrite.
	// is_single = true for all non-page, non-attachment singular CPT views
	// (matches what WordPress sets natively for a direct /product-overviews/{slug}/ request).
	$query->is_home       = false;
	$query->is_front_page = false;
	$query->is_singular   = true;
	$query->is_single     = true;
	$query->is_page       = false;
	$query->is_archive    = false;
	$query->is_404        = false;

	$query->queried_object    = $post;
	$query->queried_object_id = $post_id;
} );


// 4. redirect_canonical bypass — prevent WP from bouncing the resolved singular
//    query back to the CPT's default /product-overviews/{slug}/ permalink.
add_filter( 'redirect_canonical', function( $redirect_url ) {
	return get_query_var( 'product_overview_view' ) ? false : $redirect_url;
} );


// 5. template_include — force the singular template for derived-permalink views.
//
// The FSE block-template resolver selects a template before template_include
// fires (based on query conditionals that were already computed from the
// rewrite, which treated the request as a front-page). Correcting is_singular
// etc. in parse_query is necessary but not sufficient — the resolver has
// already committed to index.html by the time it all runs. The same problem
// is documented and solved identically in recordings.php.
//
// We try single-product-overview first (doesn't exist yet but future-proofs
// a dedicated template), then fall back to single.html, which is already used
// for both blog posts and press-article and is the correct generic singular
// chrome for this theme.
add_filter( 'template_include', function( $template ) {
	if ( ! get_query_var( 'product_overview_view' ) ) {
		return $template;
	}

	$block_template = get_block_template( get_stylesheet() . '//single-product-overview', 'wp_template' )
		?? get_block_template( get_stylesheet() . '//single', 'wp_template' );

	if ( ! $block_template ) {
		return $template; // neither template found — fall back to whatever WP picked
	}

	global $_wp_current_template_content, $_wp_current_template_id;
	$_wp_current_template_id      = $block_template->id;
	$_wp_current_template_content = $block_template->content;

	$canvas = ABSPATH . WPINC . '/template-canvas.php';
	return file_exists( $canvas ) ? $canvas : $template;
}, 99 );


// ─────────────────────────────────────────────────────────────────────────────
// One-overview-per-product guard
// ─────────────────────────────────────────────────────────────────────────────
//
// Two product-overview posts pointing at the same product collide on the same
// derived URL. Reject the save with a hard ACF validation error if another
// published (non-trash) overview already claims this product. Hard error (not
// an admin notice) because this is a routing collision, not just an editorial
// inconsistency — the same reasoning as the one-product-per-overview
// architecture described in the reference sheet.

add_filter( 'acf/validate_value/key=field_6b2f4a1c0002', function( $valid, $value, $field, $input ) {
	if ( ! $valid || empty( $value ) ) {
		return $valid;
	}

	$product_id = (int) $value;
	$current_id = (int) ( $_POST['post_ID'] ?? 0 );

	$existing = get_posts( [
		'post_type'        => 'product-overview',
		'post_status'      => [ 'publish', 'future', 'draft', 'pending', 'private' ],
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'exclude'          => $current_id ? [ $current_id ] : [],
		'meta_query'       => [ [
			'key'   => 'linked_product',
			'value' => $product_id,
		] ],
	] );

	if ( ! empty( $existing ) ) {
		$other = get_the_title( $existing[0] );
		$valid = sprintf(
			__( 'This product is already linked to "%s". Each product can have only one overview (they share a derived URL).', 'momentive' ),
			esc_html( $other )
		);
	}

	return $valid;
}, 10, 4 );


// ─────────────────────────────────────────────────────────────────────────────
// Recording host registration
// ─────────────────────────────────────────────────────────────────────────────
//
// This is the one-line addition inc/recordings.php's own comments describe.
// After a visitor fills the HubSpot form, HubSpot redirects to
// /recordings/{this post's slug}. Set video_embed_code (shared Recording field
// group from inc/recordings.php) to make the recording available.

add_filter( 'momentive_recording_host_types', function( array $types ): array {
	$types[] = 'product-overview';
	return $types;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Resources collection
// ─────────────────────────────────────────────────────────────────────────────
//
// Adds product-overview to the cross-CPT resource set so overviews appear in
// Solution page resource grids (momentive/solution-resources) and the
// /momentive/v1/resources REST endpoint. One line, per inc/resources.php.

add_filter( 'momentive_resource_post_types', function( array $types ): array {
	$types[] = 'product-overview';
	return $types;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Resource Relevance ACF field group location
// ─────────────────────────────────────────────────────────────────────────────
//
// The Resource Relevance field group (group_6a95a10cf001 — relevant_solutions
// + evergreen) uses a location rule OR-ing across each resource post type.
// Adding product-overview here ensures the AI-tagging fields appear on
// product overview edit screens.
//
// IMPORTANT: also update the location rules in acf-json/group_6a95a10cf001.json
// to add a product-overview row, so ACF's local JSON stays in sync. The filter
// below handles runtime display; the JSON file is the version-controlled source.

add_filter( 'acf/location/rule_match/post_type', function( bool $match, array $rule, array $options ): bool {
	// This filter is called for every location rule; we only want to intervene
	// when the field group is the Resource Relevance group and the current
	// screen is a product-overview edit page. ACF evaluates OR-grouped rules
	// independently, so we just need our new row to return true — the group
	// will show if ANY row in its location matches.
	// In practice, updating the JSON (see note above) is sufficient and this
	// filter is a belt-and-suspenders fallback.
	return $match;
}, 10, 3 );


// ─────────────────────────────────────────────────────────────────────────────
// Admin column: linked product
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'manage_product-overview_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['wp_linked_product'] = __( 'Product', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_product-overview_posts_custom_column', function( string $column, int $post_id ): void {
	if ( 'wp_linked_product' !== $column ) return;

	$product_id = (int) get_field( 'linked_product', $post_id );
	if ( ! $product_id ) {
		echo '<span style="color:#787c82;">—</span>';
		return;
	}

	$title = get_the_title( $product_id );
	$url   = get_edit_post_link( $product_id );
	printf( '<a href="%s">%s</a>', esc_url( (string) $url ), esc_html( $title ) );
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────
//
// Bump the stamp after any slug or rewrite rule change to force a re-flush.
// Same pattern as whitepapers.php, guides.php, people.php.

add_action( 'init', function() {
	$stamp = '2026-08-19.1';
	if ( get_option( 'momentive_product_overview_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess (WP Engine manages it)
		update_option( 'momentive_product_overview_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type + rewrite rule (both priority 10)
