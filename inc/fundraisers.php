<?php
/**
 * Fundraisers CPT and taxonomies.
 *
 * Registers the `fundraiser` custom post type and its two flat taxonomies:
 * `organization_type` and `fundraising_features`.
 *
 * Individual posts have no public permalinks — the CPT exists purely as a
 * data source for the filterable grid on /solutions/fundraising-software/ideas/.
 *
 * ACF fields: `organization_name` (text), `campaign_link` (url).
 * Both are exposed via register_rest_field() so the resource-filters JS card
 * renderer can read them from the /wp/v2/fundraisers REST endpoint.
 *
 * @package Momentive
 */

if ( ! function_exists( 'momentive_register_fundraiser_cpt' ) ) {
	/**
	 * Register the Fundraiser custom post type.
	 */
	function momentive_register_fundraiser_cpt(): void {
		$labels = [
			'name'               => _x( 'Fundraisers', 'post type general name', 'momentive' ),
			'singular_name'      => _x( 'Fundraiser', 'post type singular name', 'momentive' ),
			'add_new'            => __( 'Add New', 'momentive' ),
			'add_new_item'       => __( 'Add New Fundraiser', 'momentive' ),
			'edit_item'          => __( 'Edit Fundraiser', 'momentive' ),
			'new_item'           => __( 'New Fundraiser', 'momentive' ),
			'view_item'          => __( 'View Fundraiser', 'momentive' ),
			'view_items'         => __( 'View Fundraisers', 'momentive' ),
			'search_items'       => __( 'Search Fundraisers', 'momentive' ),
			'not_found'          => __( 'No fundraisers found.', 'momentive' ),
			'not_found_in_trash' => __( 'No fundraisers found in Trash.', 'momentive' ),
			'all_items'          => __( 'All Fundraisers', 'momentive' ),
			'menu_name'          => __( 'Fundraisers', 'momentive' ),
			'name_admin_bar'     => __( 'Fundraiser', 'momentive' ),
		];

		register_post_type( 'fundraiser', [
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'rest_base'          => 'fundraisers',
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-lightbulb',
			'menu_position'      => 26,
			'supports'           => [ 'title', 'thumbnail', 'excerpt', 'revisions' ],
			'has_archive'        => false,
			'publicly_queryable' => true,  // Required for REST + query filters
			'rewrite'            => false,
		] );
	}
}
add_action( 'init', 'momentive_register_fundraiser_cpt' );

if ( ! function_exists( 'momentive_register_fundraiser_taxonomies' ) ) {
	/**
	 * Register organization_type and fundraising_features taxonomies.
	 *
	 * Slugs match the legacy site exactly to avoid remigrating term assignments.
	 */
	function momentive_register_fundraiser_taxonomies(): void {
		// Organization Type — the sector the nonprofit serves (e.g. "Human Services").
		register_taxonomy( 'organization_type', [ 'fundraiser' ], [
			'labels'            => [
				'name'          => _x( 'Organization Types', 'taxonomy general name', 'momentive' ),
				'singular_name' => _x( 'Organization Type', 'taxonomy singular name', 'momentive' ),
				'search_items'  => __( 'Search Organization Types', 'momentive' ),
				'all_items'     => __( 'All Organization Types', 'momentive' ),
				'edit_item'     => __( 'Edit Organization Type', 'momentive' ),
				'update_item'   => __( 'Update Organization Type', 'momentive' ),
				'add_new_item'  => __( 'Add New Organization Type', 'momentive' ),
				'new_item_name' => __( 'New Organization Type Name', 'momentive' ),
				'menu_name'     => __( 'Organization Types', 'momentive' ),
			],
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );

		// Fundraising Features — GiveSmart features showcased (e.g. "Auctions", "Peer-to-Peer").
		register_taxonomy( 'fundraising_features', [ 'fundraiser' ], [
			'labels'            => [
				'name'          => _x( 'Fundraising Features', 'taxonomy general name', 'momentive' ),
				'singular_name' => _x( 'Fundraising Feature', 'taxonomy singular name', 'momentive' ),
				'search_items'  => __( 'Search Fundraising Features', 'momentive' ),
				'all_items'     => __( 'All Fundraising Features', 'momentive' ),
				'edit_item'     => __( 'Edit Fundraising Feature', 'momentive' ),
				'update_item'   => __( 'Update Fundraising Feature', 'momentive' ),
				'add_new_item'  => __( 'Add New Fundraising Feature', 'momentive' ),
				'new_item_name' => __( 'New Fundraising Feature Name', 'momentive' ),
				'menu_name'     => __( 'Fundraising Features', 'momentive' ),
			],
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );
	}
}
add_action( 'init', 'momentive_register_fundraiser_taxonomies' );

/**
 * Expose ACF fields in the REST API so the resource-filters JS renderer can
 * read them without a separate request.
 */
add_action( 'rest_api_init', function (): void {
	register_rest_field( 'fundraiser', 'organization_name', [
		'get_callback' => fn( array $post ) => (string) get_field( 'organization_name', $post['id'] ),
		'schema'       => [ 'type' => 'string', 'context' => [ 'view', 'embed' ] ],
	] );

	register_rest_field( 'fundraiser', 'campaign_link', [
		'get_callback' => fn( array $post ) => (string) get_field( 'campaign_link', $post['id'] ),
		'schema'       => [ 'type' => 'string', 'format' => 'uri', 'context' => [ 'view', 'embed' ] ],
	] );
} );
