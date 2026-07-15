<?php
/**
 * patch-hero-image-dedup.php
 *
 * WP-CLI patch: clear hero_image on any post where it duplicates the
 * featured image.
 *
 * Background: hero_image is an optional ACF field that overrides the featured
 * image on singular views. When both fields point to the same attachment
 * (same image serving two roles), the override is redundant — the featured
 * image already covers both the archive card and the singular hero. Migration
 * scripts now skip the hero_image write in that case, but older migrated posts
 * may still carry redundant hero_image values.
 *
 * This script finds those posts across ALL post types and clears hero_image.
 * Two match modes are used in sequence:
 *
 *   1. Same attachment ID — hero_image ID == _thumbnail_id. Fast, no joins.
 *   2. Same file path — both attachments reference the same _wp_attached_file
 *      value. Catches cases where the same image was sideloaded twice (different
 *      IDs, same bytes). Only checked when mode 1 doesn't match.
 *
 * Run:
 *
 *   wp eval-file migrations/patch-hero-image-dedup.php --user=<admin>
 *     → dry run (default)
 *
 *   wp eval-file migrations/patch-hero-image-dedup.php live --user=<admin>
 *     → clears redundant hero_image fields and their ACF reference meta
 *
 *   wp eval-file migrations/patch-hero-image-dedup.php live post_type=whitepaper --user=<admin>
 *     → limit to one post type
 *
 * Idempotent: re-running finds nothing if all hero_image fields are already clear.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/patch-hero-image-dedup.php [live] [post_type=<type>] --user=<admin>' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Flag handling
 * ---------------------------------------------------------------------- */

function momentive_phid_get_flags( array $argv = [] ): array {
	$flags = [
		'dry_run'   => true,
		'post_type' => '',
	];
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( in_array( $tok, [ 'live', 'go' ], true ) ) {
			$flags['dry_run'] = false;
		} elseif ( in_array( $tok, [ 'dry', 'dry-run' ], true ) ) {
			$flags['dry_run'] = true;
		} elseif ( str_starts_with( $tok, 'post_type=' ) ) {
			$flags['post_type'] = substr( $tok, 10 );
		}
	}
	if ( getenv( 'MOMENTIVE_LIVE' ) ) { $flags['dry_run'] = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )  { $flags['dry_run'] = true; }
	return $flags;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_phid_run( array $argv = [] ): void {
	global $wpdb;

	$flags     = momentive_phid_get_flags( $argv );
	$dry       = $flags['dry_run'];
	$type_filter = $flags['post_type'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  patch-hero-image-dedup' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — CLEARING FIELDS ***' ) );
	if ( '' !== $type_filter ) {
		WP_CLI::log( '  post_type: ' . $type_filter );
	} else {
		WP_CLI::log( '  post_type: all' );
	}
	WP_CLI::log( '====================================================' );

	// Find all posts that have hero_image set (non-empty).
	// We join on posts to allow post_type filtering.
	$type_clause = '';
	$query_args  = [];

	if ( '' !== $type_filter ) {
		$type_clause = " AND p.post_type = %s";
		$query_args[] = $type_filter;
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT pm.post_id, pm.meta_value AS hero_att_id, p.post_type
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = 'hero_image'
		   AND pm.meta_value != ''
		   AND pm.meta_value != '0'
		   AND p.post_status != 'trash'
		   {$type_clause}
		 ORDER BY p.post_type, pm.post_id",
		...$query_args
	) );

	if ( empty( $rows ) ) {
		WP_CLI::log( 'No posts found with hero_image set. Nothing to do.' );
		WP_CLI::success( $dry ? 'Dry run complete.' : 'Patch complete.' );
		return;
	}

	WP_CLI::log( sprintf( 'Found %d post(s) with hero_image set. Checking for duplicates…', count( $rows ) ) );

	$summary = [
		'checked'         => 0,
		'same_id'         => 0,  // hero_image ID == _thumbnail_id
		'same_file'       => 0,  // different IDs, same _wp_attached_file path
		'cleared'         => 0,
		'not_redundant'   => 0,  // hero is genuinely different — left alone
		'no_thumbnail'    => 0,  // post has hero_image but no featured image — left alone
	];
	$cleared_log = [];

	foreach ( $rows as $row ) {
		$summary['checked']++;
		$post_id     = (int) $row->post_id;
		$hero_att_id = (int) $row->hero_att_id;
		$post_type   = $row->post_type;

		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		if ( 0 === $thumb_id ) {
			// Post has hero_image but no featured image. Leave it — removing
			// hero_image here would break the singular view entirely.
			$summary['no_thumbnail']++;
			WP_CLI::log( sprintf(
				'  [skip] post #%d (%s): hero_image=%d but no featured image set',
				$post_id, $post_type, $hero_att_id
			) );
			continue;
		}

		$is_redundant = false;
		$match_reason = '';

		// Mode 1: same attachment ID.
		if ( $hero_att_id === $thumb_id ) {
			$is_redundant = true;
			$match_reason = "same attachment ID ({$hero_att_id})";
			$summary['same_id']++;
		}

		// Mode 2: same file path (different IDs).
		if ( ! $is_redundant ) {
			$hero_file  = get_post_meta( $hero_att_id, '_wp_attached_file', true );
			$thumb_file = get_post_meta( $thumb_id,    '_wp_attached_file', true );
			if (
				'' !== $hero_file
				&& '' !== $thumb_file
				&& $hero_file === $thumb_file
			) {
				$is_redundant = true;
				$match_reason = "same file path \"{$hero_file}\" (hero att #{$hero_att_id}, thumb att #{$thumb_id})";
				$summary['same_file']++;
			}
		}

		if ( ! $is_redundant ) {
			$summary['not_redundant']++;
			continue;
		}

		$title = get_the_title( $post_id );
		WP_CLI::log( sprintf(
			'  [%s] post #%d (%s) "%s": clear hero_image — %s',
			$dry ? 'dry-run' : 'clear',
			$post_id, $post_type, $title, $match_reason
		) );
		$cleared_log[] = "#{$post_id} {$post_type}: {$title}";

		if ( ! $dry ) {
			// Delete hero_image and its ACF reference key (_hero_image).
			delete_post_meta( $post_id, 'hero_image' );
			delete_post_meta( $post_id, '_hero_image' );
			clean_post_cache( $post_id );
			$summary['cleared']++;
		}
	}

	WP_CLI::log( "\n== Summary ==" );
	foreach ( $summary as $k => $v ) {
		WP_CLI::log( sprintf( '  %-20s %d', $k, $v ) );
	}

	if ( $dry && ( $summary['same_id'] + $summary['same_file'] ) > 0 ) {
		WP_CLI::log( sprintf(
			"\n  %d redundant hero_image field(s) would be cleared in a live run.",
			$summary['same_id'] + $summary['same_file']
		) );
	}

	WP_CLI::success( $dry ? 'Dry run complete.' : 'Patch complete.' );
}

momentive_phid_run( isset( $args ) && is_array( $args ) ? $args : [] );
