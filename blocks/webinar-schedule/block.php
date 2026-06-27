<?php
add_action( 'acf/init', function() {
	acf_register_block_type( [
		'name'            => 'webinar-schedule',
		'title'           => 'Webinar Schedule',
		'description'     => 'Shows the webinar date, time, and timezone. Hidden automatically once the webinar is on-demand.',
		'render_template' => get_template_directory() . '/blocks/webinar-schedule/webinar-schedule.php',
		'category'        => 'theme',
		'icon'            => 'calendar-alt',
		'keywords'        => [ 'webinar', 'schedule', 'date', 'time' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => false,
			'mode'  => false,
			'jsx'   => false,
		],
	] );
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
