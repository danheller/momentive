<?php
/**
 * Accordion block — register and render.
 *
 * Supports two data modes:
 *  - Static:  items stored as block attributes (per-page content).
 *  - Query:   items pulled from the 'faq' CPT via WP_Query.
 *
 * Three style variants: default | categorized | icon
 */

add_action( 'init', function () {

	wp_register_script(
		'momentive-accordion-editor',
		get_template_directory_uri() . '/blocks/accordion/editor.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'momentive-icon-picker' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_script(
		'momentive-accordion',
		get_template_directory_uri() . '/blocks/accordion/accordion.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-accordion',
		get_template_directory_uri() . '/blocks/accordion/style.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/accordion/block.json',
		array(
			'render_callback' => 'momentive_accordion_render',
			'editor_script'   => 'momentive-accordion-editor',
			'script'          => 'momentive-accordion',
			'style'           => 'momentive-accordion',
		)
	);
} );


/**
 * Render callback.
 */
function momentive_accordion_render( array $attributes, string $content ): string {

	$style       = sanitize_key( $attributes['style']       ?? 'default' );
	$open_first  = ! empty( $attributes['openFirst'] );
	$close_others = ! empty( $attributes['closeOthers'] );
	$query_mode  = ! empty( $attributes['queryMode'] );

	// ── Static mode ──────────────────────────────────────────────────────────

	if ( ! $query_mode ) {
		$items = array_map( function ( $item ) {
			return array(
				'question' => wp_kses_post( $item['question'] ?? '' ),
				'answer'   => wp_kses_post( $item['answer']   ?? '' ),
				'iconSlug' => sanitize_key( $item['iconSlug'] ?? '' ),
				'category' => sanitize_text_field( $item['category'] ?? '' ),
			);
		}, $attributes['items'] ?? [] );

		return momentive_accordion_markup( $items, $style, $close_others, false, [], $open_first );
	}

	// ── Query mode ───────────────────────────────────────────────────────────

	$posts_per_page = intval( $attributes['queryPostsPerPage'] ?? 9 );
	$category_slug  = sanitize_key( $attributes['queryCategory'] ?? '' );

	$query_args = array(
		'post_type'      => 'faq',
		'post_status'    => 'publish',
		'posts_per_page' => $posts_per_page,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	);

	if ( $category_slug ) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'category',
				'field'    => 'slug',
				'terms'    => $category_slug,
			),
		);
	}

	$query = new WP_Query( $query_args );
	$items = array();

	foreach ( $query->posts as $post ) {
		$cats     = get_the_terms( $post->ID, 'category' );
		$cat_name = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
		$icon_slug = get_field( 'faq_icon', $post->ID ) ?: '';
	
		// Resolve solution accent color from the category term
		$color = '';
		if ( $cats && ! is_wp_error( $cats ) ) {
			$raw = get_solution_color_for_term( $cats[0]->term_id );
			if ( $raw ) {
				$color = sanitize_hex_color( $raw );
			}
		}
	
		$items[] = array(
			'question'      => $post->post_title,
			'answer'        => apply_filters( 'the_content', $post->post_content ),
			'excerpt'       => $post->post_excerpt,
			'iconSlug'      => sanitize_key( $icon_slug ),
			'category'      => $cat_name,
			'solutionColor' => $color,   // ← new
			'id'            => $post->ID,
			'link'          => get_permalink( $post->ID ),
		);
	}

	$total_pages = $query->max_num_pages;
	wp_reset_postdata();

	$wrapper_attrs = array(
		'data-total-pages' => $total_pages,
		'data-posts-per-page' => $posts_per_page,
		'data-category' => $category_slug,
	);

	$html = momentive_accordion_markup( $items, $style, $close_others, true, $wrapper_attrs, $open_first );

	return $html;
}


/**
 * Build the accordion HTML from a normalised items array.
 *
 * @param array  $items         Normalised item rows.
 * @param string $style         Block style variant.
 * @param bool   $close_others  Whether opening one item closes the rest.
 * @param bool   $query_mode    Adds data attributes used by the load-more JS.
 * @param array  $wrapper_attrs Extra data-* attributes for the wrapper (query mode).
 */
function momentive_accordion_markup(
	array $items,
	string $style,
	bool $close_others,
	bool $query_mode = false,
	array $wrapper_attrs = [],
	bool $open_first = false
): string {

	if ( empty( $items ) ) return '';

	$classes  = 'momentive-accordion';
	$classes .= ' is-style-' . esc_attr( $style );
	if ( $close_others ) $classes .= ' js-close-others';
	if ( $query_mode )   $classes .= ' is-query-mode';

	// Extra data attributes string for the wrapper div.
	$extra = '';
	foreach ( $wrapper_attrs as $attr => $val ) {
		$extra .= ' ' . esc_attr( $attr ) . '="' . esc_attr( $val ) . '"';
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $extra; ?>>
		<?php foreach ( $items as $index => $item ) :
			$item_id  = 'accordion-item-' . uniqid();
			$panel_id = $item_id . '-panel';
			$is_first_open = $open_first && $index === 0;
			$has_icon = $style === 'icon' && ! empty( $item['iconSlug'] );
			$has_cat  = $style === 'categorized' && ! empty( $item['category'] );
		?>
		<div class="accordion-item<?php echo $is_first_open ? ' is-open' : ''; ?>"<?php
			if ( ! empty( $item['solutionColor'] ) ) {
				echo ' style="--category-color:' . esc_attr( $item['solutionColor'] ) . '"';
			}
		?>>
			<button
				class="accordion-trigger"
				type="button"
				aria-expanded="<?php echo $is_first_open ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				id="<?php echo esc_attr( $item_id ); ?>"
			>
				<?php if ( $has_icon ) : 
					momentive_use_icon( $item['iconSlug'] );
				?>
				<span class="accordion-icon" aria-hidden="true">
					<svg focusable="false">
						<use href="#icon-<?php echo esc_attr( $item['iconSlug'] ); ?>"></use>
					</svg>
				</span>
				<?php endif; ?>

				<span class="accordion-question"><?php echo esc_html( $item['question'] ); ?></span>

				<?php if ( $has_cat ) : ?>
				<span
					class="accordion-category"
					data-category="<?php echo esc_attr( sanitize_title( $item['category'] ) ); ?>"
				><?php echo esc_html( $item['category'] ); ?></span>
				<?php endif; ?>

				<span class="accordion-chevron" aria-hidden="true">
					<svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
						<path d="M1.5 4L6 8L10.5 4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
					</svg>
				</span>

			</button>

			<div
				class="accordion-panel"
				id="<?php echo esc_attr( $panel_id ); ?>"
				role="region"
				aria-labelledby="<?php echo esc_attr( $item_id ); ?>"
				<?php echo $is_first_open ? '' : 'hidden'; ?>
			>
				<div class="accordion-panel-inner">
					<?php if ( ! empty( $item['excerpt'] ) ) : ?>
						<?php echo wp_kses_post( $item['excerpt'] ); ?>
						<?php if ( ! empty( $item['link'] ) ) : ?>
							<p class="accordion-read-more">
								<a href="<?php echo esc_url( $item['link'] ); ?>">
									<?php esc_html_e( 'Read more', 'momentive' ); ?>
									<span class="screen-reader-text">: <?php echo esc_html( $item['question'] ); ?></span>
								</a>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<?php echo wp_kses_post( $item['answer'] ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
