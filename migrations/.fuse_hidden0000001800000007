<?php
/**
 * patch-testimonials-review-headline.php
 *
 * One-off backfill for testimonials already created/updated by
 * `migrate-reviews.php` before it captured `review_headline` (added
 * 2026-08-19, see that script's header comment and
 * notes/reference-sheets/testimonial-merge-plan.md).
 *
 * Background: the legacy `reviews` CPT used its own post title as the
 * reviewer's real headline (e.g. "Best Investment Ever") —
 * reviews-reference-sheet.md said to keep this verbatim. But
 * `migrate-reviews.php` set the rebuilt post's title to the reviewer's
 * attribution name instead (matching the rest of the testimonial CPT), which
 * meant the headline text was captured nowhere on posts created before the
 * `review_headline` field existed. The headline text itself was never lost
 * at the source — it's still sitting in the same legacy WXR export
 * (`momentivesoftware.reviews.current.2026-07-20.xml`) `migrate-reviews.php`
 * already reads — so this is a pure re-read-and-backfill, not a data
 * recovery problem.
 *
 * Covers both paths that create/touch a testimonial in migrate-reviews.php:
 *   - Newly CREATED testimonials: matched by the `_momentive_source_review_id`
 *     meta stamped on them at creation time.
 *   - MERGED testimonials (the 9 confirmed review<->testimonial duplicate
 *     pairs): matched via a local copy of MOMENTIVE_REV_CONFIRMED_MERGES,
 *     since those posts predate this migration and were never stamped with
 *     any review-sourced meta at all (they're pre-existing testimonial
 *     posts, only tagged/updated, not created).
 *
 * Safety: only ever sets review_headline on a post that CURRENTLY HAS NONE.
 * Never overwrites an existing value. Idempotent — safe to re-run.
 *
 * Run:
 *   wp eval-file migrations/patch-testimonials-review-headline.php
 *     → dry run (default)
 *   wp eval-file migrations/patch-testimonials-review-headline.php live
 *     → writes
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-testimonials-review-headline.php [live]' . PHP_EOL );
}

const MOMENTIVE_TRH_SRC_META = '_momentive_source_review_id'; // must match MOMENTIVE_REV_SRC_META in migrate-reviews.php
const FK_TRH_HEADLINE        = 'field_d2c7e0a19f402';           // review_headline — must match FK_REV_HEADLINE in migrate-reviews.php

/**
 * Duplicated from MOMENTIVE_REV_CONFIRMED_MERGES in migrate-reviews.php —
 * kept in sync by hand, same call already made for other small shared maps
 * across migration scripts in this project (e.g. MOMENTIVE_MT_SLUG_MAP vs
 * MOMENTIVE_TSF_SLUG_MAP). legacy review post_id => rebuilt testimonial post_id.
 */
const MOMENTIVE_TRH_MERGES = array(
	9925 => 12131,
	9708 => 10932,
	8367 => 12129,
	9965 => 10179,
	9971 => 10180,
	9948 => 3406,
	9947 => 3220,
	8371 => 10264,
);

function momentive_trh_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	return '';
}

function momentive_trh_run( array $argv ): void {
	$dry_run  = ! in_array( 'live', $argv, true );
	$wxr_path = defined( 'MOMENTIVE_TRH_LEGACY_WXR' )
		? MOMENTIVE_TRH_LEGACY_WXR
		: __DIR__ . '/exports/momentivesoftware.reviews.current.2026-07-20.xml';

	if ( ! file_exists( $wxr_path ) ) {
		WP_CLI::error( "Export not found: {$wxr_path}" );
	}

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	$xml = file_get_contents( $wxr_path );
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $matches );

	$summary = array( 'set' => 0, 'already_set' => 0, 'no_target' => 0, 'no_headline' => 0 );

	foreach ( $matches[1] as $item ) {
		if ( momentive_trh_xml_tag( $item, 'wp:post_type' ) !== 'reviews' ) {
			continue;
		}

		$legacy_id = (int) momentive_trh_xml_tag( $item, 'wp:post_id' );
		$headline  = momentive_trh_xml_tag( $item, 'title' );

		if ( '' === $headline ) {
			$summary['no_headline']++;
			continue;
		}

		// Resolve the target testimonial post: merge map first, then the
		// source-review-id meta stamped on posts this script created.
		$target_id = MOMENTIVE_TRH_MERGES[ $legacy_id ] ?? 0;

		if ( ! $target_id ) {
			$found = get_posts( array(
				'post_type'      => 'testimonials',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => MOMENTIVE_TRH_SRC_META,
				'meta_value'     => $legacy_id,
				'no_found_rows'  => true,
			) );
			$target_id = $found ? (int) $found[0] : 0;
		}

		if ( ! $target_id ) {
			$summary['no_target']++;
			WP_CLI::log( "SKIP #{$legacy_id} \"{$headline}\" — no matching rebuilt testimonial found (not yet migrated, or a legacy ID this script's maps don't cover)." );
			continue;
		}

		if ( get_post_type( $target_id ) !== 'testimonials' ) {
			WP_CLI::warning( "  #{$legacy_id} → target #{$target_id} is not a testimonials post on this site — skipping, check for a stale ID." );
			continue;
		}

		$existing = get_field( 'review_headline', $target_id );
		if ( ! empty( $existing ) ) {
			$summary['already_set']++;
			continue;
		}

		if ( $dry_run ) {
			WP_CLI::log( "  [dry-run] would set #{$target_id} \"" . get_the_title( $target_id ) . "\" → review_headline=\"{$headline}\"" );
			$summary['set']++;
			continue;
		}

		update_field( FK_TRH_HEADLINE, $headline, $target_id );
		WP_CLI::log( "  set #{$target_id} \"" . get_the_title( $target_id ) . "\" → review_headline=\"{$headline}\"" );
		$summary['set']++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'%s complete. Set: %d. Already had one (skipped): %d. No matching rebuilt testimonial: %d. No headline in export: %d.',
		$dry_run ? 'Dry run' : 'Patch',
		$summary['set'], $summary['already_set'], $summary['no_target'], $summary['no_headline']
	) );
}

momentive_trh_run( isset( $args ) && is_array( $args ) ? $args : array() );
