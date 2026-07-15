<?php
/**
 * Webinar Presenters block — register and enqueue assets.
 *
 * A "field block" — it reads the host webinar post's `presenters` ACF
 * relationship field rather than letting editors pick people per-block.
 * One block in the webinar pattern renders all presenters automatically.
 *
 * Layout (one-column | two-columns) is the only block-level ACF field.
 * The heading auto-pluralizes: "Presenter" vs "Presenters".
 *
 * Progressive link: if the People post has body content, the card links
 * to the person's permalink. No link if the profile is empty — no lightbox
 * machinery is wired up yet; that can be layered in later following the
 * same view.js pattern as acf/person.
 */

add_action( 'init', function(): void {
	register_block_type( get_template_directory() . '/blocks/webinar-presenters' );
} );

add_action( 'enqueue_block_assets', function(): void {
	if ( ! momentive_content_has_block( 'acf/webinar-presenters' ) ) {
		return;
	}

	wp_enqueue_style(
		'momentive-webinar-presenters',
		get_template_directory_uri() . '/blocks/webinar-presenters/webinar-presenters.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );
