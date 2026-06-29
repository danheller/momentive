<?php
/**
 * Block: momentive/stat-columns
 *
 * A row of statistics for case studies (and reusable elsewhere). Each stat is a
 * large VALUE string plus a description. The value is rendered VERBATIM as text
 * — no count-up animation and no prefix/number/suffix parsing.
 *
 * Why not reuse momentive/impact-stat? impact-stat animates a numeric count-up,
 * which requires splitting a value into prefix + number + suffix ($ / 35.5 / M).
 * The legacy case-study stat values are free-form strings: ~39% of them
 * (">1 million", "~50%", "24-fold", "2x ticket price", "#1", "eliminated",
 * even typos like "Hundrads") cannot be parsed into a number without losing
 * data. So this block keeps the value as an opaque string. The migration then
 * copies legacy `data_title` -> `stat_value` untouched, with zero parsing risk.
 *
 * Data: ACF repeater `stats` with subfields `stat_value` (text) and
 * `stat_description` (textarea). See acf-stat-columns.php.
 *
 * Gracefully handles 0–N stats:
 *   - 0 stats  -> renders nothing on the front end (block hides itself)
 *   - blank value but present description -> renders description only
 *   - blank description but present value -> renders value only
 */

if ( ! function_exists( 'momentive_register_stat_columns_block' ) ) {

	add_action( 'init', 'momentive_register_stat_columns_block' );

	function momentive_register_stat_columns_block(): void {
		register_block_type( __DIR__ );

		wp_register_style(
			'momentive-stat-columns',
			get_template_directory_uri() . '/blocks/stat-columns/stat-columns.css',
			[],
			wp_get_theme()->get( 'Version' )
		);
	}

	add_action( 'enqueue_block_assets', function (): void {
		if ( is_admin() ) {
			return;
		}
		if ( momentive_content_has_block( 'momentive/stat-columns' ) ) {
			wp_enqueue_style( 'momentive-stat-columns' );
		}
	} );
}

/**
 * Render callback (ACF renderTemplate target).
 *
 * @param array $block      Block settings and attributes.
 * @param string $content   Inner content (unused).
 * @param bool  $is_preview True during editor preview.
 */

$stats = get_field( 'stats' );

// Drop empty rows (both fields blank) so stray repeater rows don't render.
$rows = [];
if ( is_array( $stats ) ) {
	foreach ( $stats as $stat ) {
		$value = isset( $stat['stat_value'] ) ? trim( (string) $stat['stat_value'] ) : '';
		$desc  = isset( $stat['stat_description'] ) ? trim( (string) $stat['stat_description'] ) : '';
		if ( $value === '' && $desc === '' ) {
			continue;
		}
		$rows[] = [ 'value' => $value, 'desc' => $desc ];
	}
}

// No stats: render a placeholder in the editor, nothing on the front end.
if ( empty( $rows ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<div class="stat-columns is-placeholder"><p>Add one or more stats to display.</p></div>';
	}
	return;
}

$anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

// Count drives a data attribute so the layout can adapt (1–4 columns) via CSS.
$count = count( $rows );

printf(
	'<div class="stat-columns stat-columns--count-%d"%s>',
	(int) $count,
	$anchor
);

foreach ( $rows as $row ) {
	echo '<div class="stat-columns__item">';
	if ( $row['value'] !== '' ) {
		// Verbatim string value — NOT parsed, NOT animated.
		echo '<p class="stat-columns__value">' . esc_html( $row['value'] ) . '</p>';
	}
	if ( $row['desc'] !== '' ) {
		echo '<p class="stat-columns__description">' . esc_html( $row['desc'] ) . '</p>';
	}
	echo '</div>';
}

echo '</div>';
