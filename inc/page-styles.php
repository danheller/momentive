<?php
/**
 * Per-page one-off CSS.
 *
 * A handful of rebuilt Pages carry a genuinely bespoke visual treatment
 * inherited from the legacy Elementor build (flip-boxes, a one-off gradient,
 * a hover effect that exists nowhere else on the site). Rather than folding
 * that into momentive.scss — where it would sit unused on every other page
 * and post on the site forever — each one gets its own tiny stylesheet,
 * auto-discovered by slug and loaded only on that page.
 *
 * Usage: drop a source file at assets/sass/pages/{slug}.scss (compiles to
 * assets/css/pages/{slug}.css via the existing directory-wide sass build —
 * see "SCSS compilation" in CLAUDE.md). No registration needed; if the
 * compiled file exists for the page being viewed, it's enqueued automatically.
 * Delete the page, delete the file — nothing else to clean up.
 *
 * If the same one-off treatment turns up on two or more pages, that's the
 * signal to promote it out of this folder into a real conditionally-loaded
 * stylesheet keyed to a shared class/block — the same pattern already used
 * for solutions.css / testimonial.css / gate.css — rather than letting this
 * per-page bucket keep growing.
 */

add_action( 'wp_enqueue_scripts', function (): void {
	if ( ! is_singular() ) return;

	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( ! $slug ) return;

	$rel_path = "assets/css/pages/{$slug}.css";
	$abs_path = get_template_directory() . '/' . $rel_path;
	if ( ! file_exists( $abs_path ) ) return;

	wp_enqueue_style(
		"momentive-page-{$slug}",
		get_template_directory_uri() . '/' . $rel_path,
		[],
		// Per-file mtime rather than the theme version: these are edited
		// independently and far more often than the theme version bumps, and
		// there's no shared cache-busting reason to tie them together.
		filemtime( $abs_path )
	);
} );
