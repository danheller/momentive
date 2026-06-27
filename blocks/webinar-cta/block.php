<?php
add_action( 'acf/init', function() {
	acf_register_block_type( [
		'name'            => 'webinar-cta',
		'title'           => 'Webinar CTA Link',
		'description'     => 'Shows a "Register now" or "Watch now" link based on the webinar\'s status.',
		'render_template' => get_template_directory() . '/blocks/webinar-cta/webinar-cta.php',
		'category'        => 'theme',
		'icon'            => 'external',
		'keywords'        => [ 'webinar', 'cta', 'register', 'watch', 'link' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => false,
			'mode'  => false,
			'jsx'   => false,
		],
	] );
} );