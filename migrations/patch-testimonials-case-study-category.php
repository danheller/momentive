<?php
/**
 * patch-testimonials-case-study-category.php
 *
 * Backfills the `category` taxonomy on `testimonials` posts that were
 * created by migrate-case-studies.php's create-and-reference step
 * (momentive_cs_create_testimonial()), which never sets one unless a fix
 * added 2026-08-19 happened to apply at creation time (see that function's
 * own header comment) — most of the corpus predates the fix.
 *
 * This is a DIFFERENT source of missing categories than
 * patch-testimonials-solution-category.php, which backfills from the legacy
 * `solution_family` postmeta value on testimonials that came from the
 * dedicated testimonials CPT import. Testimonials created programmatically
 * by the Case Study migration have no such meta at all — they were never
 * part of the legacy `testimonials` CPT, so there's no legacy WXR row to
 * read a solution_family from. Run this patch AFTER
 * patch-testimonials-solution-category.php so the two don't fight over which
 * "currently has no category" posts to compete for (harmless either order in
 * practice — each only ever sets a category when one isn't already there —
 * but running the solution_family one first covers the larger, more directly
 * sourced group).
 *
 * How affected posts are found: momentive_cs_create_testimonial() stamps no
 * distinguishing meta key (only the generic `_momentive_migration_run` run
 * timestamp, shared with every other migration script) and never sets
 * `related_case_study` back to its host post — the relationship only exists
 * in the OTHER direction, as a `<!-- wp:momentive/testimonial
 * {"testimonialId":N,...} /-->` block comment inside the case study's own
 * `post_content` (momentive_cs_testimonial_block()). So this script does the
 * inverse of `report-testimonial-references.php`'s scan: walk every
 * published `case-study` post's content for that block, and for each
 * testimonial it references, backfill the testimonial's `category` from
 * ITS HOST CASE STUDY'S own category terms — not from any field on the
 * testimonial itself, which has none to read.
 *
 * Safety: only ever sets a category on a testimonial that CURRENTLY HAS
 * NONE. Never overwrites an existing assignment (same rule as
 * patch-testimonials-solution-category.php). If a testimonial is referenced
 * by more than one case study with different categories, the first one
 * encountered wins and a warning is logged — this is expected to be rare
 * (a quote reused across two case studies in different Solution families)
 * and worth a manual look if it happens, not something to resolve
 * mechanically.
 *
 * Run:
 *   wp eval-file migrations/patch-testimonials-case-study-category.php
 *     → dry run (default)
 *   wp eval-file migrations/patch-testimonials-case-study-category.php live
 *     → writes
 *   wp eval-file migrations/patch-testimonials-case-study-category.php live only=12345
 *     → single testimonial post ID
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-testimonials-case-study-category.php [live] [only=ID]' . PHP_EOL );
}

/**
 * Recursively walk parsed blocks, collecting testimonialId => referencing
 * case-study post ID. Same walk shape as
 * report-testimonial-references.php's momentive_testimonial_ref_walk_blocks(),
 * scoped here to case-study posts only and paired with the host's own ID.
 */
function momentive_tcc_walk_blocks( array $blocks, int $host_id, array &$found ): void {
	foreach ( $blocks as $block ) {
		if ( ( $block['blockName'] ?? '' ) === 'momentive/testimonial' ) {
			$tid = (int) ( $block['attrs']['testimonialId'] ?? 0 );
			if ( $tid > 0 ) {
				$found[ $tid ][] = $host_id;
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			momentive_tcc_walk_blocks( $block['innerBlocks'], $host_id, $found );
		}
	}
}

function momentive_tcc_run( array $argv ): void {
	$dry_run = ! in_array( 'live', $argv, true );
	$only    = 0;
	foreach ( $argv as $tok ) {
		if ( str_starts_with( (string) $tok, 'only=' ) ) {
			$only = (int) substr( (string) $tok, 5 );
		}
	}

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	// ---- Build testimonialId => [ host case-study post_id, ... ] map ----
	$case_study_ids = get_posts( array(
		'post_type'      => 'case-study',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	WP_CLI::log( sprintf( 'Scanning %d published case studies for momentive/testimonial references...', count( $case_study_ids ) ) );

	$refs = array(); // testimonial_id => [ case_study_id, ... ]
	foreach ( $case_study_ids as $cs_id ) {
		$content = (string) get_post_field( 'post_content', $cs_id );
		if ( ! str_contains( $content, 'momentive/testimonial' ) ) {
			continue;
		}
		$blocks = parse_blocks( $content );
		momentive_tcc_walk_blocks( $blocks, $cs_id, $refs );
	}

	WP_CLI::log( sprintf( 'Found %d distinct testimonials referenced from a case study.', count( $refs ) ) );
	WP_CLI::log( '' );

	$summary = array( 'set' => 0, 'already_set' => 0, 'no_host_category' => 0, 'conflict' => 0, 'not_testimonial' => 0 );

	foreach ( $refs as $testimonial_id => $host_ids ) {
		if ( $only && $testimonial_id !== $only ) {
			continue;
		}

		if ( get_post_type( $testimonial_id ) !== 'testimonials' ) {
			$summary['not_testimonial']++;
			WP_CLI::warning( "  #{$testimonial_id} is referenced as a testimonial but is not a testimonials post on this site — skipping (deleted/merged since?)." );
			continue;
		}

		$existing = get_the_terms( $testimonial_id, 'category' );
		if ( $existing && ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			$summary['already_set']++;
			continue;
		}

		// Resolve category from the FIRST referencing host case study that
		// actually has one. Log a conflict if more than one host has a
		// DIFFERENT category — informational only, doesn't block the write.
		$resolved_term = null;
		$conflict      = false;
		foreach ( $host_ids as $host_id ) {
			$host_cats = get_the_terms( $host_id, 'category' );
			if ( ! $host_cats || is_wp_error( $host_cats ) || empty( $host_cats ) ) {
				continue;
			}
			if ( null === $resolved_term ) {
				$resolved_term = $host_cats[0];
			} elseif ( (int) $host_cats[0]->term_id !== (int) $resolved_term->term_id ) {
				$conflict = true;
			}
		}

		if ( null === $resolved_term ) {
			$summary['no_host_category']++;
			WP_CLI::log( "SKIP #{$testimonial_id} \"" . get_the_title( $testimonial_id ) . '" — no referencing case study (' . implode( ',', $host_ids ) . ') has a category set either.' );
			continue;
		}

		if ( $conflict ) {
			$summary['conflict']++;
			WP_CLI::warning( "  #{$testimonial_id} \"" . get_the_title( $testimonial_id ) . '" is referenced by case studies in more than one category (' . implode( ',', $host_ids ) . ') — using the first match, "' . $resolved_term->name . '". Worth a manual look.' );
		}

		if ( $dry_run ) {
			WP_CLI::log( "  [dry-run] would set #{$testimonial_id} \"" . get_the_title( $testimonial_id ) . '" → category "' . $resolved_term->name . '" (from case study ' . implode( ',', $host_ids ) . ')' );
			$summary['set']++;
			continue;
		}

		wp_set_object_terms( $testimonial_id, array( (int) $resolved_term->term_id ), 'category', false );
		WP_CLI::log( "  set #{$testimonial_id} \"" . get_the_title( $testimonial_id ) . '" → category "' . $resolved_term->name . '"' );
		$summary['set']++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'%s complete. Set: %d. Already had one (skipped): %d. Host case study(s) also had none (skipped): %d. Multi-category conflicts (still set, using first match): %d. Referenced ID not a testimonials post: %d.',
		$dry_run ? 'Dry run' : 'Patch',
		$summary['set'], $summary['already_set'], $summary['no_host_category'],
		$summary['conflict'], $summary['not_testimonial']
	) );
}

momentive_tcc_run( isset( $args ) && is_array( $args ) ? $args : array() );
