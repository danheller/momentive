<?php

/**
 * Custom Post Type: Events
 *
 * CPT key:  event  (singular, matching the pattern of webinar, whitepaper, etc.)
 * URL slug: /events/{slug}/
 *
 * Events are live or virtual gatherings — conferences, user groups, summits,
 * trade shows, and so on. Unlike webinars, there is no upcoming/on-demand
 * lifecycle field yet; that can be added as an ACF field group when the
 * editorial need arises.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * Currently entirely bespoke block editor content — no ACF fields defined.
 * The existing published event post was hand-built; ACF field groups can be
 * added later via the ACF UI (acf-json/ auto-syncs them) once content
 * patterns are understood from multiple events.
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions"
 * parent term), identical to webinars, whitepapers, case studies, etc.
 * Included in momentive_get_resource_post_types() so events appear in the
 * Resources archive filter and Solution-page resource grids.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_events_setup' );

function momentive_events_setup(): void {

	$labels = [
		'name'               => _x( 'Events', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Event', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Events', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Event', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Event', 'momentive' ),
		'new_item'           => __( 'New Event', 'momentive' ),
		'edit_item'          => __( 'Edit Event', 'momentive' ),
		'view_item'          => __( 'View Event', 'momentive' ),
		'all_items'          => __( 'All Events', 'momentive' ),
		'search_items'       => __( 'Search Events', 'momentive' ),
		'not_found'          => __( 'No events found.', 'momentive' ),
		'not_found_in_trash' => __( 'No events found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-calendar-alt',
		'menu_position'      => 38,
		'show_in_rest'       => true,   // Block editor
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'events',
			'with_front' => false,
		],
		'has_archive'        => 'events',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
	];

	register_post_type( 'event', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Event type label
//
// Returns the display label for an event based on its `event_type` ACF field.
// Used by story-card.php and any other template that needs this string.
//
//   virtual  → "Virtual Event"  (default)
//   physical → "In-Person Event"
// ─────────────────────────────────────────────────────────────────────────────

function momentive_event_type_label( int $post_id = 0 ): string {
	$type = get_field( 'event_type', $post_id ?: null );
	return $type === 'physical' ? 'In-Person Event' : 'Virtual Event';
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-27.1';
	if ( get_option( 'momentive_event_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_event_rewrite_stamp', $stamp );
	}
}, 11 ); // after register_post_type (priority 10)
