<?php

/**
 * Custom Post Type: Clients
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  client  (singular, matching the pattern of whitepaper, webinar, etc.)
 *
 * Data store only — no public-facing URLs. Client posts are queried and
 * displayed by blocks (marquee, static grids, etc.), never visited directly.
 *
 * Taxonomy tagging
 * ─────────────────────────────────────────────────────────────────────────────
 * Two shared taxonomies are registered on this CPT so Query Loop and custom
 * blocks can filter logos by context:
 *
 *   category  — Solution-scoped categories (children of the "Solutions" parent
 *               term). Tag a client here to associate it with a Solution family
 *               (e.g. "Event Management Software"). Also used for industry
 *               pages: if industry terms are added as children of a separate
 *               "Industries" parent category, the same taxonomy covers both.
 *
 *   post_tag  — Free-form tagging. Use for product associations ("path-lms"),
 *               context flags ("marquee", "homepage"), or anything that doesn't
 *               fit the solution-category hierarchy. `post_tag` is natively
 *               available to the Query Loop block's taxonomy filter panel.
 *
 * Logo storage
 * ─────────────────────────────────────────────────────────────────────────────
 * Featured image  — canonical full-color logo. Used in all contexts by default.
 *
 * ACF fields (Client Settings, group_clients):
 *   logo_mono      — monochrome version for the marquee or reversed/dark
 *                    contexts. Stored as a separate image rather than relying on
 *                    CSS grayscale(), because CSS desaturation preserves luminance
 *                    (an orange logo converts lighter than a black one) and can't
 *                    produce a true, consistent monochrome across varied source
 *                    colors.
 *   client_url     — company website URL; optionally used to link logos.
 *
 * Displaying logos
 * ─────────────────────────────────────────────────────────────────────────────
 * For simple static grids: a Query Loop block filtered by category/tag works
 * without any custom block. Post title is the client name; the featured image
 * is the logo.
 *
 * For the auto-scrolling marquee (Splide): a dedicated custom block is needed
 * to drive the carousel behavior. Build that separately — this CPT registration
 * is the prerequisite.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_clients_setup' );

function momentive_clients_setup(): void {

	$labels = [
		'name'               => _x( 'Clients/Partners', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Client/Partner', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Clients/Partners', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Client/Partner', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Logo', 'momentive' ),
		'new_item'           => __( 'New Client', 'momentive' ),
		'edit_item'          => __( 'Edit Client', 'momentive' ),
		'view_item'          => __( 'View Client', 'momentive' ),
		'all_items'          => __( 'All Clients', 'momentive' ),
		'search_items'       => __( 'Search Clients', 'momentive' ),
		'not_found'          => __( 'No clients found.', 'momentive' ),
		'not_found_in_trash' => __( 'No clients found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => false,   // no public URLs — data store only
		'show_ui'            => true,    // visible in admin
		'show_in_menu'       => true,
		'show_in_rest'       => true,    // required for Query Loop + REST API access
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-building',
		'menu_position'      => 30,
		'supports'           => [
			'title',      // Client / company name
			'thumbnail',  // Full-color logo (featured image)
		],
		'taxonomies'         => [ 'category', 'post_tag' ],
		'rewrite'            => false,   // no public URLs, no rewrite rules needed
		'has_archive'        => false,
		'capability_type'    => 'post',
	];

	register_post_type( 'client', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Admin list table — logo thumbnail column
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'manage_client_posts_columns', function( array $columns ): array {
	// Insert a Logo column right after the checkbox column.
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'cb' === $key ) {
			$new['client_logo'] = __( 'Logo', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_client_posts_custom_column', function( string $column, int $post_id ): void {
	if ( 'client_logo' !== $column ) return;

	// Use 'full' size so the logo isn't cropped — WP resizes via CSS instead.
	// object-fit:contain keeps the full image within the 120×48 display cell.
	// Priority: featured image (color) → logo_mono ACF field → dash.
	$url = '';
	$alt = esc_attr( get_the_title( $post_id ) );

	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$src = wp_get_attachment_image_src( $thumb_id, 'full' );
		if ( $src ) {
			$url = esc_url( $src[0] );
		}
	}

	if ( ! $url ) {
		$mono = get_field( 'logo_mono', $post_id );
		if ( ! empty( $mono['url'] ) ) {
			$url = esc_url( $mono['url'] );
		}
	}

	if ( $url ) {
		printf(
			'<img src="%s" alt="%s" style="max-width:120px;max-height:48px;width:auto;height:auto;object-fit:contain;display:block;" />',
			$url,
			$alt
		);
	} else {
		echo '<span style="color:#aaa;">—</span>';
	}
}, 10, 2 );
