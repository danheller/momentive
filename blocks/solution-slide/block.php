<?php
/*
 * Slider card block — a single solution card for use in the query loop.
 */

add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/solution-slide' );
} );
