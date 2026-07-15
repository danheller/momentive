<?php
/**
 * test-svg-sideload.php
 *
 * Standalone check: does SVG sideloading work on THIS install?
 * Run before the full migration to confirm the SVG fix, in isolation:
 *
 *   wp eval-file test-svg-sideload.php
 *
 * It attempts to sideload one known SVG logo and reports success/failure with
 * the attachment ID and resulting URL. It does NOT attach to any case study.
 * Delete the created attachment afterward if you don't want it.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$test_url = 'https://momentivesoftware.com/wp-content/uploads/2025/07/ACTFL-LOGO.svg';

if ( ! function_exists( 'media_handle_sideload' ) ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

WP_CLI::log( "Fetching: {$test_url}" );
$tmp = download_url( $test_url );
if ( is_wp_error( $tmp ) ) {
	WP_CLI::error( 'download_url failed: ' . $tmp->get_error_message() );
}

$file_array = array(
	'name'     => basename( wp_parse_url( $test_url, PHP_URL_PATH ) ),
	'tmp_name' => $tmp,
);

// Report what fileinfo thinks the real MIME is — the usual culprit.
if ( function_exists( 'finfo_open' ) ) {
	$f = finfo_open( FILEINFO_MIME_TYPE );
	$real = finfo_file( $f, $tmp );
	finfo_close( $f );
	WP_CLI::log( "fileinfo real MIME: {$real}" );
}

$mime_cb = static function ( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
};
$check_cb = static function ( $data, $file = '', $filename = '', $mimes = null, $real_mime = '' ) {
	$name = is_string( $filename ) && '' !== $filename ? $filename : '';
	if ( preg_match( '/\.svgz?$/i', (string) $name ) ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}
	return $data;
};

add_filter( 'upload_mimes', $mime_cb, 99 );
add_filter( 'wp_check_filetype_and_ext', $check_cb, 99, 5 );

$att_id = media_handle_sideload( $file_array, 0 );

remove_filter( 'upload_mimes', $mime_cb, 99 );
remove_filter( 'wp_check_filetype_and_ext', $check_cb, 99 );

if ( is_wp_error( $att_id ) ) {
	@unlink( $tmp );
	WP_CLI::error( 'SVG sideload FAILED: ' . $att_id->get_error_message() );
}

WP_CLI::success( sprintf(
	'SVG sideload OK — attachment #%d at %s',
	$att_id,
	wp_get_attachment_url( $att_id )
) );
WP_CLI::log( '(delete this test attachment with: wp post delete ' . $att_id . ' --force)' );
