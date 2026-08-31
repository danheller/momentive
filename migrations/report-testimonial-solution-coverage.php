<?php
/**
 * report-testimonial-solution-coverage.php
 *
 * Read-only WP-CLI report: for every `testimonials` post, does it currently
 * have a `category` term assigned (the mechanism that drives the Solution
 * background tint in blocks/testimonial/block.php via get_solution_color_for_term())?
 *
 * Built to troubleshoot a 2026-08-17 report that testimonials had "lost" their
 * Solution-tint relationship and that the picker field is no longer visible in
 * the editor. Root cause of the missing FIELD is already confirmed: the
 * "Testimonial Settings" ACF group (group_6a23a12ae0f19) is missing a
 * `testimonial_solution` taxonomy-type field that inc/testimonials.php's
 * `acf/fields/taxonomy/query/name=testimonial_solution` filter has been
 * expecting all along — compare against the FAQ CPT's still-working, exactly
 * analogous `faq_solution` field (group_6a2f336a848ad) to see the intended
 * shape. Deleting an ACF field definition does NOT delete existing taxonomy
 * term relationships in the database, though — so this script exists to
 * separately answer "is the underlying DATA actually gone too, or just the
 * admin UI for seeing/editing it?" before assuming anything needs restoring
 * from a backup.
 *
 * A WXR export pulled 2026-07-27 (migrations/exports/momentive.testimonials.rebuild.2026-07-27.xml)
 * already shows 98/275 testimonials (36%) had a category term as of that date
 * — so this was already a partially-populated field, not something every
 * testimonial universally had. Run this report against the LIVE site and
 * compare the count to that baseline:
 *   - If the live count is close to 98 (adjusted for any testimonials
 *     created/deleted since), the underlying data is intact and this is
 *     purely a missing-admin-UI problem — fix by re-adding the ACF field.
 *   - If the live count is meaningfully lower than 98, some term
 *     relationships were actually cleared and a WP Engine backup restore
 *     (or a targeted re-import from the 2026-07-27 export) is the next step.
 *
 * Run:
 *   wp eval-file migrations/report-testimonial-solution-coverage.php
 *   wp eval-file migrations/report-testimonial-solution-coverage.php list-missing
 *     → also prints every testimonial post ID/title with no category term
 *
 * Makes no writes. Safe to re-run anytime.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/report-testimonial-solution-coverage.php [list-missing]' . PHP_EOL );
}

function momentive_tsc_run( array $argv ): void {
	$list_missing = in_array( 'list-missing', $argv, true );

	$q = new WP_Query( array(
		'post_type'      => 'testimonials',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );

	$total   = count( $q->posts );
	$with    = 0;
	$without = 0;
	$missing = array();
	$by_cat  = array();

	foreach ( $q->posts as $pid ) {
		$terms = get_the_terms( $pid, 'category' );
		if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$with++;
			$by_cat[ $terms[0]->name ] = ( $by_cat[ $terms[0]->name ] ?? 0 ) + 1;
		} else {
			$without++;
			$missing[] = $pid;
		}
	}

	WP_CLI::log( "Total testimonials posts: {$total}" );
	WP_CLI::log( "  With a category term:    {$with}" );
	WP_CLI::log( "  Without a category term: {$without}" );
	WP_CLI::log( '' );
	WP_CLI::log( 'Baseline from 2026-07-27 export: 98/275 had a category term at that time.' );
	WP_CLI::log( 'Compare the "With" count above against 98 (adjusted for posts added/removed since) to tell whether data was actually cleared, or this was always partially unset.' );

	if ( ! empty( $by_cat ) ) {
		WP_CLI::log( '' );
		WP_CLI::log( 'Breakdown by category term (posts that DO have one):' );
		arsort( $by_cat );
		foreach ( $by_cat as $name => $count ) {
			WP_CLI::log( "  {$name}: {$count}" );
		}
	}

	// Also check whether the related_solution field (Category Settings group,
	// group_6a100f10616e3) resolves to a color for each in-use term — a term
	// with no related_solution set would explain "field looks assigned but
	// still no tint" as a distinct third failure mode from the two above.
	WP_CLI::log( '' );
	WP_CLI::log( 'Checking whether in-use category terms resolve to a Solution accent color...' );
	$checked = array();
	foreach ( $q->posts as $pid ) {
		$terms = get_the_terms( $pid, 'category' );
		if ( ! $terms || is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		$term_id = $terms[0]->term_id;
		if ( isset( $checked[ $term_id ] ) ) {
			continue;
		}
		$checked[ $term_id ] = true;
		$color = function_exists( 'get_solution_color_for_term' ) ? get_solution_color_for_term( $term_id ) : null;
		if ( ! $color ) {
			WP_CLI::warning( "  Term \"{$terms[0]->name}\" (#{$term_id}) has no resolvable Solution color — check its related_solution field and that Solution's accent_color." );
		} else {
			WP_CLI::log( "  Term \"{$terms[0]->name}\" (#{$term_id}) → {$color}" );
		}
	}

	if ( $list_missing && ! empty( $missing ) ) {
		WP_CLI::log( '' );
		WP_CLI::log( 'Testimonials with NO category term:' );
		foreach ( $missing as $pid ) {
			WP_CLI::log( "  #{$pid} \"" . get_the_title( $pid ) . '"' );
		}
	}
}

momentive_tsc_run( isset( $args ) && is_array( $args ) ? $args : array() );
