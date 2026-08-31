<?php
/**
 * patch-testimonials-content-backfill.php
 *
 * Renamed 2026-08-19 from migrate-testimonials.php (its original docblock
 * said "delete after confirming" — kept instead, because it turns out to be
 * a standing safety net, not a one-time fix; see below).
 *
 * Copies the quote text from the `testimonial_content` postmeta key into the
 * real `post_content` field whenever `post_content` is empty, and promotes a
 * bare `testimonial_author_photo` postmeta value into the proper ACF image
 * field. Also fixes any legacy import that only ever wrote the ACF/meta
 * field.
 *
 * Why this keeps mattering: `blocks/testimonial/block.php` renders the quote
 * from `$post->post_content` ONLY — it never reads `testimonial_content`.
 * But `migrate-case-studies.php`'s `momentive_cs_create_testimonial()` and
 * (until fixed alongside this rename) `migrate-reviews.php`'s create path
 * both wrote the quote via `update_field( 'testimonial_content', ... )` and
 * left `post_content` empty on the `wp_insert_post()` call — so every
 * testimonial created by either script rendered a genuinely blank
 * `<blockquote>` on the front end, with no error and no visible sign in the
 * block editor preview. Both scripts have been fixed going forward (they now
 * set `post_content` directly at insert time), but posts they already
 * created before the fix still need this patch run once to backfill.
 * Re-running this script after any future migration/import that only sets
 * `testimonial_content` (not `post_content`) is the safety net for the same
 * mistake happening again.
 *
 * Usage:
 *   wp eval-file migrations/patch-testimonials-content-backfill.php
 *     → dry run (default)
 *   wp eval-file migrations/patch-testimonials-content-backfill.php live
 *     → writes
 */

$dry_run = ! in_array( 'live', isset( $args ) && is_array( $args ) ? $args : [], true );

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