<?php
/**
 * Person field blocks — position and LinkedIn.
 *
 * Two tiny blocks for surfacing People ACF fields inside the single-people
 * FSE template (the rest of the profile is built from core blocks: post-title,
 * post-content, post-featured-image). No build step.
 *
 * Each block is registered from its own block.json subfolder (required so the
 * editor recognizes ACF blocks); both point renderCallback at the functions
 * below. They read the CURRENT queried person via get_the_ID(), so they work
 * anywhere in a single-people template without configuration. On the editor
 * canvas (no queried person) they show a small placeholder.
 */

add_action( 'init', function () {
	register_block_type( get_template_directory() . '/blocks/person-metadata/person-position' );
	register_block_type( get_template_directory() . '/blocks/person-metadata/person-linkedin' );
} );

add_action( 'enqueue_block_assets', function () {
	if ( ! momentive_content_has_block( 'acf/person-position' ) && ! momentive_content_has_block( 'acf/person-linkedin' ) ) {
		return;
	}

	wp_enqueue_style(
		'momentive-person',
		get_template_directory_uri() . '/assets/css/person.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );



/**
 * Resolve the person ID for a field block.
 *
 * In a single-people template the queried object is the person, so get_the_ID()
 * is correct. Returns 0 if there's no people post in context (e.g. editor).
 */
function momentive_current_person_id(): int {
	$id = get_the_ID();
	return ( $id && get_post_type( $id ) === 'people' ) ? (int) $id : 0;
}


/**
 * Render: person position.
 */
function momentive_render_person_position( array $block, string $content = '', bool $is_preview = false ): void {
	$person_id = momentive_current_person_id();

	if ( ! $person_id ) {
		if ( $is_preview ) {
			echo '<p style="color:#888;">Person position (shown on a person\'s page).</p>';
		}
		return;
	}

	$position = (string) get_field( 'job_position', $person_id );
	if ( '' === $position ) {
		return;
	}

	// Honor block supports (font size / color) via the wrapper class the editor
	// generates, while keeping the project's person-position hook class.
	$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'person-position' ] );
	printf( '<p %s>%s</p>', $wrapper_attrs, esc_html( $position ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


/**
 * Render: person LinkedIn icon link.
 */
function momentive_render_person_linkedin( array $block, string $content = '', bool $is_preview = false ): void {
	$person_id = momentive_current_person_id();

	if ( ! $person_id ) {
		if ( $is_preview ) {
			echo '<p style="color:#888;">LinkedIn link (shown on a person\'s page).</p>';
		}
		return;
	}

	$linkedin = (string) get_field( 'linkedin_url', $person_id );
	if ( '' === $linkedin ) {
		return;
	}

	$name          = get_the_title( $person_id );
	$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'momentive-person__linkedin' ] );

	printf(
		'<a %s href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s</a>',
		$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		esc_url( $linkedin ),
		esc_attr( sprintf( /* translators: %s: person name */ __( '%s on LinkedIn', 'momentive' ), $name ) ),
		momentive_render_icon( 'linkedin', 'class="momentive-person__linkedin-icon"' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
