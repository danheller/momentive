<?php
add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/webinar-cta' );
} );
