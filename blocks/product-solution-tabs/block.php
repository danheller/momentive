<?php
/**
 * Product Solution Tabs block — register and render.
 *
 * Renders a row of tab buttons — one per Solution that has at least one
 * linked category — and, for each Solution, a panel of Product cards.
 * The solution list is derived automatically (get_solutions_with_products(),
 * functions.php) rather than curated per-block-instance: any Solution whose
 * linked category has products gets a tab.
 *
 * Products for a given Solution are found via get_terms_for_solution()
 * (functions.php) — there's no direct Product → Solution field; the path is
 * Solution → category term(s) → Products tagged with that term via the
 * product_category taxonomy field.
 *
 * Tabs/panels are toggled client-side by tabs.js, which also syncs the
 * active tab to the URL hash so tabs are deep-linkable and shareable.
 */

add_action( 'init', function () {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( [
		'name'            => 'product-solution-tabs',
		'title'           => 'Product Solution Tabs',
		'description'     => 'Tabbed grid of products grouped by Solution.',
		'render_callback' => 'momentive_render_product_solution_tabs',
		'category'        => 'theme',
		'icon'            => 'screenoptions',
		'keywords'        => [ 'product', 'solution', 'tabs', 'grid' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => [ 'wide', 'full' ],
			'mode'  => false,
			'jsx'   => false,
		],
	] );
} );


/**
 * Enqueue the frontend JS/CSS only on pages that actually use this block.
 *
 * momentive_content_has_block() only inspects a singular post's
 * post_content, so it always returns false on archive templates (there's
 * no single post's content to check there — the block lives in the FSE
 * archive template itself). This block is used on the Products archive,
 * so we check for that case explicitly alongside the normal singular check.
 */
add_action( 'enqueue_block_assets', function () {
	$on_singular_with_block = momentive_content_has_block( 'acf/product-solution-tabs' );
	$on_products_archive    = is_post_type_archive( 'product' );

	if ( ! $on_singular_with_block && ! $on_products_archive ) {
		return;
	}

	wp_enqueue_style(
		'momentive-product-solution-tabs',
		get_template_directory_uri() . '/blocks/product-solution-tabs/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'momentive-product-solution-tabs',
		get_template_directory_uri() . '/blocks/product-solution-tabs/tabs.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);
} );


/**
 * Render callback.
 */
function momentive_render_product_solution_tabs( array $block, string $content = '', bool $is_preview = false ): void {

	$solution_ids = get_solutions_with_products();

	if ( empty( $solution_ids ) ) {
		if ( $is_preview ) {
			echo '<p style="padding:2rem;text-align:center;color:#888;">No Solutions are currently linked to a category — set a category\'s "Related Solution" field to a Solution post to have it appear here.</p>';
		}
		return;
	}

	// Sort solutions by solution_order (ACF number field on the Solution post).
	// Unset/blank order values sort last, by falling back to a large number
	// rather than 0 — otherwise every un-ordered solution would jump to the front.
	usort( $solution_ids, function ( $a, $b ) {
		$order_a = get_field( 'solution_order', $a );
		$order_b = get_field( 'solution_order', $b );
		$order_a = ( $order_a === '' || $order_a === null ) ? PHP_INT_MAX : (int) $order_a;
		$order_b = ( $order_b === '' || $order_b === null ) ? PHP_INT_MAX : (int) $order_b;
		return $order_a <=> $order_b;
	} );

	// Build one record per tab up front, resolving each solution's linked
	// category once. The tab's visible label and URL slug both come from the
	// category (since on the live site, category names are what's shown —
	// they sometimes differ from the Solution post's own title) while order,
	// icon, and accent color still come from the Solution post, since those
	// fields only exist there. Each solution is assumed to resolve to exactly
	// one category in practice, even though get_terms_for_solution() returns
	// an array — if it ever returns more than one, we just use the first.
	$tabs = [];
	foreach ( $solution_ids as $solution_id ) {
		$solution = get_post( $solution_id );
		if ( ! $solution ) {
			continue;
		}

		$term_ids = get_terms_for_solution( $solution_id );
		$term_id  = $term_ids[0] ?? null;
		$term     = $term_id ? get_term( $term_id, 'category' ) : null;

		// Fall back to the solution's own slug/title if no category resolved,
		// so a misconfigured solution still renders a (correctly empty) tab
		// rather than silently disappearing.
		$slug  = ( $term && ! is_wp_error( $term ) ) ? $term->slug : $solution->post_name;
		$label = ( $term && ! is_wp_error( $term ) ) ? $term->name : get_the_title( $solution_id );

		$tabs[] = [
			'solution_id' => $solution_id,
			'term_ids'    => $term_ids,
			'slug'        => $slug,
			'label'       => $label,
		];
	}

	if ( empty( $tabs ) ) {
		return;
	}

	// Prepend an "All" entry. Its term_ids is the union of every solution's
	// terms, so the panel-rendering loop below can treat it identically to
	// any other tab — same query shape, just a wider term list — rather
	// than needing special-case branching later.
	$all_term_ids = [];
	foreach ( $tabs as $tab ) {
		$all_term_ids = array_merge( $all_term_ids, $tab['term_ids'] );
	}

	array_unshift( $tabs, [
		'solution_id' => null,
		'term_ids'    => array_unique( $all_term_ids ),
		'slug'        => 'all',
		'label'       => 'All',
	] );

	// Desktop and mobile disagree about what's selected by default: desktop
	// hides the All tab entirely and opens on the first real solution;
	// mobile keeps All as its default. Two separate default slugs, rather
	// than a single shared index, since "index 0" no longer means the same
	// thing in both contexts.
	$desktop_default_slug = $tabs[1]['slug'] ?? $tabs[0]['slug']; // first real solution, falling back to All if somehow only one tab exists
	$mobile_default_slug  = $tabs[0]['slug']; // All

	$wrapper_attrs = get_block_wrapper_attributes( [
		'class'               => 'momentive-product-solution-tabs',
		'data-mobile-default' => esc_attr( $mobile_default_slug ),
	] );

	echo '<div ' . $wrapper_attrs . '>';

	// ---- Tabs (desktop) -----------------------------------------------------
	//
	// All is intentionally excluded here — desktop opens on the first real
	// solution instead. It still exists in $tabs and renders in the mobile
	// dropdown and in the panels below; only this loop skips it.
	echo '<div class="tabs-row" role="tablist">';

	foreach ( $tabs as $tab ) {
		if ( $tab['solution_id'] === null ) {
			continue; // skip the All entry on desktop
		}

		$solution_id = $tab['solution_id'];
		$icon        = get_field( 'solution_icon', $solution_id );
		$is_active   = ( $tab['slug'] === $desktop_default_slug );

		$css_var = '';
		$color = get_field( 'accent_color', $solution_id );
		if ( $color ) {
			$color = sanitize_hex_color( $color );
			$css_var = '--solution:' . esc_attr( $color ) . ';';
		}

		printf(
			'<button type="button" class="tab%s" role="tab" data-tab="%s" aria-selected="%s" style="%s">',
			$is_active ? ' is-active' : '',
			esc_attr( $tab['slug'] ),
			$is_active ? 'true' : 'false',
			$css_var,			
		);

		echo momentive_render_icon( $icon, 'class="tab-icon"' );

		echo '<span class="tab-label">' . esc_html( str_replace( ' Software', '', $tab['label'] ) ) . '</span>';
		echo '</button>';
	}

	echo '</div>'; // .tabs-row

	// ---- Mobile dropdown ----------------------------------------------------
	//
	// Hidden by default; CSS shows this and hides .tabs-row below the mobile
	// breakpoint (see style.css). Rows share the .tab class and data-tab
	// attribute with the desktop tab buttons, so tabs.js's existing
	// activateTab() drives both without any solution/category-specific
	// branching — a click on either one calls the same function. Unlike the
	// desktop row, All is included here and is the default.
	echo '<div class="tabs-dropdown">';
	echo '<button type="button" class="dropdown-current" aria-expanded="false">';
	echo '<span class="dropdown-current-label">' . esc_html( $tabs[0]['label'] ) . '</span>';
	echo '<svg class="dropdown-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	echo '</button>';

	echo '<div class="dropdown-options" hidden>';
	foreach ( $tabs as $tab ) {
		$solution_id = $tab['solution_id'];
		$icon        = $solution_id ? get_field( 'solution_icon', $solution_id ) : '';
		$is_active   = ( $tab['slug'] === $mobile_default_slug );

		$css_var = '';
		$color = $solution_id ? get_field( 'accent_color', $solution_id ) : '';
		if ( $color ) {
			$color = sanitize_hex_color( $color );
			$css_var = '--solution:' . esc_attr( $color ) . ';';
		}

		printf(
			'<button type="button" class="tab dropdown-option%s" data-tab="%s" style="%s">',
			$is_active ? ' is-active' : '',
			esc_attr( $tab['slug'] ),
			$css_var
		);
		echo momentive_render_icon( $icon, 'class="dropdown-option-icon"' );
		echo '<span class="dropdown-option-label">' . esc_html( str_replace( ' Software', '', $tab['label'] ) ) . '</span>';
		echo '</button>';
	}
	echo '</div>'; // .dropdown-options
	echo '</div>'; // .tabs-dropdown

	// ---- Panels -------------------------------------------------------------
	//
	// Server-rendered default matches the desktop default (first real
	// solution), not All — desktop is the more common case and this avoids
	// any flash-of-wrong-content for the majority of visitors. tabs.js
	// corrects this on init for mobile viewports, reading the mobile
	// default from the wrapper's data attribute below.
	echo '<div class="panels">';

	foreach ( $tabs as $tab ) {
		$term_ids  = $tab['term_ids'];
		$is_active = ( $tab['slug'] === $desktop_default_slug );

		printf(
			'<div class="panel%s" role="tabpanel" data-tab="%s"%s>',
			$is_active ? ' is-active' : '',
			esc_attr( $tab['slug'] ),
			$is_active ? '' : ' hidden'
		);

		if ( empty( $term_ids ) ) {
			echo '<p class="panel-empty">No products are currently assigned to this solution.</p>';
		} else {
			$products = get_posts( [
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => 'category',
						'field'    => 'term_id',
						'terms'    => $term_ids,
					],
				],
				'meta_key'       => 'product_order',
				'orderby'        => 'meta_value_num title',
				'order'          => 'ASC',
			] );

			if ( empty( $products ) ) {
				echo '<p class="panel-empty">No products are currently assigned to this solution.</p>';
			} else {
				echo '<div class="product-grid">';
				foreach ( $products as $product ) {
					momentive_render_product_card( $product->ID );
				}
				echo '</div>';
			}
		}

		echo '</div>'; // .panel
	}

	echo '</div>'; // .panels
	echo '</div>'; // wrapper
}


/**
 * Renders a single product card. Pulled out as its own function since the
 * markup is reused identically across every panel.
 */
function momentive_render_product_card( int $product_id ): void {
	$icon    = get_field( 'product_icon', $product_id );
	$summary = get_field( 'product_summary', $product_id );
	$link    = get_permalink( $product_id );

	$css_var = '';
	$color = get_field( 'accent_color', $product_id );
	if ( $color ) {
		$color = sanitize_hex_color( $color );
		$css_var = '--product:' . esc_attr( $color ) . ';';
	}

	echo '<div class="wp-block-column product-card" style="' . esc_attr( $css_var ) . '">';

	echo '<div class="card-heading">';
	if ( $icon ) {
		echo '<span class="card-icon">' . momentive_render_icon( $icon ) . '</span>';
	}
	echo '<h3 class="wp-block-heading"><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a></h3>';
	echo '</div>';

	if ( $summary ) {
		echo '<div class="card-summary">' . wp_kses_post( $summary ) . '</div>';
	}

	echo '<p class="read-more has-arrow column-bottom"><a href="' . esc_url( $link ) . '">Explore all features</a></p>';

	echo '</div>'; // .product-card
}
