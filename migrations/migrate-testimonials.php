<?php
/**
 * One-time migration: Testimonials CPT field remapping
 *
 * Usage:
 *   wp eval-file migrate-testimonials.php
 *   wp eval-file migrate-testimonials.php --dry-run
 *
 * Place in theme root or a /migrations/ folder. Delete after confirming.
 */

$dry_run = ! empty( $args[0] );

if ( $dry_run ) {
	WP_CLI::log( '--- DRY RUN — no changes will be written ---' );
}

$posts = get_posts( [
	'post_type'      => 'testimonials',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

WP_CLI::log( sprintf( 'Found %d testimonial posts.', count( $posts ) ) );

$moved_content = 0;
$moved_photo   = 0;
$skipped       = 0;

foreach ( $posts as $post_id ) {
	$post            = get_post( $post_id );
	$legacy_content  = get_post_meta( $post_id, 'testimonial_content', true );
	$legacy_photo_id = get_post_meta( $post_id, 'testimonial_author_photo', true );

	// ── Quote text → post_content ─────────────────────────────────────────────
	// Only migrate if post_content is empty and legacy field has a value.
	if ( $legacy_content && empty( trim( $post->post_content ) ) ) {
		if ( ! $dry_run ) {
			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => wp_kses_post( $legacy_content ),
			] );
		}
		WP_CLI::log( sprintf( '[%d] Content migrated: "%s…"', $post_id, mb_substr( $legacy_content, 0, 60 ) ) );
		$moved_content++;
	} elseif ( ! empty( trim( $post->post_content ) ) ) {
		WP_CLI::log( sprintf( '[%d] Skipped content — post_content already populated.', $post_id ) );
		$skipped++;
	} else {
		WP_CLI::warning( sprintf( '[%d] No content found in either field.', $post_id ) );
	}

	// ── Author photo → ACF field ──────────────────────────────────────────────
	// The importer may have stored the attachment ID as post meta but not wired
	// it up as an ACF field. update_field() ensures ACF handles it correctly.
	$acf_photo = get_field( 'testimonial_author_photo', $post_id );

	if ( $legacy_photo_id && ! $acf_photo ) {
		if ( ! $dry_run ) {
			update_field( 'testimonial_author_photo', (int) $legacy_photo_id, $post_id );
		}
		WP_CLI::log( sprintf( '[%d] Photo migrated: attachment %s.', $post_id, $legacy_photo_id ) );
		$moved_photo++;
	} elseif ( $acf_photo ) {
		WP_CLI::log( sprintf( '[%d] Skipped photo — ACF field already populated.', $post_id ) );
	}
}

WP_CLI::success( sprintf(
	'Done. Content migrated: %d | Photos migrated: %d | Skipped: %d',
	$moved_content,
	$moved_photo,
	$skipped
) );