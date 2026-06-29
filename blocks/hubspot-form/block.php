<?php
add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/hubspot-form' );
} );

add_action( 'enqueue_block_assets', function() {
	if ( ! momentive_content_has_block( 'acf/hubspot-form' ) ) return;

	wp_enqueue_style(
		'momentive-hubspot',
		get_template_directory_uri() . '/blocks/hubspot-form/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'momentive-hubspot',
		get_template_directory_uri() . '/blocks/hubspot-form/hubspot.js',
		[],
		wp_get_theme()->get( 'Version' ),
		[ 'strategy' => 'defer', 'in_footer' => true ]
	);

} );
