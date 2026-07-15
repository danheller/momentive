<?php
/**
 * Impact Stat block registration.
 *
 * Registers the momentive/impact-stat block.
 * Source: blocks/impact-stat/src/
 * Built output: blocks/build/impact-stat/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the momentive/impact-stat block.
 */
function momentive_register_impact_stat_block(): void {
	register_block_type( __DIR__ );
}
add_action( 'init', 'momentive_register_impact_stat_block' );
