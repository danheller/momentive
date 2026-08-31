<?php
/**
 * Migration: Donation Examples
 *
 * Migrates the legacy `donation_examples` CPT (32 posts) to the rebuilt
 * `fundraiser` CPT. Reads from the WXR export; writes to the rebuilt DB.
 *
 * Field mapping:
 *   post_title              → post_title (event name)
 *   de_summary (HTML)       → post_excerpt (stripped to plain text)
 *   de_example_title        → ACF organization_name
 *   de_campaign_link        → ACF campaign_link
 *   de_example_image (ID)   → featured image (sideloaded from legacy URL)
 *   organization_type tax   → organization_type taxonomy (same slug)
 *   fundraising_features    → fundraising_features taxonomy (same slug)
 *   post_date / post_modified → preserved
 *
 * Usage:
 *   wp eval-file migrate-fundraisers.php            # dry run (default)
 *   wp eval-file migrate-fundraisers.php live       # writes to DB
 *   wp eval-file migrate-fundraisers.php live only=hope-parade-event
 *   wp eval-file migrate-fundraisers.php live limit=5
 *
 * Must run with --user=<admin> (Safe SVG / media capability gate).
 * Idempotent: upserts by legacy post ID (preserved via import_id).
 *
 * @package Momentive
 */

// ── Configuration ──────────────────────────────────────────────────────────────

define( 'MOMENTIVE_DE_WXR', defined( 'MOMENTIVE_DE_LEGACY_WXR' )
	? MOMENTIVE_DE_LEGACY_WXR
	: __DIR__ . '/exports/momentivesoftware.fundraisers.current.2026-07-27.xml'
);

define( 'MOMENTIVE_DE_UPLOADS_BASE', defined( 'MOMENTIVE_DE_UPLOADS_BASE_URL' )
	? MOMENTIVE_DE_UPLOADS_BASE_URL
	: 'https://momentivesoftware.com/wp-content/uploads'
);

define( 'MOMENTIVE_DE_RUN_META', '_momentive_de_migration_run' );

// ── Positional args ────────────────────────────────────────────────────────────
// wp eval-file doesn't accept --flags; use positional tokens instead.

$is_live   = in_array( 'live', $args ?? [], true );
$only_slug = null;
$limit     = 0;

foreach ( $args ?? [] as $arg ) {
	if ( str_starts_with( $arg, 'only=' ) ) $only_slug = substr( $arg, 5 );
	if ( str_starts_with( $arg, 'limit=' ) ) $limit = (int) substr( $arg, 6 );
}

WP_CLI::log( $is_live
	? '▶  LIVE RUN — posts will be written to the database.'
	: '○  DRY RUN — no changes will be made. Pass `live` to write.' );

if ( ! file_exists( MOMENTIVE_DE_WXR ) ) {
	WP_CLI::error( 'WXR file not found: ' . MOMENTIVE_DE_WXR );
}

// ── Parse WXR ─────────────────────────────────────────────────────────────────

$xml = simplexml_load_file( MOMENTIVE_DE_WXR );
if ( ! $xml ) {
	WP_CLI::error( 'Failed to parse WXR file.' );
}

$xml->registerXPathNamespace( 'wp',      'http://wordpress.org/export/1.2/' );
$xml->registerXPathNamespace( 'content', 'http://purl.org/rss/1.0/modules/content/' );
$xml->registerXPathNamespace( 'excerpt', 'http://wordpress.org/export/1.2/excerpt/' );
$xml->registerXPathNamespace( 'dc',      'http://purl.org/dc/elements/1.1/' );

// Build attachment URL map from the WXR itself (avoids needing a separate media export).
$attachment_url_map = []; // legacy attachment ID => full URL
foreach ( $xml->channel->item as $item ) {
	$post_type = (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_type;
	if ( $post_type !== 'attachment' ) continue;
	$post_id  = (int) $item->children( 'http://wordpress.org/export/1.2/' )->post_id;
	$guid     = (string) $item->guid;
	if ( $post_id && $guid ) {
		$attachment_url_map[ $post_id ] = $guid;
	}
	// Also index by _wp_attached_file meta path.
	foreach ( $item->children( 'http://wordpress.org/export/1.2/' )->postmeta as $meta ) {
		if ( (string) $meta->meta_key === '_wp_attached_file' ) {
			$rel = (string) $meta->meta_value;
			if ( $rel && ! isset( $attachment_url_map[ $post_id ] ) ) {
				$attachment_url_map[ $post_id ] = MOMENTIVE_DE_UPLOADS_BASE . '/' . $rel;
			}
		}
	}
}

// Collect donation_examples posts.
$posts = [];
foreach ( $xml->channel->item as $item ) {
	$post_type = (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_type;
	$status    = (string) $item->children( 'http://wordpress.org/export/1.2/' )->status;
	if ( $post_type !== 'donation_examples' ) continue;
	if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) continue;
	$posts[] = $item;
}

if ( $only_slug ) {
	$posts = array_filter( $posts, fn( $item ) =>
		(string) $item->children( 'http://wordpress.org/export/1.2/' )->post_name === $only_slug
	);
}
if ( $limit > 0 ) {
	$posts = array_slice( $posts, 0, $limit );
}

WP_CLI::log( sprintf( 'Found %d donation_examples posts to migrate.', count( $posts ) ) );

// ── Counters ──────────────────────────────────────────────────────────────────

$created = $updated = $skipped = $errors = 0;
$no_image = $no_campaign_link = [];

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Get a postmeta value from a SimpleXML item.
 */
function msw_de_get_meta( SimpleXMLElement $item, string $key ): string {
	foreach ( $item->children( 'http://wordpress.org/export/1.2/' )->postmeta as $meta ) {
		if ( (string) $meta->meta_key === $key ) {
			return (string) $meta->meta_value;
		}
	}
	return '';
}

/**
 * Strip HTML and return plain-text excerpt.
 * de_summary contains <p> tags from the legacy WYSIWYG field.
 */
function msw_de_strip_html( string $html ): string {
	$text = wp_strip_all_tags( $html );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Sideload an image from a URL and return the new attachment ID,
 * or 0 on failure. Deduplicates via _momentive_source_url meta.
 */
function msw_de_sideload_image( string $url, int $post_id ): int {
	if ( ! $url ) return 0;

	// Check for an already-sideloaded copy.
	$existing = get_posts( [
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_momentive_source_url',
		'meta_value'  => $url,
		'fields'      => 'ids',
		'numberposts' => 1,
	] );
	if ( ! empty( $existing ) ) {
		return $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "  [image] download failed: {$url} — " . $tmp->get_error_message() );
		return 0;
	}

	$file_array = [
		'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	];

	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "  [image] sideload failed: {$url} — " . $att_id->get_error_message() );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return $att_id;
}

// ── Main loop ─────────────────────────────────────────────────────────────────

foreach ( $posts as $item ) {
	$wp    = $item->children( 'http://wordpress.org/export/1.2/' );
	$title = (string) $item->title;
	$slug  = (string) $wp->post_name;
	$legacy_id = (int) $wp->post_id;
	$status    = (string) $wp->status === 'publish' ? 'publish' : 'draft';

	WP_CLI::log( "Processing: [{$legacy_id}] {$title}" );

	// ── Field extraction ─────────────────────────────────────────────────────

	$org_name      = trim( msw_de_get_meta( $item, 'de_example_title' ) );
	$campaign_link = trim( msw_de_get_meta( $item, 'de_campaign_link' ) );
	$summary_html  = msw_de_get_meta( $item, 'de_summary' );
	$image_id_str  = msw_de_get_meta( $item, 'de_example_image' );
	$legacy_img_id = $image_id_str ? (int) $image_id_str : 0;

	$excerpt = msw_de_strip_html( $summary_html );

	if ( ! $campaign_link ) {
		$no_campaign_link[] = $title;
		WP_CLI::warning( "  [link] no campaign_link on: {$title}" );
	}

	// ── Taxonomy terms ───────────────────────────────────────────────────────

	$org_type_slugs = [];
	$feature_slugs  = [];

	foreach ( $item->category as $cat ) {
		$domain   = (string) $cat['domain'];
		$nicename = (string) $cat['nicename'];
		if ( $domain === 'organization_type' )  $org_type_slugs[] = $nicename;
		if ( $domain === 'fundraising_features' ) $feature_slugs[] = $nicename;
	}

	// ── Dates ────────────────────────────────────────────────────────────────

	$post_date     = (string) $wp->post_date;
	$post_date_gmt = (string) $wp->post_date_gmt;
	$post_modified = (string) $wp->post_modified;
	$post_mod_gmt  = (string) $wp->post_modified_gmt;

	// ── Upsert ──────────────────────────────────────────────────────────────
	// Match by legacy post ID (preserved via import_id). Fall back to slug.

	$existing = get_post( $legacy_id );
	if ( $existing && $existing->post_type !== 'fundraiser' ) {
		$existing = null; // ID taken by a different post type; fall back to slug.
	}
	if ( ! $existing ) {
		$existing = get_page_by_path( $slug, OBJECT, 'fundraiser' );
	}

	if ( $is_live ) {
		global $wpdb;

		$post_data = [
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_type'    => 'fundraiser',
			'post_date'    => $post_date,
			'post_date_gmt'=> $post_date_gmt,
		];

		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
			$new_id = wp_update_post( $post_data, true );
			if ( is_wp_error( $new_id ) ) {
				WP_CLI::warning( "  [error] update failed: " . $new_id->get_error_message() );
				$errors++;
				continue;
			}
			$updated++;
			WP_CLI::log( "  → updated post {$new_id}" );
		} else {
			$post_data['import_id'] = $legacy_id;
			$new_id = wp_insert_post( $post_data, true );
			if ( is_wp_error( $new_id ) ) {
				WP_CLI::warning( "  [error] insert failed: " . $new_id->get_error_message() );
				$errors++;
				continue;
			}
			$created++;
			WP_CLI::log( "  → created post {$new_id}" );
		}

		// Preserve original post_modified (wp_update_post always sets it to now).
		$wpdb->update(
			$wpdb->posts,
			[ 'post_modified' => $post_modified, 'post_modified_gmt' => $post_mod_gmt ],
			[ 'ID' => $new_id ]
		);

		// ── ACF fields ───────────────────────────────────────────────────────

		update_field( 'organization_name', $org_name, $new_id );
		update_field( 'campaign_link', $campaign_link, $new_id );

		// ── Featured image ───────────────────────────────────────────────────

		$image_url = $legacy_img_id && isset( $attachment_url_map[ $legacy_img_id ] )
			? $attachment_url_map[ $legacy_img_id ]
			: '';

		if ( $image_url ) {
			$att_id = msw_de_sideload_image( $image_url, $new_id );
			if ( $att_id ) {
				set_post_thumbnail( $new_id, $att_id );
				WP_CLI::log( "  [image] set featured image (attachment {$att_id})" );
			} else {
				$no_image[] = $title;
			}
		} else {
			$no_image[] = $title;
			WP_CLI::warning( "  [image] no image URL found for legacy attachment ID {$legacy_img_id}" );
		}

		// ── Taxonomy terms ───────────────────────────────────────────────────

		// Ensure terms exist, then assign by slug.
		$org_type_ids = [];
		foreach ( $org_type_slugs as $slug_term ) {
			$term = get_term_by( 'slug', $slug_term, 'organization_type' );
			if ( ! $term ) {
				// Create it — term name is derived from slug (e.g. "human-services" → "Human Services").
				$name   = ucwords( str_replace( '-', ' ', $slug_term ) );
				$result = wp_insert_term( $name, 'organization_type', [ 'slug' => $slug_term ] );
				$term   = is_wp_error( $result ) ? null : get_term( $result['term_id'], 'organization_type' );
			}
			if ( $term ) $org_type_ids[] = $term->term_id;
		}
		if ( $org_type_ids ) {
			wp_set_object_terms( $new_id, $org_type_ids, 'organization_type' );
		}

		$feature_ids = [];
		foreach ( $feature_slugs as $slug_term ) {
			$term = get_term_by( 'slug', $slug_term, 'fundraising_features' );
			if ( ! $term ) {
				$name   = ucwords( str_replace( '-', ' ', $slug_term ) );
				$result = wp_insert_term( $name, 'fundraising_features', [ 'slug' => $slug_term ] );
				$term   = is_wp_error( $result ) ? null : get_term( $result['term_id'], 'fundraising_features' );
			}
			if ( $term ) $feature_ids[] = $term->term_id;
		}
		if ( $feature_ids ) {
			wp_set_object_terms( $new_id, $feature_ids, 'fundraising_features' );
		}

		// ── Run stamp ────────────────────────────────────────────────────────

		update_post_meta( $new_id, MOMENTIVE_DE_RUN_META, gmdate( 'Y-m-d H:i:s' ) );

	} else {
		// Dry run — log what would happen.
		$action = $existing ? 'update' : 'create';
		WP_CLI::log( "  [dry] would {$action} | org: {$org_name} | link: {$campaign_link}" );
		WP_CLI::log( "  [dry] org_types: " . implode( ', ', $org_type_slugs ) .
					 " | features: " . implode( ', ', $feature_slugs ) );
		WP_CLI::log( "  [dry] image legacy ID: {$legacy_img_id}" .
					 ( isset( $attachment_url_map[ $legacy_img_id ] )
					   ? ' ✓ URL found'
					   : ' ✗ URL not in WXR' ) );
	}
}

// ── Summary ────────────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '── Migration complete ──────────────────────────────────────────' );
WP_CLI::log( "  Created:  {$created}" );
WP_CLI::log( "  Updated:  {$updated}" );
WP_CLI::log( "  Errors:   {$errors}" );

if ( ! empty( $no_image ) ) {
	WP_CLI::log( '' );
	WP_CLI::warning( 'Posts with no image sideloaded (' . count( $no_image ) . '):' );
	foreach ( $no_image as $t ) WP_CLI::log( "  - {$t}" );
}

if ( ! empty( $no_campaign_link ) ) {
	WP_CLI::log( '' );
	WP_CLI::warning( 'Posts with no campaign link (' . count( $no_campaign_link ) . '):' );
	foreach ( $no_campaign_link as $t ) WP_CLI::log( "  - {$t}" );
}

if ( $is_live ) {
	WP_CLI::success( 'Done. Flush permalinks if this is the first run: wp rewrite flush' );
} else {
	WP_CLI::log( "Run with 'live' to write these changes to the database." );
}
