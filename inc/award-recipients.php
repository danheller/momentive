<?php

/**
 * Custom Post Type: Award Recipients
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  award-recipient  (singular, hyphenated — consistent with case-study,
 *           press-article, etc.)
 * URL slug: /award-recipients/{slug}/
 *
 * Award recipients live on a single page (/bring-on-better-awards/) as a
 * filterable grid. Singular URLs exist but currently redirect: to the linked
 * blog post when one is set, otherwise to the awards page. This keeps inbound
 * links working and leaves the door open for a proper singular view later if
 * the design calls for it.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * All data lives in ACF fields (defined in acf-json/); the post body is unused.
 * Fields (matching the legacy CPT's postmeta keys):
 *   • award_received           — select: the award category name
 *   • year_awarded             — number: year of the award (e.g. 2025)
 *   • organization_logo        — image: org logo (attachment)
 *   • organization_name        — text: org display name
 *   • individual_recipient     — true/false: is this a person rather than an org?
 *   • individual_photo         — image: headshot (when individual_recipient = true)
 *   • individual_name          — text: person's name (when individual_recipient = true)
 *   • award_recipient_description — wysiwyg: description paragraph(s)
 *   • related_blog_post        — post object → post: linked blog post (optional)
 *
 * Filtering on the awards page
 * ─────────────────────────────────────────────────────────────────────────────
 * The design filters by award category and year. Both are ACF fields, not
 * taxonomies. The awards page's filter UI (via resource-filters or a custom
 * block) should query against `award_received` and `year_awarded` postmeta.
 * A taxonomy was considered but the vocabulary is locked and editors should
 * not be able to add or rename award categories — a select field is the right
 * model (same reasoning as webinar_type).
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_award_recipients_setup' );

function momentive_award_recipients_setup(): void {

	$labels = [
		'name'               => _x( 'Award Recipients', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Award Recipient', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Award Recipients', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Award Recipient', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Award Recipient', 'momentive' ),
		'new_item'           => __( 'New Award Recipient', 'momentive' ),
		'edit_item'          => __( 'Edit Award Recipient', 'momentive' ),
		'view_item'          => __( 'View Award Recipient', 'momentive' ),
		'all_items'          => __( 'All Award Recipients', 'momentive' ),
		'search_items'       => __( 'Search Award Recipients', 'momentive' ),
		'not_found'          => __( 'No award recipients found.', 'momentive' ),
		'not_found_in_trash' => __( 'No award recipients found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,   // Required for Query Loop to query this type
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-awards',
		'menu_position'      => 37,
		'show_in_rest'       => true,   // Block editor
		'supports'           => [
			'title',      // Recipient/org name as post title
			'excerpt',    // Optional: card excerpt fallback
			'thumbnail',  // Optional featured image
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'award-recipients',
			'with_front' => false,
		],
		'has_archive'        => false,  // No archive — the awards page is hand-built
		'show_in_nav_menus'  => false,
		'capability_type'    => 'post',
		'taxonomies'         => [],
		'template_lock'      => false,
	];

	register_post_type( 'award-recipient', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Singular redirect
// ─────────────────────────────────────────────────────────────────────────────
//
// Singular award-recipient URLs redirect rather than render. If a related blog
// post is set, redirect there (permanent — the blog post is the canonical
// destination for that recipient's story). Otherwise fall back to the awards
// page. This keeps any inbound links working and leaves the door open for a
// proper singular view later without a change in architecture.

add_action( 'template_redirect', 'momentive_award_recipient_redirect' );

function momentive_award_recipient_redirect(): void {
	if ( ! is_singular( 'award-recipient' ) ) {
		return;
	}

	$post_id          = get_the_ID();
	$related_blog_id  = get_field( 'related_blog_post', $post_id );
	$redirect_url     = '';

	if ( $related_blog_id ) {
		// get_field returns a post object or ID depending on field return format.
		if ( is_object( $related_blog_id ) ) {
			$redirect_url = get_permalink( $related_blog_id->ID );
		} elseif ( is_int( $related_blog_id ) || is_numeric( $related_blog_id ) ) {
			$redirect_url = get_permalink( (int) $related_blog_id );
		}
	}

	if ( ! $redirect_url ) {
		// Fall back to the awards page by slug.
		$awards_page = get_page_by_path( 'bring-on-better-awards' );
		$redirect_url = $awards_page ? get_permalink( $awards_page ) : home_url( '/bring-on-better-awards/' );
	}

	wp_redirect( $redirect_url, 301 );
	exit;
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-25.1';
	if ( get_option( 'momentive_award_recipient_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false );
		update_option( 'momentive_award_recipient_rewrite_stamp', $stamp );
	}
}, 11 );


// ─────────────────────────────────────────────────────────────────────────────
// Admin: "Award" column showing award_received + year_awarded
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'manage_award-recipient_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['award_category'] = __( 'Award', 'momentive' );
			$new['award_year']     = __( 'Year', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_award-recipient_posts_custom_column', function( string $column, int $post_id ): void {
	if ( 'award_category' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'award_received', true ) ?: '—' );
	}
	if ( 'award_year' === $column ) {
		echo esc_html( get_post_meta( $post_id, 'year_awarded', true ) ?: '—' );
	}
}, 10, 2 );

add_filter( 'manage_edit-award-recipient_sortable_columns', function( array $cols ): array {
	$cols['award_year'] = 'award_year';
	return $cols;
} );

add_action( 'pre_get_posts', function( \WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) return;
	if ( $query->get( 'post_type' ) !== 'award-recipient' ) return;
	if ( $query->get( 'orderby' ) === 'award_year' ) {
		$query->set( 'meta_key', 'year_awarded' );
		$query->set( 'orderby', 'meta_value_num' );
	}
} );
