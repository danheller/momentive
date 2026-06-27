<?php
add_action( 'acf/init', function() {
	acf_register_block_type( [
		'name'            => 'webinar-status',
		'title'           => 'Webinar Status Label',
		'description'     => 'Shows an "Upcoming" or "On-Demand" label based on the webinar\'s status.',
		'render_template' => get_template_directory() . '/blocks/webinar-status/webinar-status.php',
		'category'        => 'theme',
		'icon'            => 'tag',
		'keywords'        => [ 'webinar', 'status', 'label', 'upcoming', 'on-demand' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => false,
			'mode'  => false,
			'jsx'   => false,
		],
	] );
} );
