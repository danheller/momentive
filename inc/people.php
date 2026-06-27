<?php

/**
 * Custom Post Type: People
 *
 * Unified profile type for leadership, blog authors, and webinar presenters.
 * Differentiated by the non-exclusive 'person_role' taxonomy. A profile may
 * optionally be linked to a WP user (see linked_user field) for self-publishing.
 */

function momentive_people_setup() {
	$labels = array(
		'name'               => _x( 'People', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Person', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'People', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Person', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add Person', 'momentive' ),
		'add_new_item'       => __( 'Add a Person', 'momentive' ),
		'new_item'           => __( 'New Person', 'momentive' ),
		'edit_item'          => __( 'Edit Person', 'momentive' ),
		'view_item'          => __( 'View Person', 'momentive' ),
		'all_items'          => __( 'All People', 'momentive' ),
		'search_items'       => __( 'Search People', 'momentive' ),
		'parent_item_colon'  => __( 'Parent Person:', 'momentive' ),
		'not_found'          => __( 'No people found.', 'momentive' ),
		'not_found_in_trash' => __( 'No people found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-groups',
		'menu_position'      => 50,
		'show_in_rest'       => true,
		'taxonomies'         => array( 'person_role' ),
		'supports'           => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		),
		// URL structure
		'rewrite'            => array(
			'slug'       => 'people',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => false,
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'people', $args );
}
add_action( 'init', 'momentive_people_setup' );


/**
 * Taxonomy: Person Role
 *
 * Non-exclusive classification (leader / author / presenter). A single person
 * may hold several roles, so templates must not assume one term per person.
 */
function momentive_person_role_setup() {
	$labels = array(
		'name'              => _x( 'Roles', 'taxonomy general name', 'momentive' ),
		'singular_name'     => _x( 'Role', 'taxonomy singular name', 'momentive' ),
		'menu_name'         => __( 'Roles', 'momentive' ),
		'all_items'         => __( 'All Roles', 'momentive' ),
		'edit_item'         => __( 'Edit Role', 'momentive' ),
		'view_item'         => __( 'View Role', 'momentive' ),
		'update_item'       => __( 'Update Role', 'momentive' ),
		'add_new_item'      => __( 'Add New Role', 'momentive' ),
		'new_item_name'     => __( 'New Role Name', 'momentive' ),
		'search_items'      => __( 'Search Roles', 'momentive' ),
		'not_found'         => __( 'No roles found.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false, // tag-like, as you intended
		'show_in_rest'       => true,
		'show_admin_column'  => true,  // surfaces role in the People list table
		'show_in_nav_menus'  => true,
		'rewrite'            => array(
			'slug'       => 'role',
			'with_front' => false,
		),
	);
	register_taxonomy( 'person_role', array( 'people' ), $args );
}
add_action( 'init', 'momentive_person_role_setup' );


/**
 * Seed the fixed Person Role vocabulary and lock it.
 *
 * The role taxonomy is a closed set (leader / author / presenter). We insert
 * the canonical terms once and remove the UI for adding or deleting terms so
 * editors can only assign existing roles, never invent new ones.
 */
function momentive_seed_person_roles() {
	$roles = array(
		'leader'    => 'Leader',
		'author'    => 'Author',
		'presenter' => 'Presenter',
	);

	foreach ( $roles as $slug => $name ) {
		if ( ! term_exists( $slug, 'person_role' ) ) {
			wp_insert_term( $name, 'person_role', array( 'slug' => $slug ) );
		}
	}
}
add_action( 'init', 'momentive_seed_person_roles', 20 ); // after taxonomy registration

/**
 * Lock the Person Role vocabulary: remove create/edit/delete/assign-new
 * capabilities so the meta box becomes a checklist of the fixed terms only.
 */
function momentive_lock_person_roles( $args, $taxonomy ) {
	if ( 'person_role' !== $taxonomy ) {
		return $args;
	}
	$args['capabilities'] = array(
		'manage_terms' => 'do_not_allow', // hides the Roles admin submenu
		'edit_terms'   => 'do_not_allow', // no renaming/editing terms
		'delete_terms' => 'do_not_allow', // no deleting terms
		'assign_terms' => 'edit_posts',   // editors can still assign existing roles
	);
	return $args;
}
add_filter( 'register_taxonomy_args', 'momentive_lock_person_roles', 10, 2 );