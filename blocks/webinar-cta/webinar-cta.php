<?php
/**
 * Webinar CTA Link block.
 *
 * Shows a status-aware call to action on webinar cards (and anywhere else):
 *   • upcoming  → "Register now" → the webinar page (registration front door)
 *   • on-demand → "Watch now"    → /recordings/{slug} (the recording view)
 *
 * Reads the computed status so it flips in lockstep with the status label,
 * schedule block, and form resolver. The on-demand URL reuses
 * momentive_recording_url() so it stays consistent with the recording layer.
 */

$post_id = get_the_ID();

$status = function_exists( 'momentive_webinar_status' )
	? momentive_webinar_status( $post_id )
	: ( get_field( 'webinar_type', $post_id ) ?: 'upcoming' );

// Treat as watchable only when on-demand AND a recording is actually available
// (video embed present). This keeps the card honest: a "Watch now" link should
// never land the visitor on the registration page because the recording isn't
// up yet. Falls back to "Register now" otherwise.
$watchable = ( 'on-demand' === $status )
	&& ( ! function_exists( 'momentive_recording_is_available' )
		|| momentive_recording_is_available( $post_id ) );

if ( $watchable ) {
	$label = __( 'Watch now', 'momentive' );
	$url   = function_exists( 'momentive_recording_url' )
		? momentive_recording_url( $post_id )
		: get_permalink( $post_id );
} else {
	$label = __( 'Register now', 'momentive' );
	$url   = get_permalink( $post_id );
}

printf(
	'<a class="read-more wp-block-read-more webinar-cta is-webinar-%1$s" href="%2$s">%3$s</a>',
	esc_attr( $watchable ? 'on-demand' : 'upcoming' ),
	esc_url( $url ),
	esc_html( $label )
);