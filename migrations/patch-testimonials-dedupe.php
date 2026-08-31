<?php
/**
 * patch-testimonials-dedupe.php
 *
 * Merges confirmed duplicate `testimonials` posts into one canonical post
 * each, rewrites any hardcoded `momentive/testimonial` block references
 * (`testimonialId` attribute) site-wide to point at the canonical ID, then
 * trashes (not force-deletes) the non-canonical duplicates.
 *
 * Background (2026-08-19): while investigating the missing Solution-tint
 * field, a normalized-quote comparison across the full rebuilt `testimonials`
 * corpus (275 posts, from momentive.testimonials.rebuild.2026-07-27.xml)
 * turned up 11 exact-duplicate clusters (12 "extra" posts total) — the same
 * quote entered as two separate posts, almost always because the content was
 * migrated once via the dedicated testimonials CPT migration AND once again
 * via a different post type's create-and-reference step (Case Study /
 * Solutions), which matches by normalized quote text and can miss a match
 * when the quote text itself differs slightly (bracketed edits, minor
 * rewording) even though it's clearly the same underlying testimonial.
 *
 * Canonical post per cluster was chosen by ACF field completeness (whichever
 * copy has more of testimonial_author_photo / testimonial_author_description
 * / related_case_study populated), NOT simply "keep the lower ID" — in several
 * clusters the LATER duplicate actually has a related_case_study link the
 * original never got, so blindly preferring the older post would have thrown
 * away real data. See notes/reference-sheets/testimonial-merge-plan.md for
 * the full per-cluster comparison this map was built from.
 *
 * Run:
 *   wp eval-file migrations/patch-testimonials-dedupe.php
 *     → dry run (default): shows every reference it WOULD rewrite and every
 *       post it WOULD trash, writes nothing.
 *   wp eval-file migrations/patch-testimonials-dedupe.php live
 *     → writes: rewrites references, trashes non-canonical duplicates.
 *
 * Trash, not delete: every merged-away post is moved to Trash
 * (`wp_trash_post`), not permanently removed, specifically so this is easy to
 * undo if a cluster turns out to be wrong on closer look.
 *
 * IMPORTANT — reference sweep is post_content only. This scans every post's
 * `post_content` for the block-comment pattern `momentive/testimonial
 * {"testimonialId":N,...}` and rewrites N. It does NOT scan ACF Post Object
 * fields on OTHER post types that might point at a testimonial by ID (no
 * such field is currently known to exist in this codebase — testimonials are
 * only ever surfaced via this block or via a Query Loop, which resolves
 * dynamically by taxonomy/post type and needs no reference rewrite at all).
 * If a curated "featured testimonials" field is ever added elsewhere, extend
 * this script's sweep before relying on it for that field too.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-testimonials-dedupe.php [live]' . PHP_EOL );
}

/**
 * duplicate post_id => canonical post_id.
 * See the header comment and testimonial-merge-plan.md for how these 11
 * clusters (12 duplicates) were found and how canonical was chosen.
 */
const MOMENTIVE_TD_MERGE_MAP = array(
	2513  => 519,
	3222  => 12150,
	11161 => 12150,
	12119 => 5317,
	12120 => 6053,
	12121 => 7217,
	7218  => 12122,
	7220  => 12123,
	12148 => 10049,
	11151 => 10145,
	11159 => 12149,
	11149 => 12124,
);

function momentive_td_run( array $argv ): void {
	global $wpdb;
	$dry_run = ! in_array( 'live', $argv, true );

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	foreach ( MOMENTIVE_TD_MERGE_MAP as $dup_id => $canon_id ) {
		$dup_id   = (int) $dup_id;
		$canon_id = (int) $canon_id;

		if ( get_post_type( $dup_id ) !== 'testimonials' ) {
			WP_CLI::warning( "#{$dup_id} is not a testimonials post on this site — skipping (already merged/trashed, or ID doesn't match this environment)." );
			continue;
		}
		if ( get_post_type( $canon_id ) !== 'testimonials' ) {
			WP_CLI::warning( "Canonical target #{$canon_id} for duplicate #{$dup_id} is not a testimonials post on this site — skipping this pair, check the map." );
			continue;
		}

		WP_CLI::log( "Cluster: #{$dup_id} (\"" . get_the_title( $dup_id ) . "\") → canonical #{$canon_id} (\"" . get_the_title( $canon_id ) . '")' );

		// ---- Find and rewrite every hardcoded block reference site-wide ----
		$pattern = '/"testimonialId":' . $dup_id . '(?=[,}])/';
		$posts   = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			 WHERE post_content LIKE %s
			   AND post_status NOT IN ('trash','auto-draft')",
			'%"testimonialId":' . $dup_id . '%'
		) );

		foreach ( $posts as $row ) {
			// Guard against a substring false-positive (e.g. dup_id=5 matching
			// "testimonialId":51) even though the LIKE above is already loose —
			// the regex's lookahead for [,}] after the number is the real filter.
			if ( ! preg_match( $pattern, $row->post_content ) ) {
				continue;
			}
			$new_content = preg_replace( $pattern, '"testimonialId":' . $canon_id, $row->post_content );

			if ( $dry_run ) {
				WP_CLI::log( "  [dry-run] would rewrite testimonialId {$dup_id} → {$canon_id} in post #{$row->ID} (\"" . get_the_title( $row->ID ) . '")' );
				continue;
			}

			// wp_slash before wp_update_post — this post_content contains JSON
			// in a block comment, which wp_update_post's internal wp_unslash()
			// would otherwise mangle (the same gotcha CLAUDE.md documents for
			// every HubSpot-embed/ACF-block migration in this project).
			wp_update_post( wp_slash( array(
				'ID'           => $row->ID,
				'post_content' => $new_content,
			) ), true );
			WP_CLI::log( "  rewrote testimonialId {$dup_id} → {$canon_id} in post #{$row->ID} (\"" . get_the_title( $row->ID ) . '")' );
		}

		// ---- Trash the duplicate (recoverable, not permanent) --------------
		if ( $dry_run ) {
			WP_CLI::log( "  [dry-run] would trash duplicate #{$dup_id}" );
			continue;
		}
		wp_trash_post( $dup_id );
		WP_CLI::log( "  trashed duplicate #{$dup_id}" );
	}

	WP_CLI::log( '' );
	WP_CLI::success( ( $dry_run ? 'Dry run' : 'Dedupe' ) . ' complete. ' . count( MOMENTIVE_TD_MERGE_MAP ) . ' duplicate posts processed.' );
	if ( $dry_run ) {
		WP_CLI::log( 'Re-run with `live` to write. Trashed posts (not deleted) can be restored from Trash if a cluster turns out wrong.' );
	}
}

momentive_td_run( isset( $args ) && is_array( $args ) ? $args : array() );
