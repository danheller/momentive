<?php
/**
 * Mega Menu Panel block registration.
 *
 * Source:       blocks/megamenu-panel/src/
 * Built output: blocks/build/megamenu-panel/
 *
 * Uses InnerBlocks — panel content is serialized into post content and
 * rendered directly by WordPress. No PHP render callback needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function momentive_register_megamenu_panel_block(): void {
	register_block_type( __DIR__ );
}
add_action( 'init', 'momentive_register_megamenu_panel_block' );
