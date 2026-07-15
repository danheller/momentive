<?php
/**
 * Patch: fix featured images and post_excerpt on migrated webinar posts.
 *
 * Addresses two bugs from the initial migration run:
 *
 *   1. Wrong featured image — the migration set featured image from
 *      `resource_hero_image` (the singular-view hero), not from `_thumbnail_id`
 *      (the archive card image). These are different attachments on 99 of 149
 *      webinars. This patch sideloads the correct `_thumbnail_id` image and
 *      sets it as the featured image.
 *
 *      hero_image handling:
 *        - When `_thumbnail_id == resource_hero_image`: hero_image is a
 *          redundant override (same image), so it is cleared.
 *        - When they differ: hero_image already holds the correct singular-view
 *          image and is left untouched.
 *
 *   2. post_excerpt missing — the migration never wrote excerpt text. This
 *      patch reads excerpt:encoded from the WXR and writes it.
 *
 * Usage (dry run, safe default):
 *   wp eval-file migrations/patch-webinar-images-excerpts.php --user=<admin>
 *
 * Usage (live write):
 *   wp eval-file migrations/patch-webinar-images-excerpts.php live --user=<admin>
 *
 * --user=<admin> is required: Safe SVG gates SVG handling on user capability.
 *
 * Flags (positional, same pattern as migrate-webinars.php):
 *   live / go      → write changes (default: dry run)
 *   only=<slug>    → patch one post by its rebuilt slug
 */

/* ---- Flags ---------------------------------------------------------------- */

$_flags = isset( $args ) && is_array( $args ) ? $args : [];
$dry    = true;
$only   = '';

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )               { $dry  = false; }
	if ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) { $dry  = true; }
	if ( str_starts_with( $tok, 'only=' ) )                        { $only = substr( $tok, 5 ); }
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  Webinar patch: featured images + post_excerpt' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
if ( '' !== $only ) { WP_CLI::log( '  only: "' . $only . '"' ); }
WP_CLI::log( '=====================================================' );

/* ---- Shared WXR parser helper --------------------------------------------- */

function momentive_patch_xml_items( string $path ): array {
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "WXR not found: {$path}" );
		return [];
	}
	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::error( "Could not read: {$path}" );
		return [];
	}
	preg_match_all( '#<item>(.*?)</item>#s', $xml, $m );
	return $m[1] ?? [];
}

/* ---- Build legacy attachment-ID → URL map --------------------------------- */

$wxr_path = __DIR__ . '/momentivesoftware.webinars.current.2026-07-01.xml';
$base_url  = 'https://momentivesoftware.com/wp-content/uploads/';

$attach_map = [];
foreach ( momentive_patch_xml_items( $wxr_path ) as $item ) {
	if ( false === strpos( $item, 'post_type><![CDATA[attachment]]>' ) ) {
		continue;
	}
	if ( ! preg_match( '#<wp:post_id>(\d+)</wp:post_id>#', $item, $pm ) ) {
		continue;
	}
	if ( ! preg_match(
		'#<wp:meta_key><!\[CDATA\[_wp_attached_file\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]>#s',
		$item, $fm
	) ) {
		continue;
	}
	$attach_map[ (int) $pm[1] ] = $base_url . ltrim( $fm[1], '/' );
}

WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs.', count( $attach_map ) ) );

/* ---- Build slug → {thumb_id, hero_id, excerpt} map from WXR -------------- */

$legacy_map = []; // slug → array

foreach ( momentive_patch_xml_items( $wxr_path ) as $item ) {
	if ( false === strpos( $item, 'post_type><![CDATA[webinars]]>' ) ) {
		continue;
	}
	if ( ! preg_match( '#<wp:post_name><!\[CDATA\[(.*?)\]\]>#', $item, $sm ) ) {
		continue;
	}
	$slug = $sm[1];

	$metas = [];
	if ( preg_match_all(
		'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
		$item, $mm, PREG_SET_ORDER
	) ) {
		foreach ( $mm as $pair ) {
			if ( ! array_key_exists( $pair[1], $metas ) ) {
				$metas[ $pair[1] ] = $pair[2];
			}
		}
	}

	$exc = '';
	if ( preg_match( '#<excerpt:encoded><!\[CDATA\[(.*?)\]\]></excerpt:encoded>#s', $item, $em ) ) {
		$exc = trim( $em[1] );
	}

	$legacy_map[ $slug ] = [
		'thumb_id' => (int) ( $metas['_thumbnail_id']      ?? 0 ),
		'hero_id'  => (int) ( $metas['resource_hero_image'] ?? 0 ),
		'excerpt'  => $exc,
	];
}

WP_CLI::log( sprintf( 'Legacy WXR: %d webinar items.', count( $legacy_map ) ) );

/* ---- Sideload helper (mirrors migration script) --------------------------- */

function momentive_patch_sideload( string $url, int $post_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}

	// Dedup: already sideloaded?
	$existing = get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_momentive_source_url',
		'meta_value'     => $url,
		'no_found_rows'  => true,
	] );
	if ( $existing ) {
		return (int) $existing[0];
	}

	if ( $dry ) {
		WP_CLI::log( "    [dry] would sideload: {$url}" );
		return 0;
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "    media fetch FAILED: {$url} ({$tmp->get_error_message()})" );
		return 0;
	}

	$file_array = [
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	];
	$ext    = strtolower( pathinfo( $file_array['name'], PATHINFO_EXTENSION ) );
	$is_svg = in_array( $ext, [ 'svg', 'svgz' ], true );
	$mime_cb = static fn( $m ) => array_merge( $m, [ 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml' ] );

	if ( $is_svg ) { add_filter( 'upload_mimes', $mime_cb, 99 ); }
	$att_id = media_handle_sideload( $file_array, $post_id );
	if ( $is_svg ) { remove_filter( 'upload_mimes', $mime_cb, 99 ); }

	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "    media import FAILED: {$url} ({$att_id->get_error_message()})" );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return (int) $att_id;
}

/* ---- Query migrated webinar posts ---------------------------------------- */

$query_args = [
	'post_type'      => 'webinar',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
	'meta_key'       => '_momentive_migration_run',
];
if ( '' !== $only ) {
	$query_args['name'] = $only;
}

$post_ids = get_posts( $query_args );
WP_CLI::log( sprintf( 'Found %d migrated webinar post(s) to patch.', count( $post_ids ) ) . "\n" );

/* ---- Patch each post ------------------------------------------------------ */

$summary = [
	'processed'           => 0,
	'thumbnail_fixed'     => 0,  // new featured image sideloaded + set
	'thumbnail_already_ok'=> 0,  // _thumbnail_id == resource_hero_image; no change needed
	'thumbnail_missing'   => 0,  // no _thumbnail_id in legacy WXR
	'hero_cleared'        => 0,  // hero_image cleared (was redundant same-image override)
	'excerpt_written'     => 0,
	'slug_not_in_wxr'     => 0,
];
$media_failures = [];

foreach ( $post_ids as $post_id ) {
	$post_id = (int) $post_id;
	$slug    = get_post_field( 'post_name', $post_id );
	$title   = get_the_title( $post_id );
	$summary['processed']++;

	WP_CLI::log( "[{$post_id}] {$title} ({$slug})" );

	$legacy = $legacy_map[ $slug ] ?? null;
	if ( null === $legacy ) {
		WP_CLI::log( "  slug not in WXR — skipped" );
		$summary['slug_not_in_wxr']++;
		continue;
	}

	$thumb_id = $legacy['thumb_id'];
	$hero_id  = $legacy['hero_id'];

	/* 1. Featured image ---------------------------------------------------- */
	if ( $thumb_id === 0 ) {
		WP_CLI::log( "  no _thumbnail_id in legacy — thumbnail skipped" );
		$summary['thumbnail_missing']++;
	} elseif ( $thumb_id === $hero_id ) {
		// Same image: the migration already sideloaded it and set it as featured
		// image (just also set hero_image redundantly). Featured image is correct.
		WP_CLI::log( "  _thumbnail_id == resource_hero_image ({$thumb_id}) — featured image already correct" );
		$summary['thumbnail_already_ok']++;
	} else {
		// Different images: need to sideload _thumbnail_id and set as featured image.
		if ( ! isset( $attach_map[ $thumb_id ] ) ) {
			WP_CLI::log( "  thumbnail legacy ID {$thumb_id} not in attachment map — skipped" );
			$media_failures[] = "{$title}: thumbnail legacy ID {$thumb_id} not in attachment map";
			$summary['thumbnail_missing']++;
		} else {
			$url     = $attach_map[ $thumb_id ];
			$new_att = momentive_patch_sideload( $url, $post_id, $dry );
			if ( $new_att > 0 ) {
				if ( $dry ) {
					WP_CLI::log( "  [dry] would set featured image to attachment #{$new_att} ({$url})" );
				} else {
					set_post_thumbnail( $post_id, $new_att );
					WP_CLI::log( "  set featured image → #{$new_att}" );
				}
				$summary['thumbnail_fixed']++;
			} elseif ( ! $dry ) {
				WP_CLI::log( "  sideload failed — featured image not updated" );
				$media_failures[] = "{$title}: sideload failed for {$url}";
			}
		}
	}

	/* 2. hero_image: clear only when it was a redundant same-image override -- */
	if ( $thumb_id > 0 && $thumb_id === $hero_id ) {
		// hero_image ACF field has return_format=array; read ID from the array.
		$hero_field = get_field( 'hero_image', $post_id );
		$hero_att_id = is_array( $hero_field ) ? (int) ( $hero_field['ID'] ?? 0 ) : (int) $hero_field;

		if ( $hero_att_id > 0 ) {
			if ( $dry ) {
				WP_CLI::log( "  [dry] would clear hero_image (redundant same-image override)" );
			} else {
				update_field( 'hero_image', '', $post_id );
				WP_CLI::log( "  cleared hero_image (redundant same-image override)" );
			}
			$summary['hero_cleared']++;
		} else {
			WP_CLI::log( "  hero_image already empty — no action" );
		}
	}
	// When thumb_id != hero_id, hero_image correctly holds the singular hero — leave it.

	/* 3. post_excerpt ------------------------------------------------------ */
	$wxr_excerpt     = $legacy['excerpt'];
	$current_excerpt = get_post_field( 'post_excerpt', $post_id );

	if ( '' !== $current_excerpt ) {
		WP_CLI::log( "  excerpt already set — no action" );
	} elseif ( '' === $wxr_excerpt ) {
		WP_CLI::log( "  no excerpt in WXR — no action" );
	} else {
		if ( $dry ) {
			WP_CLI::log( "  [dry] would write excerpt: " . mb_substr( $wxr_excerpt, 0, 80 ) . '…' );
		} else {
			wp_update_post( [ 'ID' => $post_id, 'post_excerpt' => $wxr_excerpt ] );
			WP_CLI::log( "  wrote excerpt" );
		}
		$summary['excerpt_written']++;
	}
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( "\n== Summary ==" );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-26s %d', $k, $v ) );
}

if ( $media_failures ) {
	WP_CLI::log( "\n== Media failures (" . count( $media_failures ) . ") ==" );
	foreach ( $media_failures as $line ) {
		WP_CLI::log( '  ' . $line );
	}
}

WP_CLI::success( $dry ? 'Dry run complete. Pass `live` to apply changes.' : 'Patch complete.' );
