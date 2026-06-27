<?php
add_action( 'acf/init', function() {
	acf_register_block_type( [
		'name'            => 'recording',
		'title'           => 'Recording Video',
		'description'     => 'Renders the recording video embed (Wistia, Vimeo, or YouTube) from the post\'s Recording fields.',
		'render_template' => get_template_directory() . '/blocks/recording/recording.php',
		'category'        => 'theme',
		'icon'            => 'video-alt3',
		'keywords'        => [ 'recording', 'video', 'embed', 'webinar' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => false,
			'mode'  => false,
			'jsx'   => false,
		],
	] );
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
