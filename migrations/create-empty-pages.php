<?php
/**
 * create-empty-pages.php
 *
 * WP-CLI: create empty page shells on the REBUILT site for every legacy page
 * that doesn't already exist here. Preserves the legacy post ID (via
 * `import_id`), slug, title, parent hierarchy, and menu order.
 *
 * What "empty" means: post_content is left blank. The idea is to stamp every
 * legacy page into the rebuilt site so that (a) slugs/IDs are claimed and
 * (b) the admin "Rebuilt?" column (inc/admin-columns.php) can show at a glance
 * which pages still need real content.
 *
 * SKIP conditions:
 *   - The page has no slug in the WXR (draft / auto-draft with no permalink).
 *   - A post already exists at the legacy ID with real block content — that
 *     page has already been rebuilt; don't overwrite it.
 *   - A page already exists at the same slug with real block content.
 *
 * UPDATE condition (instead of create):
 *   - A post exists at the legacy ID but is an empty shell → update its
 *     title / slug / parent / menu_order to match the WXR (idempotent cleanup).
 *
 * PARENT RESOLUTION:
 *   Top-level pages (post_parent = 0) are processed first. Children are
 *   processed in a second pass. Since parent IDs are preserved via import_id,
 *   the legacy post_parent value IS the rebuilt parent's post ID after pass 1.
 *
 * USAGE (flags are POSITIONAL — `wp eval-file` rejects --flags):
 *
 *   wp eval-file migrations/create-empty-pages.php               # dry run
 *   wp eval-file migrations/create-empty-pages.php live          # writes
 *   wp eval-file migrations/create-empty-pages.php live only=232 # one page by legacy ID
 *   wp eval-file migrations/create-empty-pages.php live limit=10 # first 10
 *   MOMENTIVE_LIVE=1 wp eval-file migrations/create-empty-pages.php
 *
 * SOURCE FILE:
 *   migrations/exports/momentivesoftware.pages.current.2026-08-30.xml
 *   Override via MOMENTIVE_PAGES_WXR env var.
 *
 * SAFETY: dry-run by default. Pages with real block content are never touched.
 * Re-running is safe (idempotent).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/create-empty-pages.php [live] [only=<id>] [limit=<n>]' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_EP_RUN_META = '_momentive_migration_run';

/**
 * A page whose post_content is only this trivial block counts as "empty"
 * even though post_content is non-empty — the block editor silently inserts
 * it on first open/save. Same pattern as migrate-solutions.php.
 */
const MOMENTIVE_EP_TRIVIAL_EMPTY = '#<!--\s*wp:paragraph\s*-->\s*<p[^>]*>\s*</p>\s*<!--\s*/wp:paragraph\s*-->\s*#i';

/** Stable per-process run identifier stamped on created/updated posts. */
function momentive_ep_run_id(): string {
	static $id = null;
	if ( null === $id ) {
		$id = gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	return $id;
}

/* -------------------------------------------------------------------------
 * Flag parsing
 * ---------------------------------------------------------------------- */

function momentive_ep_get_flags( array $argv = [] ): array {
	$flags = [
		'dry_run' => true,  // SAFE BY DEFAULT
		'only'    => 0,     // legacy post ID filter, 0 = no filter
		'limit'   => 0,
	];

	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( in_array( $tok, [ 'live', 'go' ], true ) ) {
			$flags['dry_run'] = false;
		} elseif ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) {
			$flags['dry_run'] = true;
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = (int) substr( $tok, 5 );
		} elseif ( str_starts_with( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		}
	}

	if ( getenv( 'MOMENTIVE_LIVE' ) )  { $flags['dry_run'] = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )   { $flags['dry_run'] = true; }
	if ( getenv( 'MOMENTIVE_ONLY' ) )  { $flags['only']    = (int) getenv( 'MOMENTIVE_ONLY' ); }
	if ( getenv( 'MOMENTIVE_LIMIT' ) ) { $flags['limit']   = (int) getenv( 'MOMENTIVE_LIMIT' ); }

	return $flags;
}

/* -------------------------------------------------------------------------
 * WXR parsing
 * ---------------------------------------------------------------------- */

/**
 * Returns all <page> items from the WXR as an array of arrays:
 *   id, slug, title, post_parent, menu_order, pub_date_gmt
 */
function momentive_ep_load_legacy_pages(): array {
	$wxr_path = getenv( 'MOMENTIVE_PAGES_WXR' ) ?: __DIR__ . '/exports/momentivesoftware.pages.current.2026-08-30.xml';

	if ( ! file_exists( $wxr_path ) ) {
		WP_CLI::error( "WXR not found: {$wxr_path}" );
		return [];
	}

	$xml = simplexml_load_file( $wxr_path );
	if ( ! $xml ) {
		WP_CLI::error( "Failed to parse WXR: {$wxr_path}" );
		return [];
	}

	$xml->registerXPathNamespace( 'wp',      'http://wordpress.org/export/1.2/' );
	$xml->registerXPathNamespace( 'content', 'http://purl.org/rss/1.0/modules/content/' );
	$xml->registerXPathNamespace( 'dc',      'http://purl.org/dc/elements/1.1/' );

	$pages = [];

	foreach ( $xml->channel->item as $item ) {
		$item->registerXPathNamespace( 'wp', 'http://wordpress.org/export/1.2/' );

		$post_type = (string) $item->xpath( 'wp:post_type' )[0];
		if ( 'page' !== $post_type ) {
			continue;
		}

		$slug = (string) $item->xpath( 'wp:post_name' )[0];
		if ( '' === trim( $slug ) ) {
			// No slug = inaccessible page (draft / auto-draft). Skip.
			continue;
		}

		$id          = (int)    $item->xpath( 'wp:post_id' )[0];
		$post_parent = (int)    $item->xpath( 'wp:post_parent' )[0];
		$menu_order  = (int)    $item->xpath( 'wp:menu_order' )[0];
		$pub_date    = (string) $item->pubDate;  // RFC-2822 from <pubDate>

		// Parse pub date → GMT datetime string WP expects (Y-m-d H:i:s).
		$ts       = $pub_date ? strtotime( $pub_date ) : 0;
		$date_gmt = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql', true );

		// Decode the title (CDATA or plain text).
		$title = html_entity_decode( (string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$pages[] = [
			'id'          => $id,
			'slug'        => $slug,
			'title'       => $title,
			'post_parent' => $post_parent,
			'menu_order'  => $menu_order,
			'date_gmt'    => $date_gmt,
		];
	}

	// Sort: top-level first (post_parent = 0), then children — so parent IDs
	// are claimed before any child tries to reference them.
	usort( $pages, static function ( $a, $b ): int {
		$a_top = ( 0 === $a['post_parent'] ) ? 0 : 1;
		$b_top = ( 0 === $b['post_parent'] ) ? 0 : 1;
		if ( $a_top !== $b_top ) {
			return $a_top - $b_top;
		}
		return $a['id'] - $b['id'];
	} );

	return $pages;
}

/* -------------------------------------------------------------------------
 * Content helpers
 * ---------------------------------------------------------------------- */

/**
 * Returns true if this post has real (non-trivial) block content that a
 * human presumably wrote. Never overwrite one of these.
 */
function momentive_ep_has_real_content( int $post_id ): bool {
	$content   = (string) get_post_field( 'post_content', $post_id );
	$stripped  = preg_replace( MOMENTIVE_EP_TRIVIAL_EMPTY, '', $content );
	$stripped  = trim( (string) $stripped );
	return '' !== $stripped && str_contains( $stripped, '<!-- wp:' );
}

/* -------------------------------------------------------------------------
 * Find or create a page shell
 * ---------------------------------------------------------------------- */

/**
 * @return int  rebuilt post ID, or 0 on failure.
 */
function momentive_ep_upsert_page( array $page, bool $dry ): int {
	$legacy_id   = $page['id'];
	$slug        = $page['slug'];
	$title       = $page['title'];
	$post_parent = $page['post_parent'];
	$menu_order  = $page['menu_order'];
	$date_gmt    = $page['date_gmt'];

	// ── 1. Try direct ID lookup ──────────────────────────────────────────
	$existing = get_post( $legacy_id );
	if ( $existing && 'page' === $existing->post_type ) {
		if ( momentive_ep_has_real_content( $legacy_id ) ) {
			WP_CLI::log( sprintf( '  [skip-rebuilt]   ID=%-6d  %s', $legacy_id, $title ) );
			return $legacy_id;
		}
		// Empty shell at the right ID — update its metadata to match legacy.
		WP_CLI::log( sprintf( '  [update-shell]   ID=%-6d  %s', $legacy_id, $title ) );
		if ( ! $dry ) {
			wp_update_post( [
				'ID'          => $legacy_id,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $post_parent,
				'menu_order'  => $menu_order,
				'post_status' => 'publish',
			] );
			update_post_meta( $legacy_id, MOMENTIVE_EP_RUN_META, momentive_ep_run_id() );
		}
		return $legacy_id;
	}

	if ( $existing && 'page' !== $existing->post_type ) {
		WP_CLI::warning( sprintf( 'ID %d exists but is post_type "%s" — cannot claim it for a page. Skipping "%s".', $legacy_id, $existing->post_type, $title ) );
		return 0;
	}

	// ── 2. Try slug lookup (page may exist at a different ID) ────────────
	$by_slug = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $by_slug ) {
		if ( momentive_ep_has_real_content( (int) $by_slug->ID ) ) {
			WP_CLI::log( sprintf( '  [skip-rebuilt]   ID=%-6d  %s  (slug match, rebuilt ID=%d)', $legacy_id, $title, $by_slug->ID ) );
			return (int) $by_slug->ID;
		}
		// Empty shell at a different ID. Update it.
		WP_CLI::log( sprintf( '  [update-shell]   ID=%-6d→%d  %s  (slug match)', $legacy_id, $by_slug->ID, $title ) );
		if ( ! $dry ) {
			wp_update_post( [
				'ID'          => $by_slug->ID,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $post_parent,
				'menu_order'  => $menu_order,
				'post_status' => 'publish',
			] );
			update_post_meta( $by_slug->ID, MOMENTIVE_EP_RUN_META, momentive_ep_run_id() );
		}
		return (int) $by_slug->ID;
	}

	// ── 3. Create a new empty shell ──────────────────────────────────────
	WP_CLI::log( sprintf( '  [create]         ID=%-6d  %s', $legacy_id, $title ) );
	if ( $dry ) {
		return $legacy_id; // pretend it would be created
	}

	$result = wp_insert_post( [
		'import_id'       => $legacy_id,   // preserve legacy numeric ID
		'post_type'       => 'page',
		'post_status'     => 'publish',
		'post_title'      => $title,
		'post_name'       => $slug,
		'post_content'    => '',
		'post_parent'     => $post_parent,
		'menu_order'      => $menu_order,
		'post_date_gmt'   => $date_gmt,
		'post_modified_gmt' => $date_gmt,
	], true );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( sprintf( '  [error] ID=%d "%s": %s', $legacy_id, $title, $result->get_error_message() ) );
		return 0;
	}

	update_post_meta( (int) $result, MOMENTIVE_EP_RUN_META, momentive_ep_run_id() );
	return (int) $result;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_ep_run( array $argv = [] ): void {
	$flags = momentive_ep_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '======================================================' );
	WP_CLI::log( '  Create empty pages' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( $flags['only'] > 0 ) { WP_CLI::log( '  only:  ID ' . $flags['only'] ); }
	if ( $flags['limit'] > 0 ) { WP_CLI::log( '  limit: ' . $flags['limit'] ); }
	WP_CLI::log( '======================================================' );

	$pages = momentive_ep_load_legacy_pages();
	if ( empty( $pages ) ) {
		WP_CLI::error( 'No pages parsed from WXR.' );
		return;
	}
	WP_CLI::log( sprintf( 'WXR: %d pages with slugs parsed.', count( $pages ) ) );

	// Apply filters.
	if ( $flags['only'] > 0 ) {
		$only   = $flags['only'];
		$pages  = array_values( array_filter( $pages, static fn( $p ) => $p['id'] === $only ) );
		if ( empty( $pages ) ) {
			WP_CLI::error( "No legacy page found with ID {$only}." );
			return;
		}
	}
	if ( $flags['limit'] > 0 ) {
		$pages = array_slice( $pages, 0, $flags['limit'] );
	}

	$summary = [
		'created'       => 0,
		'updated'       => 0,
		'skip_rebuilt'  => 0,
		'skip_conflict' => 0,
		'error'         => 0,
	];

	foreach ( $pages as $page ) {
		$before_id = get_post( $page['id'] ) ? $page['id'] : null;

		// Pre-snapshot the state to determine what happened.
		$before_rebuilt = $before_id && momentive_ep_has_real_content( $page['id'] );
		$before_exists  = null !== get_page_by_path( $page['slug'], OBJECT, 'page' ) || ( null !== $before_id );

		$result_id = momentive_ep_upsert_page( $page, $dry );

		if ( 0 === $result_id ) {
			$summary['skip_conflict']++;
		} elseif ( $before_rebuilt ) {
			$summary['skip_rebuilt']++;
		} elseif ( $before_exists ) {
			$summary['updated']++;
		} else {
			$summary['created']++;
		}
	}

	WP_CLI::log( '' );
	WP_CLI::log( '── Summary ' . ( $dry ? '(dry run)' : '(live)' ) . ' ──────────────────────────────' );
	WP_CLI::log( sprintf( '  Created:          %d', $summary['created'] ) );
	WP_CLI::log( sprintf( '  Updated (shell):  %d', $summary['updated'] ) );
	WP_CLI::log( sprintf( '  Skipped (rebuilt):%d', $summary['skip_rebuilt'] ) );
	WP_CLI::log( sprintf( '  Skipped (conflict):%d', $summary['skip_conflict'] ) );
	WP_CLI::log( sprintf( '  Total processed:  %d', count( $pages ) ) );

	if ( $dry ) {
		WP_CLI::log( '' );
		WP_CLI::log( '  Re-run with `live` to write.' );
	}

	WP_CLI::success( 'Done.' );
}

momentive_ep_run( $args ?? [] );
