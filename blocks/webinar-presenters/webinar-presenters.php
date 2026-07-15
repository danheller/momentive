<?php
/**
 * Webinar Presenters block — render template.
 *
 * Variables provided by ACF's block renderer:
 *   $block      – block settings and attributes
 *   $is_preview – true during editor AJAX preview
 *   $post_id    – the host post ID (reliable even in FSE templates where
 *                 get_the_ID() is outside the main query loop)
 */

// Read presenters from the host webinar post, not from a block-level field.
$presenters = get_field( 'presenters', $post_id );

if ( empty( $presenters ) || ! is_array( $presenters ) ) {
	if ( $is_preview ) {
		echo '<p style="padding:1.5rem;text-align:center;color:#888;">Assign presenters in the Webinar Settings panel to see them here.</p>';
	}
	// Render nothing on the front end — the block self-hides.
	return;
}

// Block-level ACF fields.
$layout          = ( get_field( 'layout' ) ?: 'two-columns' );
$show_headshots  = (bool) get_field( 'show_headshots' );

$count   = count( $presenters );
$heading = ( 1 === $count ) ? __( 'Presenter', 'momentive' ) : __( 'Presenters', 'momentive' );

$classes = 'momentive-webinar-presenters layout-' . sanitize_html_class( $layout );
if ( ! $show_headshots ) {
	$classes .= ' no-headshots';
}

$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => $classes ] );

echo '<div ' . $wrapper_attrs . '>';
echo '<h2 class="momentive-webinar-presenters__heading">' . esc_html( $heading ) . '</h2>';
echo '<div class="momentive-webinar-presenters__grid">';

foreach ( $presenters as $person ) {
	$person_id = is_object( $person ) ? (int) $person->ID : (int) $person;

	// Skip unpublished profiles on the front end.
	if ( ! $is_preview && get_post_status( $person_id ) !== 'publish' ) {
		continue;
	}

	$name         = get_the_title( $person_id );
	$position     = (string) get_field( 'job_position', $person_id );
	$organization = (string) get_field( 'organization', $person_id );
	$certs        = (string) get_field( 'certifications', $person_id );
	$title_display = $position . ( $organization ? ', ' . $organization : '' );
	$permalink    = get_permalink( $person_id );

	// Link the card only when the person has body content (bio). No link = no
	// broken expectation for visitors. Lightbox behavior can be layered on
	// later via view.js without changing this markup.
	$has_bio = (bool) trim( get_post_field( 'post_content', $person_id ) );

	$thumb = $show_headshots ? get_the_post_thumbnail(
		$person_id,
		'thumbnail',
		[
			'loading'  => 'lazy',
			'decoding' => 'async',
			'alt'      => esc_attr( $name ),
		]
	) : '';

	// Render as <a> when the profile has content, <div> otherwise.
	if ( $has_bio ) {
		printf(
			'<a class="momentive-webinar-presenters__card is-linked" href="%s">',
			esc_url( $permalink )
		);
	} else {
		echo '<div class="momentive-webinar-presenters__card">';
	}

	if ( $thumb ) {
		echo '<span class="momentive-webinar-presenters__photo">' . $thumb . '</span>';
	}

	echo '<span class="momentive-webinar-presenters__info">';
	echo '<span class="momentive-webinar-presenters__name">' . esc_html( $name );
	if ( $certs ) {
		echo ', ' . esc_html( $certs );
	}
	echo '</span>';
	if ( $title_display ) {
		echo '<span class="momentive-webinar-presenters__title">' . esc_html( $title_display ) . '</span>';
	}
	echo '</span>'; // .info

	echo $has_bio ? '</a>' : '</div>';
}

echo '</div>'; // .grid
echo '</div>'; // wrapper
