<?php
add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/recording' );
} );

add_action( 'enqueue_block_assets', function() {
	if ( ! momentive_content_has_block( 'acf/recording' ) ) return;

	wp_enqueue_style(
		'momentive-recording',
		get_template_directory_uri() . '/blocks/recording/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );
