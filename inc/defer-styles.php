<?php
/**
 * Defer non-critical stylesheets (preload + swap-to-stylesheet-on-load).
 *
 * Several stylesheets are already conditionally enqueued — momentive-testimonial,
 * momentive-solutions, momentive-gate, etc. only load on pages that use the
 * matching block/CPT. But on the pages where they DO load, they're still
 * render-blocking, even though the blocks they style (testimonial cards,
 * solution sliders, the gated whitepaper layout) are rarely in the first
 * viewport of content.
 *
 * Rather than hand-rolling a wp_head() output per file (hardcoding one URL,
 * bypassing WP's dependency/versioning system), this rewrites the <link> tag
 * WordPress already prints for specific registered handles into the standard
 * preload/onload pattern. The existing wp_register_style()/wp_enqueue_style()
 * calls at each call site are untouched — same versioning, same
 * momentive_content_has_block() conditionals — this just changes how the tag
 * is printed once WP decides to print it.
 *
 * To defer another stylesheet: add its handle to the array below. No change
 * needed at the call site.
 */

add_filter( 'style_loader_tag', function ( string $html, string $handle, string $href, string $media ): string {

	// Never rewrite in wp-admin, AJAX, or REST contexts — this covers the
	// block editor (including ACF block preview via the block-renderer REST
	// endpoint and the iframed post/site editor), where styles need to apply
	// synchronously for an accurate preview.
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $html;
	}

	$deferred_handles = apply_filters( 'momentive_deferred_style_handles', [
		'momentive-testimonial',
		'momentive-solutions',
		'momentive-gate',
	] );

	if ( ! in_array( $handle, $deferred_handles, true ) ) {
		return $html;
	}

	$href  = esc_url( $href );
	$media = esc_attr( $media ?: 'all' );
	$id    = esc_attr( $handle ) . '-css';

	return sprintf(
		'<link rel="preload" as="style" id="%3$s" href="%1$s" media="%2$s" onload="this.onload=null;this.rel=\'stylesheet\'" />' .
		'<noscript><link rel="stylesheet" id="%3$s" href="%1$s" media="%2$s" /></noscript>' . "\n",
		$href,
		$media,
		$id
	);

}, 10, 4 );
