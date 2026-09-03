<?php
/**
 * Landing Page — simplified header / footer swap.
 *
 * Adds two ACF true/false fields to any post:
 *   landing_header  — swaps the site header to parts/header-landing pattern
 *   landing_footer  — swaps the site footer to parts/footer-landing pattern
 *
 * ACF field group to create in the UI:
 *   Title:    Landing Page Settings
 *   Location: post_type == post  OR  post_type == webinar  OR  (any other CPT)
 *   Fields:
 *     - landing_header  (true_false, default 0, label "Simplified header")
 *     - landing_footer  (true_false, default 0, label "Simplified footer")
 *
 * When landing_header is enabled:
 *   • The announcement bar is suppressed.
 *   • The header template part output is replaced with momentive/header-landing.
 * When landing_footer is enabled:
 *   • The footer template part output is replaced with momentive/footer-landing.
 *
 * Both landing patterns are editable in the Site Editor.
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Whether the current singular post requests the landing header.
 * Result is cached per request.
 */
function momentive_is_landing_header(): bool {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	if ( ! is_singular() ) {
		return $cache = false;
	}
	// Use get_queried_object_id() — inside FSE template parts get_the_ID() can
	// return 0, making get_field() silently resolve to nothing. Same gotcha
	// documented in CLAUDE.md for ACF render templates.
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return $cache = false;
	}
	return $cache = (bool) get_field( 'landing_header', $post_id );
}

/**
 * Whether the current singular post requests the landing footer.
 * Result is cached per request.
 */
function momentive_is_landing_footer(): bool {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	if ( ! is_singular() ) {
		return $cache = false;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return $cache = false;
	}
	return $cache = (bool) get_field( 'landing_footer', $post_id );
}

// ── Template part swap ────────────────────────────────────────────────────────

/**
 * Replace the header / footer template part output when landing flags are set.
 *
 * Why render_block (not render_block_data on core/pattern):
 * The Site Editor saves customized template parts to the DB as full block HTML,
 * overriding the file — so the core/pattern reference may no longer exist inside
 * the template part. Hooking render_block on core/template-part intercepts the
 * entire rendered output regardless of the template part's internal structure.
 */
add_filter( 'render_block', function ( string $block_content, array $parsed_block ): string {
	if ( 'core/template-part' !== ( $parsed_block['blockName'] ?? '' ) ) {
		return $block_content;
	}

	$slug = $parsed_block['attrs']['slug'] ?? '';

	if ( 'header' === $slug && momentive_is_landing_header() ) {
		return do_blocks( '<!-- wp:pattern {"slug":"momentive/header-landing"} /-->' );
	}

	if ( 'footer' === $slug && momentive_is_landing_footer() ) {
		return do_blocks( '<!-- wp:pattern {"slug":"momentive/footer-landing"} /-->' );
	}

	return $block_content;
}, 10, 2 );
