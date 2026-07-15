<?php
/**
 * Webinar Form Heading block — render template.
 *
 * Outputs a bold paragraph above the HubSpot form. Text is determined by
 * whether the webinar is upcoming or on-demand, with an optional editor
 * override via the block-level ACF field.
 *
 * Variables provided by ACF's block renderer:
 *   $block      – block settings and attributes
 *   $is_preview – true during editor AJAX preview
 *   $post_id    – the host post ID (reliable in FSE templates)
 */

// Editor override takes precedence.
$override = trim( (string) get_field( 'form_heading_override' ) );

if ( $override ) {
	$text = $override;
} else {
	$status = function_exists( 'momentive_webinar_status' )
		? momentive_webinar_status( $post_id )
		: ( get_field( 'webinar_type', $post_id ) ?: 'on-demand' );

	$text = ( 'upcoming' === $status )
		? __( 'Save your spot', 'momentive' )
		: __( 'Fill out the form to watch this webinar', 'momentive' );
}

printf( '<p><strong>%s</strong></p>', esc_html( $text ) );
