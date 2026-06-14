<?php

/**
 * Icon System
 *
 * SVG sprites loaded from /assets/icons/*.svg files in the theme directory.
 * Add or remove icons by adding/removing files from that folder.
 *
 * Also registers the momentive/icon-block standalone block and the
 * Media & Text icon overlay feature (editor.js).
 */


// ---------------------------------------------------------------------------
// Icon discovery
// ---------------------------------------------------------------------------

/**
 * Scan /assets/icons/ directory and return [ slug => label ] pairs.
 * Label is derived from the filename: "dollar-sign.svg" → "Dollar Sign".
 */
function momentive_get_available_icons(): array {
	$icon_dir = get_stylesheet_directory() . '/assets/icons/';
	$files    = glob( $icon_dir . '*.svg' );

	if ( empty( $files ) ) {
		return [];
	}

	$icons = [];
	foreach ( $files as $file ) {
		$slug          = basename( $file, '.svg' );
		$icons[ $slug ] = ucwords( str_replace( '-', ' ', $slug ) );
	}

	ksort( $icons );
	return $icons;
}

/**
 * Get the SVG file path for a given icon slug.
 */
function momentive_get_icon_path( string $slug ): string {
	return get_stylesheet_directory() . '/assets/icons/' . sanitize_file_name( $slug ) . '.svg';
}

/**
 * Read an SVG file and return the inner markup (paths, etc.) and its viewBox.
 * Returns null if the file doesn't exist or can't be parsed.
 *
 * @return array{ viewBox: string, inner: string }|null
 */
function momentive_parse_svg_file( string $slug ): ?array {
	$file = momentive_get_icon_path( $slug );
	if ( ! file_exists( $file ) ) {
		return null;
	}

	$raw = file_get_contents( $file );
	if ( ! $raw ) {
		return null;
	}

	// Suppress XML errors for slightly-malformed SVGs from design tools.
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $raw );
	libxml_clear_errors();

	if ( ! $xml ) {
		return null;
	}

	$attrs   = $xml->attributes();
	$viewBox = isset( $attrs['viewBox'] ) ? (string) $attrs['viewBox'] : '0 0 24 24';

	// Strip the outer <svg>…</svg> wrapper; keep everything inside.
	$inner = preg_replace( '/<\/?svg[^>]*>/i', '', $raw );
	$inner = trim( $inner );

	return [
		'viewBox' => $viewBox,
		'inner'   => $inner,
	];
}


// ---------------------------------------------------------------------------
// SVG sprite output
// ---------------------------------------------------------------------------

/**
 * Output hidden <svg><symbol>…</symbol></svg> sprite markup.
 *
 * @param string[]|null $slugs  Specific slugs to output, or null for all.
 */
function momentive_output_svg_symbols( ?array $slugs = null ): void {
	$all_icons = momentive_get_available_icons();

	if ( $slugs === null ) {
		$slugs = array_keys( $all_icons );
	} else {
		// Only output slugs that actually exist.
		$slugs = array_intersect( $slugs, array_keys( $all_icons ) );
	}

	if ( empty( $slugs ) ) {
		return;
	}

	echo '<svg style="display:none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' . "\n";

	foreach ( $slugs as $slug ) {
		$parsed = momentive_parse_svg_file( $slug );
		if ( ! $parsed ) {
			continue;
		}
		printf(
			'<symbol id="icon-%s" viewBox="%s">%s</symbol>' . "\n",
			esc_attr( $slug ),
			esc_attr( $parsed['viewBox'] ),
			$parsed['inner'] // Already sanitized SVG from our own theme directory.
		);
	}

	echo '</svg>' . "\n";
}


// ---------------------------------------------------------------------------
// Frontend sprite injection
//
// Currently outputs all icons. The commented-out selective version below is
// the upgrade path once the icon-link block and other consumers are stable.
// ---------------------------------------------------------------------------


// Selective output — requires all icon consumers to call momentive_use_icon().

add_action( 'wp_footer', function () {
	$slugs = momentive_get_used_icons();
	momentive_output_svg_symbols( $slugs ?: null );
}, 20 );

/**
 * Register an icon slug as needed on this page.
 * Call from render callbacks so the footer sprite only includes what's used.
 */
function momentive_use_icon( string $slug ): void {
	global $momentive_icons_used;
	if ( ! isset( $momentive_icons_used ) ) {
		$momentive_icons_used = [];
	}
	$momentive_icons_used[] = $slug;
}

function momentive_get_used_icons(): array {
	global $momentive_icons_used;
	return array_unique( $momentive_icons_used ?? [] );
}


// ---------------------------------------------------------------------------
// Icon block registration  (momentive/icon-block)
// ---------------------------------------------------------------------------

add_action( 'init', 'momentive_register_icon_block' );

function momentive_register_icon_block(): void {
	wp_register_script(
		'momentive-icon-picker',
		get_stylesheet_directory_uri() . '/assets/js/icon-picker.js',
		[ 'wp-element', 'wp-components', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' )
	);

	wp_register_script(
		'momentive-icon-block',
		get_stylesheet_directory_uri() . '/blocks/icon-block/block.js',
		[ 'momentive-icon-picker', 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' )
	);

	wp_register_style(
		'momentive-icon-block-editor',
		get_stylesheet_directory_uri() . '/blocks/icon-block/editor.css',
		[ 'wp-edit-blocks' ],
		wp_get_theme()->get( 'Version' )
	);

	wp_register_style(
		'momentive-icon-block-style',
		get_stylesheet_directory_uri() . '/blocks/icon-block/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/icon-block',
		[
			'render_callback' => 'momentive_render_icon_block',
			'editor_script'   => 'momentive-icon-block',
			'editor_style'    => 'momentive-icon-block-editor',
			'style'           => 'momentive-icon-block-style',
		]
	);
}

function momentive_render_icon_block( array $attributes ): string {
	$icon_id      = sanitize_key( $attributes['iconId']          ?? '' );
	$shape        = sanitize_key( $attributes['shape']           ?? 'circle' );
	$bg_color     = sanitize_key( $attributes['backgroundColor'] ?? 'light' );
	$icon_color   = sanitize_key( $attributes['iconColor']       ?? 'accent' );
	$custom_class = sanitize_html_class( $attributes['className'] ?? '' );

	if ( empty( $icon_id ) ) {
		return '';
	}

	momentive_use_icon( $icon_id );

	$classes = [
		'svg-icon',
		'shape-' . $shape,
		'bg-' . $bg_color,
		'is-color-' . $icon_color,
		$icon_id . '-icon',
	];

	if ( $custom_class ) {
		$classes[] = $custom_class;
	}

	return sprintf(
		'<span class="%s"><svg aria-hidden="true" focusable="false"><use href="#icon-%s"></use></svg></span>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $icon_id )
	);
}

add_action( 'init', 'momentive_register_icon_link_block' );

function momentive_register_icon_link_block(): void {
	wp_register_script(
		'momentive-icon-link-block',
		get_stylesheet_directory_uri() . '/blocks/icon-link/block.js',
		[ 'momentive-icon-picker', 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' )
	);

	wp_register_style(
		'momentive-icon-link-style',
		get_stylesheet_directory_uri() . '/blocks/icon-link/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/icon-link',
		[
			'render_callback' => 'momentive_render_icon_link_block',
			'editor_script'   => 'momentive-icon-link-block',
			'style'           => 'momentive-icon-link-style',
			'editor_style'    => 'momentive-icon-link-style',
		]
	);
}

function momentive_render_icon_link_block( array $attributes ): string {
	$url          = esc_url( $attributes['url']   ?? '' );
	$title        = esc_html( $attributes['title'] ?? '' );
	$tagline      = esc_html( $attributes['tagline'] ?? '' );
	$icon_slug    = sanitize_key( $attributes['iconSlug'] ?? '' );
	$icon_size    = in_array( $attributes['iconSize'] ?? '', [ 'small', 'large' ], true )
					? $attributes['iconSize']
					: 'small';
	$accent_color = sanitize_hex_color( $attributes['accentColor'] ?? '' );

	// If no manual override, try to resolve from the linked post
	if ( ! $accent_color && ! empty( $attributes['url'] ) ) {
		$post_id = url_to_postid( $attributes['url'] );
		if ( $post_id ) {
			$accent_color = sanitize_hex_color( get_field( 'accent_color', $post_id ) ?: '' );
		}
	}

	// Nothing useful to render without at least a title or URL.
	if ( ! $title && ! $url ) {
		return '';
	}

	// Register this icon for the footer sprite.
	if ( $icon_slug ) {
		momentive_use_icon( $icon_slug );
	}

	// Inline custom property carries the accent color into the CSS cascade.
	$style_attr = $accent_color
		? ' style="--icon-link-accent:' . esc_attr( $accent_color ) . '"'
		: '';

	// Icon markup
	$icon_html = '';
	if ( $icon_slug ) {
		$icon_html = sprintf(
			'<span class="icon-link__icon" aria-hidden="true">' .
			'<svg focusable="false"><use href="#icon-%s"></use></svg>' .
			'</span>',
			esc_attr( $icon_slug )
		);
	}

	// Tagline markup
	$tagline_html = $tagline
		? '<span class="icon-link__tagline">' . $tagline . '</span>'
		: '';

	return sprintf(
		'<div class="wp-block-momentive-icon-link"%s>' .
		'<a class="icon-link icon-link--%s" href="%s">' .
		'%s' .
		'<span class="icon-link__text">' .
		'<span class="icon-link__title">%s</span>' .
		'%s' .
		'</span>' .
		'</a>' .
		'</div>',
		$style_attr,
		esc_attr( $icon_size ),
		$url,
		$icon_html,
		$title,
		$tagline_html
	);
}


// ---------------------------------------------------------------------------
// Editor: pass icon list to block.js + output sprite in editor footer
// ---------------------------------------------------------------------------

add_action( 'enqueue_block_editor_assets', 'momentive_icon_block_editor_assets' );

function momentive_icon_block_editor_assets(): void {
	wp_enqueue_script( 'momentive-icon-picker' );

	$available = momentive_get_available_icons(); // [ slug => label ]
	$icons_data = [];

	foreach ( $available as $slug => $label ) {
		$parsed = momentive_parse_svg_file( $slug );
		$icons_data[ $slug ] = [
			'label'   => $label,
			'viewBox' => $parsed ? $parsed['viewBox'] : '0 0 24 24',
			'inner'   => $parsed ? $parsed['inner']   : '',
		];
	}

	wp_localize_script( 'momentive-icon-picker', 'momentiveIcons', [
		'available' => $available,   // keep for backward compat (icon-picker uses this)
		'svgData'   => $icons_data,  // new: full SVG data for inline rendering
	] );
}

// Output the SVG sprite inside the block editor.

// Outer admin document — serves the sidebar icon picker.
add_action( 'admin_footer', function () {
	if ( ! get_current_screen()?->is_block_editor() ) {
		return;
	}
	momentive_output_svg_symbols();
} );

// Editor iframe canvas — serves block previews.
add_action( 'enqueue_block_editor_assets', 'momentive_inject_svg_sprite_into_editor' );

function momentive_inject_svg_sprite_into_editor(): void {
	// Capture the sprite markup as a string.
	ob_start();
	momentive_output_svg_symbols();
	$sprite = ob_get_clean();

	// Escape for use inside a JS string.
	$sprite_js = wp_json_encode( $sprite );

	// Inject into the editor iframe's document once it's ready.
	wp_add_inline_script( 'momentive-icon-picker', <<<JS
		( function () {
			function injectSprite( doc ) {
				if ( doc.getElementById( 'momentive-svg-sprite' ) ) return;
				var div = doc.createElement( 'div' );
				div.id = 'momentive-svg-sprite';
				div.innerHTML = $sprite_js;
				doc.body.insertBefore( div, doc.body.firstChild );
			}

			// The iframe canvas isn't available immediately — poll for it.
			function tryInject() {
				var iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
				if ( iframe && iframe.contentDocument && iframe.contentDocument.body ) {
					injectSprite( iframe.contentDocument );
				} else {
					setTimeout( tryInject, 100 );
				}
			}

			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', tryInject );
			} else {
				tryInject();
			}
		} )();
		JS
	);
}

// ---------------------------------------------------------------------------
// Admin: Icons gallery page (Appearance → Icons)
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'momentive_register_icons_admin_page' );

function momentive_register_icons_admin_page(): void {
	add_theme_page(
		__( 'Icons', 'momentive' ),
		__( 'Icons', 'momentive' ),
		'edit_posts',
		'momentive-icons',
		'momentive_render_icons_admin_page'
	);
}

function momentive_render_icons_admin_page(): void {
	$icons    = momentive_get_available_icons();
	$icon_dir = get_stylesheet_directory() . '/assets/icons/';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Icons', 'momentive' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: path to icons directory */
				esc_html__( 'SVG files in %s. Add or remove files to update this list.', 'momentive' ),
				'<code>' . esc_html( str_replace( ABSPATH, '/', $icon_dir ) ) . '</code>'
			);
			?>
		</p>

		<?php if ( empty( $icons ) ) : ?>
			<p><?php esc_html_e( 'No icons found. Add .svg files to the assets/icons/ directory.', 'momentive' ); ?></p>
		<?php else : ?>
			<style>
				.momentive-icon-gallery {
					display: grid;
					grid-template-columns: repeat( auto-fill, minmax( 120px, 1fr ) );
					gap: 16px;
					margin-top: 20px;
				}
				.momentive-icon-gallery__item {
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 8px;
					padding: 16px 8px;
					background: #fff;
					border: 1px solid #ddd;
					border-radius: 4px;
					text-align: center;
				}
				.momentive-icon-gallery__item svg {
					width: 40px;
					height: 40px;
					fill: #1d2327;
				}
				.momentive-icon-gallery__slug {
					font-family: monospace;
					font-size: 11px;
					color: #50575e;
					word-break: break-all;
				}
			</style>

			<!-- Inline the sprite so icons render on this page -->
			<?php momentive_output_svg_symbols(); ?>

			<div class="momentive-icon-gallery">
				<?php foreach ( $icons as $slug => $label ) : ?>
					<div class="momentive-icon-gallery__item">
						<svg aria-hidden="true" focusable="false">
							<use href="#icon-<?php echo esc_attr( $slug ); ?>"></use>
						</svg>
						<span class="momentive-icon-gallery__label"><?php echo esc_html( $label ); ?></span>
						<span class="momentive-icon-gallery__slug"><?php echo esc_html( $slug ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}