<?php
/**
 * Webinar Status Label block.
 *
 * Outputs "Upcoming Webinar", "Webinar Series", or "On-Demand Webinar" using the
 * global .top-label style. Status (upcoming/on-demand) is computed live; the
 * "series" treatment is an upcoming webinar flagged is_series. A series that has
 * passed its end date becomes on-demand like any other webinar.
 */

$post_id = get_the_ID();

$status = function_exists( 'momentive_webinar_status' )
	? momentive_webinar_status( $post_id )
	: ( get_field( 'webinar_type', $post_id ) ?: 'upcoming' );

$is_series = function_exists( 'momentive_webinar_is_series' )
	&& momentive_webinar_is_series( $post_id );

if ( 'on-demand' === $status ) {
	$label   = __( 'On-Demand Webinar', 'momentive' );
	$variant = 'on-demand';
} elseif ( $is_series ) {
	$label   = __( 'Webinar Series', 'momentive' );
	$variant = 'series';
} else {
	$label   = __( 'Upcoming Webinar', 'momentive' );
	$variant = 'upcoming';
}

printf(
	'<p class="top-label is-webinar-%s">%s</p>',
	esc_attr( $variant ),
	esc_html( $label )
);