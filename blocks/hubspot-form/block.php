<?php
add_action( 'acf/init', function() {
	acf_register_block_type( [
		'name'            => 'hubspot-form',
		'title'           => 'HubSpot Form',
		'description'     => 'Embeds a HubSpot form via embed code.',
		'render_template' => get_template_directory() . '/blocks/hubspot-form/hubspot-form.php',
		'category'        => 'embed',
		'icon'            => 'feedback',
		'keywords'        => [ 'hubspot', 'form', 'demo' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => false,
			'mode'  => false,
			'jsx'   => false,
		],
	] );
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
