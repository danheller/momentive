<?php
/**
 * create-empty-posts.php
 *
 * WP-CLI: create empty CPT shells on the REBUILT site for every legacy post
 * that doesn't already exist here. Works for any post type — intended for CPTs
 * that have no automated migration script and must be rebuilt by hand.
 *
 * What "empty" means: post_content is left blank. The idea is to stamp every
 * legacy post into the rebuilt site so that (a) slugs/IDs are claimed and
 * (b) the admin "Rebuilt?" column can show at a glance which posts still need
 * real content. Same pattern as create-empty-pages.php.
 *
 * SUPPORTED CPTs (with their WXR defaults):
 *   toolkits         momentivesoftware.toolkits.current.2026-09-01.xml
 *   lp               momentivesoftware.lp.current.2026-09-01.xml
 *   interactive-tools momentivesoftware.interactive-tools.current.2026-09-01.xml
 *   who-we-serve     momentivesoftware.who-we-serve.current.2026-09-01.xml
 *   product-overviews momentivesoftware.product-overviews.current.2026-09-01.xml
 *   award-recipients momentivesoftware.award-recipients.current.2026-09-01.xml
 *   (any other CPT with a matching WXR in exports/)
 *
 * SKIP conditions:
 *   - The post has no slug in the WXR (draft / auto-draft with no permalink).
 *   - A post already exists at the legacy ID with real block content — it's been
 *     rebuilt; don't overwrite it.
 *   - A post already exists at the same slug with real block content.
 *
 * UPDATE condition (instead of create):
 *   - A post exists at the legacy ID but is an empty shell → update its
 *     title / slug / post_parent to match the WXR (idempotent cleanup).
 *
 * USAGE (flags are POSITIONAL — `wp eval-file` rejects --flags):
 *
 *   wp eval-file migrations/create-empty-posts.php type=toolkit legacy_type=toolkits live
 *   wp eval-file migrations/create-empty-posts.php type=lp live              # landing pages
 *   wp eval-file migrations/create-empty-posts.php type=interactive-tools live
 *   wp eval-file migrations/create-empty-posts.php type=who-we-serve live
 *   wp eval-file migrations/create-empty-posts.php type=product-overviews live
 *   wp eval-file migrations/create-empty-posts.php type=award-recipients live
 *   wp eval-file migrations/create-empty-posts.php type=toolkit legacy_type=toolkits live only=1234
 *   wp eval-file migrations/create-empty-posts.php type=toolkit legacy_type=toolkits live limit=5
 *
 * legacy_type=<slug>: the post_type value in the WXR when it differs from the
 *   rebuilt CPT slug (e.g. legacy "toolkits" → rebuilt "toolkit"). Defaults to
 *   the value of type=.
 *
 * WXR override: set MOMENTIVE_CEP_WXR env var to a full path, or pass
 * wxr=<filename> (relative to migrations/exports/) as a positional arg.
 * When legacy_type differs from type, the default WXR filename is derived from
 * legacy_type (e.g. legacy_type=toolkits → momentivesoftware.toolkits.current.2026-09-01.xml).
 *
 * SAFETY: dry-run by default. Posts with real block content are never touched.
 * Re-running is safe (idempotent).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/create-empty-posts.php type=<post_type> [live] [only=<id>] [limit=<n>]' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_CEP_RUN_META = '_momentive_migration_run';

/**
 * A post whose post_content is only this trivial block counts as "empty"
 * even though post_content is non-empty — the block editor silently inserts
 * it on first open/save. Same pattern as create-empty-pages.php.
 */
const MOMENTIVE_CEP_TRIVIAL_EMPTY = '#<!--\s*wp:paragraph\s*-->\s*<p[^>]*>\s*</p>\s*<!--\s*/wp:paragraph\s*-->\s*#i';

/** Stable per-process run identifier stamped on created/updated posts. */
function momentive_cep_run_id(): string {
	static $id = null;
	if ( null === $id ) {
		$id = gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	return $id;
}

/* -------------------------------------------------------------------------
 * Flag parsing
 * ---------------------------------------------------------------------- */

function momentive_cep_get_flags( array $argv = [] ): array {
	$flags = [
		'dry_run'     => true,   // SAFE BY DEFAULT
		'post_type'   => '',
		'legacy_type' => '',     // WXR post_type filter; defaults to post_type if empty
		'wxr'         => '',
		'only'        => 0,
		'limit'       => 0,
	];

	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( in_array( $tok, [ 'live', 'go' ], true ) ) {
			$flags['dry_run'] = false;
		} elseif ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) {
			$flags['dry_run'] = true;
		} elseif ( str_starts_with( $tok, 'type=' ) ) {
			$flags['post_type'] = substr( $tok, 5 );
		} elseif ( str_starts_with( $tok, 'legacy_type=' ) ) {
			$flags['legacy_type'] = substr( $tok, 12 );
		} elseif ( str_starts_with( $tok, 'wxr=' ) ) {
			$flags['wxr'] = substr( $tok, 4 );
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = (int) substr( $tok, 5 );
		} elseif ( str_starts_with( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		}
	}

	if ( getenv( 'MOMENTIVE_LIVE' ) )      { $flags['dry_run']   = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )       { $flags['dry_run']   = true; }
	if ( getenv( 'MOMENTIVE_ONLY' ) )      { $flags['only']      = (int) getenv( 'MOMENTIVE_ONLY' ); }
	if ( getenv( 'MOMENTIVE_LIMIT' ) )     { $flags['limit']     = (int) getenv( 'MOMENTIVE_LIMIT' ); }
	if ( getenv( 'MOMENTIVE_CEP_WXR' ) )   { $flags['wxr']       = getenv( 'MOMENTIVE_CEP_WXR' ); }

	return $flags;
}

/**
 * Resolve the WXR file path. Checks (in order):
 *   1. MOMENTIVE_CEP_WXR env var (absolute path).
 *   2. `wxr=<filename>` flag (relative to exports/).
 *   3. Default: exports/momentivesoftware.{type}.current.2026-09-01.xml
 */
/**
 * @param string $wxr_type  The type slug to use when building the default filename
 *                          (equals legacy_type when set, otherwise post_type).
 */
function momentive_cep_resolve_wxr( string $wxr_type, string $wxr_flag ): string {
	if ( $wxr_flag !== '' ) {
		// If it looks like a full path, use as-is; otherwise treat as a filename
		// relative to migrations/exports/.
		if ( str_starts_with( $wxr_flag, '/' ) ) {
			return $wxr_flag;
		}
		return __DIR__ . '/exports/' . $wxr_flag;
	}

	return __DIR__ . '/exports/momentivesoftware.' . $wxr_type . '.current.2026-09-01.xml';
}

/* -------------------------------------------------------------------------
 * WXR parsing
 * ---------------------------------------------------------------------- */

/**
 * Returns all items of the given post_type from the WXR:
 *   id, slug, title, post_parent, date_gmt
 */
function momentive_cep_load_legacy_posts( string $wxr_path, string $post_type ): array {
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

	$posts = [];

	foreach ( $xml->channel->item as $item ) {
		$item->registerXPathNamespace( 'wp', 'http://wordpress.org/export/1.2/' );

		$type = (string) $item->xpath( 'wp:post_type' )[0];
		if ( $post_type !== $type ) {
			continue;
		}

		$slug = (string) $item->xpath( 'wp:post_name' )[0];
		if ( '' === trim( $slug ) ) {
			// No slug = inaccessible post (draft / auto-draft). Skip.
			continue;
		}

		$id          = (int)    $item->xpath( 'wp:post_id' )[0];
		$post_parent = (int)    $item->xpath( 'wp:post_parent' )[0];
		$pub_date    = (string) $item->pubDate;

		$ts       = $pub_date ? strtotime( $pub_date ) : 0;
		$date_gmt = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql', true );

		$title = html_entity_decode( (string) $item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$posts[] = [
			'id'          => $id,
			'slug'        => $slug,
			'title'       => $title,
			'post_parent' => $post_parent,
			'date_gmt'    => $date_gmt,
		];
	}

	// Sort: top-level first (post_parent = 0), then children — so parent IDs
	// are claimed before any child tries to reference them.
	usort( $posts, static function ( $a, $b ): int {
		$a_top = ( 0 === $a['post_parent'] ) ? 0 : 1;
		$b_top = ( 0 === $b['post_parent'] ) ? 0 : 1;
		if ( $a_top !== $b_top ) {
			return $a_top - $b_top;
		}
		return $a['id'] - $b['id'];
	} );

	return $posts;
}

/* -------------------------------------------------------------------------
 * Content helpers
 * ---------------------------------------------------------------------- */

function momentive_cep_has_real_content( int $post_id ): bool {
	$content  = (string) get_post_field( 'post_content', $post_id );
	$stripped = preg_replace( MOMENTIVE_CEP_TRIVIAL_EMPTY, '', $content );
	$stripped = trim( (string) $stripped );
	return '' !== $stripped && str_contains( $stripped, '<!-- wp:' );
}

/* -------------------------------------------------------------------------
 * Find or create a post shell
 * ---------------------------------------------------------------------- */

function momentive_cep_upsert_post( array $post, string $post_type, bool $dry ): int {
	$legacy_id   = $post['id'];
	$slug        = $post['slug'];
	$title       = $post['title'];
	$post_parent = $post['post_parent'];
	$date_gmt    = $post['date_gmt'];

	// ── 1. Try direct ID lookup ──────────────────────────────────────────
	$existing = get_post( $legacy_id );
	if ( $existing && $post_type === $existing->post_type ) {
		if ( momentive_cep_has_real_content( $legacy_id ) ) {
			WP_CLI::log( sprintf( '  [skip-rebuilt]   ID=%-6d  %s', $legacy_id, $title ) );
			return $legacy_id;
		}
		// Empty shell at the right ID — update its metadata.
		WP_CLI::log( sprintf( '  [update-shell]   ID=%-6d  %s', $legacy_id, $title ) );
		if ( ! $dry ) {
			wp_update_post( [
				'ID'          => $legacy_id,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $post_parent,
				'post_status' => 'publish',
			] );
			update_post_meta( $legacy_id, MOMENTIVE_CEP_RUN_META, momentive_cep_run_id() );
		}
		return $legacy_id;
	}

	if ( $existing && $post_type !== $existing->post_type ) {
		WP_CLI::warning( sprintf( 'ID %d exists but is post_type "%s" — cannot claim it for "%s". Skipping "%s".', $legacy_id, $existing->post_type, $post_type, $title ) );
		return 0;
	}

	// ── 2. Try slug lookup ───────────────────────────────────────────────
	$by_slug = get_page_by_path( $slug, OBJECT, $post_type );
	if ( $by_slug ) {
		if ( momentive_cep_has_real_content( (int) $by_slug->ID ) ) {
			WP_CLI::log( sprintf( '  [skip-rebuilt]   ID=%-6d  %s  (slug match, rebuilt ID=%d)', $legacy_id, $title, $by_slug->ID ) );
			return (int) $by_slug->ID;
		}
		WP_CLI::log( sprintf( '  [update-shell]   ID=%-6d→%d  %s  (slug match)', $legacy_id, $by_slug->ID, $title ) );
		if ( ! $dry ) {
			wp_update_post( [
				'ID'          => $by_slug->ID,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_parent' => $post_parent,
				'post_status' => 'publish',
			] );
			update_post_meta( $by_slug->ID, MOMENTIVE_CEP_RUN_META, momentive_cep_run_id() );
		}
		return (int) $by_slug->ID;
	}

	// ── 3. Create a new empty shell ──────────────────────────────────────
	WP_CLI::log( sprintf( '  [create]         ID=%-6d  %s', $legacy_id, $title ) );
	if ( $dry ) {
		return $legacy_id;
	}

	$result = wp_insert_post( [
		'import_id'         => $legacy_id,
		'post_type'         => $post_type,
		'post_status'       => 'publish',
		'post_title'        => $title,
		'post_name'         => $slug,
		'post_content'      => '',
		'post_parent'       => $post_parent,
		'post_date_gmt'     => $date_gmt,
		'post_modified_gmt' => $date_gmt,
	], true );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( sprintf( '  [error] ID=%d "%s": %s', $legacy_id, $title, $result->get_error_message() ) );
		return 0;
	}

	update_post_meta( (int) $result, MOMENTIVE_CEP_RUN_META, momentive_cep_run_id() );
	return (int) $result;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_cep_run( array $argv = [] ): void {
	$flags       = momentive_cep_get_flags( $argv );
	$dry         = $flags['dry_run'];
	$post_type   = $flags['post_type'];
	$legacy_type = $flags['legacy_type'] !== '' ? $flags['legacy_type'] : $post_type;

	if ( '' === $post_type ) {
		WP_CLI::error( 'Required: type=<post_type>. Example: wp eval-file migrations/create-empty-posts.php type=toolkit legacy_type=toolkits live' );
		return;
	}

	// Default WXR filename is derived from legacy_type (which may differ from
	// the rebuilt CPT slug — e.g. legacy "toolkits" → rebuilt "toolkit").
	$wxr_path = momentive_cep_resolve_wxr( $legacy_type, $flags['wxr'] );

	WP_CLI::log( '======================================================' );
	WP_CLI::log( '  Create empty posts: ' . $post_type . ( $legacy_type !== $post_type ? "  (WXR type: {$legacy_type})" : '' ) );
	WP_CLI::log( '  WXR:  ' . basename( $wxr_path ) );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( $flags['only'] > 0 )  { WP_CLI::log( '  only:  ID ' . $flags['only'] ); }
	if ( $flags['limit'] > 0 ) { WP_CLI::log( '  limit: ' . $flags['limit'] ); }
	WP_CLI::log( '======================================================' );

	$posts = momentive_cep_load_legacy_posts( $wxr_path, $legacy_type );
	if ( empty( $posts ) ) {
		WP_CLI::error( "No published posts of type \"{$legacy_type}\" parsed from WXR. Check the legacy_type= value matches what's in the WXR (use `grep wp:post_type` to confirm)." );
		return;
	}
	WP_CLI::log( sprintf( 'WXR: %d "%s" posts with slugs parsed (will be created as "%s").', count( $posts ), $legacy_type, $post_type ) );

	if ( $flags['only'] > 0 ) {
		$only  = $flags['only'];
		$posts = array_values( array_filter( $posts, static fn( $p ) => $p['id'] === $only ) );
		if ( empty( $posts ) ) {
			WP_CLI::error( "No legacy post found with ID {$only}." );
			return;
		}
	}
	if ( $flags['limit'] > 0 ) {
		$posts = array_slice( $posts, 0, $flags['limit'] );
	}

	$summary = [ 'created' => 0, 'updated' => 0, 'skip_rebuilt' => 0, 'skip_conflict' => 0, 'error' => 0 ];

	foreach ( $posts as $post ) {
		$before_id      = get_post( $post['id'] ) ? $post['id'] : null;
		$before_rebuilt = $before_id && momentive_cep_has_real_content( $post['id'] );
		$by_slug        = get_page_by_path( $post['slug'], OBJECT, $post_type );
		$before_exists  = null !== $by_slug || null !== $before_id;

		$result_id = momentive_cep_upsert_post( $post, $post_type, $dry );

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
	WP_CLI::log( sprintf( '  Created:           %d', $summary['created'] ) );
	WP_CLI::log( sprintf( '  Updated (shell):   %d', $summary['updated'] ) );
	WP_CLI::log( sprintf( '  Skipped (rebuilt): %d', $summary['skip_rebuilt'] ) );
	WP_CLI::log( sprintf( '  Skipped (conflict):%d', $summary['skip_conflict'] ) );
	WP_CLI::log( sprintf( '  Total processed:   %d', count( $posts ) ) );

	if ( $dry ) {
		WP_CLI::log( '' );
		WP_CLI::log( '  Re-run with `live` to write.' );
	}

	WP_CLI::success( 'Done.' );
}

momentive_cep_run( $args ?? [] );
