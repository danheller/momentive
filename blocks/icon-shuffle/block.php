<?php
/**
 * Icon Shuffle Grid – server-side render and registration
 *
 * Place this file at: /wp-content/plugins/momentive-blocks/blocks/icon-shuffle/render.php
 * or call register_block_type() from your plugin's main file pointing at the block directory.
 */

/**
 * Registers the block and manually enqueues all scripts/styles.
 * No build step required — edit.js uses wp.* globals directly.
 */
function momentive_register_icon_shuffle_block(): void {

	$block_dir = __DIR__;
	$block_url = get_template_directory_uri() . '/blocks/icon-shuffle/';

	// ── Editor script ────────────────────────────────────────────────────────
	// Depends on the wp.* globals we use inside edit.js.
	wp_register_script(
		'momentive-icon-shuffle-editor',
		$block_url . 'edit.js',
		[
			'wp-blocks',        // wp.blocks.registerBlockType
			'wp-block-editor',  // useBlockProps, InspectorControls, MediaUpload
			'wp-components',    // PanelBody, Button, RangeControl, etc.
			'wp-element',       // wp.element.createElement
			'wp-i18n',          // wp.i18n.__
		],
		filemtime( $block_dir . '/edit.js' ), // cache-bust on file change
		true
	);

	// ── Frontend view script ─────────────────────────────────────────────────
	// Pure vanilla JS, no WP dependencies.
	wp_register_script(
		'momentive-icon-shuffle-view',
		$block_url . 'view.js',
		[],
		filemtime( $block_dir . '/view.js' ),
		true // load in footer
	);

	// ── Shared frontend + editor styles ─────────────────────────────────────
	wp_register_style(
		'momentive-icon-shuffle-style',
		$block_url . 'style.css',
		[],
		filemtime( $block_dir . '/style.css' )
	);

	// ── Editor-only styles ───────────────────────────────────────────────────
	wp_register_style(
		'momentive-icon-shuffle-editor-style',
		$block_url . 'editor.css',
		[],
		filemtime( $block_dir . '/editor.css' )
	);

	// ── Register the block, wiring up the handles above ──────────────────────
	register_block_type(
		$block_dir,
		[
			'render_callback' => 'momentive_render_icon_shuffle',
			'editor_script'   => 'momentive-icon-shuffle-editor',
			'editor_style'    => 'momentive-icon-shuffle-editor-style',
			'style'           => 'momentive-icon-shuffle-style',
			'script'          => 'momentive-icon-shuffle-view',
		]
	);
}
add_action( 'init', 'momentive_register_icon_shuffle_block' );

/**
 * Server-side render callback.
 *
 * Outputs the static HTML shell. The JS view script takes over on the frontend.
 * Images are embedded in a data attribute as JSON so the JS doesn't need a
 * separate REST request – keeps it simple and avoids a flash of empty content.
 *
 * @param array $attributes Block attributes.
 * @return string HTML output.
 */
function momentive_render_icon_shuffle( array $attributes ): string {
	$images              = $attributes['images']              ?? [];
	$columns             = (int) ( $attributes['columns']            ?? 5 );
	$cell_count          = (int) ( $attributes['cellCount']          ?? 14 );
	$cell_size           = (int) ( $attributes['cellSize']           ?? 24 );
	$interval            = (int) ( $attributes['interval']           ?? 350 );
	$transition_duration = (int) ( $attributes['transitionDuration'] ?? 600 );
	$center_image        = $attributes['centerImage']                ?? null;
	$center_cell_index   = (int) ( $attributes['centerCellIndex']    ?? 7 );

	if ( empty( $images ) ) {
		return '';
	}

	// Clamp cell count so we always have at least one offstage image.
	$pool_size  = count( $images );
	$cell_count = min( $cell_count, $pool_size - 1 );
	$cell_count = max( $cell_count, 1 );

	// Sanitize images for JSON output.
	$safe_images = array_map(
		static function ( $img ) {
			return [
				'url' => esc_url( $img['url'] ?? '' ),
				'alt' => esc_attr( $img['alt'] ?? '' ),
			];
		},
		$images
	);

	$config = wp_json_encode(
		[
			'images'             => array_values( $safe_images ),
			'cellCount'          => $cell_count,
			'interval'           => $interval,
			'transitionDuration' => $transition_duration,
			'centerCellIndex'    => $center_image ? $center_cell_index : -1,
		]
	);

	// Grid inline styles.
	$grid_style = sprintf(
		'grid-template-columns: repeat(%d, %dpx);',
		$columns,
		$cell_size
	);
	$cell_style = sprintf( 'width: %1$dpx; height: %1$dpx;', $cell_size );

	// Build cell HTML.
	// The center slot is skipped from the shuffle pool and filled with the
	// center image instead. Grid positions are 0-indexed; we track shuffle
	// image index separately so the skip doesn't create a gap in the pool.
	$cells_html      = '';
	$shuffle_img_idx = 0; // walks through $safe_images independently of $grid_pos

	$total_grid_cells = $cell_count + ( $center_image ? 1 : 0 );

	for ( $grid_pos = 0; $grid_pos < $total_grid_cells; $grid_pos++ ) {

		// ── Center image slot ────────────────────────────────────────────────
		if ( $center_image && $grid_pos === $center_cell_index ) {
			$cells_html .= sprintf(
				'<div class="icon-shuffle-cell icon-shuffle-cell--center" style="%s">
					<img src="%s" alt="%s" loading="eager" decoding="async" />
				</div>',
				esc_attr( $cell_style ),
				esc_url( $center_image['url'] ?? '' ),
				esc_attr( $center_image['alt'] ?? '' )
			);
			continue;
		}

		// ── Shuffle cell ─────────────────────────────────────────────────────
		if ( $shuffle_img_idx >= count( $safe_images ) ) {
			break; // pool exhausted
		}

		$img         = $safe_images[ $shuffle_img_idx ];
		$loading     = $shuffle_img_idx < 6 ? 'eager' : 'lazy';
		$cells_html .= sprintf(
			'<div class="icon-shuffle-cell" style="%s">
				<img class="icon-shuffle-img--current" src="%s" alt="%s" loading="%s" decoding="async" />
				<img class="icon-shuffle-img--incoming" src="" alt="" aria-hidden="true" />
			</div>',
			esc_attr( $cell_style ),
			esc_url( $img['url'] ),
			esc_attr( $img['alt'] ),
			$loading
		);

		$shuffle_img_idx++;
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		[ 'data-icon-shuffle-config' => $config ]
	);

	return sprintf(
		'<div %s><div class="icon-shuffle-grid" style="%s">%s</div></div>',
		$wrapper_attributes,
		esc_attr( $grid_style ),
		$cells_html
	);
}
