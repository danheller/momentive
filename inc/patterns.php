<?php
/**
 * Add a "Patterns" item to the dashboard left menu with submenu links
 * to synced patterns, theme patterns, and "Add New".
 */

add_action( 'admin_menu', function () {

	// Point the top-level item directly to the patterns list.
	// The empty string for the callback means no page is rendered —
	// clicking the item just follows the URL.
	add_menu_page(
		'Patterns',
		'Patterns',
		'edit_posts',
		'edit.php?post_type=wp_block', // ← direct URL, no callback needed
		'',
		'dashicons-layout',
		4
	);

	add_submenu_page(
		'edit.php?post_type=wp_block', // ← must match the parent slug exactly
		'Synced Patterns',
		'Synced Patterns',
		'edit_posts',
		'edit.php?post_type=wp_block'
	);

	add_submenu_page(
		'edit.php?post_type=wp_block',
		'Theme Patterns',
		'Theme Patterns',
		'edit_theme_options',
		'site-editor.php?path=/patterns'
	);

	add_submenu_page(
		'edit.php?post_type=wp_block',
		'Add New Pattern',
		'Add New',
		'edit_posts',
		'post-new.php?post_type=wp_block'
	);

} );

add_filter( 'parent_file', function ( $parent_file ) {
	global $current_screen;
	if ( $current_screen && $current_screen->post_type === 'wp_block' ) {
		return 'edit.php?post_type=wp_block'; // 'momentive-patterns';
	}
	return $parent_file;
} );

add_filter( 'submenu_file', function ( $submenu_file ) {
	global $current_screen;
	if ( $current_screen && $current_screen->post_type === 'wp_block' ) {
		return 'edit.php?post_type=wp_block';
	}
	return $submenu_file;
} );

/**
 * Add custom categories for block patterns
 */

add_action( 'init', function () {
	register_block_pattern_category( 'momentive-section',   [ 'label' => 'Section' ] );
	register_block_pattern_category( 'momentive-hero',      [ 'label' => 'Hero' ] );
	register_block_pattern_category( 'momentive-card',      [ 'label' => 'Card' ] );
	register_block_pattern_category( 'momentive-cta',       [ 'label' => 'Call to Action' ] );
} );

 