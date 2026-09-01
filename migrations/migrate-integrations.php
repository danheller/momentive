<?php
/**
 * Migration: Integrations
 *
 * Populates the rebuilt `integration` CPT from the legacy WXR export:
 *   - Sets integration_type and integration_capability taxonomy terms
 *   - Sideloads the logo image and writes it to the `integration_logo` ACF field
 *   - Sets `linked_products` ACF field to the Path LMS product post
 *
 * All 62 legacy integrations belong to Path LMS. If integrations for other
 * products are added later, run a targeted update via the block editor.
 *
 * Run modes (positional args — wp eval-file does not accept --flags):
 *
 *   wp eval-file migrations/migrate-integrations.php                  # dry run (default)
 *   wp eval-file migrations/migrate-integrations.php live             # writes
 *   wp eval-file migrations/migrate-integrations.php live limit=10    # first 10 only
 *   wp eval-file migrations/migrate-integrations.php live only=aba-mcle  # single slug
 *
 * Must run with --user=<admin-login-or-id> (required for media sideloading via Safe SVG).
 *
 * Overridable constants (define before running or in wp-config.php):
 *   MOMENTIVE_INT_WXR         — path to the WXR file (default: migrations/exports/momentivesoftware.integrations.current.2026-09-01.xml)
 *   MOMENTIVE_INT_UPLOADS_BASE — base URL for sideloading images (default: https://momentivesoftware.com/wp-content/uploads/)
 *   MOMENTIVE_INT_PATH_LMS_SLUG — slug of the Path LMS product post (default: path-lms)
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

// ── Config ────────────────────────────────────────────────────────────────────

$wxr_path     = defined( 'MOMENTIVE_INT_WXR' )
	? MOMENTIVE_INT_WXR
	: __DIR__ . '/exports/momentivesoftware.integrations.current.2026-09-01.xml';

$uploads_base = defined( 'MOMENTIVE_INT_UPLOADS_BASE' )
	? MOMENTIVE_INT_UPLOADS_BASE
	: 'https://momentivesoftware.com/wp-content/uploads/';

$path_lms_slug = defined( 'MOMENTIVE_INT_PATH_LMS_SLUG' )
	? MOMENTIVE_INT_PATH_LMS_SLUG
	: 'path-lms';

// ── Positional args ───────────────────────────────────────────────────────────

$live       = in_array( 'live', $args, true );
$only_slug  = '';
$limit      = 0;

foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, 'only=' ) ) {
		$only_slug = substr( $arg, 5 );
	}
	if ( str_starts_with( $arg, 'limit=' ) ) {
		$limit = (int) substr( $arg, 6 );
	}
}

if ( ! $live ) {
	WP_CLI::log( '=== DRY RUN — pass `live` to write ===' );
}

// ── Validate WXR ─────────────────────────────────────────────────────────────

if ( ! file_exists( $wxr_path ) ) {
	WP_CLI::error( "WXR not found: $wxr_path" );
}

// ── Resolve Path LMS product ──────────────────────────────────────────────────

$path_lms_post = get_page_by_path( $path_lms_slug, OBJECT, 'product' );
if ( ! $path_lms_post ) {
	WP_CLI::error( "Path LMS product not found (slug: $path_lms_slug). Set MOMENTIVE_INT_PATH_LMS_SLUG if the slug differs." );
}
$path_lms_id = (int) $path_lms_post->ID;
WP_CLI::log( "Path LMS product: #{$path_lms_id} \"{$path_lms_post->post_title}\"" );

// ── Parse WXR ────────────────────────────────────────────────────────────────

$xml = simplexml_load_file( $wxr_path );
if ( ! $xml ) {
	WP_CLI::error( "Failed to parse WXR: $wxr_path" );
}

$xml->registerXPathNamespace( 'wp',      'http://wordpress.org/export/1.2/' );
$xml->registerXPathNamespace( 'content', 'http://purl.org/rss/1.0/modules/content/' );

// Build attachment ID → source URL map from _wp_attached_file meta.
$attachment_url_map = []; // legacy_attachment_id => full URL

foreach ( $xml->channel->item as $item ) {
	$post_type = (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_type;
	if ( $post_type !== 'attachment' ) {
		continue;
	}
	$att_id = (int) (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_id;
	foreach ( $item->children( 'http://wordpress.org/export/1.2/' )->postmeta as $meta ) {
		$key = (string) $meta->meta_key;
		$val = (string) $meta->meta_value;
		if ( $key === '_wp_attached_file' ) {
			$attachment_url_map[ $att_id ] = rtrim( $uploads_base, '/' ) . '/' . $val;
			break;
		}
	}
}

WP_CLI::log( 'Attachment URL map: ' . count( $attachment_url_map ) . ' entries' );

// Collect integration items from WXR.
$legacy_items = []; // slug => data

foreach ( $xml->channel->item as $item ) {
	$post_type = (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_type;
	if ( $post_type !== 'integrations' ) { // legacy CPT slug is plural
		continue;
	}

	$slug   = (string) $item->children( 'http://wordpress.org/export/1.2/' )->post_name;
	$status = (string) $item->children( 'http://wordpress.org/export/1.2/' )->status;
	if ( $status !== 'publish' ) {
		continue;
	}

	$title        = (string) $item->title;
	$type_terms   = [];
	$cap_terms    = [];
	$thumbnail_id = 0;

	foreach ( $item->category as $cat ) {
		$domain   = (string) $cat['domain'];
		$nicename = (string) $cat['nicename'];
		$name     = (string) $cat;
		if ( $domain === 'integrations-type' ) {
			$type_terms[] = [ 'slug' => $nicename, 'name' => $name ];
		} elseif ( $domain === 'integrations-capabilities' ) {
			$cap_terms[] = [ 'slug' => $nicename, 'name' => $name ];
		}
	}

	foreach ( $item->children( 'http://wordpress.org/export/1.2/' )->postmeta as $meta ) {
		if ( (string) $meta->meta_key === '_thumbnail_id' ) {
			$thumbnail_id = (int) (string) $meta->meta_value;
			break;
		}
	}

	$legacy_items[ $slug ] = compact( 'slug', 'title', 'type_terms', 'cap_terms', 'thumbnail_id' );
}

WP_CLI::log( 'Integrations in WXR: ' . count( $legacy_items ) );

// ── Apply only= / limit= ──────────────────────────────────────────────────────

if ( $only_slug ) {
	if ( ! isset( $legacy_items[ $only_slug ] ) ) {
		WP_CLI::error( "No integration with slug '$only_slug' found in WXR." );
	}
	$legacy_items = [ $only_slug => $legacy_items[ $only_slug ] ];
}

if ( $limit > 0 ) {
	$legacy_items = array_slice( $legacy_items, 0, $limit, true );
}

// ── Counters ──────────────────────────────────────────────────────────────────

$count_created       = 0;
$count_updated       = 0;
$count_skipped       = 0;
$unresolved_posts    = [];
$unresolved_logos    = [];

// ── Helper: sideload image ────────────────────────────────────────────────────

/**
 * Sideload an image from a remote URL, deduping by _momentive_source_url.
 * Returns the new (or existing) attachment ID, or 0 on failure.
 */
function msw_int_sideload_image( string $url, string $title, bool $live ): int {
	if ( empty( $url ) ) {
		return 0;
	}

	// Dedup check.
	$existing = get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'meta_query'     => [
			[
				'key'   => '_momentive_source_url',
				'value' => $url,
			],
		],
		'fields' => 'ids',
	] );

	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	if ( ! $live ) {
		return -1; // Placeholder for dry run.
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}

	$file_array = [
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	];

	$att_id = media_handle_sideload( $file_array, 0, $title );

	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return (int) $att_id;
}

// ── Main loop ─────────────────────────────────────────────────────────────────

foreach ( $legacy_items as $slug => $data ) {

	// Find or create the rebuilt post.
	$rebuilt = get_page_by_path( $slug, OBJECT, 'integration' );
	if ( $rebuilt ) {
		$post_id = (int) $rebuilt->ID;
		WP_CLI::log( "[{$data['title']}] #{$post_id} (existing)" );
	} elseif ( $live ) {
		$post_id = wp_insert_post( [
			'post_type'   => 'integration',
			'post_status' => 'publish',
			'post_title'  => $data['title'],
			'post_name'   => $slug,
		], true );
		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( "[SKIP] Could not create post for slug: $slug — " . $post_id->get_error_message() );
			$unresolved_posts[] = $slug;
			$count_skipped++;
			continue;
		}
		WP_CLI::log( "[{$data['title']}] #{$post_id} (created)" );
		$count_created++;
	} else {
		WP_CLI::log( "[{$data['title']}] Would create post (slug: $slug)" );
		$post_id = 0; // Placeholder for dry run — field writes below are skipped when !$live.
	}

	// ── Taxonomy terms ────────────────────────────────────────────────────────

	// Seed terms if missing, then assign.
	$type_term_ids = [];
	foreach ( $data['type_terms'] as $t ) {
		$term = get_term_by( 'slug', $t['slug'], 'integration_type' );
		if ( ! $term ) {
			if ( $live ) {
				$result = wp_insert_term( $t['name'], 'integration_type', [ 'slug' => $t['slug'] ] );
				$term   = is_wp_error( $result ) ? null : get_term( $result['term_id'], 'integration_type' );
			} else {
				WP_CLI::log( "  [type] Would create term: {$t['name']}" );
			}
		}
		if ( $term ) {
			$type_term_ids[] = (int) $term->term_id;
		}
	}

	$cap_term_ids = [];
	foreach ( $data['cap_terms'] as $c ) {
		$term = get_term_by( 'slug', $c['slug'], 'integration_capability' );
		if ( ! $term ) {
			if ( $live ) {
				$result = wp_insert_term( $c['name'], 'integration_capability', [ 'slug' => $c['slug'] ] );
				$term   = is_wp_error( $result ) ? null : get_term( $result['term_id'], 'integration_capability' );
			} else {
				WP_CLI::log( "  [cap] Would create term: {$c['name']}" );
			}
		}
		if ( $term ) {
			$cap_term_ids[] = (int) $term->term_id;
		}
	}

	if ( $live ) {
		wp_set_object_terms( $post_id, $type_term_ids, 'integration_type' );
		wp_set_object_terms( $post_id, $cap_term_ids,  'integration_capability' );
		WP_CLI::log( '  [type] ' . implode( ', ', array_column( $data['type_terms'], 'name' ) ) );
		WP_CLI::log( '  [cap]  ' . ( $data['cap_terms'] ? implode( ', ', array_column( $data['cap_terms'], 'name' ) ) : '(none)' ) );
	} else {
		WP_CLI::log( '  [type] Would set: ' . implode( ', ', array_column( $data['type_terms'], 'name' ) ) );
		WP_CLI::log( '  [cap]  Would set: ' . ( $data['cap_terms'] ? implode( ', ', array_column( $data['cap_terms'], 'name' ) ) : '(none)' ) );
	}

	// ── Logo image ────────────────────────────────────────────────────────────

	$logo_url = $data['thumbnail_id'] && isset( $attachment_url_map[ $data['thumbnail_id'] ] )
		? $attachment_url_map[ $data['thumbnail_id'] ]
		: '';

	if ( $logo_url ) {
		$att_id = msw_int_sideload_image( $logo_url, $data['title'], $live );
		if ( $att_id === -1 ) {
			WP_CLI::log( "  [logo] Would sideload: $logo_url" );
		} elseif ( $att_id > 0 ) {
			if ( $live ) {
				set_post_thumbnail( $post_id, $att_id );
				WP_CLI::log( "  [logo] #{$att_id}" );
			}
		} else {
			WP_CLI::warning( "  [logo] Sideload failed: $logo_url" );
			$unresolved_logos[] = $slug;
		}
	} else {
		WP_CLI::warning( "  [logo] No source URL for thumbnail ID {$data['thumbnail_id']}" );
		$unresolved_logos[] = $slug;
	}

	// ── Linked products ───────────────────────────────────────────────────────

	if ( $live ) {
		update_field( 'linked_products', [ $path_lms_id ], $post_id );
		WP_CLI::log( "  [product] Path LMS #{$path_lms_id}" );
	} else {
		WP_CLI::log( "  [product] Would set: Path LMS #{$path_lms_id}" );
	}

	$count_updated++;
}

// ── Summary ───────────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '=== Summary ===' );
WP_CLI::log( ( $live ? 'Created' : 'Would create' ) . ": $count_created" );
WP_CLI::log( ( $live ? 'Updated' : 'Would update' ) . " (existing): $count_updated" );
WP_CLI::log( "Skipped (insert error): $count_skipped" );

if ( $unresolved_posts ) {
	WP_CLI::log( '' );
	WP_CLI::log( 'Posts not found on rebuilt site (' . count( $unresolved_posts ) . '):' );
	foreach ( $unresolved_posts as $s ) {
		WP_CLI::log( "  $s" );
	}
}

if ( $unresolved_logos ) {
	WP_CLI::log( '' );
	WP_CLI::log( 'Logo sideload failures (' . count( $unresolved_logos ) . '):' );
	foreach ( $unresolved_logos as $s ) {
		WP_CLI::log( "  $s" );
	}
}

if ( ! $live ) {
	WP_CLI::log( '' );
	WP_CLI::log( '=== DRY RUN complete — pass `live` to write ===' );
}
