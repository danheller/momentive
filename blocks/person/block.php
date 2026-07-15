<?php
/**
 * Person block — register and render (no build step).
 *
 * Matches the project's lightweight block pattern (cf. product-solution-tabs):
 * acf_register_block_type with a PHP render callback, a plain view.js and
 * style.css enqueued only where the block is used. No JSX, no webpack.
 *
 * The person is chosen via an ACF Post Object field ("person") attached to
 * this block (see the field group below / register it in ACF). The card links
 * to the person's permalink (SEO-visible, shareable); view.js intercepts the
 * click to open the profile in a <dialog> lightbox, and auto-opens on a
 * matching #person-{slug} hash for deep links. No JS == the link just
 * navigates to the real profile page.
 */

add_action( 'init', function () {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( [
		'name'            => 'person',
		'title'           => 'Person',
		'description'     => 'A person\'s headshot, name, and position, linked to their profile (opens in a lightbox).',
		'render_callback' => 'momentive_render_person',
		'category'        => 'theme',
		'icon'            => 'id-alt',
		'keywords'        => [ 'person', 'team', 'leader', 'profile', 'headshot' ],
		'mode'            => 'preview',
		'supports'        => [
			'align' => [ 'wide', 'full' ],
			'mode'  => false,
			'jsx'   => false,
		],
	] );
} );


/**
 * Enqueue this block's JS/CSS only where it's used.
 *
 * Mirrors product-solution-tabs: check singular post_content for the block,
 * plus any template context where the block lives outside post_content. The
 * Our Team page is a normal page, so the singular check covers it; the extra
 * archive-style guard is omitted here since People aren't shown via an archive
 * template that embeds this block. Add one if that changes.
 */
add_action( 'enqueue_block_assets', function () {
	if ( ! momentive_content_has_block( 'acf/person' ) ) {
		return;
	}

	wp_enqueue_style(
		'momentive-person',
		get_template_directory_uri() . '/assets/css/person.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'momentive-person',
		get_template_directory_uri() . '/blocks/person/view.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);
} );


/**
 * Render callback.
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during AJAX preview in the editor.
 */
function momentive_render_person( array $block, string $content = '', bool $is_preview = false ): void {

	// ACF Post Object field on the block. Return format = ID keeps this simple;
	// if it's set to return the post object, normalize to an ID.
	$person = get_field( 'person' );
	$person_id = is_object( $person ) ? (int) $person->ID : (int) $person;

	if ( ! $person_id || get_post_type( $person_id ) !== 'people' ) {
		if ( $is_preview ) {
			echo '<p style="padding:1.5rem;text-align:center;color:#888;">Select a person in the block settings.</p>';
		}
		return;
	}

	// In the editor preview, the published-status gate would hide a draft the
	// editor is actively working on, so only enforce "publish" on the front end.
	if ( ! $is_preview && get_post_status( $person_id ) !== 'publish' ) {
		return;
	}

	$name              = get_the_title( $person_id );
	$position          = (string) get_field( 'job_position', $person_id );
	$organization      = (string) get_field( 'organization', $person_id );
	$show_organization = (bool) get_field( 'show_organization' ); // block-level field
	$position_display  = $position . ( ( $show_organization && $organization ) ? ', ' . $organization : '' );
	$linkedin          = (string) get_field( 'linkedin_url', $person_id );
	$permalink = get_permalink( $person_id );
	$slug      = get_post_field( 'post_name', $person_id );
	$dom_id    = 'person-' . sanitize_html_class( $slug );

	$thumb = get_the_post_thumbnail(
		$person_id,
		'medium_large',
		[ 'loading' => 'lazy', 'decoding' => 'async', 'alt' => esc_attr( $name ) ]
	);

	$profile_content = apply_filters( 'the_content', get_post_field( 'post_content', $person_id ) );

	$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'momentive-person' ] );

	echo '<div ' . $wrapper_attrs . '>';

	// ---- Card (a real link to the permalink) --------------------------------
	printf(
		'<a class="momentive-person__card" href="%s" id="%s" data-person-target="%s-dialog">',
		esc_url( $permalink ),
		esc_attr( $dom_id ),
		esc_attr( $dom_id )
	);

	if ( $thumb ) {
		echo '<span class="momentive-person__photo">' . $thumb . '</span>';
	}
	echo '<span class="momentive-person__name">' . esc_html( $name ) . '</span>';
	if ( $position_display ) {
		echo '<span class="momentive-person__position">' . esc_html( $position_display ) . '</span>';
	}
	echo '</a>';

	// ---- Hidden profile, revealed as a modal dialog by view.js --------------
	// In the editor preview, a <dialog> with showModal() isn't wired up, so we
	// skip it there to avoid confusing markup; the card preview is enough.
	if ( ! $is_preview ) {
		printf(
			'<dialog class="momentive-person__dialog" id="%s-dialog" aria-label="%s">',
			esc_attr( $dom_id ),
			esc_attr( $name )
		);
		echo '<div class="momentive-person__dialog-inner">';
		printf(
			'<button type="button" class="momentive-person__close" aria-label="%s">&times;</button>',
			esc_attr__( 'Close profile', 'momentive' )
		);

		echo '<div class="momentive-person__profile">';
		echo '<div class="momentive-person__profile-photo">'
			. get_the_post_thumbnail( $person_id, 'large', [ 'alt' => esc_attr( $name ) ] )
			. '</div>';

		echo '<div class="momentive-person__profile-body">';
		echo '<h2 class="momentive-person__profile-name">' . esc_html( $name ) . '</h2>';
		if ( $position_display ) {
			echo '<p class="momentive-person__profile-position">' . esc_html( $position_display ) . '</p>';
		}
		if ( $linkedin ) {
			printf(
				'<a class="momentive-person__linkedin" href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
				esc_url( $linkedin ),
				esc_attr( sprintf( /* translators: %s: person name */ __( '%s on LinkedIn', 'momentive' ), $name ) ),
				momentive_render_icon( 'linkedin', 'class="momentive-person__linkedin-icon"' )
			);
		}
		echo '<div class="momentive-person__profile-content">' . $profile_content . '</div>';
		echo '</div>'; // .profile-body
		echo '</div>'; // .profile

		echo '</div>'; // .dialog-inner
		echo '</dialog>';
	}

	echo '</div>'; // wrapper
}
