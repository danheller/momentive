<?php
/**
 * Icon List block registration
 * blocks/icon-list/block.php
 *
 * JS-registered block with a PHP render_callback. Mirrors how icon-block /
 * icon-link are wired: the editor script depends on `momentive-icon-picker`
 * (which localizes window.momentiveIcons), and the front end is rendered by
 * render.php.
 *
 * If your theme already registers blocks in a loop over blocks/*\/block.json,
 * you only need to (a) ensure render.php is picked up and (b) ensure the
 * editor script handle below is enqueued with the icon-picker dependency.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function (): void {

	$dir = get_stylesheet_directory() . '/blocks/icon-list';
	$uri = get_stylesheet_directory_uri() . '/blocks/icon-list';

	// Editor script — depends on the shared icon picker so window.momentive.IconPicker
	// and window.momentiveIcons are available before this runs.
	wp_register_script(
		'momentive-icon-list-editor',
		$uri . '/block.js',
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-i18n',
			'momentive-icon-picker',
		),
		filemtime( $dir . '/block.js' ),
		true
	);

	// Frontend + editor styles.
	wp_register_style(
		'momentive-icon-list',
		$uri . '/style.css',
		array(),
		filemtime( $dir . '/style.css' )
	);

	register_block_type( $dir, array(
		'editor_script'   => 'momentive-icon-list-editor',
		'style'           => 'momentive-icon-list',
		'editor_style'    => 'momentive-icon-list',
		'render_callback' => function ( $attributes, $content, $block ) use ( $dir ) {
			ob_start();
			include $dir . '/block.php';
			return ob_get_clean();
		},
	) );
} );


/**
 * Renders the `items` attribute as a list of icon/description
 * rows. Icons use the project sprite convention (<use href="#icon-<slug>">),
 * matching momentive/icon-block. The visual treatment (no shape, no
 * background, secondary color) lives in style.css, so no per-row markup
 * is needed here beyond the .icon-list__icon wrapper.
 *
 * $attributes, $content, $block are provided by render_callback.
 *
 * @var array $attributes
 */


$items = isset( $attributes['items'] ) && is_array( $attributes['items'] )
	? $attributes['items']
	: array();

// Drop rows that have neither an icon nor text.
$items = array_values( array_filter( $items, static function ( $item ) {
	$icon = isset( $item['iconSlug'] ) ? trim( (string) $item['iconSlug'] ) : '';
	$text = isset( $item['text'] ) ? trim( (string) $item['text'] ) : '';
	return '' !== $icon || '' !== $text;
} ) );

// Nothing to show: render nothing on the front end (matches stat-columns).
if ( empty( $items ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes();

ob_start();
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<ul class="icon-list">
		<?php foreach ( $items as $item ) :
			$slug = isset( $item['iconSlug'] ) ? trim( (string) $item['iconSlug'] ) : '';
			$text = isset( $item['text'] ) ? trim( (string) $item['text'] ) : '';
			?>
			<li class="icon-list__item">
				<?php if ( '' !== $slug ) : ?>
					<span class="icon-list__icon" aria-hidden="true">
						<?php
						// Prefer the shared helper if present; fall back to a raw sprite ref.
						if ( function_exists( 'momentive_render_icon' ) ) {
							echo momentive_render_icon( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							printf(
								'<svg aria-hidden="true" focusable="false"><use href="#icon-%s"></use></svg>',
								esc_attr( $slug )
							);
						}
						?>
					</span>
				<?php endif; ?>

				<?php if ( '' !== $text ) : ?>
					<span class="icon-list__text"><?php echo esc_html( $text ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
