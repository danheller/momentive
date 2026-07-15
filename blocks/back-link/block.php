<?php
/**
 * Back Link block — registration.
 *
 * ACF handles editor UI and rendering via back-link.php.
 * No CSS needed: .back-link styles live in the global momentive.css.
 */
add_action( 'init', function (): void {
	register_block_type( get_template_directory() . '/blocks/back-link' );
} );
