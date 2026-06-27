<?php
/**
 * Recordings: the shared /recordings/{slug} layer.
 *
 * A "recording" is a video that lives on some owning post (a webinar, and later
 * a product overview). Rather than give each owner type its own recording URL
 * space, every recording resolves through one flat namespace: /recordings/{slug}.
 *
 * This file is deliberately host-agnostic. The set of post types that can host a
 * recording is the 'momentive_recording_host_types' filter — adding products
 * later is a one-line filter callback, not an edit here.
 *
 *   Host types ........ momentive_recording_host_types() — filterable
 *   Resolve a slug .... momentive_recording_resolve( $slug ) — post ID or 0
 *   Has a recording ... momentive_recording_is_available( $post_id ) — bool
 *   Canonical URL ..... momentive_recording_url( $post_id ) — derived or override
 *
 * Access model: recordings are NOT gated (matching the current site). The
 * HubSpot form is a soft lead-capture step that redirects to the recording; the
 * URL itself is public. This layer only resolves and renders — it doesn't guard.
 *
 * IMPORTANT: flush rewrite rules once after deploying (visit Settings →
 * Permalinks) or the /recordings/ rule won't take effect.
 */

defined( 'ABSPATH' ) || exit;


/**
 * Post types that can host a recording.
 *
 * @return string[]
 */
function momentive_recording_host_types() {
	return (array) apply_filters( 'momentive_recording_host_types', array( 'webinar' ) );
}


/**
 * Resolve a recording slug to its owning post ID, searching across all host
 * types. Returns 0 if nothing matches.
 *
 * Slugs are supposed to be unique across host types (enforced on save, below),
 * but if a collision slips through, host-type order is the deterministic
 * tiebreak: the first type listed in momentive_recording_host_types() wins.
 *
 * @param string $slug
 * @return int Post ID, or 0.
 */
function momentive_recording_resolve( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return 0;
	}

	foreach ( momentive_recording_host_types() as $post_type ) {
		$posts = get_posts( array(
			'post_type'        => $post_type,
			'name'             => $slug,
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		) );
		if ( ! empty( $posts ) ) {
			return (int) $posts[0];
		}
	}

	return 0;
}


/**
 * Whether a post actually has a playable recording right now.
 *
 * A recording is available when a video embed exists AND — for hosts that have
 * a notion of "not yet happened" (webinars) — the event is in its on-demand
 * state. Hosts without that notion (e.g. product overviews) are available as
 * soon as a video embed is present.
 *
 * @param int $post_id
 * @return bool
 */
function momentive_recording_is_available( $post_id ) {
	$has_video = trim( (string) get_field( 'video_embed_code', $post_id ) ) !== '';
	if ( ! $has_video ) {
		return false;
	}

	// Webinars gate availability on the computed on-demand status.
	if ( 'webinar' === get_post_type( $post_id ) && function_exists( 'momentive_webinar_status' ) ) {
		return 'on-demand' === momentive_webinar_status( $post_id );
	}

	return true;
}


/**
 * Canonical recording URL for a post: the off-pattern override if set,
 * otherwise the derived /recordings/{slug}.
 *
 * @param int $post_id
 * @return string
 */
function momentive_recording_url( $post_id ) {
	$override = trim( (string) get_field( 'recording_url', $post_id ) );
	if ( '' !== $override ) {
		return $override;
	}
	$slug = get_post_field( 'post_name', $post_id );
	return home_url( user_trailingslashit( 'recordings/' . $slug ) );
}


// ─────────────────────────────────────────────────────────────────────────────
// Rewrite + query var
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	add_rewrite_rule(
		'^recordings/([^/]+)/?$',
		'index.php?recording_slug=$matches[1]',
		'top'
	);
} );

add_filter( 'query_vars', function( array $vars ) {
	$vars[] = 'recording_slug';
	$vars[] = 'recording_view';
	return $vars;
} );


// ─────────────────────────────────────────────────────────────────────────────
// Resolve the /recordings/{slug} request onto its owning post
// ─────────────────────────────────────────────────────────────────────────────
//
// We resolve the slug to its owning post and hand WordPress a normal singular
// query for that post, with recording_view flagged so the template can render
// the recording-focused view (video, no registration chrome). The URL stays
// /recordings/{slug} in the address bar.

// We resolve /recordings/{slug} to its owning post in two stages:
//
//   1. parse_query — translate the recording_slug rewrite var into a native
//      singular query (post_type + name) AND fix the conditional flags. This
//      runs AFTER WordPress computes is_home/is_singular/etc. from the parsed
//      request, which is essential: those flags are locked in before
//      pre_get_posts, so setting query vars there leaves is_home stuck on (the
//      request came in via a rewrite WordPress treated as the front page). By
//      correcting the flags here, the request is correctly classified singular.
//
//   2. The template_include filter (below) then swaps in the 'recording' block
//      template for the now-correctly-classified singular request.

add_action( 'parse_query', function( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$slug = $query->get( 'recording_slug' );
	if ( ! $slug ) {
		return;
	}

	$post_id = momentive_recording_resolve( $slug );

	// No such recording host → leave as-is; it 404s. (The not-yet-available
	// case is handled in template_redirect, which still resolves the ID.)
	if ( ! $post_id ) {
		return;
	}

	$post      = get_post( $post_id );
	$post_type = $post->post_type;

	// Point the query at the resolved post as a native singular query.
	$query->set( 'post_type', $post_type );
	$query->set( 'name', $post->post_name );
	$query->set( $post_type, $post->post_name );
	$query->set( 'recording_view', 1 );
	$query->set( 'recording_slug', '' );

	// Correct the conditional flags WordPress already computed from the rewrite
	// (which left is_home on). This is the part pre_get_posts cannot do.
	$query->is_home     = false;
	$query->is_front_page = false;
	$query->is_singular = true;
	$query->is_single   = ( 'post' === $post_type );
	$query->is_page     = ( 'page' === $post_type );
	$query->is_archive  = false;
	$query->is_404      = false;

	// Set the queried object so get_queried_object_id() and the template
	// hierarchy see the right post.
	$query->queried_object    = $post;
	$query->queried_object_id = $post_id;
} );


// Keep the URL at /recordings/{slug}. Because the query now resolves to a
// singular post, WordPress's canonical redirect would otherwise bounce to the
// post's real permalink (/webinars/{slug}). Disable it for this view.
add_filter( 'redirect_canonical', function( $redirect_url ) {
	return get_query_var( 'recording_view' ) ? false : $redirect_url;
} );


// ─────────────────────────────────────────────────────────────────────────────
// No-recording-yet → send to the registration page instead of an empty player
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'template_redirect', function() {
	if ( ! get_query_var( 'recording_view' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	// If the recording isn't available, the visitor followed a recording link
	// for something not yet watchable. Send them to the post's own page (the
	// registration front door) rather than showing nothing.
	if ( ! momentive_recording_is_available( $post_id ) ) {
		wp_safe_redirect( get_permalink( $post_id ), 302 ); // 302: may become available later
		exit;
	}
} );


// ─────────────────────────────────────────────────────────────────────────────
// Slug collision guard
// ─────────────────────────────────────────────────────────────────────────────
//
// WordPress only enforces slug uniqueness within a post type, but /recordings/
// is a flat namespace shared across host types. When a recording host is saved,
// ensure its slug doesn't already belong to a DIFFERENT host-type post; if it
// does, suffix it so the namespace stays unambiguous.

add_filter( 'wp_unique_post_slug', function( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
	$host_types = momentive_recording_host_types();

	// Only guard recording hosts.
	if ( ! in_array( $post_type, $host_types, true ) ) {
		return $slug;
	}

	$others = array_diff( $host_types, array( $post_type ) );
	if ( empty( $others ) ) {
		return $slug; // only one host type — WP's own per-type uniqueness suffices
	}

	$check = $slug;
	$n     = 2;

	while ( momentive_recording_slug_taken_elsewhere( $check, $post_id, $others ) ) {
		$check = $original_slug . '-' . $n;
		$n++;
	}

	return $check;
}, 10, 6 );


/**
 * Does this slug already belong to a published post in one of the given
 * (other) host types, excluding the post being saved?
 *
 * @param string   $slug
 * @param int      $exclude_id
 * @param string[] $post_types
 * @return bool
 */
function momentive_recording_slug_taken_elsewhere( $slug, $exclude_id, $post_types ) {
	$posts = get_posts( array(
		'post_type'        => $post_types,
		'name'             => $slug,
		'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'exclude'          => array( (int) $exclude_id ),
		'no_found_rows'    => true,
		'suppress_filters' => false,
	) );

	return ! empty( $posts );
}


// ─────────────────────────────────────────────────────────────────────────────
// Legacy /assets/* → /recordings/{slug} redirect
// ─────────────────────────────────────────────────────────────────────────────
//
// The old site served recordings under /assets/{type}/{slug} (e.g.
// /assets/on-demand-webinar/…, /assets/product-overview/…, /assets/video/…).
// Old HubSpot emails and external links still point there. Rather than
// enumerate every type prefix, we catch any /assets/* path, take the trailing
// segment as the slug, and 301 to /recordings/{slug} if it resolves.
//
// Toolkit asset hubs also lived under /assets/ but are NOT recordings; those
// get their own redirects elsewhere (handled with the hubs migration). If a
// slug doesn't resolve to a recording host, we leave it alone so the hub
// redirects (or a normal 404) can take over.

add_action( 'template_redirect', function() {
	if ( ! is_404() ) {
		return; // only act when nothing else matched
	}

	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $path || 0 !== strpos( ltrim( $path, '/' ), 'assets/' ) ) {
		return;
	}

	// Trailing path segment is the slug.
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
	$slug     = end( $segments );
	if ( ! $slug ) {
		return;
	}

	$post_id = momentive_recording_resolve( $slug );
	if ( $post_id ) {
		wp_safe_redirect( momentive_recording_url( $post_id ), 301 );
		exit;
	}
	// Unresolved → fall through (hub redirects / 404).
} );


// ─────────────────────────────────────────────────────────────────────────────
// Template selection: recording view → the recording block template
// ─────────────────────────────────────────────────────────────────────────────
//
// The recording view renders through the block-template canvas (one pass,
// correct header/footer/wp-site-blocks wrapper). Host-agnostic: works for any
// recording host (webinars now, product overviews later).
//
// Why template_include and not single_template_hierarchy:
// In this block theme the single_template_hierarchy filter was not being
// consulted for these requests (the block-template resolver had already settled
// on single-webinar), so prepending 'recording' there had no effect. The
// reliable hook is template_include — the final filter before the template
// loads, which always fires. We swap the resolved block template for our
// 'recording' template by loading it and feeding it to the canvas via the
// standard block-template globals.
//
// Requires templates/recording.html to exist.

add_filter( 'template_include', function( $template ) {
	if ( ! get_query_var( 'recording_view' ) ) {
		return $template;
	}

	// Locate our block template by slug.
	$block_template = get_block_template( get_stylesheet() . '//recording', 'wp_template' );

	if ( ! $block_template ) {
		return $template; // recording.html missing → fall back to normal template
	}

	// Feed the located template into the block-template canvas. WordPress renders
	// whatever the global "current template" points to when the canvas loads, so
	// setting these globals makes our recording template render through the same
	// one-pass canvas as any other block template.
	global $_wp_current_template_content, $_wp_current_template_id;
	$_wp_current_template_id      = $block_template->id;
	$_wp_current_template_content = $block_template->content;

	// The block canvas is rendered by WordPress's canonical template-canvas PHP.
	// Returning its path ensures the globals above are what get rendered.
	$canvas = ABSPATH . WPINC . '/template-canvas.php';
	return file_exists( $canvas ) ? $canvas : $template;
}, 99 );
