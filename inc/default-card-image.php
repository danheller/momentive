<?php
/**
 * Default card image fallback.
 *
 * When core/post-featured-image renders empty (no thumbnail set), substitute
 * the branded default card image so archive grids never show a blank slot.
 *
 * Gated to non-singular contexts only — hero blocks on single posts/pages use
 * the same core/post-featured-image block and shouldn't show the fallback.
 */

add_filter( 'render_block', function ( string $html, array $block ): string {
	if ( $block['blockName'] !== 'core/post-featured-image' ) {
		return $html;
	}

	// Only inject on archive/query-loop contexts, not singular heroes.
	if ( is_singular() ) {
		return $html;
	}

	// Non-empty means the post has a real thumbnail — pass through untouched.
	if ( trim( $html ) !== '' ) {
		return $html;
	}

	$fallback = esc_url( get_template_directory_uri() . '/assets/images/default-card-image.webp' );

	return '<figure class="wp-block-post-featured-image">'
		. '<img src="' . $fallback . '" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;">'
		. '</figure>';
}, 10, 2 );
