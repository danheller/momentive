<?php
/**
 * migrate-testimonials.php
 *
 * WP-CLI gap-fill migration: imports legacy `testimonials` posts that don't
 * yet exist on the rebuilt site at all.
 *
 * Background: the bulk of the rebuilt `testimonials` corpus came from an ad
 * hoc import done very early in this project (not a script kept in this
 * folder), which preserved legacy post IDs 1:1 for whatever it migrated —
 * confirmed by `patch-testimonials-solution-category.php`'s gap check
 * (`get_post_type( $legacy_id ) !== 'testimonials'`). A fresh full export,
 * `momentivesoftware.testimonials.current.2026-09-01.xml` (157 posts), showed
 * ~59 of those 157 legacy IDs have no matching post on the rebuilt site at
 * all — never imported. This script imports exactly those, and only those:
 * any legacy ID that already resolves to a `testimonials` post on this site
 * is skipped outright (this is NOT a general re-import or update tool).
 *
 * Run (from the theme root, or adjust the path to migrations/):
 *
 *   wp eval-file migrations/migrate-testimonials.php
 *     → dry run (default)
 *   wp eval-file migrations/migrate-testimonials.php live
 *     → writes
 *   wp eval-file migrations/migrate-testimonials.php live limit=10
 *     → first 10 gap posts only
 *   wp eval-file migrations/migrate-testimonials.php live only=12345
 *     → single post by legacy ID
 *   wp eval-file migrations/migrate-testimonials.php live --user=<admin>
 *     → needed only if any author photo actually sideloads an SVG (Safe SVG
 *       capability gate — see momentive_cs_sideload()'s equivalent comment
 *       in migrate-case-studies.php). Most photos here are raster.
 *
 * Overridable constants:
 *   MOMENTIVE_MT_LEGACY_WXR       — path to the fresh testimonials export
 *   MOMENTIVE_MT_CASE_STUDY_WXR   — path to the legacy case-studies export,
 *                                   used only to resolve `related_case_study`
 *
 * Idempotent: every new post is created with `import_id` set to the legacy
 * post ID (same convention `migrate-solutions.php` uses for its hub/child
 * posts), so a re-run naturally skips anything already imported by this
 * script or by the earlier ad hoc import — both look identical to WordPress.
 *
 * IMPORTANT — this writes the quote to real `post_content`, not a
 * `testimonial_content` field/meta. See the comment in
 * `migrations/patch-testimonials-content-backfill.php` for why: the block
 * renderer (`blocks/testimonial/block.php`) reads `post_content` only, and
 * two other migration scripts in this project got this wrong until
 * 2026-08-19. Do not "fix" this back to `update_field('testimonial_content', ...)`.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-testimonials.php [live] [limit=N] [only=legacy_id]' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_MT_CPT      = 'testimonials';
const MOMENTIVE_MT_LEGACY   = 'testimonials'; // post_type value in the legacy WXR
const MOMENTIVE_MT_RUN_META = '_momentive_migration_run';

/**
 * Legacy `solution_family` slug -> rebuilt `category` taxonomy slug.
 * Kept in sync with MOMENTIVE_TSF_SLUG_MAP in patch-testimonials-solution-category.php
 * — duplicated rather than shared, same "small enough to just keep in sync by
 * hand" call already made for MOMENTIVE_REBUILD_TRIVIAL_EMPTY_BLOCK across two
 * unrelated `wp eval-file` scripts. If this map ever changes, update both.
 * event-mgmt/careers slugs match the LIVE (2026-08-19, post-rename) category
 * slugs — event-management/career-centers — not any earlier/legacy naming.
 */
const MOMENTIVE_MT_SLUG_MAP = array(
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

/**
 * Legacy `testimonial_type` postmeta value -> testimonial_type taxonomy term
 * name. Same mapping as backfill-testimonial-type-taxonomy.php (client/employee
 * are the only two values ever seen in any export of this CPT).
 */
const MOMENTIVE_MT_TYPE_MAP = array(
	'client'   => 'Client',
	'employee' => 'Employee',
);

/* -------------------------------------------------------------------------
 * XML helpers (same shape as every other migration script in this project)
 * ---------------------------------------------------------------------- */

function momentive_mt_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return '';
}

function momentive_mt_xml_meta( string $item, string $key ): string {
	if ( preg_match(
		'#<wp:meta_key><!\[CDATA\[' . preg_quote( $key, '#' ) . '\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
		$item, $m
	) ) {
		return html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
	}
	return '';
}

function momentive_mt_get_flags( array $argv ): array {
	$flags = array( 'live' => false, 'limit' => 0, 'only' => 0 );
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( 'live' === $tok ) {
			$flags['live'] = true;
		} elseif ( str_starts_with( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = (int) substr( $tok, 5 );
		}
	}
	return $flags;
}

/**
 * Normalize a quote for duplicate detection. Identical logic to
 * momentive_cs_norm_quote() in migrate-case-studies.php — kept local rather
 * than shared across scripts, same call as the slug map above.
 */
function momentive_mt_norm_quote( string $q ): string {
	if ( '' === trim( $q ) ) {
		return '';
	}
	$q   = html_entity_decode( $q, ENT_QUOTES, 'UTF-8' );
	$q   = wp_strip_all_tags( $q );
	$map = array(
		"\xE2\x80\x99" => "'", "\xE2\x80\x98" => "'",
		"\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
		"\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
		"\xC2\xA0"     => ' ',
	);
	$q = strtr( $q, $map );
	$q = preg_replace( '#\[[^\]]*\]#', ' ', $q );
	$q = strtolower( $q );
	$q = preg_replace( '#[^a-z0-9 ]#', ' ', $q );
	$q = preg_replace( '#\s+#', ' ', $q );
	return trim( (string) $q );
}

/** Build normalized-quote => post_id index of every existing published testimonial. */
function momentive_mt_build_existing_index(): array {
	$ids   = get_posts( array(
		'post_type'      => MOMENTIVE_MT_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	$index = array();
	foreach ( $ids as $id ) {
		$norm = momentive_mt_norm_quote( (string) get_post_field( 'post_content', $id ) );
		if ( '' !== $norm ) {
			$index[ $norm ] = $id;
		}
	}
	return $index;
}

/**
 * Build legacy case-study post_id => rebuilt case-study post_id, by matching
 * the legacy WXR's own <wp:post_name> slug against a live get_page_by_path()
 * lookup. migrate-case-studies.php upserts by slug (not import_id — case
 * study IDs are NOT preserved 1:1 the way Solutions/Testimonials are), so
 * this is the only reliable resolution path; legacy post ID alone cannot be
 * trusted to match the rebuilt case-study post ID.
 */
function momentive_mt_build_case_study_map(): array {
	$path = defined( 'MOMENTIVE_MT_CASE_STUDY_WXR' )
		? MOMENTIVE_MT_CASE_STUDY_WXR
		: __DIR__ . '/exports/momentivesoftware.case-studies.current.2026-09-01.xml';

	$map = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Legacy case-studies WXR not found at {$path}; related_case_study will be left unresolved for all posts (falls back to related_case_study_url only, which is empty in this export anyway)." );
		return $map;
	}

	$xml = file_get_contents( $path );
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $items );
	foreach ( $items[1] as $item ) {
		if ( momentive_mt_xml_tag( $item, 'wp:post_type' ) !== 'case_studies'
			&& momentive_mt_xml_tag( $item, 'wp:post_type' ) !== 'case-study' ) {
			continue;
		}
		$legacy_id = (int) momentive_mt_xml_tag( $item, 'wp:post_id' );
		$slug      = momentive_mt_xml_tag( $item, 'wp:post_name' );
		if ( ! $legacy_id || '' === $slug ) {
			continue;
		}
		$rebuilt = get_page_by_path( $slug, OBJECT, 'case-study' );
		if ( $rebuilt ) {
			$map[ $legacy_id ] = (int) $rebuilt->ID;
		}
	}
	WP_CLI::log( sprintf( 'Case study map: %d legacy IDs resolved to rebuilt case-study posts.', count( $map ) ) );
	return $map;
}

/**
 * Sideload an author photo by legacy attachment ID, resolved against the
 * attachment <item>s present in THIS export (the testimonials WXR carries
 * its own small attachment set — most referenced photo IDs are NOT present
 * as an <item> here, since the export was a targeted CPT pull, not a full
 * media export; those are logged as unresolved, not guessed at).
 */
function momentive_mt_build_photo_map( string $xml ): array {
	$map = array();
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $items );
	foreach ( $items[1] as $item ) {
		if ( momentive_mt_xml_tag( $item, 'wp:post_type' ) !== 'attachment' ) {
			continue;
		}
		$legacy_id = (int) momentive_mt_xml_tag( $item, 'wp:post_id' );
		$url       = momentive_mt_xml_tag( $item, 'wp:attachment_url' );
		if ( $legacy_id && '' !== $url ) {
			$map[ $legacy_id ] = $url;
		}
	}
	return $map;
}

function momentive_mt_sideload( string $url, int $post_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_momentive_source_url',
		'meta_value'     => $url,
		'no_found_rows'  => true,
	) );
	if ( $existing ) {
		return (int) $existing[0];
	}
	if ( $dry ) {
		WP_CLI::log( "    [dry-run] would sideload photo: {$url}" );
		return 0;
	}
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "    photo fetch FAILED: {$url} ({$tmp->get_error_message()})" );
		return 0;
	}
	$file_array = array(
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);
	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "    photo import FAILED: {$url} ({$att_id->get_error_message()})" );
		return 0;
	}
	update_post_meta( $att_id, '_momentive_source_url', $url );
	return (int) $att_id;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_mt_run( array $argv ): void {
	$flags   = momentive_mt_get_flags( $argv );
	$dry_run = ! $flags['live'];

	$wxr_path = defined( 'MOMENTIVE_MT_LEGACY_WXR' )
		? MOMENTIVE_MT_LEGACY_WXR
		: __DIR__ . '/exports/momentivesoftware.testimonials.current.2026-09-01.xml';

	if ( ! file_exists( $wxr_path ) ) {
		WP_CLI::error( "Export not found: {$wxr_path}" );
	}

	WP_CLI::log( $dry_run ? '=== DRY RUN — no writes will be made ===' : '=== LIVE RUN ===' );

	// Ensure the two testimonial_type terms exist (may already, from
	// backfill-testimonial-type-taxonomy.php / migrate-reviews.php).
	foreach ( MOMENTIVE_MT_TYPE_MAP as $term_name ) {
		if ( ! $dry_run && ! term_exists( $term_name, 'testimonial_type' ) ) {
			wp_insert_term( $term_name, 'testimonial_type' );
		}
	}

	$xml = file_get_contents( $wxr_path );
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $matches );

	$photo_map      = momentive_mt_build_photo_map( $xml );
	$case_study_map = momentive_mt_build_case_study_map();
	$existing_index = momentive_mt_build_existing_index();

	$processed = 0;
	$summary   = array(
		'created' => 0, 'already_exists' => 0, 'skipped_dup' => 0,
		'skipped_draft' => 0, 'photo_unresolved' => 0, 'case_study_unresolved' => 0,
	);

	foreach ( $matches[1] as $item ) {
		if ( momentive_mt_xml_tag( $item, 'wp:post_type' ) !== MOMENTIVE_MT_LEGACY ) {
			continue;
		}

		$legacy_id = (int) momentive_mt_xml_tag( $item, 'wp:post_id' );
		$status    = momentive_mt_xml_tag( $item, 'wp:status' );
		$title     = momentive_mt_xml_tag( $item, 'title' );

		if ( $flags['only'] && $legacy_id !== $flags['only'] ) {
			continue;
		}

		// ---- Gap check: skip anything that already exists on this site ----
		if ( get_post_type( $legacy_id ) === MOMENTIVE_MT_CPT ) {
			$summary['already_exists']++;
			continue;
		}
		if ( get_post_type( $legacy_id ) !== false ) {
			WP_CLI::warning( "SKIP #{$legacy_id} \"{$title}\" — legacy ID is already occupied by a different post type (" . get_post_type( $legacy_id ) . ") on this site. Cannot use import_id; needs manual review." );
			continue;
		}

		if ( 'publish' !== $status ) {
			WP_CLI::log( "SKIP #{$legacy_id} \"{$title}\" — status={$status}, not publishing drafts." );
			$summary['skipped_draft']++;
			continue;
		}

		if ( $flags['limit'] > 0 && $processed >= $flags['limit'] ) {
			break;
		}
		$processed++;

		$quote        = momentive_mt_xml_meta( $item, 'testimonial_content' );
		$author_name  = momentive_mt_xml_meta( $item, 'testimonial_author_name' );
		$author_desc  = momentive_mt_xml_meta( $item, 'testimonial_author_description' );
		$photo_legacy = (int) momentive_mt_xml_meta( $item, 'testimonial_author_photo' );
		$case_legacy  = (int) momentive_mt_xml_meta( $item, 'related_case_study' );
		$case_url     = momentive_mt_xml_meta( $item, 'case_study_url' );
		$sol_family   = momentive_mt_xml_meta( $item, 'solution_family' );
		$type_meta    = momentive_mt_xml_meta( $item, 'testimonial_type' );
		$post_date    = momentive_mt_xml_tag( $item, 'wp:post_date' );

		// ---- Possible-duplicate check: warn, don't silently create a dupe ----
		$norm = momentive_mt_norm_quote( $quote );
		if ( '' !== $norm && isset( $existing_index[ $norm ] ) ) {
			WP_CLI::warning( "SKIP #{$legacy_id} \"{$title}\" — quote normalizes identically to existing testimonial #{$existing_index[ $norm ]} (\"" . get_the_title( $existing_index[ $norm ] ) . "\"). Likely already imported under a different ID during the original ad hoc import. Not creating — verify by hand and use only={$legacy_id} to force if this is genuinely a distinct post." );
			$summary['skipped_dup']++;
			continue;
		}

		WP_CLI::log( "CREATE #{$legacy_id} \"{$title}\" ({$author_name})" );

		if ( $dry_run ) {
			WP_CLI::log( sprintf( '  [dry-run] would create: quote=%.60s… | photo_legacy=%s | case_legacy=%s | solution_family=%s | type=%s',
				$quote, $photo_legacy ?: '(none)', $case_legacy ?: '(none)', $sol_family ?: '(none)', $type_meta ?: '(none)' ) );
			$summary['created']++;
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'    => MOMENTIVE_MT_CPT,
			'post_status'  => 'publish',
			'post_title'   => $title ?: wp_trim_words( wp_strip_all_tags( $quote ), 8, '…' ),
			// See header comment — post_content is the ONLY place the block
			// renderer reads the quote from. Do not switch this to update_field().
			'post_content' => wp_kses_post( $quote ),
			'import_id'    => $legacy_id,
		), true );

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( '  failed to create: ' . $post_id->get_error_message() );
			continue;
		}

		update_field( 'testimonial_author_name', $author_name, $post_id );
		update_field( 'testimonial_author_description', $author_desc, $post_id );

		// ---- Author photo ----
		if ( $photo_legacy && isset( $photo_map[ $photo_legacy ] ) ) {
			$att_id = momentive_mt_sideload( $photo_map[ $photo_legacy ], $post_id, false );
			if ( $att_id ) {
				update_field( 'testimonial_author_photo', $att_id, $post_id );
			}
		} elseif ( $photo_legacy ) {
			WP_CLI::warning( "  #{$legacy_id} — author photo (legacy attachment #{$photo_legacy}) not present in this export; left empty." );
			$summary['photo_unresolved']++;
		}

		// ---- Related case study ----
		if ( $case_legacy && isset( $case_study_map[ $case_legacy ] ) ) {
			update_field( 'related_case_study', $case_study_map[ $case_legacy ], $post_id );
		} elseif ( $case_legacy ) {
			WP_CLI::warning( "  #{$legacy_id} — related_case_study (legacy #{$case_legacy}) could not be resolved to a rebuilt case-study post; left empty." );
			$summary['case_study_unresolved']++;
			if ( $case_url ) {
				update_field( 'related_case_study_url', $case_url, $post_id );
			}
		} elseif ( $case_url ) {
			update_field( 'related_case_study_url', $case_url, $post_id );
		}

		// ---- Category (Solution family) ----
		if ( $sol_family && isset( MOMENTIVE_MT_SLUG_MAP[ $sol_family ] ) ) {
			$term = get_term_by( 'slug', MOMENTIVE_MT_SLUG_MAP[ $sol_family ], 'category' );
			if ( $term ) {
				wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'category', false );
			} else {
				WP_CLI::warning( "  #{$legacy_id} — mapped category slug \"" . MOMENTIVE_MT_SLUG_MAP[ $sol_family ] . '" has no matching term on this site.' );
			}
		} elseif ( $sol_family ) {
			WP_CLI::warning( "  #{$legacy_id} — solution_family \"{$sol_family}\" has no entry in MOMENTIVE_MT_SLUG_MAP." );
		}

		// ---- Type taxonomy ----
		if ( $type_meta && isset( MOMENTIVE_MT_TYPE_MAP[ $type_meta ] ) ) {
			wp_set_object_terms( $post_id, MOMENTIVE_MT_TYPE_MAP[ $type_meta ], 'testimonial_type', false );
		}

		update_post_meta( $post_id, MOMENTIVE_MT_RUN_META, gmdate( 'Y-m-d H:i:s' ) );

		// Force the real legacy post date — wp_insert_post's date handling for
		// an already-'publish' status isn't reliable, same fix used in every
		// other migration script in this project.
		if ( $post_date ) {
			global $wpdb;
			$wpdb->update( $wpdb->posts, array(
				'post_date'     => $post_date,
				'post_date_gmt' => get_gmt_from_date( $post_date ),
			), array( 'ID' => $post_id ) );
		}

		$summary['created']++;
	}

	WP_CLI::log( '' );
	WP_CLI::success( sprintf(
		'%s complete. Created: %d. Already existed (skipped): %d. Possible duplicate (skipped): %d. Draft (skipped): %d. Unresolved photos: %d. Unresolved case studies: %d.',
		$dry_run ? 'Dry run' : 'Migration',
		$summary['created'], $summary['already_exists'], $summary['skipped_dup'],
		$summary['skipped_draft'], $summary['photo_unresolved'], $summary['case_study_unresolved']
	) );

	if ( $dry_run ) {
		WP_CLI::log( 'Re-run with `live` to write.' );
	}
}

momentive_mt_run( isset( $args ) && is_array( $args ) ? $args : array() );
