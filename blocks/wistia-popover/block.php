<?php
/**
 * Wistia Popover Video block — registration and asset enqueue.
 *
 * Loads the Wistia player.js only on pages that contain this block.
 * The Wistia web component (<wistia-player>) relies on that script to
 * define the custom element; without it the element renders as unknown HTML.
 */

add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/wistia-popover' );
} );

add_action( 'enqueue_block_assets', function() {
	if ( is_admin() ) return;
	if ( ! momentive_content_has_block( 'momentive/wistia-popover' ) ) return;

	// Wistia player — defines the <wistia-player> web component.
	wp_enqueue_script(
		'wistia-player',
		'https://fast.wistia.com/player.js',
		[],
		null,
		[ 'strategy' => 'async', 'in_footer' => true ]
	);

	wp_enqueue_style(
		'momentive-wistia-popover',
		get_template_directory_uri() . '/blocks/wistia-popover/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	// view.js: wires .js-video-play buttons to the nearest wistia-player.
	// Deferred so it runs after Wistia's async player.js, but the click handler
	// itself guards against the player not yet being defined.
	wp_enqueue_script(
		'momentive-wistia-popover-view',
		get_template_directory_uri() . '/blocks/wistia-popover/view.js',
		[],
		wp_get_theme()->get( 'Version' ),
		[ 'strategy' => 'defer', 'in_footer' => true ]
	);
} );
