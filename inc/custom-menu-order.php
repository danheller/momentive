<?php
/**
 * Reorganize the dashboard menu to make the order more logical
 */

/**
 * Move WP Activity Log to the end
 */

add_action( 'admin_menu', function() {
    global $menu;
    foreach ( $menu as $position => $item ) {
        if ( isset( $item[2] ) && $item[2] === 'wsal-auditlog' ) {
            $menu[999] = $item;
            unset( $menu[ $position ] );
            break;
        }
    }
}, 9999 );


function momentive_custom_menu_order( $menu_ord ) {
	if ( ! $menu_ord ) return true;
	
	// Customize the order of items in the array
	return array(
		'index.php',                         // Dashboard
		'separator1',                        // Separator
		'edit.php?post_type=wp_block',       // Patterns
		'upload.php',                        // Media
		'edit.php',                          // Blog
		'edit.php?post_type=press-article',  // Newsroom

	);
}
add_filter( 'custom_menu_order', 'momentive_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'momentive_custom_menu_order', 10, 1 );

