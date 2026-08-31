<?php

/**
 * Block: momentive/client-marquee
 *
 * A scrolling marquee or static grid of client logos, queried dynamically from
 * the Clients CPT. Attributes control:
 *
 *   mode             — 'marquee' (Splide AutoScroll) or 'grid' (static CSS grid)
 *   logoVariant      — 'mono' (ACF logo_mono field) or 'color' (featured image)
 *                      Falls back to featured image when logo_mono is empty.
 *   gridSize         — 'large' | 'medium' | 'small' (grid mode only)
 *   count            — max logos to query (default 20)
 *   heading          — optional eyebrow text above the logos
 *   filterByCategory — category term ID (0 = all)
 *   filterByTag      — post_tag term ID (0 = all)
 *
 * Marquee mode reuses the existing sliders.js `setupautosliding()` function by
 * emitting a `<div class="autoslider client-logos">` wrapper containing `<figure>`
 * elements — the same pattern `setupautosliding()` already handles. sliders.js is
 * conditionally enqueued via the render_block filter below.
 *
 * Grid mode is purely CSS — the `.client-logos.grid` structure plus size modifiers
 * (.medium, .small) handled in client-marquee.css.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function (): void {

	wp_register_script(
		'momentive-client-marquee-editor',
		get_template_directory_uri() . '/blocks/client-marquee/editor.js',
		[
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-api-fetch',
			'wp-i18n',
		],
		filemtime( get_template_directory() . '/blocks/client-marquee/editor.js' ),
		true
	);

	register_block_type(
		get_template_directory() . '/blocks/client-marquee/block.json',
		[
			'api_version'     => 3,
			'editor_script'   => 'momentive-client-marquee-editor',
			'render_callback' => 'momentive_render_client_marquee',
			'attributes'      => [
				'mode'             => [ 'type' => 'string', 'default' => 'marquee' ],
				'logoVariant'      => [ 'type' => 'string', 'default' => 'mono' ],
				'gridSize'         => [ 'type' => 'string', 'default' => 'medium' ],
				'count'            => [ 'type' => 'number', 'default' => 20 ],
				'filterByCategory' => [ 'type' => 'number', 'default' => 0 ],
				'filterByTag'      => [ 'type' => 'number', 'default' => 0 ],
				'showName'         => [ 'type' => 'boolean', 'default' => false ],
				'fadedLogos'       => [ 'type' => 'boolean', 'default' => false ],
				'twoRow'           => [ 'type' => 'boolean', 'default' => false ],
				'showMask'         => [ 'type' => 'boolean', 'default' => true ],
				'className'        => [ 'type' => 'string', 'default' => '' ],
				'anchor'           => [ 'type' => 'string', 'default' => '' ],
				'style'            => [ 'type' => 'object' ],
			],
		]
	);
} );

function momentive_render_client_marquee( array $attributes, string $content, WP_Block $block ): string {
	ob_start();
	require __DIR__ . '/render.php';
	return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// Conditional CSS enqueue
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function (): void {
	wp_register_style(
		'momentive-client-marquee',
		get_template_directory_uri() . '/blocks/client-marquee/client-marquee.css',
		[ 'momentive' ],
		wp_get_theme()->get( 'Version' )
	);
} );

add_action( 'enqueue_block_assets', function (): void {
	$on_singular = momentive_content_has_block( 'momentive/client-marquee' );
	$on_archive  = is_post_type_archive( 'solutions' );
	if ( ! is_admin() && ! $on_singular && ! $on_archive ) return;
	wp_enqueue_style( 'momentive-client-marquee' );
} );
