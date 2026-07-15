<?php
/**
 * patch-case-study-dates.php
 *
 * Restore original posted/modified dates on already-migrated case-study posts,
 * read from the legacy WXR export, matched by slug. Run WITHOUT re-migrating:
 *
 *   wp eval-file patch-case-study-dates.php                 # DRY RUN (default)
 *   wp eval-file patch-case-study-dates.php live            # apply changes
 *
 * Set MOMENTIVE_LEGACY_WXR to the export path if it isn't beside this script.
 *
 * Safe to re-run: it only sets dates, idempotently. Dry-run by default so a
 * mis-typed flag can't write.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$dry = true;
foreach ( ( isset( $args ) && is_array( $args ) ? $args : array() ) as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( 'live' === $tok || 'go' === $tok ) { $dry = false; }
}
if ( getenv( 'MOMENTIVE_LIVE' ) ) { $dry = false; }
if ( getenv( 'MOMENTIVE_DRY' ) )  { $dry = true; }

$wxr = defined( 'MOMENTIVE_LEGACY_WXR' )
	? MOMENTIVE_LEGACY_WXR
	: __DIR__ . '/momentivesoftware_current-case-studies_2026-06-29.xml';

if ( ! file_exists( $wxr ) ) {
	WP_CLI::error( "Legacy WXR not found at {$wxr}. Set MOMENTIVE_LEGACY_WXR." );
}
$xml = file_get_contents( $wxr );
if ( false === $xml ) {
	WP_CLI::error( 'Could not read legacy WXR.' );
}

WP_CLI::log( '== Restore case-study dates ==' . ( $dry ? '  (DRY RUN)' : '  (LIVE)' ) );

/** Extract a single tag value (CDATA or plain) from an item block. */
$tag = static function ( string $item, string $name ): string {
	if ( preg_match( '#<' . preg_quote( $name, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $name, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $name, '#' ) . '>(.*?)</' . preg_quote( $name, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	return '';
};

$valid = static function ( $d ): string {
	$d = trim( (string) $d );
	return ( '' !== $d && 0 !== strpos( $d, '0000-00-00' ) ) ? $d : '';
};

// Build slug => dates from the WXR.
$by_slug = array();
if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
	foreach ( $items[1] as $item ) {
		if ( false === strpos( $item, '<wp:post_type><![CDATA[case_studies]]>' ) ) {
			continue;
		}
		$slug = $tag( $item, 'wp:post_name' );
		if ( '' === $slug ) {
			continue;
		}
		$by_slug[ $slug ] = array(
			'post_date'         => $valid( $tag( $item, 'wp:post_date' ) ),
			'post_date_gmt'     => $valid( $tag( $item, 'wp:post_date_gmt' ) ),
			'post_modified'     => $valid( $tag( $item, 'wp:post_modified' ) ),
			'post_modified_gmt' => $valid( $tag( $item, 'wp:post_modified_gmt' ) ),
		);
	}
}
WP_CLI::log( sprintf( 'Legacy dates indexed for %d slugs.', count( $by_slug ) ) );

global $wpdb;
$updated = 0;
$skipped = 0;
$missing = array();

$rebuilt = get_posts( array(
	'post_type'      => 'case-study',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
) );
WP_CLI::log( sprintf( 'Rebuilt case-study posts found: %d', count( $rebuilt ) ) );

foreach ( $rebuilt as $pid ) {
	$slug = get_post_field( 'post_name', $pid );
	if ( ! isset( $by_slug[ $slug ] ) ) {
		$missing[] = "{$slug} (post #{$pid}) — no legacy match";
		$skipped++;
		continue;
	}

	$d = $by_slug[ $slug ];
	$set = array();
	foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $f ) {
		if ( '' !== $d[ $f ] ) {
			$set[ $f ] = $d[ $f ];
		}
	}
	if ( empty( $set ) ) {
		$skipped++;
		continue;
	}

	$current = get_post( $pid );
	$already = ( $current->post_date === ( $set['post_date'] ?? $current->post_date ) )
		&& ( $current->post_modified === ( $set['post_modified'] ?? $current->post_modified ) );

	WP_CLI::log( sprintf(
		'  #%d %s  date %s -> %s | modified %s -> %s%s',
		$pid, $slug,
		$current->post_date, $set['post_date'] ?? $current->post_date,
		$current->post_modified, $set['post_modified'] ?? $current->post_modified,
		$already ? '  (already correct)' : ''
	) );

	if ( $dry || $already ) {
		continue;
	}

	$wpdb->update( $wpdb->posts, $set, array( 'ID' => $pid ) );
	clean_post_cache( $pid );
	$updated++;
}

WP_CLI::log( "\n== Summary ==" );
WP_CLI::log( sprintf( '  rebuilt posts: %d', count( $rebuilt ) ) );
WP_CLI::log( sprintf( '  %s:       %d', $dry ? 'would update' : 'updated', $dry ? ( count( $rebuilt ) - $skipped ) : $updated ) );
WP_CLI::log( sprintf( '  skipped:       %d', $skipped ) );
if ( $missing ) {
	WP_CLI::log( "\n== No legacy match (left unchanged) ==" );
	foreach ( $missing as $line ) {
		WP_CLI::log( '  ' . $line );
	}
}

WP_CLI::success( $dry ? 'Dry run complete — re-run with "live" to apply.' : 'Dates restored.' );
