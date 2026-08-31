<?php
/**
 * Integration List block — registration + PHP render callback.
 *
 * JS-registered block (editor.js handles the Gutenberg canvas + InnerBlocks).
 * PHP provides the full front-end render: sidebar filters, optional CTA card
 * (from InnerBlocks via $content), and the integration card grid.
 *
 * ACF field group "Integration List" (group_6b20a1f0c0002) is attached via
 * location rule block == momentive/integration-list and remains accessible via
 * get_field() in the render callback.
 *
 * @package Momentive
 */

// ──────────────────────────────────────────────────────────────────────────────
// Registration
// ──────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'momentive_register_integration_list_block' ) ) {
	/**
	 * Register the block type and its assets.
	 */
	function momentive_register_integration_list_block(): void {
		wp_register_style(
			'momentive-integration-list',
			get_template_directory_uri() . '/blocks/integration-list/integration-list.css',
			[],
			wp_get_theme()->get( 'Version' )
		);

		wp_register_script(
			'momentive-integration-list',
			get_template_directory_uri() . '/blocks/integration-list/integration-list.js',
			[],
			wp_get_theme()->get( 'Version' ),
			true
		);

		wp_register_script(
			'momentive-integration-list-editor',
			get_template_directory_uri() . '/blocks/integration-list/editor.js',
			[ 'wp-blocks', 'wp-block-editor', 'wp-element' ],
			wp_get_theme()->get( 'Version' ),
			true
		);

		register_block_type(
			__DIR__,
			[
				'render_callback' => 'momentive_integration_list_render',
				'editor_script'   => 'momentive-integration-list-editor',
			]
		);
	}
}
add_action( 'init', 'momentive_register_integration_list_block' );

add_action( 'enqueue_block_assets', function (): void {
	if ( is_admin() ) {
		return;
	}
	if ( ! momentive_content_has_block( 'momentive/integration-list' ) ) {
		return;
	}
	wp_enqueue_style( 'momentive-integration-list' );
	wp_enqueue_script( 'momentive-integration-list' );
} );

// ──────────────────────────────────────────────────────────────────────────────
// Render callback
// ──────────────────────────────────────────────────────────────────────────────

if ( ! function_exists( 'momentive_integration_list_render' ) ) {
	/**
	 * PHP render callback — runs on the front end only.
	 * The editor canvas is handled entirely by editor.js (InnerBlocks + placeholder).
	 *
	 * @param array    $attributes Block attributes (anchor, className, …).
	 * @param string   $content    Rendered InnerBlocks HTML (the CTA card).
	 * @return string
	 */
	function momentive_integration_list_render( array $attributes, string $content ): string {

		// 1. Resolve block-level product filter (ACF field bound to this block).
		$raw_filter_products = get_field( 'filter_products' );
		$filter_product_ids  = [];
		if ( ! empty( $raw_filter_products ) ) {
			foreach ( (array) $raw_filter_products as $p ) {
				$filter_product_ids[] = is_object( $p ) ? (int) $p->ID : (int) $p;
			}
			$filter_product_ids = array_filter( $filter_product_ids );
		}

		// 2. Fetch all published integrations.
		$all_integrations = get_posts( [
			'post_type'      => 'integration',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		// 3. Filter by product IDs when block-level filter is active.
		if ( ! empty( $filter_product_ids ) ) {
			$all_integrations = array_filter( $all_integrations, function ( $post ) use ( $filter_product_ids ): bool {
				$linked = get_field( 'linked_products', $post->ID );
				if ( empty( $linked ) ) {
					return false;
				}
				foreach ( (array) $linked as $lp ) {
					$lpid = is_object( $lp ) ? (int) $lp->ID : (int) $lp;
					if ( in_array( $lpid, $filter_product_ids, true ) ) {
						return true;
					}
				}
				return false;
			} );
			$all_integrations = array_values( $all_integrations );
		}

		if ( empty( $all_integrations ) ) {
			return '';
		}

		// 4. Collect available filter options from the result set.
		$available_types        = []; // slug => name
		$available_capabilities = []; // slug => name
		$available_products     = []; // id  => name

		foreach ( $all_integrations as $integration ) {
			$types = get_the_terms( $integration->ID, 'integration_type' );
			if ( $types && ! is_wp_error( $types ) ) {
				foreach ( $types as $term ) {
					$available_types[ $term->slug ] = $term->name;
				}
			}

			$caps = get_the_terms( $integration->ID, 'integration_capability' );
			if ( $caps && ! is_wp_error( $caps ) ) {
				foreach ( $caps as $term ) {
					$available_capabilities[ $term->slug ] = $term->name;
				}
			}

			$linked = get_field( 'linked_products', $integration->ID );
			if ( ! empty( $linked ) ) {
				foreach ( (array) $linked as $lp ) {
					$lpid    = is_object( $lp ) ? (int) $lp->ID : (int) $lp;
					$lptitle = is_object( $lp ) ? $lp->post_title : get_the_title( $lpid );
					if ( $lpid ) {
						$available_products[ $lpid ] = $lptitle;
					}
				}
			}
		}

		uasort( $available_types, fn ( $a, $b ) => strcmp( $a, $b ) );
		uasort( $available_capabilities, fn ( $a, $b ) => strcmp( $a, $b ) );
		uasort( $available_products, fn ( $a, $b ) => strcmp( $a, $b ) );

		$show_product_filter = count( $available_products ) > 1;

		// 5. InnerBlocks CTA content.
		$has_cta = ! empty( $content );

		// 6. Capability slug → icon slug map.
		$cap_icons = [
			'activity-writebacks'  => 'bx-refresh',
			'content'              => 'bx-pencil',
			'data'                 => 'bx-data',
			'ecommerce'            => 'bx-dollar',
			'external-credit-sync' => 'bx-award',
			'sso'                  => 'bx-lock',
			'teams'                => 'bx-group',
			'virtual-events'       => 'bx-calendar-event',
		];

		if ( function_exists( 'momentive_use_icon' ) ) {
			foreach ( $cap_icons as $icon_slug ) {
				momentive_use_icon( $icon_slug );
			}
		}

		// 7. Block wrapper attributes.
		$block_id    = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : wp_unique_id( 'il-' );
		$block_class = 'integration-list' . ( ! empty( $attributes['className'] ) ? ' ' . esc_attr( $attributes['className'] ) : '' );
		$id_attr     = ! empty( $attributes['anchor'] ) ? ' id="' . esc_attr( $attributes['anchor'] ) . '"' : '';

		ob_start();
		?>
		<div<?php echo $id_attr; ?> class="<?php echo esc_attr( $block_class ); ?>">

			<!-- Sidebar filters -->
			<aside class="integration-list__sidebar">
				<div class="integration-list__filter-header">
					<span class="integration-list__filter-title">Select a category</span>
					<button type="button" class="integration-list__reset">Reset</button>
				</div>

				<?php if ( $show_product_filter ) : ?>
				<div class="integration-list__filter-group">
					<select class="il-type-select integration-list__select--product" aria-label="<?php esc_attr_e( 'Product', 'momentive' ); ?>">
						<option value="">All products</option>
						<?php foreach ( $available_products as $pid => $pname ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $available_types ) ) : ?>
				<div class="integration-list__filter-group">
					<select class="il-type-select integration-list__select--type" aria-label="<?php esc_attr_e( 'Type', 'momentive' ); ?>">
						<option value="">Type</option>
						<?php foreach ( $available_types as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $available_capabilities ) ) : ?>
				<div class="integration-list__filter-group">
					<div class="il-cap-dropdown">
						<button
							type="button"
							class="il-cap-dropdown__toggle"
							aria-expanded="false"
							aria-controls="il-cap-panel-<?php echo esc_attr( $block_id ); ?>"
						>Capabilities</button>
						<div
							class="il-cap-dropdown__panel"
							id="il-cap-panel-<?php echo esc_attr( $block_id ); ?>"
							hidden
						>
							<?php foreach ( $available_capabilities as $slug => $name ) : ?>
							<label class="filter-item">
								<input type="checkbox" class="integration-list__capability-checkbox" value="<?php echo esc_attr( $slug ); ?>">
								<?php echo esc_html( $name ); ?>
							</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</aside>

			<!-- Main: optional CTA card + card grid -->
			<div class="integration-list__main">

				<?php if ( $has_cta ) : ?>
				<div class="integration-list__cta">
					<?php echo $content; ?>
				</div>
				<?php endif; ?>

				<div class="integration-list__grid">
					<?php foreach ( $all_integrations as $integration ) :
						$card_types = get_the_terms( $integration->ID, 'integration_type' );
						$type_slug  = ( $card_types && ! is_wp_error( $card_types ) ) ? $card_types[0]->slug : '';
						$type_name  = ( $card_types && ! is_wp_error( $card_types ) ) ? $card_types[0]->name : '';

						$card_caps = get_the_terms( $integration->ID, 'integration_capability' );
						$cap_slugs = [];
						$cap_names = [];
						if ( $card_caps && ! is_wp_error( $card_caps ) ) {
							foreach ( $card_caps as $cap ) {
								$cap_slugs[] = $cap->slug;
								$cap_names[] = $cap->name;
							}
						}

						$card_linked_products = get_field( 'linked_products', $integration->ID );
						$card_product_ids     = [];
						if ( ! empty( $card_linked_products ) ) {
							foreach ( (array) $card_linked_products as $lp ) {
								$card_product_ids[] = is_object( $lp ) ? (int) $lp->ID : (int) $lp;
							}
						}

						$logo_id = get_post_thumbnail_id( $integration->ID );
					?>
					<article
						class="integration-card"
						data-type="<?php echo esc_attr( $type_slug ); ?>"
						data-capabilities="<?php echo esc_attr( implode( ' ', $cap_slugs ) ); ?>"
						data-products="<?php echo esc_attr( implode( ' ', $card_product_ids ) ); ?>"
					>
						<div class="integration-card__logo">
							<?php if ( $logo_id ) : ?>
							<?php echo wp_get_attachment_image( $logo_id, 'full', false, [ 'loading' => 'lazy' ] ); ?>
							<?php endif; ?>
						</div>

						<p class="integration-card__name"><?php echo esc_html( $integration->post_title ); ?></p>

						<?php if ( $type_name ) : ?>
						<p class="integration-card__type"><?php echo esc_html( $type_name ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $cap_slugs ) ) : ?>
						<div class="integration-card__capabilities">
							<?php foreach ( $cap_slugs as $i => $cap_slug ) :
								$cap_label = $cap_names[ $i ] ?? $cap_slug;
								$icon_slug = $cap_icons[ $cap_slug ] ?? '';
							?>
							<span class="integration-cap-icon" data-tooltip="<?php echo esc_attr( $cap_label ); ?>">
								<?php if ( $icon_slug && function_exists( 'momentive_render_icon' ) ) :
									echo momentive_render_icon( $icon_slug );
								endif; ?>
							</span>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</article>
					<?php endforeach; ?>

					<p class="integration-list__no-results" aria-live="polite" style="display:none;">No integrations match the selected filters.</p>
				</div>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}
