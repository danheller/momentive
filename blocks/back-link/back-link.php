<?php
/**
 * Back Link block — render template.
 *
 * Variables provided by ACF's block renderer:
 *   $block      – block settings and attributes
 *   $is_preview – true during editor AJAX preview
 *   $post_id    – the host post ID
 */

$url   = (string) get_field( 'url' );
$label = (string) get_field( 'label' ) ?: __( 'All posts', 'momentive' );

if ( ! $url ) {
	if ( $is_preview ) {
		echo '<p style="padding:1rem;color:#888;font-style:italic;">Back Link — set URL and label in the block fields →</p>';
	}
	return;
}

printf(
	'<div class="back-link"><a href="%s">%s</a></div>',
	esc_url( $url ),
	esc_html( $label )
);
