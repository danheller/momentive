<?php

/**
 * Custom Post Type: Products
 *
 * Mirrors the Solutions CPT setup where appropriate, with these differences:
 *   - Non-hierarchical (flat list, no parent/child)
 *   - URL slug: /products/
 *   - Categories shared with Solutions (children of the "Solutions" category)
 *   - Product Type taxonomy: "Active Product" / "Orphan Product"
 *   - ACF fields: accent_color, product_icon (parallel to solution equivalents)
 */


// ---------------------------------------------------------------------------
// Post type
// ---------------------------------------------------------------------------

add_action( 'init', 'momentive_products_setup' );

function momentive_products_setup(): void {

	$labels = [
		'name'               => _x( 'Products', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Product', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Products', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Product', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Product', 'momentive' ),
		'new_item'           => __( 'New Product', 'momentive' ),
		'edit_item'          => __( 'Edit Product', 'momentive' ),
		'view_item'          => __( 'View Product', 'momentive' ),
		'all_items'          => __( 'All Products', 'momentive' ),
		'search_items'       => __( 'Search Products', 'momentive' ),
		'not_found'          => __( 'No products found.', 'momentive' ),
		'not_found_in_trash' => __( 'No products found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-screenoptions',
		'menu_position'      => 30,
		'show_in_rest'       => true,
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
			// No 'page-attributes' — flat CPT, no ordering/parent UI needed.
		],
		'rewrite'            => [
			'slug'       => 'products',
			'with_front' => false,
		],
		'has_archive'        => 'products',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category', 'product_type' ],
		'template'           => [],   // Populated later once pages are built out.
		'template_lock'      => false,
	];

	register_post_type( 'product', $args );


	// ── Product Type taxonomy ──────────────────────────────────────────────
	//
	// Two terms mirror the old site's "product-type" taxonomy:
	//   Active Product  (nicename: active-product)
	//   Orphan Product (nicename: orphan-product)
	//
	// "Orphan" reflects products that exist but aren't actively marketed —
	// useful for filtering them out of front-end queries (e.g. the marquee)
	// while keeping their posts available for search and direct links.

	$type_labels = [
		'name'              => _x( 'Product Types', 'taxonomy general name', 'momentive' ),
		'singular_name'     => _x( 'Product Type', 'taxonomy singular name', 'momentive' ),
		'all_items'         => __( 'All Types', 'momentive' ),
		'edit_item'         => __( 'Edit Type', 'momentive' ),
		'add_new_item'      => __( 'Add New Type', 'momentive' ),
		'new_item_name'     => __( 'New Type Name', 'momentive' ),
		'menu_name'         => __( 'Product Types', 'momentive' ),
	];

	register_taxonomy( 'product_type', [ 'product' ], [
		'labels'            => $type_labels,
		'hierarchical'      => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'publicly_queryable' => false,  // No front-end archive needed.
		'public'            => false,
		'rewrite'           => false,
	] );


	// ── Seed Product Type terms if they don't exist yet ───────────────────
	// Runs on every init but wp_insert_term() is a no-op when the term
	// already exists, so this is safe to leave in permanently.

	if ( ! term_exists( 'Active Product', 'product_type' ) ) {
		wp_insert_term( 'Active Product', 'product_type', [ 'slug' => 'active-product' ] );
	}
	if ( ! term_exists( 'Orphan Product', 'product_type' ) ) {
		wp_insert_term( 'Orphan Product', 'product_type', [ 'slug' => 'orphan-product' ] );
	}
}


// ---------------------------------------------------------------------------
// Shared solution categories — scoped to children of "Solutions"
// ---------------------------------------------------------------------------
//
// Products and testimonials both use children of the built-in "Solutions"
// category. This filter restricts the category metabox / block panel on
// product posts to only show those children, keeping the UI uncluttered.

add_filter( 'acf/fields/taxonomy/query/name=product_category', function( array $args ): array {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );

// hide the default category panel

add_action( 'enqueue_block_editor_assets', function() {
	if ( get_post_type() !== 'product' ) return;

	wp_add_inline_script( 'wp-blocks', "
		wp.domReady( function() {
			wp.data.dispatch( 'core/edit-post' )
				.removeEditorPanel( 'taxonomy-panel-category' );
		} );
	" );
} );


// ---------------------------------------------------------------------------
// Accent color — product singular pages
// ---------------------------------------------------------------------------
//
// Injects --page-accent-color as a root-level custom property on single product pages,
// making it available to any block on the page without inline styles on every
// individual element. Parallels the Solutions equivalent in solutions.php.

add_action( 'wp_head', function() {
	if ( ! is_singular( 'product' ) ) return;
	$post_id = get_the_ID();

	/* Note: Product accent colors are divided into a tinted color used in the hero 
	 * background (page_accent_color) and a color used for the product icon (accent_color).
	 * It might be worth renaming these later. */
	$accent_color = get_field( 'page_accent_color', $post_id );
	$icon_color = get_field( 'accent_color', $post_id );

	$props = '';
	if ( $accent_color ) $props .= '--page-accent-color: ' . esc_attr( $accent_color ) . '; ';
	if ( $icon_color )   $props .= '--page-icon-color: '   . esc_attr( $icon_color )   . '; ';

	if ( $props ) {
		echo '<style>body { ' . $props . '}</style>';
	}
} );

// ---------------------------------------------------------------------------
// ACF: product_icon field — icon picker population + preview
// ---------------------------------------------------------------------------

add_action( 'acf/render_field/name=product_icon', function( array $field ): void {
	$slug = $field['value'];
	// Ignore legacy attachment IDs that may have been imported.
	if ( ! $slug || is_numeric( $slug ) ) return;

	echo '<div style="margin-top:8px; width:48px; height:48px;">';
	momentive_output_svg_symbols( [ $slug ] );
	echo '<svg style="width:100%;height:100%;"><use href="#icon-' . esc_attr( $slug ) . '"></use></svg>';
	echo '</div>';
} );

add_filter( 'acf/load_field/name=product_icon', function( array $field ): array {
	// Guard: only populate for product posts so we don't interfere with any
	// other post type that might happen to have a field named product_icon.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->post_type !== 'product' ) {
		return $field;
	}

	$field['choices'] = array_merge(
		[ '' => '— None —' ],
		momentive_get_available_icons()
	);
	return $field;
} );


// ---------------------------------------------------------------------------
// Admin column: show Product Type badge in the posts list
// ---------------------------------------------------------------------------

add_filter( 'manage_product_posts_columns', function( array $columns ): array {
	unset( $columns['taxonomy-product_type'] ); // remove WP's auto column
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['product_type'] = __( 'Type', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_product_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column !== 'product_type' ) return;

	$terms = get_the_terms( $post_id, 'product_type' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		echo '<span style="color:#999">—</span>';
		return;
	}

	foreach ( $terms as $term ) {
		$color = $term->slug === 'active-product' ? '#00a32a' : '#787c82';
		printf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:%s;color:#fff;">%s</span>',
			esc_attr( $color ),
			esc_html( $term->name )
		);
	}
}, 10, 2 );


// ---------------------------------------------------------------------------
// Product Marquee: only query Active Products
// ---------------------------------------------------------------------------
//
// The product-marquee render callback uses get_posts() with post_type=product.
// This filter adds a product_type term constraint to that query so Orphan
// products are excluded from the scrolling marquee automatically.
//
// The filter is keyed to the 'product-marquee' query context set by the block.
// If you ever need an unfiltered product list elsewhere, use a different
// 'suppress_filters' => true query or a different post_type context.

add_filter( 'momentive_product_marquee_query_args', function( array $args ): array {
	$args['tax_query'] = [
		[
			'taxonomy' => 'product_type',
			'field'    => 'slug',
			'terms'    => 'active-product',
		],
	];
	return $args;
} );


// ---------------------------------------------------------------------------
// For new 'product' posts, use "patterns/product-content.php" pattern. 
// ---------------------------------------------------------------------------


add_action( 'init', function() {
	$cpt = get_post_type_object( 'product' );
	if ( ! $cpt ) return;
	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/product-content' );
	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );
