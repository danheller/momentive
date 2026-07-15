<?php
add_action( 'init', function (): void {
	register_block_type( get_template_directory() . '/blocks/webinar-form-heading' );
} );
