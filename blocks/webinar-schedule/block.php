<?php
add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/webinar-schedule' );
} );

add_action( 'enqueue_block_assets', function() {
	$on_singular_with_block = momentive_content_has_block( 'acf/webinar-schedule' );
	$on_webinar_archive    = is_post_type_archive( 'webinar' );

	if ( ! $on_singular_with_block && ! $on_webinar_archive ) {
		return;
	}

	wp_enqueue_style(
		'momentive-webinar-schedule',
		get_template_directory_uri() . '/blocks/webinar-schedule/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );
