<?php
/**
 * Patch: Convert Rank Math blocks to native Momentive blocks.
 *
 * Converts in post_content (post_type = post, status = publish or draft):
 *   rank-math/faq-block  →  momentive/accordion
 *   rank-math/toc-block  →  momentive/table-of-contents
 *
 * Data is read entirely from the block comment's JSON attribute (not from the
 * rendered HTML between the tags, which may contain localhost URLs on a local
 * dev environment).
 *
 * RankMath FAQ answer HTML is normalised before storage:
 *   - <br><br> separators → </p><p> paragraph breaks
 *   - bare text wrapped in <p>
 *   - empty <p> tags removed
 *
 * Usage (run from the rebuilt site's root):
 *   wp eval-file migrations/patch-rankmath-blocks.php            # dry-run
 *   wp eval-file migrations/patch-rankmath-blocks.php live       # write
 *
 * Must run as an admin user so media capabilities are available if ever needed:
 *   wp eval-file migrations/patch-rankmath-blocks.php --user=<admin>
 *
 * Idempotent: posts that contain no rank-math blocks are skipped; re-running
 * on already-patched posts is safe (the regex won't match).
 */

$args    = $args ?? [];
$dry_run = ! in_array( 'live', $args, true );

WP_CLI::log( $dry_run
	? '=== DRY RUN — pass `live` as first positional arg to write ==='
	: '=== LIVE RUN — changes will be written to the database ==='
);

global $wpdb;

// ── Fetch all posts that still contain a rank-math block ──────────────────────

$post_ids = $wpdb->get_col( "
	SELECT ID FROM {$wpdb->posts}
	WHERE post_type   = 'post'
	  AND post_status IN ('publish','draft','pending','future','private')
	  AND post_content LIKE '%wp:rank-math/%'
" );

if ( ! $post_ids ) {
	WP_CLI::success( 'No posts found with rank-math blocks. Nothing to do.' );
	return;
}

WP_CLI::log( sprintf( 'Found %d post(s) with rank-math blocks.', count( $post_ids ) ) );

// ── Counters ──────────────────────────────────────────────────────────────────

$stats = [
	'posts_patched'  => 0,
	'posts_skipped'  => 0,
	'faq_converted'  => 0,
	'faq_items'      => 0,
	'toc_converted'  => 0,
	'parse_errors'   => [],
];

// ── Process each post ─────────────────────────────────────────────────────────

foreach ( $post_ids as $post_id ) {
	$post    = get_post( $post_id );
	$content = $post->post_content;
	$changed = false;

	// ── 1. rank-math/faq-block → momentive/accordion ──────────────────────

	$content = preg_replace_callback(
		'/<!-- wp:rank-math\/faq-block (\{.*?\}) -->.*?<!-- \/wp:rank-math\/faq-block -->/s',
		function ( $m ) use ( $post_id, &$stats ) {
			$data = json_decode( $m[1], true );

			if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['questions'] ) ) {
				$stats['parse_errors'][] = "Post {$post_id}: could not parse faq-block JSON";
				return $m[0]; // leave untouched
			}

			$items = [];
			foreach ( $data['questions'] as $q ) {
				// Respect the 'visible' flag if set to false.
				if ( isset( $q['visible'] ) && false === $q['visible'] ) {
					continue;
				}

				$question = trim( wp_strip_all_tags( $q['title'] ?? '' ) );
				$answer   = msw_normalize_faq_answer( $q['content'] ?? '' );

				if ( ! $question && ! $answer ) {
					continue;
				}

				$items[] = [
					'_key'     => substr( md5( $q['id'] ?? uniqid() ), 0, 7 ),
					'question' => $question,
					'answer'   => $answer,
					'iconSlug' => '',
					'category' => '',
				];
			}

			if ( empty( $items ) ) {
				$stats['parse_errors'][] = "Post {$post_id}: faq-block had no usable questions";
				return ''; // remove the empty block
			}

			$attrs_json = wp_json_encode(
				[ 'items' => $items ],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			$stats['faq_converted']++;
			$stats['faq_items'] += count( $items );

			// Self-closing block (save() returns null).
			return "<!-- wp:momentive/accordion {$attrs_json} /-->";
		},
		$content
	);

	if ( $content !== $post->post_content ) {
		$changed = true;
	}

	// ── 2. rank-math/toc-block → momentive/table-of-contents ─────────────

	$new_content = preg_replace_callback(
		'/<!-- wp:rank-math\/toc-block (\{.*?\}) -->.*?<!-- \/wp:rank-math\/toc-block -->/s',
		function ( $m ) use ( $post_id, &$stats ) {
			$data  = json_decode( $m[1], true );
			$title = isset( $data['title'] ) && $data['title'] !== ''
				? trim( $data['title'] )
				: 'Contents';

			$attrs_json = wp_json_encode(
				[ 'title' => $title ],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);

			$stats['toc_converted']++;

			// Self-closing block.
			return "<!-- wp:momentive/table-of-contents {$attrs_json} /-->";
		},
		$content
	);

	if ( $new_content !== $content ) {
		$content = $new_content;
		$changed = true;
	}

	// ── Write ─────────────────────────────────────────────────────────────

	if ( ! $changed ) {
		$stats['posts_skipped']++;
		continue;
	}

	$title_display = mb_strimwidth( $post->post_title, 0, 60, '…' );

	if ( $dry_run ) {
		WP_CLI::log( "[dry-run] Would patch post {$post_id}: {$title_display}" );
		$stats['posts_patched']++;
		continue;
	}

	$result = wp_update_post( wp_slash( [
		'ID'           => $post_id,
		'post_content' => $content,
	] ), true );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( "Post {$post_id}: update failed — " . $result->get_error_message() );
		$stats['posts_skipped']++;
	} else {
		WP_CLI::log( "[patched] Post {$post_id}: {$title_display}" );
		$stats['posts_patched']++;
	}
}

// ── Summary ───────────────────────────────────────────────────────────────────

WP_CLI::log( '' );
WP_CLI::log( '=== Summary ===' );
WP_CLI::log( "Posts patched:           {$stats['posts_patched']}" );
WP_CLI::log( "Posts skipped (no-op):   {$stats['posts_skipped']}" );
WP_CLI::log( "FAQ blocks converted:    {$stats['faq_converted']} ({$stats['faq_items']} items total)" );
WP_CLI::log( "TOC blocks converted:    {$stats['toc_converted']}" );

if ( $stats['parse_errors'] ) {
	WP_CLI::log( '' );
	WP_CLI::warning( 'Parse errors (blocks left unchanged):' );
	foreach ( $stats['parse_errors'] as $err ) {
		WP_CLI::log( "  $err" );
	}
}

WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Patch complete.' );

// =============================================================================
// Helpers
// =============================================================================

/**
 * Normalise a RankMath FAQ answer HTML string for storage in momentive/accordion.
 *
 * RankMath stores answers with <br><br> as paragraph separators and sometimes
 * bare text without <p> wrappers. The accordion's RichText editor (multiline:p)
 * stores content as <p>…</p><p>…</p>, so we normalise to match.
 *
 * @param string $html Raw HTML from rank-math/faq-block JSON `content` field.
 * @return string Normalised HTML safe for wp_kses_post().
 */
function msw_normalize_faq_answer( string $html ): string {
	$html = trim( $html );
	if ( ! $html ) {
		return '';
	}

	// Replace <br><br> (any whitespace between, any self-close variant) with
	// a paragraph boundary marker.
	$html = preg_replace( '/<br\s*\/?>\s*<br\s*\/?>/', '</p><p>', $html );

	// Wrap in <p> if the content doesn't start with a block-level element.
	if ( ! preg_match( '/^\s*<(?:p|ul|ol|h[1-6]|blockquote|div|figure)\b/i', $html ) ) {
		$html = '<p>' . $html . '</p>';
	}

	// Remove empty paragraphs that might have been created by the conversion.
	$html = preg_replace( '/<p>\s*<\/p>/', '', $html );

	// Collapse multiple spaces (but preserve newlines for readability).
	$html = preg_replace( '/[^\S\n]+/', ' ', $html );

	return trim( $html );
}
