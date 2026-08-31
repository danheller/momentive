<?php

/**
 * Custom Post Type: Who We Serve
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * CPT key:  who-we-serve
 * URL slug: /who-we-serve/{slug}/
 * Archive:  /who-we-serve/  (template TBD — archive design is still in flux)
 *
 * Each post represents a single industry or market segment Momentive serves
 * (e.g. Healthcare & Medical, Government, Associations). The CPT exists so
 * that the archive/parent page can use a Query Loop rather than hand-placed
 * blocks — important once the archive design settles, since excerpt and
 * featured image both need to be queryable.
 *
 * Content architecture
 * ─────────────────────────────────────────────────────────────────────────────
 * • post title     — industry name, shown in card headings and page titles
 * • post excerpt   — short blurb for the card grid (no fallback to full content
 *                    — get_the_excerpt filter in functions.php already ensures
 *                    blank excerpts stay blank on cards)
 * • thumbnail      — card image / featured image for the archive grid
 * • post content   — free-form body for the individual industry page (future;
 *                    no singular template yet)
 *
 * Taxonomy
 * ─────────────────────────────────────────────────────────────────────────────
 * Shares the built-in `category` taxonomy so that industry posts can
 * optionally be associated with solution families, consistent with other
 * resource CPTs (case-study, webinar, whitepaper, etc.). No custom taxonomy
 * for now — the archive design will clarify whether filtering is needed.
 *
 * Archive
 * ─────────────────────────────────────────────────────────────────────────────
 * `has_archive => 'who-we-serve'` reserves the URL and makes the CPT
 * queryable at that path, but no `templates/archive-who-we-serve.html`
 * exists yet. WordPress will fall back to index.html until one is added.
 * The archive design is on hold pending a design discussion.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_who_we_serve_setup' );

// Enqueue archive stylesheet on the Who We Serve archive page.
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-who-we-serve',
		get_template_directory_uri() . '/assets/css/who-we-serve.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_post_type_archive( 'who-we-serve' ) ) {
		wp_enqueue_style( 'momentive-who-we-serve' );
	}
} );

function momentive_who_we_serve_setup(): void {

	$labels = [
		'name'               => _x( 'Who We Serve', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Industry', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Who We Serve', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Who We Serve', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Industry', 'momentive' ),
		'new_item'           => __( 'New Industry', 'momentive' ),
		'edit_item'          => __( 'Edit Industry', 'momentive' ),
		'view_item'          => __( 'View Industry', 'momentive' ),
		'all_items'          => __( 'All Industries', 'momentive' ),
		'search_items'       => __( 'Search Industries', 'momentive' ),
		'not_found'          => __( 'No industries found.', 'momentive' ),
		'not_found_in_trash' => __( 'No industries found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-groups',
		'menu_position'      => 42,
		'show_in_rest'       => true,        // Block editor
		'supports'           => [
			'title',      // Industry name
			'editor',     // Body for individual industry page (future)
			'excerpt',    // Short blurb for archive card grid
			'thumbnail',  // Card image
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'who-we-serve',
			'with_front' => false,
		],
		'has_archive'        => 'who-we-serve',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // Solution-scoped categories (shared)
		'template_lock'      => false,
	];

	register_post_type( 'who-we-serve', $args );
}


// ─────────────────────────────────────────────────────────────────────────────
// Singular redirect for stub posts
// ─────────────────────────────────────────────────────────────────────────────
//
// Many industry posts exist only to populate the archive card grid — they have
// no real singular page content yet. Rather than showing a blank page, redirect
// those posts to the archive. A 302 (temporary) redirect is used because the
// singular page is expected to arrive eventually.
//
// No editor action required: the redirect fires automatically when post_content
// is empty or contains only the trivial empty-paragraph block WordPress silently
// adds the first time a post is opened in the editor. It stops firing as soon
// as real content is saved — no toggle to flip, no field to clear.

add_action( 'template_redirect', function() {
	if ( ! is_singular( 'who-we-serve' ) ) return;

	$post    = get_queried_object();
	$content = trim( $post->post_content ?? '' );

	// The block WordPress writes when a post is opened but nothing is typed.
	$trivial = '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';

	if ( empty( $content ) || $content === $trivial ) {
		$archive = get_post_type_archive_link( 'who-we-serve' );
		wp_redirect( $archive ?: home_url( '/who-we-serve/' ), 302 );
		exit;
	}
} );


// ─────────────────────────────────────────────────────────────────────────────
// One-time rewrite flush
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$stamp = '2026-08-25.1';
	if ( get_option( 'momentive_who_we_serve_rewrite_stamp' ) !== $stamp ) {
		flush_rewrite_rules( false ); // false = skip .htaccess rewrite (WP Engine manages it)
		update_option( 'momentive_who_we_serve_rewrite_stamp', $stamp );
	}
}, 20 );
