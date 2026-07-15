<?php
/**
 * Block: momentive/linked-products
 *
 * Renders a curated list of related products as logos linking to each product
 * page. The product list comes from an ACF relationship/post-object field
 * (`linked_products`) on the host post, so logo images and URLs are pulled live
 * from the Product CPT — change a product's logo once and every linked-products
 * instance updates.
 *
 * Deliberately generic (named "linked-products", not "case-study-products") so
 * it can be reused outside case studies — e.g. a solution page listing its
 * products. The heading is a block attribute (default "Solutions"), so the
 * label is defined in ONE place and can be changed globally without touching
 * post content. Mirrors the momentive/person convention: ACF post selection,
 * real <a> to the permalink, no over-restriction.
 *
 * Block-level fields (heading text, show/hide heading) are block.json
 * attributes. The product SELECTION lives in an ACF field group bound to this
 * block (field name: `linked_products`). See acf-groups.php.
 */

if ( ! function_exists( 'momentive_register_linked_products_block' ) ) {

	add_action( 'init', 'momentive_register_linked_products_block' );

	function momentive_register_linked_products_block(): void {
		register_block_type( __DIR__ );

		// Front-end styles — registered here, enqueued conditionally below.
		wp_register_style(
			'momentive-linked-products',
			get_template_directory_uri() . '/blocks/linked-products/linked-products.css',
			[],
			wp_get_theme()->get( 'Version' )
		);
	}

	// Conditional enqueue: only when the block is present (singular) — matches
	// the project's enqueue_block_assets + momentive_content_has_block pattern.
	add_action( 'enqueue_block_assets', function (): void {
		if ( is_admin() ) {
			return;
		}
		if ( momentive_content_has_block( 'momentive/linked-products' ) ) {
			wp_enqueue_style( 'momentive-linked-products' );
		}
	} );
}

/**
 * Render callback (ACF renderTemplate target).
 *
 * @param array  $block      Block settings and attributes.
 * @param string $content    Block inner content (unused).
 * @param bool   $is_preview True during AJAX editor preview.
 * @param int    $post_id    The post ID this block is rendering on.
 */

$heading      = get_field( 'heading' );          // ACF text field (see acf-linked-products.php)
$show_heading = get_field( 'show_heading' );
$heading      = ( $heading === null || $heading === false || $heading === '' ) ? 'Solutions' : $heading;
$show_heading = ( $show_heading === null ) ? true : (bool) $show_heading;

// Block-level override first; fall back to the post's products.
//
// IMPORTANT: use the $post_id that ACF passes into the renderTemplate, NOT
// get_the_ID(). On the front end inside an FSE template, blocks render outside
// the main query loop, so get_the_ID() does not reliably return the host post —
// which made the post-level fallback return nothing and the block render blank
// on the front end while still working in the editor preview (where ACF's
// preview context happens to make get_the_ID() resolve). ACF provides the
// correct host post ID as $post_id; ACF blocks in a query loop also expose it
// via $block['data'] context. Resolve defensively.
$host_id = 0;
if ( isset( $post_id ) && $post_id ) {
	$host_id = is_numeric( $post_id ) ? (int) $post_id : 0;
}
if ( ! $host_id ) {
	$host_id = get_the_ID() ?: 0;
}

$products = get_field( 'linked_products' ); // block instance field (override)
if ( empty( $products ) && $host_id ) {
	$products = get_field( 'linked_products', $host_id ); // post-level default
}

// In the editor preview with nothing selected yet, show a friendly placeholder.
if ( empty( $products ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<div class="linked-products is-placeholder"><p>Select one or more products to display.</p></div>';
	}
	return; // Front end: render nothing rather than an empty box.
}

// Normalize to an array of post IDs/objects (post-object can return single).
$products = is_array( $products ) ? $products : [ $products ];

$anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

echo '<div class="linked-products"' . $anchor . '>';

if ( $show_heading && $heading ) {
	echo '<h2 class="linked-products__heading has-large-font-size">' . esc_html( $heading ) . '</h2>';
}

echo '<ul class="linked-products__list">';

foreach ( $products as $product ) {
	$pid = is_object( $product ) ? (int) $product->ID : (int) $product;
	if ( ! $pid || get_post_type( $pid ) !== 'product' || get_post_status( $pid ) !== 'publish' ) {
		continue; // skip non-products / drafts / trashed
	}

	$permalink = get_permalink( $pid );
	$name      = get_the_title( $pid );

	// Prefer the unendorsed logo (matches the ECS sidebar). Fall back through
	// the variants, then to the product title text if no logo image is set.
	$logo_id = get_field( 'product_logo_unendorsed', $pid )
		?: get_field( 'product_logo_endorsed', $pid );

	echo '<li class="linked-products__item">';
	echo '<a href="' . esc_url( $permalink ) . '" class="linked-products__link">';

	if ( $logo_id ) {
		// ACF image field may return ID or array depending on return format.
		$logo_id  = is_array( $logo_id ) ? ( $logo_id['ID'] ?? 0 ) : (int) $logo_id;
		$img_html = wp_get_attachment_image(
			$logo_id,
			'large',
			false,
			[
				'class' => 'linked-products__logo',
				'alt'   => esc_attr( $name ),
				'loading' => 'lazy',
			]
		);
		echo $img_html ?: esc_html( $name );
	} else {
		echo '<span class="linked-products__name">' . esc_html( $name ) . '</span>';
	}

	echo '</a>';
	echo '</li>';
}

echo '</ul>';
echo '</div>';
