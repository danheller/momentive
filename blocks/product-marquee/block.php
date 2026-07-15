<?php
/**
 * Product Marquee block — registration and render callback.
 *
 * Queries all published Products, shuffles them, splits them into two rows,
 * and outputs the markup that view.js promotes into two Splide autoScroll
 * instances (one scrolling left, one right).
 *
 * Icons are rendered via the theme's SVG sprite system. Each used icon slug
 * is registered with momentive_use_icon() so the footer hook outputs the
 * correct <symbol> elements without a separate call here.
 *
 * Accent colors are scoped as --product on each card wrapper, with
 * color-mix() derivatives computed in style.css.
 */

add_action( 'init', 'momentive_register_product_marquee_block' );

function momentive_register_product_marquee_block(): void {
	wp_register_script(
		'momentive-product-marquee-view',
		get_stylesheet_directory_uri() . '/blocks/product-marquee/view.js',
		[ 'sliders' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type( __DIR__ . '/block.json', [
		'render_callback' => 'momentive_render_product_marquee',
		'view_script'     => 'momentive-product-marquee-view',
	] );
}

function momentive_render_product_marquee( array $attributes ): string {

	// ── Query all published products ─────────────────────────────────────────

	$query_args = apply_filters( 'momentive_product_marquee_query_args', [
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	] );

	$products = get_posts( $query_args );

	if ( empty( $products ) ) {
		return '';
	}

	// ── Shuffle and split ────────────────────────────────────────────────────

	shuffle( $products );

	$mid    = (int) ceil( count( $products ) / 2 );
	$row_1  = array_slice( $products, 0, $mid );
	$row_2  = array_slice( $products, $mid );

	// ── Build card HTML ──────────────────────────────────────────────────────

	/**
	 * Render a single product card.
	 *
	 * Prefers the product_logo_unendorsed image field (the full horizontal
	 * wordmark, matching the live site). Falls back to icon + title text
	 * if no logo image has been uploaded yet, so in-progress products still
	 * render something usable in the marquee.
	 *
	 * Registers the icon slug for the footer sprite (fallback path only)
	 * and returns the <li> HTML.
	 */
	$render_card = static function ( WP_Post $product ): string {
		$title        = get_the_title( $product );
		$permalink    = get_permalink( $product );
		$logo         = get_field( 'product_logo_unendorsed', $product->ID );
		$icon_slug    = get_field( 'product_icon', $product->ID );
		$accent_color = get_field( 'accent_color', $product->ID );

		// Scope the accent color as a CSS custom property on the card.
		$style_attr = '';
		if ( $accent_color ) {
			$style_attr = ' style="--product:' . esc_attr( sanitize_hex_color( $accent_color ) ) . '"';
		}

		// ── Preferred: logo image ───────────────────────────────────────────
		if ( $logo && is_array( $logo ) && ! empty( $logo['url'] ) ) {
			$alt = ! empty( $logo['alt'] ) ? $logo['alt'] : $title;

			return sprintf(
				'<li class="splide__slide product-card product-card--logo"%s>' .
				'<a class="product-card__link" href="%s">' .
				'<img class="product-card__logo" src="%s" alt="%s" loading="lazy" />' .
				'</a>' .
				'</li>',
				$style_attr,
				esc_url( $permalink ),
				esc_url( $logo['url'] ),
				esc_attr( $alt )
			);
		}

		// ── Fallback: icon + title text ─────────────────────────────────────
		$icon_html = '';
		if ( $icon_slug ) {
			momentive_use_icon( $icon_slug );
			$icon_html = sprintf(
				'<span class="product-card__icon" aria-hidden="true">' .
				'<svg focusable="false"><use href="#icon-%s"></use></svg>' .
				'</span>',
				esc_attr( $icon_slug )
			);
		}

		return sprintf(
			'<li class="splide__slide product-card"%s>' .
			'<a class="product-card__link" href="%s">' .
			'%s' .
			'<span class="product-card__title">%s</span>' .
			'</a>' .
			'</li>',
			$style_attr,
			esc_url( $permalink ),
			$icon_html,
			esc_html( $title )
		);
	};

	$cards_row_1 = implode( '', array_map( $render_card, $row_1 ) );
	$cards_row_2 = implode( '', array_map( $render_card, $row_2 ) );

	// ── Wrapper output ───────────────────────────────────────────────────────

	$wrapper_attributes = get_block_wrapper_attributes( [
		'class' => 'product-marquee',
	] );

	return sprintf(
		'<div %s>
			<div class="product-marquee__row product-marquee__row--left splide" aria-label="%s">
				<div class="splide__track">
					<ul class="splide__list">%s</ul>
				</div>
			</div>
			<div class="product-marquee__row product-marquee__row--right splide" aria-label="%s">
				<div class="splide__track">
					<ul class="splide__list">%s</ul>
				</div>
			</div>
		</div>',
		$wrapper_attributes,
		esc_attr__( 'Products, row 1', 'momentive' ),
		$cards_row_1,
		esc_attr__( 'Products, row 2', 'momentive' ),
		$cards_row_2
	);
}
