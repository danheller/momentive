<?php
/**
 * backfill-testimonial-type-taxonomy.php
 *
 * One-time backfill ahead of retiring the ACF `testimonial_type` select field
 * (Testimonial Settings, field_6a23a1a0985df) in favor of the real
 * `testimonial_type` taxonomy already registered in inc/testimonials.php.
 *
 * Both mechanisms have coexisted on the `testimonials` CPT: a real taxonomy
 * (used for admin filtering and available to native Query Loop taxQuery), and
 * an unrelated ACF select field with the same name/slug that nothing in the
 * theme actually reads (no `get_field( 'testimonial_type', ... )` call exists
 * anywhere in blocks/patterns/templates). An export of the live testimonial
 * posts (migrations/momentive.testimonials.rebuild.2026-07-27.xml, 250 posts)
 * was analyzed to check whether the two ever disagree before removing the
 * field:
 *
 *   - 139 posts have both the ACF value and a taxonomy term — 0 mismatches
 *     (client/Client, employee/Employee always agree).
 *   - 26 posts have the ACF value set but NO taxonomy term.
 *   - 85 posts have neither.
 *   - 0 posts have a taxonomy term but no ACF value.
 *
 * This script closes that 26-post gap by assigning the taxonomy term implied
 * by the existing ACF value, so no classification is lost once the ACF field
 * is deleted. Value mapping (the only two values ever actually seen in data,
 * despite the ACF field's own choices list currently being client/partner/
 * other — "employee" isn't even one of its defined choices, which is itself
 * a sign this field has drifted from a much older schema):
 *
 *   client   -> Client
 *   employee -> Employee
 *
 * Run modes (positional args — `wp eval-file` doesn't accept --flags):
 *
 *   wp eval-file migrations/backfill-testimonial-type-taxonomy.php          # dry run (default)
 *   wp eval-file migrations/backfill-testimonial-type-taxonomy.php live     # writes
 *
 * Idempotent: skips any post that already has a testimonial_type term
 * assigned (append-only, never removes an existing term).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/backfill-testimonial-type-taxonomy.php [live]' . PHP_EOL );
}

const MOMENTIVE_TESTIMONIAL_TYPE_MAP = [
	'client'   => 'Client',
	'employee' => 'Employee',
];

function momentive_backfill_testimonial_type_run( array $argv ): void {
	$live = in_array( 'live', $argv, true );

	WP_CLI::log( $live ? '--- LIVE RUN — writing taxonomy terms ---' : '--- DRY RUN — no changes will be written (pass "live" to write) ---' );

	$post_ids = get_posts( [
		'post_type'      => 'testimonials',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	WP_CLI::log( sprintf( 'Found %d testimonial posts.', count( $post_ids ) ) );

	$assigned      = 0;
	$already_set   = 0;
	$no_acf_value  = 0;
	$unmapped      = [];

	foreach ( $post_ids as $post_id ) {
		$existing_terms = wp_get_post_terms( $post_id, 'testimonial_type', [ 'fields' => 'names' ] );

		if ( is_wp_error( $existing_terms ) ) {
			WP_CLI::warning( sprintf( '[%d] Could not read existing terms: %s', $post_id, $existing_terms->get_error_message() ) );
			continue;
		}

		if ( ! empty( $existing_terms ) ) {
			$already_set++;
			continue;
		}

		$acf_value = get_field( 'testimonial_type', $post_id );

		if ( empty( $acf_value ) ) {
			$no_acf_value++;
			continue;
		}

		if ( ! isset( MOMENTIVE_TESTIMONIAL_TYPE_MAP[ $acf_value ] ) ) {
			$unmapped[] = [ $post_id, $acf_value ];
			WP_CLI::warning( sprintf( '[%d] ACF value "%s" has no known taxonomy mapping — skipped.', $post_id, $acf_value ) );
			continue;
		}

		$term_name = MOMENTIVE_TESTIMONIAL_TYPE_MAP[ $acf_value ];

		WP_CLI::log( sprintf( '[%d] "%s" — assigning term "%s" (from ACF value "%s")', $post_id, get_the_title( $post_id ), $term_name, $acf_value ) );

		if ( $live ) {
			$result = wp_set_object_terms( $post_id, $term_name, 'testimonial_type', false );
			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( '[%d] Failed to assign term: %s', $post_id, $result->get_error_message() ) );
				continue;
			}
		}

		$assigned++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'Done. Assigned: %d | Already had a term: %d | No ACF value to backfill from: %d | Unmapped ACF values: %d',
		$assigned,
		$already_set,
		$no_acf_value,
		count( $unmapped )
	) );

	if ( ! $live && $assigned > 0 ) {
		WP_CLI::log( 'Re-run with "live" to write these terms.' );
	}
}

momentive_backfill_testimonial_type_run( isset( $args ) && is_array( $args ) ? $args : [] );
