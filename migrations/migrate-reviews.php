<?php
/**
 * migrate-reviews.php
 *
 * WP-CLI migration script: legacy `reviews` CPT → rebuilt `testimonial` CPT.
 *
 * This does NOT create a new `review` CPT. Per the architecture decision in
 * notes/reference-sheets/testimonial-merge-plan.md, reviews fold directly into
 * the existing `testimonial` CPT via a new `review` term on the already-registered
 * `testimonial_type` taxonomy (inc/testimonials.php — non-hierarchical, so a post
 * can already carry more than one term with no field-architecture change).
 *
 * Reads from a single WXR export (reviews posts only, no separate assets file —
 * this CPT has no media to sideload).
 *
 * Run (from the theme root, or adjust the path to migrations/):
 *
 *   wp eval-file migrations/migrate-reviews.php
 *     → dry run (default); shows what would be written, including which reviews
 *       resolve to a CONFIRMED MERGE (existing testimonial gets tagged + updated,
 *       no new post) vs. a NEW testimonial post.
 *
 *   wp eval-file migrations/migrate-reviews.php live
 *     → writes: creates new testimonial posts, updates merged ones, tags the
 *       orphan G2 testimonial that has no matching review at all.
 *
 *   wp eval-file migrations/migrate-reviews.php live limit=10
 *     → first 10 legacy reviews only (after skipping known internal duplicates).
 *
 *   wp eval-file migrations/migrate-reviews.php live only=givesmart-just-do-it
 *     → single review by legacy slug.
 *
 * No --user flag is required — unlike most migrations in this project, Reviews
 * has no image/logo sideload to trip the Safe SVG capability gate on.
 *
 * Overridable constant:
 *   MOMENTIVE_REV_LEGACY_WXR — path to the legacy reviews export
 *   (default: migrations/exports/momentivesoftware.reviews.current.2026-09-01.xml)
 *
 * Idempotent: upserts by a stamped `_momentive_source_review_id` meta key on
 * newly-created posts, so re-running updates in place. Merged (pre-existing)
 * testimonials are matched by hardcoded post ID (see MOMENTIVE_REV_CONFIRMED_MERGES
 * below) — safe to re-run, since it always resolves to the same target post.
 *
 * ---------------------------------------------------------------------------
 * REQUIRED SETUP BEFORE RUNNING LIVE — read this first
 * ---------------------------------------------------------------------------
 * This script writes to three ACF fields that do not exist yet on the
 * "Testimonial Settings" field group (group_6a23a12ae0f19): `review_source`,
 * `review_source_link`, and (for the separate Video Testimonials fold-in,
 * not handled by this script) `video_embed_code`. Add these in the ACF UI
 * first — see notes/reference-sheets/testimonial-merge-plan.md for the exact
 * field specs — then fill in the real field keys below. The script refuses to
 * run live (WP_CLI::error, hard stop) if these are left as placeholders, so it
 * can't silently write to a field that doesn't exist.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-reviews.php [live] [limit=N] [only=slug]' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_REV_CPT       = 'testimonials'; // rebuilt CPT DB name (plural, matches inc/testimonials.php)
const MOMENTIVE_REV_LEGACY    = 'reviews';       // post_type value in the legacy WXR
const MOMENTIVE_REV_RUN_META  = '_momentive_migration_run';
const MOMENTIVE_REV_SRC_META  = '_momentive_source_review_id'; // stamped on newly-created posts for idempotent upsert

// Added to "Testimonial Settings" (group_6a23a12ae0f19) on 2026-08-19 — see
// testimonial-merge-plan.md. Confirm these keys still match that file if the
// group is ever edited again in the ACF UI (ACF may regenerate on next save).
const FK_REV_SOURCE      = 'field_8fe702e2a34a2'; // review_source (select: Capterra/G2/TrustRadius/Software Advice)
const FK_REV_SOURCE_LINK = 'field_b5b41991ca3f5'; // review_source_link (URL)
const FK_REV_HEADLINE    = 'field_d2c7e0a19f402'; // review_headline (text) — added 2026-08-19, see below

/**
 * The legacy `reviews` CPT used its own post title as the reviewer's actual
 * headline (e.g. "Best Investment Ever") — reviews-reference-sheet.md says
 * to keep this verbatim. But this script's post_title instead follows the
 * rebuilt testimonial convention (attribution name, e.g. "Amber B."),
 * matching every other testimonial and making sense once a review can be
 * tagged onto an existing plain testimonial post. That means the headline
 * needs its own field so it isn't silently dropped — `review_headline`,
 * added to "Testimonial Settings" alongside review_source/review_source_link.
 */

/**
 * Legacy review post_id → rebuilt testimonial post_id.
 *
 * These nine pairs are CONFIRMED duplicates — see the "Confirmed" sections of
 * notes/reference-sheets/reviews-reference-sheet.md for the evidence (verbatim
 * title matches, near-identical body text). Hand-verified, not fuzzy-matched at
 * runtime, for the same reason MOMENTIVE_SOL_FORCE_PARENT and similar explicit
 * override maps exist elsewhere in this codebase: judgment calls like "is this
 * really the same customer" shouldn't be re-derived mechanically on every run.
 *
 * For a legacy review ID in this map, the script does NOT create a new
 * testimonial post. It instead updates the existing target post: adds the
 * `review` term, and fills in review_source / review_source_link from the
 * legacy review data (the target testimonial otherwise keeps everything it
 * already has — job title, author name format, etc. — untouched).
 */
const MOMENTIVE_REV_CONFIRMED_MERGES = array(
	9925 => 12131, // Reid S., "Strong product, good value"          → "Reid S., Donor Relations Manager (G2 Review)"
	9708 => 10932, // Anthony C., "I Love VolunteerMatters"           → "Anthony C., Volunteer Manager (G2 Review)"
	8367 => 12129, // Patricia N., "So glad we chose Volunteer Matters!" → "Patricia N., CEO (G2 Review)"
	9965 => 10179, // Amber Berkey, "GiveSmart- Just Do It"           → "Amber B., Fundraising and Development Officer, Nonprofit"
	9971 => 10180, // Bunny Rosenberg, "Easy to use for galas and auctions!" → "Bunny R., Director of Marketing & Communications, Nonprofit"
	9948 => 3406,  // Becca, "Slick Functionality..."                 → "Neurocritical Care Society"
	9947 => 3220,  // Stacy, "The only way to go!" (BlueSky eLEARN — see open question re: product-name swap) → "Minnesota County Attorneys Association" (says Path LMS)
	8371 => 10264, // Laura M., "Very happy with this software"       → "Laura, CME Director, Education"
);

/**
 * Legacy review IDs to skip outright — confirmed internal duplicates within
 * the reviews export itself (not review↔testimonial dupes). See the reviews
 * reference sheet's "Duplicate and near-duplicate content" section.
 */
const MOMENTIVE_REV_SKIP_IDS = array(
	10054, // "Strong product, good value (DUPLICATE)" draft — duplicate of 9925, which is itself merged above.
);

/**
 * Rebuilt testimonial posts that are confirmed review-derived but have NO
 * matching post anywhere in the reviews export (the review was hand-copied
 * into a testimonial and never got its own reviews CPT entry). These just
 * need the `review` term + a source label with no link — handled once,
 * separately from the per-review loop below.
 */
const MOMENTIVE_REV_ORPHAN_TESTIMONIALS = array(
	// testimonial post_id => review_source value (no review_source_link available)
	12130 => 'G2', // "Ernie Y., Development Manager (G2 Review)"
);

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function momentive_rev_run_id(): string {
	static $id = '';
	if ( '' === $id ) {
		$id = gmdate( 'Y-m-d H:i:s' );
	}
	return $id;
}

/** Hard stop if the ACF field keys above haven't been filled in for a live run. */
function momentive_rev_require_field_keys( bool $dry_run ): void {
	if ( $dry_run ) {
		return; // dry-run doesn't write, so missing keys are fine to preview around.
	}
	if ( '' === FK_REV_SOURCE || '' === FK_REV_SOURCE_LINK || '' === FK_REV_HEADLINE ) {
		WP_CLI::error(
			'FK_REV_SOURCE / FK_REV_SOURCE_LINK / FK_REV_HEADLINE are still empty placeholders. ' .
			'Add the review_source, review_source_link, and review_headline fields to the "Testimonial ' .
			'Settings" ACF group first (see notes/reference-sheets/testimonial-merge-plan.md), ' .
			'then fill in the real field keys at the top of this script before running live.'
		);
	}
}

/**
 * Extract a CDATA-wrapped or plain child-tag value from an XML item string.
 * Same helper shape as every other migration script in this project.
 */
function momentive_rev_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return '';
}

/** Extract every <wp:postmeta> value for a given meta key from an item string. */
function momentive_rev_xml_meta( string $item, string $key ): string {
	if ( preg_match(
		'#<wp:meta_key><!\[CDATA\[' . preg_quote( $key, '#' ) . '\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
		$item, $m
	) ) {
		return html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
	}
	return '';
}

/** Parse positional flags — same convention as every migration script here. */
function momentive_rev_get_flags( array $argv ): array {
	$flags = array( 'live' => false, 'limit' => 0, 'only' => '' );
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( 'live' === $tok ) {
			$flags['live'] = true;
		} elseif ( str_starts_with( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = substr( $tok, 5 );
		}
	}
	return $flags;
}

/* -------------------------------------------------------------------------
 * Content conversion
 * ---------------------------------------------------------------------- */

/**
 * Reviews' post_content is already clean native Gutenberg paragraph blocks
 * (confirmed in the reference sheet — the one CPT in this project where that's
 * true), so no HTML-to-block conversion or Word-artifact cleanup is needed
 * here, unlike every other resource CPT migration. Just strip block comments
 * back to plain text for the testimonial_content field, which stores plain
 * text/rich text, not block markup.
 */
function momentive_rev_plain_text_from_blocks( string $content ): string {
	$content = preg_replace( '#<!--\s*/?wp:.*?-->#s', '', $content );
	$content = wp_strip_all_tags( $content );
	return trim( preg_replace( '/\s+/', ' ', $content ) );
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_rev_run( array $argv ): void {
	$flags    = momentive_rev_get_flags( $argv );
	$dry_run  = ! $flags['live'];
	$wxr_path = defined( 'MOMENTIVE_REV_LEGACY_WXR' )
		? MOMENTIVE_REV_LEGACY_WXR
		: __DIR__ . '/exports/momentivesoftware.reviews.current.2026-09-01.xml';

	momentive_rev_require_field_keys( $dry_run );

	if ( ! file_exists( $wxr_path ) ) {
		WP_CLI::error( "Export not found: {$wxr_path}" );
	}

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	// Ensure the `review` term exists on the testimonial_type taxonomy — a
	// one-line addition to an already-registered taxonomy, not a schema change.
	if ( ! $dry_run && ! term_exists( 'review', 'testimonial_type' ) ) {
		$r = wp_insert_term( 'Review', 'testimonial_type', array( 'slug' => 'review' ) );
		if ( is_wp_error( $r ) ) {
			WP_CLI::warning( 'Could not create the "review" term: ' . $r->get_error_message() );
		} else {
			WP_CLI::log( 'Created "review" term on testimonial_type.' );
		}
	} elseif ( $dry_run ) {
		WP_CLI::log( term_exists( 'review', 'testimonial_type' )
			? '"review" term already exists on testimonial_type.'
			: '[dry-run] would create "review" term on testimonial_type.' );
	}

	// ---- Orphan testimonials with no matching review post -----------------
	WP_CLI::log( '' );
	WP_CLI::log( '--- Orphan review-derived testimonials (no matching reviews-CPT post) ---' );
	foreach ( MOMENTIVE_REV_ORPHAN_TESTIMONIALS as $tid => $source ) {
		if ( get_post_type( $tid ) !== MOMENTIVE_REV_CPT ) {
			WP_CLI::warning( "  #{$tid} is not a {$tid} testimonials post on this site — skipping. Confirm the ID is correct for this environment." );
			continue;
		}
		if ( $dry_run ) {
			WP_CLI::log( "  [dry-run] would tag #{$tid} (\"" . get_the_title( $tid ) . "\") as review, source={$source}, no link" );
			continue;
		}
		wp_set_object_terms( $tid, 'review', 'testimonial_type', true ); // append, don't replace
		update_field( FK_REV_SOURCE, $source, $tid );
		WP_CLI::log( "  tagged #{$tid} (\"" . get_the_title( $tid ) . "\") as review, source={$source}" );
	}

	// ---- Parse the reviews export -------------------------------------
	$xml   = file_get_contents( $wxr_path );
	$items = array();
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $matches );
	foreach ( $matches[1] as $item ) {
		if ( momentive_rev_xml_tag( $item, 'wp:post_type' ) !== MOMENTIVE_REV_LEGACY ) {
			continue;
		}
		$items[] = $item;
	}
	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Found %d legacy review items in export.', count( $items ) ) );

	$processed = 0;
	$summary   = array( 'merged' => 0, 'created' => 0, 'skipped_dup' => 0, 'skipped_draft' => 0, 'skipped_already_imported' => 0 );

	foreach ( $items as $item ) {
		$legacy_id = (int) momentive_rev_xml_tag( $item, 'wp:post_id' );
		$slug      = momentive_rev_xml_tag( $item, 'wp:post_name' );
		$status    = momentive_rev_xml_tag( $item, 'wp:status' );
		$title     = momentive_rev_xml_tag( $item, 'title' );

		if ( '' !== $flags['only'] && $slug !== $flags['only'] ) {
			continue;
		}
		if ( $flags['limit'] > 0 && $processed >= $flags['limit'] ) {
			break;
		}

		if ( in_array( $legacy_id, MOMENTIVE_REV_SKIP_IDS, true ) ) {
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — known internal duplicate, see reviews-reference-sheet.md." );
			$summary['skipped_dup']++;
			continue;
		}
		if ( 'publish' !== $status ) {
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — status={$status}, not publishing drafts." );
			$summary['skipped_draft']++;
			continue;
		}

		$processed++;

		$review_name   = momentive_rev_xml_meta( $item, 'review_name' );
		$review_date   = momentive_rev_xml_meta( $item, 'review_date' ); // unix timestamp
		$source        = momentive_rev_xml_meta( $item, 'review_rating_source' );
		$source_link   = momentive_rev_xml_meta( $item, 'review_rating_source_link' );
		$content_raw   = momentive_rev_xml_tag( $item, 'content:encoded' );
		$quote         = momentive_rev_plain_text_from_blocks( $content_raw );
		$categories    = array();
		preg_match_all( '#<category domain="category" nicename="([^"]*)">#', $item, $cat_m );
		$categories    = $cat_m[1] ?? array();

		// ---- Confirmed merge: update the existing testimonial, don't duplicate ----
		if ( isset( MOMENTIVE_REV_CONFIRMED_MERGES[ $legacy_id ] ) ) {
			$target_id = MOMENTIVE_REV_CONFIRMED_MERGES[ $legacy_id ];
			WP_CLI::log( "MERGE #{$legacy_id} \"{$title}\" ({$review_name}) → existing testimonial #{$target_id} \"" . get_the_title( $target_id ) . '"' );

			if ( $dry_run ) {
				WP_CLI::log( "  [dry-run] would set testimonial_type += review, review_source={$source}, review_source_link={$source_link}" );
				$summary['merged']++;
				continue;
			}

			if ( get_post_type( $target_id ) !== MOMENTIVE_REV_CPT ) {
				WP_CLI::warning( "  target #{$target_id} is not a testimonials post on this site — skipping merge, check MOMENTIVE_REV_CONFIRMED_MERGES against this environment's actual post IDs." );
				continue;
			}

			wp_set_object_terms( $target_id, 'review', 'testimonial_type', true );
			update_field( FK_REV_SOURCE, $source, $target_id );
			update_field( FK_REV_SOURCE_LINK, $source_link, $target_id );

			// Backfill the review's own headline too, same never-overwrite rule.
			if ( '' !== $title && empty( get_field( 'review_headline', $target_id ) ) ) {
				update_field( FK_REV_HEADLINE, $title, $target_id );
				WP_CLI::log( "  backfilled review_headline on merge target: \"{$title}\"" );
			}

			// Backfill category (Solution family) from the review's own category
			// terms, but ONLY if the target testimonial currently has none — same
			// "never overwrite an existing assignment" rule as
			// patch-testimonials-solution-category.php. Most of these nine merge
			// targets predate that field entirely, so this is likely to actually
			// fire, not just a defensive no-op.
			$existing_cat = get_the_terms( $target_id, 'category' );
			if ( ( ! $existing_cat || is_wp_error( $existing_cat ) || empty( $existing_cat ) ) && ! empty( $categories ) ) {
				wp_set_object_terms( $target_id, $categories, 'category', false );
				WP_CLI::log( '  backfilled category on merge target from the review: ' . implode( ', ', $categories ) );
			}

			$summary['merged']++;
			continue;
		}

		// ---- No match — create a new testimonial post -------------------
		// Idempotency check: was this legacy review already imported by a
		// prior run of this script? The docblock always claimed this script
		// upserts by MOMENTIVE_REV_SRC_META, but this lookup was missing
		// until 2026-08-19 — every prior run would have silently re-created
		// a duplicate post for every non-merged review on a second live run.
		// Confirm this hasn't already happened (e.g. via
		// report-testimonial-references.php or a manual duplicate check)
		// before trusting this fix retroactively.
		$already = get_posts( array(
			'post_type'      => MOMENTIVE_REV_CPT,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => MOMENTIVE_REV_SRC_META,
			'meta_value'     => $legacy_id,
			'no_found_rows'  => true,
		) );
		if ( $already ) {
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — already imported as testimonial #{$already[0]} (matched via " . MOMENTIVE_REV_SRC_META . ' meta).' );
			$summary['skipped_already_imported']++;
			continue;
		}

		WP_CLI::log( "CREATE #{$legacy_id} \"{$title}\" ({$review_name}) — no matching testimonial found" );

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] would create: name=%s | source=%s | headline=%s | quote=%.60s…', $review_name, $source, $title, $quote ) );
			$summary['created']++;
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => MOMENTIVE_REV_CPT,
			'post_status'  => 'publish',
			'post_title'   => $review_name ?: wp_trim_words( $quote, 8, '…' ),
			// The block renderer (blocks/testimonial/block.php) reads the quote
			// from post_content directly — it never reads a `testimonial_content`
			// field/meta. Setting it here at insert time (not via update_field()
			// afterward) is required, or the testimonial renders a blank
			// <blockquote> on the front end. See patch-testimonials-content-backfill.php
			// for the 2026-08-19 fix + backfill for posts created before this line existed.
			'post_content' => wp_kses_post( $quote ),
			'post_date'    => $review_date ? gmdate( 'Y-m-d H:i:s', (int) $review_date ) : '',
		), true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( '  failed to create: ' . $post_id->get_error_message() );
			continue;
		}

		update_field( 'testimonial_author_name', $review_name, $post_id );
		update_field( FK_REV_SOURCE, $source, $post_id );
		update_field( FK_REV_SOURCE_LINK, $source_link, $post_id );
		if ( '' !== $title ) {
			update_field( FK_REV_HEADLINE, $title, $post_id );
		}
		wp_set_object_terms( $post_id, 'review', 'testimonial_type', true );
		if ( ! empty( $categories ) ) {
			wp_set_object_terms( $post_id, $categories, 'category', false );
		}
		update_post_meta( $post_id, MOMENTIVE_REV_SRC_META, $legacy_id );
		update_post_meta( $post_id, MOMENTIVE_REV_RUN_META, momentive_rev_run_id() );

		// post_date above only sets the initial insert; force it the same way
		// every other migration in this project does, since wp_insert_post's
		// date handling for already-published statuses can be inconsistent.
		if ( $review_date ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array(
				'post_date'     => gmdate( 'Y-m-d H:i:s', (int) $review_date ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', (int) $review_date ),
			), array( 'ID' => $post_id ) );
		}

		$summary['created']++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'%s complete. Merged into existing testimonials: %d. New testimonials created: %d. Already imported (skipped): %d. Skipped (internal duplicate): %d. Skipped (draft): %d.',
		$dry_run ? 'Dry run' : 'Migration',
		$summary['merged'], $summary['created'], $summary['skipped_already_imported'], $summary['skipped_dup'], $summary['skipped_draft']
	) );

	if ( $dry_run ) {
		WP_CLI::log( 'Re-run with `live` to write. Remember: FK_REV_SOURCE / FK_REV_SOURCE_LINK / FK_REV_HEADLINE must be filled in first — see the header comment.' );
	}
}

momentive_rev_run( isset( $args ) && is_array( $args ) ? $args : array() );
