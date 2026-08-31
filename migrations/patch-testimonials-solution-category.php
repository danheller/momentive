<?php
/**
 * patch-testimonials-solution-category.php
 *
 * Backfills the `category` taxonomy (Solution family) on already-migrated
 * `testimonials` posts, sourced from the legacy `solution_family` ACF text
 * field — which is what the legacy site actually used, NOT a taxonomy at all.
 *
 * Background (2026-08-19): troubleshooting a missing Solution-tint field
 * turned up that only 98/275 rebuilt testimonials have a `category` term set.
 * A fresh full export of the legacy `reviews`... er, `testimonials` CPT
 * (momentivesoftware.testimonials.current.2026-08-19.xml, 157 posts) shows
 * why: the legacy site never used the native `category` taxonomy for
 * testimonials at all (0/157 have one) — it used a plain ACF text field,
 * `solution_family`, holding short slugs (`assn-mgmt`, `event-mgmt`, etc.),
 * populated on 147/157 posts. That field was never translated into a real
 * `category` term assignment during the original testimonials migration.
 * This script does that translation, for posts that already exist on the
 * rebuilt site and currently have no category at all.
 *
 * This is NOT a full testimonials migration — it only sets one taxonomy on
 * posts that already exist (matched by legacy post ID, which this project's
 * testimonials migration already preserves 1:1, same as Solutions/Case
 * Studies). It does not create new posts. 59 of the 157 posts in the fresh
 * export don't exist on the rebuilt site at all yet — those are logged as
 * "not found" and are a separate, real migration gap worth a follow-up
 * (probably a small `migrate-testimonials.php` for whatever's left), not
 * something this patch script's scope covers.
 *
 * Run:
 *   wp eval-file migrations/patch-testimonials-solution-category.php
 *     → dry run (default)
 *   wp eval-file migrations/patch-testimonials-solution-category.php live
 *     → writes
 *   wp eval-file migrations/patch-testimonials-solution-category.php live only=519
 *     → single post by legacy/rebuilt ID
 *
 * Safety: only ever sets a category on a post that CURRENTLY HAS NONE. Never
 * overwrites or touches a category already assigned — if an editor already
 * fixed one by hand, or a different migration path already set one correctly
 * (e.g. the Case Study create-and-reference inheritance added the same day
 * as this script), this leaves it alone.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-testimonials-solution-category.php [live] [only=ID]' . PHP_EOL );
}

const MOMENTIVE_TSF_LEGACY_WXR = __DIR__ . '/exports/momentivesoftware.testimonials.current.2026-08-19.xml';

/**
 * Legacy `solution_family` slug → rebuilt `category` taxonomy slug.
 * Confirmed by cross-referencing every distinct value found in the fresh
 * export against the category slugs already established elsewhere in this
 * project (MOMENTIVE_SOL_FAMILY_TO_CAT_SLUG in migrate-solutions.php, and the
 * category breakdown in the Reviews reference sheet) — a clean 1:1 map, no
 * ambiguous or unresolved values found in the 2026-08-19 export.
 *
 * event-mgmt/careers history: a live dry run on 2026-08-19 first found these
 * mapping to "event-technology"/"career-services" (the legacy site's actual,
 * inconsistent category slugs — confirmed slug-independent of the Solution↔
 * category tint relationship, which resolves via the category term's
 * `related_solution` ACF field, not the slug string). Daniel renamed both
 * live category terms to "event-management"/"career-centers" to match this
 * map's existing pattern, with a redirect left in place from the old
 * `/blog/category/{old-slug}/` archive URLs as insurance. Map now reflects
 * the current (renamed) slugs.
 */
const MOMENTIVE_TSF_SLUG_MAP = array(
	'assn-mgmt'      => 'association-management',
	'event-mgmt'     => 'event-management',
	'learn-mgmt'     => 'learning-management',
	'accounting'     => 'accounting',
	'fundraising'    => 'fundraising',
	'careers'        => 'career-centers',
	'vol-mgmt'       => 'volunteer-management',
	'crt-mgmt'       => 'certification-management',
	'data-analytics' => 'data-analytics',
);

function momentive_tsf_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	return '';
}

function momentive_tsf_xml_meta( string $item, string $key ): string {
	if ( preg_match(
		'#<wp:meta_key><!\[CDATA\[' . preg_quote( $key, '#' ) . '\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
		$item, $m
	) ) {
		return html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
	}
	return '';
}

function momentive_tsf_run( array $argv ): void {
	$dry_run = ! in_array( 'live', $argv, true );
	$only    = 0;
	foreach ( $argv as $tok ) {
		if ( str_starts_with( (string) $tok, 'only=' ) ) {
			$only = (int) substr( (string) $tok, 5 );
		}
	}

	if ( ! file_exists( MOMENTIVE_TSF_LEGACY_WXR ) ) {
		WP_CLI::error( 'Export not found: ' . MOMENTIVE_TSF_LEGACY_WXR );
	}

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	$xml = file_get_contents( MOMENTIVE_TSF_LEGACY_WXR );
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $matches );

	$summary = array( 'set' => 0, 'already_set' => 0, 'no_solution_family' => 0, 'unmapped_slug' => 0, 'no_term' => 0, 'not_found' => 0 );
	$unmapped_slugs = array();

	foreach ( $matches[1] as $item ) {
		if ( momentive_tsf_xml_tag( $item, 'wp:post_type' ) !== 'testimonials' ) {
			continue;
		}
		$legacy_id = (int) momentive_tsf_xml_tag( $item, 'wp:post_id' );
		if ( $only && $legacy_id !== $only ) {
			continue;
		}
		$title = momentive_tsf_xml_tag( $item, 'title' );
		$sf    = momentive_tsf_xml_meta( $item, 'solution_family' );

		if ( get_post_type( $legacy_id ) !== 'testimonials' ) {
			$summary['not_found']++;
			WP_CLI::log( "NOT FOUND on rebuilt site: #{$legacy_id} \"{$title}\" — not part of this patch's scope, see header comment." );
			continue;
		}

		$existing = get_the_terms( $legacy_id, 'category' );
		if ( $existing && ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			$summary['already_set']++;
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — already has category \"{$existing[0]->name}\", leaving it alone." );
			continue;
		}

		if ( '' === $sf ) {
			$summary['no_solution_family']++;
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — no legacy solution_family value to backfill from." );
			continue;
		}

		$cat_slug = MOMENTIVE_TSF_SLUG_MAP[ $sf ] ?? '';
		if ( '' === $cat_slug ) {
			$summary['unmapped_slug']++;
			$unmapped_slugs[ $sf ] = ( $unmapped_slugs[ $sf ] ?? 0 ) + 1;
			WP_CLI::warning( "  #{$legacy_id} \"{$title}\" — solution_family \"{$sf}\" has no entry in MOMENTIVE_TSF_SLUG_MAP." );
			continue;
		}

		$term = get_term_by( 'slug', $cat_slug, 'category' );
		if ( ! $term ) {
			$summary['no_term']++;
			WP_CLI::warning( "  #{$legacy_id} \"{$title}\" — mapped to category slug \"{$cat_slug}\" but no such term exists on this site." );
			continue;
		}

		if ( $dry_run ) {
			WP_CLI::log( "  [dry-run] would set #{$legacy_id} \"{$title}\" → category \"{$term->name}\" (from solution_family={$sf})" );
			$summary['set']++;
			continue;
		}

		wp_set_object_terms( $legacy_id, array( (int) $term->term_id ), 'category', false );
		WP_CLI::log( "  set #{$legacy_id} \"{$title}\" → category \"{$term->name}\"" );
		$summary['set']++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'%s complete. Set: %d. Already had one (skipped): %d. No solution_family (skipped): %d. Unmapped slug: %d. Term missing: %d. Not found on rebuilt site: %d.',
		$dry_run ? 'Dry run' : 'Patch',
		$summary['set'], $summary['already_set'], $summary['no_solution_family'],
		$summary['unmapped_slug'], $summary['no_term'], $summary['not_found']
	) );

	if ( $summary['not_found'] > 0 ) {
		WP_CLI::log( sprintf(
			'%d legacy testimonials in this export have no matching post on the rebuilt site at all — that is a migration gap, not something this patch can fix.',
			$summary['not_found']
		) );
	}
}

momentive_tsf_run( isset( $args ) && is_array( $args ) ? $args : array() );
