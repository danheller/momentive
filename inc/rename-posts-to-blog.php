<?php
/**
 * Use the name "Blog" in place of "Posts" in admin screens
 */

add_action( 'admin_menu', 'momentive_change_post_labels' );

add_action( 'init', 'momentive_change_post_object' );

function momentive_change_post_labels() {

	global $menu, $submenu;
	$menu[5][0] = 'Blog';
	$submenu['edit.php'][5][0]  = 'Blog Posts';
	$submenu['edit.php'][10][0] = 'Add Blog Post';
	$submenu['edit.php'][16][0] = 'Tags';

}

function momentive_change_post_object() {

	$post_type = get_post_type_object( 'post' );
	$labels = $post_type->labels;
	$labels->name               = 'Blog';
	$labels->singular_name      = 'Blog';
	$labels->add_new            = 'Add Blog Post';
	$labels->add_new_item       = 'Add Blog Post';
	$labels->edit_item          = 'Edit Blog Post';
	$labels->new_item           = 'Blog Post';
	$labels->view_item          = 'View Blog Post';
	$labels->search_items       = 'Search Blog Posts';
	$labels->not_found          = 'No blog posts found';
	$labels->not_found_in_trash = 'No blog posts found in Trash';
	$labels->all_items          = 'Blog Posts';
	$labels->menu_name          = 'Blog';
	$labels->name_admin_bar     = 'Blog Post';

}