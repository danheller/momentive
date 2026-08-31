<?php

/**
 * Custom Post Type: Videos
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  video  (singular, matching the pattern of whitepaper, webinar, etc.)
 * URL slug: /videos/{slug}/
 *
 * Consolidates the legacy "Videos" CPT (/videos/) and "Video Testimonials" CPT
 * (/testimonials/) into a single type. The one Video Testimonial post
 * (ams-abvma) is redirected from /testimonials/ams-abvma/ to /videos/ams-abvma/
 * via the Redirection plugin. The `video_type` taxonomy (term: "testimonial")
 * preserves that distinction for filtering without requiring a separate CPT.
 *
 * Videos are gated content: a two-column layout with description + checklist on
 * the left and a HubSpot registration form on the right. After form submission
 * the Wistia video embed (stored in `video_embed_code`) is revealed. Structure
 * follows the whitepaper/infographic pattern closely.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * Post body (block editor):
 *   • Description copy
 *   • Optional checklist
 *   • acf/hubspot-form block (gated — HubSpot delivers the video link on submit)
 *
 * ACF fields (Video Settings group, group_6b1f2a3c4e001):
 *   • hero_image        — singular-view hero image override (separate from thumbnail)
 *   • video_embed_code  — Wistia embed code; store here so it can be surfaced in a
 *                         thank-you page or a JS-reveal pattern later without editing
 *                         block content
 *
 * Categorisation
 * ─────────────────────────────────────────────────────────────────────────────
 * Shared solution-scoped "category" taxonomy (children of the "Solutions" parent
 * term), identical to whitepapers, case studies, webinars, etc.
 *
 * video_type taxonomy
 * ─────────────────────────────────────────────────────────────────────────────
 * Flat, locked vocabulary (same pattern as person_role). Seeded term: "testimonial".
 * Editors see a checkbox meta box; they cannot add or rename terms — adding a new
 * type is a one-line code change in momentive_seed_video_types() below.
 * manage/edit/delete caps set to do_not_allow (also hides the admin submenu).
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_videos_setup' );

// Front-end styles for the single-video view.
// Reuses gate.css (same two-column gated layout as whitepapers/infographics).
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'video' ) ) {
		wp_enqueue_style( 'momentive-gate' );
	}
} );

function momentive_videos_setup(): void {

	// ── CPT ──────────────────────────────────────────────────────────────────

	$labels = [
		'name'               => _x( 'Videos', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Video', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Videos', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Video', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Video', 'momentive' ),
		'new_item'           => __( 'New Video', 'momentive' ),
		'edit_item'          => __( 'Edit Video', 'momentive' ),
		'view_item'          => __( 'View Video', 'momentive' ),
		'all_items'          => __( 'All Videos', 'momentive' ),
		'search_items'       => __( 'Search Videos', 'momentive' ),
		'not_found'          => __( 'No videos found.', 'momentive' ),
		'not_found_in_trash' => __( 'No videos found in Trash.', 'momentive' ),
	];

	register_post_type( 'video', [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-video-alt3',
		'menu_position'      => 38,
		'show_in_rest'       => true,
		'supports'           => [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'videos',
			'with_front' => false,
		],
		'has_archive'        => 'videos',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category', 'video_type' ],
		'template'           => [],   // populated below once the pattern exists
		'template_lock'      => false,
	] );

	// ── video_type taxonomy ───────────────────────────────────────────────────

	register_taxonomy( 'video_type', [ 'video' ], [
		'labels'             => [
			'name'          => _x( 'Video Types', 'Taxonomy general name', 'momentive' ),
			'singular_name' => _x( 'Video Type', 'Taxonomy singular name', 'momentive' ),
			'menu_name'     => _x( 'Video Types', 'Admin Menu text', 'momentive' ),
		],
		'public'             => false,
		'publicly_queryable' => false,
		'hierarchical'       => false,
		'show_ui'            => true,
		'show_in_menu'       => true,   // shown under Videos in the sidebar
		'show_in_nav_menus'  => false,
		'show_in_rest'       => true,
		'show_admin_column'  => true,
		'query_var'          => false,
		// Lock the vocabulary — term management is a code change, not an editor task.
		'capabilities'       => [
			'manage_terms' => 'do_not_allow',
			'edit_terms'   => 'do_not_allow',
			'delete_terms' => 'do_not_allow',
			'assign_terms' => 'edit_posts',
		],
		'rewrite'            => false,
	] );
}


// ─────────────────────────────────────────────────────────────────────────────
// Seed video_type terms
// ─────────────────────────────────────────────────────────────────────────────
//
// Inserts the fixed vocabulary once on init (idempotent: wp_insert_term skips
// existing terms). To add a new type, append to $types here — not in the editor.

add_action( 'init', 'momentive_seed_video_types', 20 );

function momentive_seed_video_types(): void {
	$types = [
		'testimonial' => 'Testimonial',
	];

	foreach ( $types as $slug => $name ) {
		if ( ! term_exists( $slug, 'video_type' ) ) {
			wp_insert_term( $name, 'video_type', [ 'slug' => $slug ] );
		}
	}
}


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-19.1';
	if ( get_option( 'momentive_video_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false );
		update_option( 'momentive_video_rewrite_stamp', $stamp );
	}
}, 11 );


// ─────────────────────────────────────────────────────────────────────────────
// Redirect legacy Video Testimonials URLs → /videos/
// ─────────────────────────────────────────────────────────────────────────────
//
// The legacy site served video testimonials at /testimonials/{slug}/, a URL
// namespace that no longer resolves once these posts move to /videos/. This
// PHP redirect covers the one known post (ams-abvma) and any others that may
// have been indexed under that prefix. The Redirection plugin can manage this
// as a static rule if preferred — this is a belt-and-suspenders fallback.

add_action( 'template_redirect', function(): void {
	if ( ! is_404() ) return;

	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?? '', '/' );

	// Match /testimonials/{slug}/ — but only if it's NOT a real testimonial CPT post.
	if ( ! preg_match( '#^testimonials/([^/]+)/?$#', $path, $m ) ) return;

	$slug = $m[1];

	// Only redirect if a video post exists at this slug.
	$video = get_page_by_path( $slug, OBJECT, 'video' );
	if ( ! $video ) return;

	wp_redirect( home_url( '/videos/' . $slug . '/' ), 301 );
	exit;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Admin column: Video Type badge
// ─────────────────────────────────────────────────────────────────────────────
// show_admin_column on the taxonomy already adds a column, but a badge is
// cleaner than a linked term name for a short locked vocabulary.

add_filter( 'manage_video_posts_columns', function( array $columns ): array {
	// The taxonomy's auto-added column is adequate; just inject the Gated column.
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['wp_gated'] = __( 'Gated', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_video_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column !== 'wp_gated' ) return;

	$gated = str_contains( get_post_field( 'post_content', $post_id ), '<!-- wp:acf/hubspot-form' );
	if ( $gated ) {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#00a32a;color:#fff;">Gated</span>';
	} else {
		echo '<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#787c82;color:#fff;">Ungated</span>';
	}
}, 10, 2 );


// ─────────────────────────────────────────────────────────────────────────────
// Default block pattern as the new-post template
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$cpt = get_post_type_object( 'video' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/video-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );
