<?php
/**
 * Impact Stat block registration.
 *
 * Drop this file into your theme's inc/ or blocks/ directory and
 * require it from functions.php:
 *
 *   require_once get_template_directory() . '/inc/blocks/impact-stat.php';
 *
 * The block.json, build/index.js, build/view.js, and build/style-index.css
 * files should all live in the same directory as this PHP file
 * (e.g. inc/blocks/impact-stat/).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the momentive/impact-stat block.
 */
function momentive_register_impact_stat_block(): void {
	register_block_type( __DIR__ . '/impact-stat' );
}
add_action( 'init', 'momentive_register_impact_stat_block' );
