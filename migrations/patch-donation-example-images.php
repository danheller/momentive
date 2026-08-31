<?php
/**
 * Patch: Donation Example Images
 *
 * Sideloads featured images for fundraiser posts that were created by
 * migrate-fundraisers.php but are missing a featured image — typically
 * because the source URLs on momentivedev.wpenginepowered.com require HTTP
 * Basic Auth, which download_url() doesn't support.
 *
 * Usage:
 *   wp eval-file patch-fundraiser-images.php auth=user:pass            # dry run
 *   wp eval-file patch-fundraiser-images.php auth=user:pass live       # writes
 *   wp eval-file patch-fundraiser-images.php auth=user:pass live only=4048
 *
 * Must run with --user=<admin> (Safe SVG / media capability gate).
 *
 * The auth=user:pass token is the HTTP Basic Auth credentials for the WP Engine
 * staging environment (set in the WP Engine portal under your install → HTTP Auth).
 *
 * @package Momentive
 */

// ── Config ──────────────────────────────────────────────────────────────────

define( 'MOMENTIVE_DEPI_WXR', defined( 'MOMENTIVE_DE_LEGACY_WXR' )
	? MOMENTIVE_DE_LEGACY_WXR
	: __DIR__ . '/exports/momentivesoftware.fundraisers.current.2026-07-27.xml'
);

define( 'MOMENTIVE_DEPI_UPLOADS_BASE', 'https://momentivedev.wpenginepowered.com/wp-content/uploads' );

// ── Args ────────────────────────────────────────────────────────────────────

$is_live   = in_array( 'live', $args ?? [], true );
$only_id   = null;
$basic_auth = '';

foreach ( $args ?? [] as $arg ) {
	if ( str_starts_with( $arg, 'auth=' ) )  $basic_auth = substr( $arg, 5 );
	if ( str_starts_with( $arg, 'only=' ) )  $only_id    = (int) substr( $arg, 5 );
}

if ( ! $basic_auth ) {
	WP_CLI::error( 'Required: auth=user:pass  (HTTP Basic Auth for the staging environment).' );
}

WP_CLI::log( $is_live
	? '▶  LIVE RUN — featured images will be sideloaded.'
	: '○  DRY RUN — no changes will be made. Pass `live` to write.' );

// ── Parse WXR — build attachment URL map ────────────────────────────────────

if ( ! file_exists( MOMENTIVE_DEPI_WXR ) ) {
	WP_CLI::error( 'WXR file not found: ' . MOMENTIVE_DEPI_WXR );
}

$xml = simplexml_load_file( MOMENTIVE_DEPI_WXR );
if ( ! $xml ) {
	WP_CLI::error( 'Failed to parse WXR file.' );
}

$xml->registerXPathNamespace( 'wp', 'http://wordpress.org/export/1.2/' );

// Map: legacy attachment ID → URL
$attachment_url_map = [];
foreach ( $xml->channel->item as $item ) {
	$wp   = $item->children( 'http://wordpress.org/export/1.2/' );
	$type = (string) $wp->post_type;
	if ( $type !== 'attachment' ) continue;

	$post_id = (int) $wp->post_id;
	$guid    = (string) $item->guid;
	if ( $post_id && $guid ) {
		$attachment_url_map[ $post_id ] = $guid;
	}
	foreach ( $wp->postmeta as $meta ) {
		if ( (string) $meta->meta_key === '_wp_attached_file' && ! isset( $attachment_url_map[ $post_id ] ) ) {
			$attachment_url_map[ $post_id ] = MOMENTIVE_DEPI_UPLOADS_BASE . '/' . (string) $meta->meta_value;
		}
	}
}

// Map: legacy post ID → legacy attachment ID
$post_image_map = []; // legacy_post_id => legacy_attachment_id
foreach ( $xml->channel->item as $item ) {
	$wp   = $item->children( 'http://wordpress.org/export/1.2/' );
	$type = (string) $wp->post_type;
	if ( $type !== 'donation_examples' ) continue;

	$legacy_id = (int) $wp->post_id;
	foreach ( $wp->postmeta as $meta ) {
		if ( (string) $meta->meta_key === 'de_example_image' ) {
			$post_image_map[ $legacy_id ] = (int) $meta->meta_value;
			break;
		}
	}
}

// ── Find fundraiser posts missing a featured image ────────────────────

$query_args = [
	'post_type'      => 'fundraiser',
	'post_status'    => [ 'publish', 'draft' ],
	'posts_per_page' => -1,
	'meta_query'     => [
		[
			'key'     => '_thumbnail_id',
			'compare' => 'NOT EXISTS',
		],
	],
	'fields' => 'ids',
];

if ( $only_id ) {
	unset( $query_args['meta_query'] ); // patch even if it already has an image when targeting one post
	$query_args['p'] = $only_id;
}

$post_ids = get_posts( $query_args );

if ( empty( $post_ids ) ) {
	WP_CLI::success( 'No fundraiser posts are missing a featured image.' );
	exit;
}

WP_CLI::log( sprintf( 'Found %d post(s) to patch.', count( $post_ids ) ) );

// ── Helper: authenticated sideload ──────────────────────────────────────────

function msw_depi_sideload( string $url, int $post_id, string $basic_auth ): int {
	if ( ! $url ) return 0;

	// Dedup by source URL.
	$existing = get_posts( [
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_key'    => '_momentive_source_url',
		'meta_value'  => $url,
		'fields'      => 'ids',
		'numberposts' => 1,
	] );
	if ( ! empty( $existing ) ) {
		WP_CLI::log( "  [image] reusing existing attachment {$existing[0]}" );
		return $existing[0];
	}

	// Authenticated download to a temp file.
	$response = wp_remote_get( $url, [
		'timeout' => 60,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $basic_auth ),
		],
	] );

	if ( is_wp_error( $response ) ) {
		WP_CLI::warning( "  [image] request failed: {$url} — " . $response->get_error_message() );
		return 0;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code !== 200 ) {
		WP_CLI::warning( "  [image] HTTP {$code}: {$url}" );
		return 0;
	}

	$body = wp_remote_retrieve_body( $response );
	if ( ! $body ) {
		WP_CLI::warning( "  [image] empty response body: {$url}" );
		return 0;
	}

	// Write to temp file.
	$tmp = wp_tempnam( basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
	file_put_contents( $tmp, $body );

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$file_array = [
		'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	];

	$att_id = media_handle_sideload( $file_array, $post_id );
	@unlink( $tmp );

	if ( is_wp_error( $att_id ) ) {
		WP_CLI::warning( "  [image] sideload failed: {$url} — " . $att_id->get_error_message() );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return $att_id;
}

// ── Main loop ────────────────────────────────────────────────────────────────

$patched = $failed = 0;

foreach ( $post_ids as $post_id ) {
	$post = get_post( $post_id );
	WP_CLI::log( "Processing: [{$post_id}] {$post->post_title}" );

	// Look up the legacy attachment ID → URL from the WXR maps.
	$legacy_att_id = $post_image_map[ $post_id ] ?? 0;
	$image_url     = $legacy_att_id && isset( $attachment_url_map[ $legacy_att_id ] )
		? $attachment_url_map[ $legacy_att_id ]
		: '';

	if ( ! $image_url ) {
		WP_CLI::warning( "  [image] no URL found for post {$post_id} (legacy att ID: {$legacy_att_id})" );
		$failed++;
		continue;
	}

	WP_CLI::log( "  [image] {$image_url}" );

	if ( $is_live ) {
		$att_id = msw_depi_sideload( $image_url, $post_id, $basic_auth );
		if ( $att_id ) {
			set_post_thumbnail( $post_id, $att_id );
			WP_CLI::log( "  [image] ✓ set featured image (attachment {$att_id})" );
			$patched++;
		} else {
			$failed++;
		}
	} else {
		WP_CLI::log( "  [dry] would sideload and set as featured image" );
		$patched++;
	}
}

// ── Summary ──────────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '── Patch complete ──────────────────────────────────────────────' );
WP_CLI::log( "  Patched: {$patched}" );
WP_CLI::log( "  Failed:  {$failed}" );

if ( ! $is_live ) {
	WP_CLI::log( "Run with 'live' to apply these changes." );
}
