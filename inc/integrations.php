<?php
/**
 * Integrations CPT and taxonomies.
 *
 * Registers the `integration` custom post type and its two flat taxonomies:
 * `integration_type` and `integration_capability`.
 *
 * @package Momentive
 */

if ( ! function_exists( 'momentive_register_integration_cpt' ) ) {
	/**
	 * Register the Integration custom post type.
	 */
	function momentive_register_integration_cpt(): void {
		$labels = [
			'name'                  => _x( 'Integrations', 'post type general name', 'momentive' ),
			'singular_name'         => _x( 'Integration', 'post type singular name', 'momentive' ),
			'add_new'               => __( 'Add New', 'momentive' ),
			'add_new_item'          => __( 'Add New Integration', 'momentive' ),
			'edit_item'             => __( 'Edit Integration', 'momentive' ),
			'new_item'              => __( 'New Integration', 'momentive' ),
			'view_item'             => __( 'View Integration', 'momentive' ),
			'view_items'            => __( 'View Integrations', 'momentive' ),
			'search_items'          => __( 'Search Integrations', 'momentive' ),
			'not_found'             => __( 'No integrations found.', 'momentive' ),
			'not_found_in_trash'    => __( 'No integrations found in Trash.', 'momentive' ),
			'all_items'             => __( 'All Integrations', 'momentive' ),
			'menu_name'             => __( 'Integrations', 'momentive' ),
			'name_admin_bar'        => __( 'Integration', 'momentive' ),
		];

		register_post_type( 'integration', [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-rest-api',
			'menu_position'       => 25,
			'supports'            => [ 'title', 'thumbnail', 'revisions' ],
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'rewrite'             => false,
		] );
	}
}
add_action( 'init', 'momentive_register_integration_cpt' );

if ( ! function_exists( 'momentive_register_integration_taxonomies' ) ) {
	/**
	 * Register integration_type and integration_capability taxonomies.
	 */
	function momentive_register_integration_taxonomies(): void {
		// integration_type
		register_taxonomy( 'integration_type', [ 'integration' ], [
			'labels'            => [
				'name'              => _x( 'Types', 'taxonomy general name', 'momentive' ),
				'singular_name'     => _x( 'Type', 'taxonomy singular name', 'momentive' ),
				'search_items'      => __( 'Search Types', 'momentive' ),
				'all_items'         => __( 'All Types', 'momentive' ),
				'edit_item'         => __( 'Edit Type', 'momentive' ),
				'update_item'       => __( 'Update Type', 'momentive' ),
				'add_new_item'      => __( 'Add New Type', 'momentive' ),
				'new_item_name'     => __( 'New Type Name', 'momentive' ),
				'menu_name'         => __( 'Types', 'momentive' ),
			],
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );

		// integration_capability
		register_taxonomy( 'integration_capability', [ 'integration' ], [
			'labels'            => [
				'name'              => _x( 'Capabilities', 'taxonomy general name', 'momentive' ),
				'singular_name'     => _x( 'Capability', 'taxonomy singular name', 'momentive' ),
				'search_items'      => __( 'Search Capabilities', 'momentive' ),
				'all_items'         => __( 'All Capabilities', 'momentive' ),
				'edit_item'         => __( 'Edit Capability', 'momentive' ),
				'update_item'       => __( 'Update Capability', 'momentive' ),
				'add_new_item'      => __( 'Add New Capability', 'momentive' ),
				'new_item_name'     => __( 'New Capability Name', 'momentive' ),
				'menu_name'         => __( 'Capabilities', 'momentive' ),
			],
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );
	}
}
add_action( 'init', 'momentive_register_integration_taxonomies' );
