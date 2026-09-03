<?php
/**
 * migrate-press-articles.php
 *
 * WP-CLI migration script: legacy `press-article` CPT (Newsroom).
 *
 * Full field → destination analysis lives in notes/press-article-reference-sheet.md
 * — read that first if anything here looks surprising. Summary of what makes
 * this CPT different from the closest analog (migrate-posts.php, the Blog
 * migration, which this script's shortcode/CTA plumbing is adapted from):
 *
 *   - Body content in the WXR is ALREADY valid Gutenberg block markup (a
 *     residue of an earlier bulk import pass) — no Word-artifact stripping
 *     needed, no rank-math/faq-block conversion. Only the page SCAFFOLD
 *     (hero, breadcrumb, byline, sidebar) is missing, plus a handful of
 *     inline additions (see below).
 *   - `news-category` postmeta (not a real taxonomy on the legacy site) maps
 *     to one of exactly 3 real "category" terms on the rebuilt site, which
 *     also now drives the front-end permalink prefix (inc/blog-and-newsroom.php).
 *   - `hero_overline` / `hero_subtitle` render as an eyebrow paragraph and a
 *     large paragraph in the header. `hero_title` is confirmed VESTIGIAL
 *     (byte-identical to the post title in every case checked) — deliberately
 *     not read anywhere in this script.
 *   - `hero_image_alignment === 'bottom'` (exactly 1 post in the whole corpus,
 *     `inspiring-leaders-tirrah-switzer`) adds a specific padding style to the
 *     entry-header group — confirmed with Daniel, implemented generically
 *     rather than special-cased by slug/ID.
 *   - `display_featured_image_on_page` is confirmed DEAD — deliberately not
 *     read; the featured-image block is always emitted.
 *   - `op_name` / `op_link` / `op_logo` (in-the-news posts only) render an
 *     outlet-attribution block at the end of the body.
 *   - The canonical "About TA" boilerplate is a SYNCED PATTERN reference
 *     (`<!-- wp:block {"ref":10593} /-->`), appended automatically based on
 *     the post's CATEGORY (press-releases / in-the-news → yes,
 *     momentive-in-action → no) — not based on any meta field. A post's own
 *     `additional_about_sections` data is only migrated inline when it is
 *     genuinely distinct content (i.e. its `about_title` isn't "About TA" —
 *     e.g. a per-person bio or "About The Stevie Awards").
 *   - `sc_cta_-_*` (CTA Section, template 1458) and `resource_cta_*` are the
 *     ONLY shortcode-era fields actually used anywhere in this corpus (2
 *     posts and 1 post respectively) — every other shortcode family
 *     (tips, checklist, cta-with-image, the older cta-dash/cta_block field
 *     system, custom_sidebar_cta_*) is confirmed vestigial and deliberately
 *     unhandled.
 *   - Legacy IDs are NOT reliable for this CPT: 3 of the first 7 hand-rebuilt
 *     posts turned out to have been re-imported under a new ID with the same
 *     slug (10242→10265, 793→7810, 11191→18275). This script upserts by
 *     SLUG, not legacy ID.
 *
 * Run (from the theme directory):
 *
 *   wp eval-file migrations/migrate-press-articles.php --user=<admin>
 *     → dry run; logs what would change without writing anything
 *
 *   wp eval-file migrations/migrate-press-articles.php live --user=<admin>
 *     → writes posts, sideloads media
 *
 *   wp eval-file migrations/migrate-press-articles.php live limit=10 --user=<admin>
 *     → first 10 posts only
 *
 *   wp eval-file migrations/migrate-press-articles.php live only=my-post-slug --user=<admin>
 *     → single post by slug (also bypasses the "already rebuilt" guard below)
 *
 *   wp eval-file migrations/migrate-press-articles.php live force --user=<admin>
 *     → also overwrites posts already carrying real page-scaffold markup
 *
 * --user=<admin> is REQUIRED: Safe SVG gates SVG sideloads on capability.
 *
 * "Already rebuilt" guard: by default this script SKIPS any post whose
 * current post_content already contains the `post-layout` className — i.e.
 * a post someone has hand-rebuilt with the real scaffold already (this is
 * exactly the check notes/press-article-reference-sheet.md flagged as
 * missing from report-rebuild-progress.php's generic classifier). Pass
 * `force`, or target the post directly with `only=<slug>`, to override.
 *
 * Idempotent: upserts by SLUG (see the ID-drift note above). Rollback: posts
 * written by this script are stamped with _momentive_migration_run; restore
 * from a pre-migration DB backup for anything more than a single post.
 *
 * Overridable constants: MOMENTIVE_PA_LEGACY_WXR, MOMENTIVE_PA_UPLOADS_BASE,
 * MOMENTIVE_PA_ABOUT_TA_PATTERN_REF (the synced pattern post ID — 10593 on
 * this site; override if that pattern is ever recreated under a new ID).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-press-articles.php [live] [limit=N] [only=slug] [force] --user=<admin>' . PHP_EOL );
}

/* =========================================================================
 * Run-mode flags (positional args — wp eval-file ignores -- flags)
 * ====================================================================== */

$dry       = true;
$limit     = 0;
$only_slug = '';
$force     = false;

if ( ! empty( $args ) && is_array( $args ) ) {
	foreach ( $args as $arg ) {
		if ( 'live' === $arg )                       { $dry       = false; }
		elseif ( 'force' === $arg )                  { $force     = true; }
		elseif ( str_starts_with( $arg, 'limit=' ) ) { $limit     = (int) substr( $arg, 6 ); }
		elseif ( str_starts_with( $arg, 'only=' )  ) { $only_slug = substr( $arg, 5 ); }
	}
}

WP_CLI::log( sprintf(
	'migrate-press-articles: %s  limit=%s  only=%s  force=%s',
	$dry ? 'DRY RUN' : 'LIVE',
	$limit ?: 'none',
	$only_slug ?: 'all',
	$force ? 'yes' : 'no'
) );

/* =========================================================================
 * Constants
 * ====================================================================== */

const MOMENTIVE_PA_RUN_META = '_momentive_migration_run';

// The canonical "About TA" synced pattern (wp_block post), confirmed via the
// rebuilt content of 10265/4030/7810. Appended automatically based on
// category — see momentive_pa_should_append_about_ta() below.
if ( ! defined( 'MOMENTIVE_PA_ABOUT_TA_PATTERN_REF' ) ) {
	define( 'MOMENTIVE_PA_ABOUT_TA_PATTERN_REF', 10593 );
}

$pa_wxr_path = defined( 'MOMENTIVE_PA_LEGACY_WXR' )
	? MOMENTIVE_PA_LEGACY_WXR
	: __DIR__ . '/exports/momentivesoftware.press-articles.current.2026-09-01.xml';

$pa_uploads_base = rtrim(
	defined( 'MOMENTIVE_PA_UPLOADS_BASE' )
		? MOMENTIVE_PA_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/',
	'/'
) . '/';

/**
 * news-category meta value → rebuilt category term.
 *
 * Explicit map, not a 1:1 read of the term's own slug — the "Press Release"
 * term's actual slug is the singular `press-release`, while the legacy meta
 * value (and the URL prefix implemented in inc/blog-and-newsroom.php) is
 * plural `press-releases`. The other two happen to match their meta value
 * exactly. See notes/press-article-reference-sheet.md's "Category resolution"
 * section.
 */
const MOMENTIVE_PA_CATEGORY_MAP = [
	'press-releases'      => [ 'slug' => 'press-release',       'name' => 'Press Release' ],
	'in-the-news'          => [ 'slug' => 'in-the-news',         'name' => 'In the News' ],
	'momentive-in-action'  => [ 'slug' => 'momentive-in-action', 'name' => 'Momentive in Action' ],
];

/* =========================================================================
 * Utilities (mirrors migrate-posts.php's momentive_pm_* equivalents)
 * ====================================================================== */

/** True for 'true', '1', 'yes', 'on'; false for anything else. */
function momentive_pa_truthy( string $v ): bool {
	return in_array( trim( $v ), [ 'true', '1', 'yes', 'on' ], true );
}

function momentive_pa_run_id(): string {
	static $id = '';
	if ( '' === $id ) $id = gmdate( 'Y-m-d H:i:s' );
	return $id;
}

/**
 * Strip MS-Word span cruft from HTML. Confirmed unnecessary for this CPT
 * (checked all 64 legacy posts, zero Word fingerprints found) but run
 * defensively anyway — cheap, and harmless on content that doesn't need it.
 */
function momentive_pa_strip_word( string $html ): string {
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-props|ccp-charstyle)[^>]*>(.*?)</span>#si',
		'$1', $html
	);
	$wc = 'NormalTextRun|TextRun|EOP|SpellingError|CommentStart|CommentEnd|SCXW\d+|BCX\d+|ContextualSpellingError';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*(?:' . $wc . ')[^"]*"[^>]*>(.*?)</span>#si',
		'$1', $html
	);
	$html = preg_replace( '#<span\b(?:\s*)>(.*?)</span>#si', '$1', $html );
	return $html;
}

function momentive_pa_strip_block_comments( string $html ): string {
	return preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $html );
}

/** Extract CDATA-wrapped or plain child-tag text from a raw XML item string. */
function momentive_pa_xml_tag( string $xml, string $tag ): string {
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s',
		$xml, $m
	) ) return $m[1];
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s',
		$xml, $m
	) ) return html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return '';
}

/* =========================================================================
 * HTML → Gutenberg block converters (shared by CTA / resource_cta / the
 * per-post "about" section — all need to turn a raw HTML fragment into
 * paragraph/heading/list blocks)
 * ====================================================================== */

function momentive_pa_p( string $inner, string $class = '' ): string {
	$inner = trim( $inner );
	if ( '' === $inner ) return '';
	$attrs = $class ? " {\"className\":\"{$class}\"}" : '';
	$p     = $class ? "<p class=\"{$class}\">{$inner}</p>" : "<p>{$inner}</p>";
	return "<!-- wp:paragraph{$attrs} -->\n{$p}\n<!-- /wp:paragraph -->";
}

/**
 * Convert a field's raw HTML to an array of Gutenberg block markup strings.
 * Mirrors momentive_pm_desc_blocks() from migrate-posts.php.
 */
function momentive_pa_desc_blocks( string $raw_html, string $first_p_class = '' ): array {
	$html = momentive_pa_strip_block_comments( $raw_html );
	$html = momentive_pa_strip_word( $html );
	$html = trim( $html );
	if ( '' === $html ) return [];

	if ( ! preg_match( '#<(?:p|ul|ol|h[2-6]|blockquote|table)\b#i', $html ) ) {
		$text = trim( wp_strip_all_tags( $html ) );
		return '' !== $text ? [ momentive_pa_p( $text, $first_p_class ) ] : [];
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>' );
	libxml_clear_errors();

	$root = $doc->getElementById( '__root__' );
	if ( ! $root ) {
		$text = trim( wp_strip_all_tags( $html ) );
		return '' !== $text ? [ momentive_pa_p( $text, $first_p_class ) ] : [];
	}

	$blocks  = [];
	$first_p = true;

	foreach ( $root->childNodes as $node ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$t = trim( $node->textContent );
			if ( '' === $t ) continue;
			$class    = ( $first_p && $first_p_class ) ? $first_p_class : '';
			$blocks[] = momentive_pa_p( esc_html( $t ), $class );
			$first_p  = false;
			continue;
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) continue;

		$tag   = strtolower( $node->nodeName );
		$outer = trim( $doc->saveHTML( $node ) );
		$inner = preg_replace(
			'#^<' . preg_quote( $tag, '#' ) . '[^>]*>(.*)</' . preg_quote( $tag, '#' ) . '>$#si',
			'$1', $outer
		);

		switch ( $tag ) {
			case 'p':
				$t = trim( $inner );
				if ( '' === $t || '<br>' === $t || '<br/>' === $t ) break;
				$class    = ( $first_p && $first_p_class ) ? $first_p_class : '';
				$blocks[] = momentive_pa_p( $t, $class );
				$first_p  = false;
				break;

			case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
				$level = (int) $tag[1];
				$t     = trim( wp_strip_all_tags( $inner ) );
				if ( '' === $t ) break;
				$blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n"
				          . "<h{$level} class=\"wp-block-heading\">{$t}</h{$level}>\n"
				          . "<!-- /wp:heading -->";
				$first_p  = false;
				break;

			case 'ul': case 'ol':
				$ordered = ( 'ol' === $tag );
				$el      = $ordered ? 'ol' : 'ul';
				$attrs   = $ordered ? ' {"ordered":true}' : '';
				$items   = momentive_pa_li_items( $node, $doc );
				if ( '' !== $items ) {
					$blocks[] = "<!-- wp:list{$attrs} -->\n"
					          . "<{$el} class=\"wp-block-list\">{$items}</{$el}>\n"
					          . "<!-- /wp:list -->";
					$first_p  = false;
				}
				break;

			default:
				$t = trim( wp_strip_all_tags( $inner ) );
				if ( '' === $t ) break;
				$class    = ( $first_p && $first_p_class ) ? $first_p_class : '';
				$blocks[] = momentive_pa_p( esc_html( $t ), $class );
				$first_p  = false;
		}
	}

	return array_values( array_filter( $blocks ) );
}

function momentive_pa_li_items( DOMNode $ul, DOMDocument $doc ): string {
	$items = '';
	foreach ( $ul->childNodes as $li ) {
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) continue;

		$style     = method_exists( $li, 'getAttribute' ) ? $li->getAttribute( 'style' ) : '';
		$child_els = array_filter(
			iterator_to_array( $li->childNodes ),
			fn( $c ) => XML_ELEMENT_NODE === $c->nodeType
		);
		if ( strpos( $style, 'list-style-type: none' ) !== false && count( $child_els ) === 1 ) {
			$inner_ul = reset( $child_els );
			if ( $inner_ul && in_array( strtolower( $inner_ul->nodeName ), [ 'ul', 'ol' ], true ) ) {
				$items .= momentive_pa_li_items( $inner_ul, $doc );
				continue;
			}
		}

		$li_outer = trim( $doc->saveHTML( $li ) );
		$li_inner = preg_replace( '#^<li[^>]*>(.*)</li>$#si', '$1', $li_outer );
		$items   .= "<!-- wp:list-item -->\n<li>{$li_inner}</li>\n<!-- /wp:list-item -->\n";
	}
	return $items;
}

/* =========================================================================
 * Block emitters — low-level pieces (shared by CTA / resource_cta)
 * ====================================================================== */

function momentive_pa_btn( string $text, string $url, bool $new_tab, bool $arrow ): string {
	$text = trim( $text );
	$url  = trim( $url );
	if ( '' === $text || '' === $url ) return '';

	$href   = esc_url( $url );
	$target = $new_tab ? ' target="_blank" rel="noreferrer noopener"' : '';

	if ( $arrow ) {
		return "<!-- wp:button {\"className\":\"has-arrow upward\"} -->\n"
		     . "<div class=\"wp-block-button has-arrow upward\">"
		     . "<a class=\"wp-block-button__link wp-element-button\" href=\"{$href}\"{$target}>{$text}</a>"
		     . "</div>\n<!-- /wp:button -->";
	}
	return "<!-- wp:button -->\n"
	     . "<div class=\"wp-block-button\">"
	     . "<a class=\"wp-block-button__link wp-element-button\" href=\"{$href}\"{$target}>{$text}</a>"
	     . "</div>\n<!-- /wp:button -->";
}

function momentive_pa_buttons( string ...$btns ): string {
	$inner = implode( '', array_filter( $btns ) );
	if ( '' === $inner ) return '';
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">{$inner}</div>\n<!-- /wp:buttons -->";
}

function momentive_pa_highlight( string $modifier, string $inner ): string {
	$class = trim( 'is-style-highlight ' . $modifier );
	return "<!-- wp:group {\"className\":\"{$class}\",\"layout\":{\"type\":\"constrained\"}} -->\n"
	     . "<div class=\"wp-block-group {$class}\">\n{$inner}\n</div>\n"
	     . "<!-- /wp:group -->";
}

/**
 * CTA highlight block (template 1458 — sc_cta_-_* fields).
 * The ONLY CTA shortcode ever used by a press-article (2 posts). Mirrors
 * momentive_pm_cta_block() from migrate-posts.php.
 */
function momentive_pa_cta_block(
	string $header,
	string $desc,
	string $btn_text,
	string $btn_url,
	bool   $btn_new_tab
): string {
	$header = trim( $header );
	$desc   = trim( $desc );
	if ( '' === $header && '' === $desc ) return '';

	$has_header = '' !== $header;
	$parts      = [];

	if ( $has_header ) {
		$parts[] = momentive_pa_p( esc_html( $header ), 'heading' );
	}
	if ( '' !== $desc ) {
		$desc_blocks = momentive_pa_desc_blocks( $desc, $has_header ? 'blurb' : '' );
		array_push( $parts, ...$desc_blocks );
	}

	$btn = momentive_pa_btn( $btn_text, $btn_url, $btn_new_tab, true );
	if ( '' !== $btn ) {
		$parts[] = momentive_pa_buttons( $btn );
	}

	if ( empty( $parts ) ) return '';

	$modifier = $has_header ? 'with-heading with-button' : 'with-button';
	return momentive_pa_highlight( $modifier, implode( "\n\n", $parts ) );
}

/**
 * Resource CTA block (resource_cta_* meta fields — 1 post: path-lms-advanced-assessments).
 * Mirrors momentive_pm_resource_cta_block() from migrate-posts.php.
 */
function momentive_pa_resource_cta_block(
	string $title,
	string $btn_text,
	string $btn_url,
	bool   $btn_new_tab
): string {
	$title    = trim( wp_strip_all_tags( $title ) );
	$btn_text = trim( $btn_text );
	$btn_url  = trim( $btn_url );

	if ( '' === $title || '' === $btn_text || '' === $btn_url ) return '';

	$btn   = momentive_pa_btn( $btn_text, $btn_url, $btn_new_tab, false );
	$inner = momentive_pa_p( $title, 'heading' ) . "\n\n" . momentive_pa_buttons( $btn );

	return momentive_pa_highlight( 'with-heading with-button', $inner );
}

/**
 * Outlet-attribution block (op_name/op_link/op_logo — in-the-news posts only).
 *
 * Confirmed shape from 4030 and 4372's rebuilt content: a bordered
 * "is-style-outline source" group, logo image (24px tall, when present) +
 * "This article was originally published by {name}" paragraph. Placed as the
 * last thing in the post-content column, before the About block(s).
 *
 * $logo_att_id: new (rebuilt-site) attachment ID, 0 if sideload failed/dry-run
 *               (falls back to the raw legacy URL as the <img> src — same
 *               degrade-gracefully convention as momentive_pm_cta_image_block()).
 * $logo_url:    the image URL to use as src regardless of $logo_att_id.
 * Returns '' when op_name or op_link is empty.
 */
function momentive_pa_outlet_attribution_block(
	string $op_name,
	string $op_link,
	int    $logo_att_id,
	string $logo_url
): string {
	$op_name  = trim( $op_name );
	$op_link  = trim( $op_link );
	$logo_url = trim( $logo_url );
	if ( '' === $op_name || '' === $op_link ) return '';

	$parts = [];

	if ( '' !== $logo_url ) {
		$id_json   = $logo_att_id > 0 ? "\"id\":{$logo_att_id}," : '';
		$img_class = $logo_att_id > 0 ? ' class="wp-image-' . $logo_att_id . '"' : '';
		$img       = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $op_name ) . '"'
		           . $img_class . ' style="width:auto;height:24px"/>';
		$parts[]   = "<!-- wp:image {{$id_json}\"width\":\"auto\",\"height\":\"24px\",\"sizeSlug\":\"medium\",\"linkDestination\":\"none\"} -->\n"
		           . "<figure class=\"wp-block-image size-medium is-resized\">{$img}</figure>\n<!-- /wp:image -->";
	}

	$parts[] = "<!-- wp:paragraph -->\n"
	         . '<p>This article was originally published by <a href="' . esc_url( $op_link ) . '" target="_blank" rel="noreferrer noopener">'
	         . esc_html( $op_name ) . "</a>.</p>\n<!-- /wp:paragraph -->";

	$inner = implode( "\n\n", $parts );

	return "<!-- wp:group {\"className\":\"is-style-outline source\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|x-small\",\"bottom\":\"var:preset|spacing|x-small\",\"left\":\"var:preset|spacing|small\",\"right\":\"var:preset|spacing|small\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
	     . "<div class=\"wp-block-group is-style-outline source\" style=\"padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--small)\">\n"
	     . "{$inner}\n</div>\n<!-- /wp:group -->";
}

/**
 * A post's OWN "about" section from additional_about_sections, when it is
 * genuinely distinct content — NOT the canonical "About TA" boilerplate
 * (which is instead covered by the category-driven synced pattern; see
 * momentive_pa_should_append_about_ta()). Confirmed shape from 4372's Lisa
 * Zola Greer bio: a plain "is-style-outline" group, optional heading (only
 * when about_title is set — 4372's was empty), then the description as
 * ordinary paragraph/list/heading blocks.
 *
 * The "is this the About TA boilerplate" check is a case-insensitive match
 * on about_title — confirmed against 789/1975/10242 (all titled exactly
 * "About TA"). Posts with a genuinely different about_title (e.g. "About
 * The Stevie Awards") are NOT directly confirmed by a rebuilt example, but
 * the inference is straightforward: they're distinct content the synced
 * pattern doesn't cover, so they migrate inline like Lisa Zola Greer's bio.
 */
function momentive_pa_own_about_block( string $serialized ): string {
	if ( '' === trim( $serialized ) ) return '';

	$data = @unserialize( $serialized );
	if ( ! is_array( $data ) || empty( $data ) ) return '';

	// Always exactly 1 item in this corpus, but loop defensively in case a
	// future post has more.
	$blocks = [];
	foreach ( $data as $row ) {
		$title = trim( (string) ( $row['about_title'] ?? '' ) );
		$desc  = trim( (string) ( $row['about_description'] ?? '' ) );
		if ( '' === $desc ) continue;
		if ( 0 === strcasecmp( $title, 'About TA' ) ) continue; // covered by the synced pattern instead

		$parts = [];
		if ( '' !== $title ) {
			$parts[] = "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">" . esc_html( $title ) . "</h3>\n<!-- /wp:heading -->";
		}
		array_push( $parts, ...momentive_pa_desc_blocks( $desc ) );
		if ( empty( $parts ) ) continue;

		$inner    = implode( "\n\n", $parts );
		$blocks[] = "<!-- wp:group {\"className\":\"is-style-outline\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|x-small\",\"bottom\":\"var:preset|spacing|x-small\",\"left\":\"var:preset|spacing|x-small\",\"right\":\"var:preset|spacing|x-small\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
		          . "<div class=\"wp-block-group is-style-outline\" style=\"padding-top:var(--wp--preset--spacing--x-small);padding-right:var(--wp--preset--spacing--x-small);padding-bottom:var(--wp--preset--spacing--x-small);padding-left:var(--wp--preset--spacing--x-small)\">\n"
		          . "{$inner}\n</div>\n<!-- /wp:group -->";
	}

	return implode( "\n\n", $blocks );
}

/**
 * Whether the category-driven canonical "About TA" synced pattern should be
 * appended. Confirmed 4-for-4 / 0-for-3 across the 7 hand-rebuilt posts:
 * press-releases and in-the-news get it, momentive-in-action never does.
 * `show_default_about` (always 'true' across all 64 legacy posts) carries
 * no signal and is deliberately not read.
 */
function momentive_pa_should_append_about_ta( string $news_category ): bool {
	return in_array( $news_category, [ 'press-releases', 'in-the-news' ], true );
}

/* =========================================================================
 * Category resolution: news-category meta → real "category" term
 * ====================================================================== */

function momentive_pa_resolve_category_term( string $news_category ): int {
	static $cache = [];

	$news_category = trim( $news_category );
	if ( '' === $news_category || ! isset( MOMENTIVE_PA_CATEGORY_MAP[ $news_category ] ) ) {
		return 0;
	}
	if ( isset( $cache[ $news_category ] ) ) {
		return $cache[ $news_category ];
	}

	$map  = MOMENTIVE_PA_CATEGORY_MAP[ $news_category ];
	$term = get_term_by( 'slug', $map['slug'], 'category' );
	if ( $term && ! is_wp_error( $term ) ) {
		return $cache[ $news_category ] = (int) $term->term_id;
	}

	// Doesn't exist yet — create it once (all 3 already exist on this site as
	// of this writing; this is a defensive fallback, not the expected path).
	$inserted = wp_insert_term( $map['name'], 'category', [ 'slug' => $map['slug'] ] );
	if ( is_wp_error( $inserted ) ) {
		WP_CLI::warning( "    Could not create/find category term '{$map['name']}': " . $inserted->get_error_message() );
		return $cache[ $news_category ] = 0;
	}
	return $cache[ $news_category ] = (int) $inserted['term_id'];
}

/* =========================================================================
 * Media: attachment map + sideload
 * ====================================================================== */

/** Mirrors momentive_pm_build_att_map() from migrate-posts.php. */
function momentive_pa_build_att_map( string $path, string $base ): array {
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "WXR not found at {$path}; media sideloading disabled." );
		return [];
	}

	$xml = file_get_contents( $path );
	$map = [];

	if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		foreach ( $items[1] as $item ) {
			if ( false === strpos( $item, 'post_type><![CDATA[attachment]]>' ) ) continue;
			if ( ! preg_match( '#<wp:post_id>(\d+)</wp:post_id>#', $item, $pm ) ) continue;
			if ( ! preg_match(
				'#<wp:meta_key><!\[CDATA\[_wp_attached_file\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]>#s',
				$item, $fm
			) ) continue;
			$map[ (int) $pm[1] ] = $base . ltrim( $fm[1], '/' );
		}
	}

	WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs resolved.', count( $map ) ) );
	return $map;
}

/** Mirrors momentive_pm_sideload() from migrate-posts.php / migrate-webinars.php. */
function momentive_pa_sideload( string $url, int $parent_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) return 0;

	$existing = get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_momentive_source_url',
		'meta_value'     => $url,
		'no_found_rows'  => true,
	] );
	if ( $existing ) return (int) $existing[0];

	if ( $dry ) {
		WP_CLI::log( "    [dry] sideload: {$url}" );
		return 0;
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$tmp = download_url( $url );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "    sideload FAILED: {$url} ({$tmp->get_error_message()})" );
		return 0;
	}

	$file    = [ 'name' => basename( parse_url( $url, PHP_URL_PATH ) ), 'tmp_name' => $tmp ];
	$ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	$is_svg  = in_array( $ext, [ 'svg', 'svgz' ], true );
	$mime_cb = static fn( $m ) => array_merge( $m, [ 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml' ] );

	if ( $is_svg ) add_filter( 'upload_mimes', $mime_cb, 99 );
	$att_id = media_handle_sideload( $file, $parent_id );
	if ( $is_svg ) remove_filter( 'upload_mimes', $mime_cb, 99 );

	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "    sideload FAILED: {$url} ({$att_id->get_error_message()})" );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return $att_id;
}

/* =========================================================================
 * Author assignment
 * ====================================================================== */

/**
 * Set the post_author_ref ACF field by matching ppma_authors_name to a
 * People CPT post title. Mirrors momentive_pm_set_author() from
 * migrate-posts.php exactly — same field (shared "Post Settings" ACF group
 * covers both `post` and `press-article`).
 */
function momentive_pa_set_author( int $post_id, string $author_name ): void {
	$author_name = trim( $author_name );
	if ( '' === $author_name ) return;

	global $wpdb;
	$person_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'people' AND post_status = 'publish' AND post_title = %s
		 LIMIT 1",
		$author_name
	) );

	if ( 0 === $person_id ) {
		WP_CLI::warning( "    Author '{$author_name}' not found in People CPT — post_author_ref unset." );
		return;
	}

	update_field( 'post_author_ref', $person_id, $post_id );
}

/* =========================================================================
 * Page scaffold
 * ====================================================================== */

/**
 * Wrap post body content in the press-article-content.php pattern structure,
 * with the hero_overline eyebrow and hero_subtitle paragraph conditionally
 * inserted, and the (confirmed, narrow) hero_image_alignment="bottom" style
 * applied to the entry-header group when requested.
 *
 * Confirmed against 9866 (plain), 10265 (plain, no hero fields), 2125
 * (overline + subtitle + bottom-alignment style), 4030/4372/7810/18275
 * (plain, matching 9866's shape).
 */
function momentive_pa_scaffold( string $body, string $hero_overline, string $hero_subtitle, bool $hero_bottom_align ): string {
	$hero_overline = trim( $hero_overline );
	$hero_subtitle = trim( $hero_subtitle );

	$header_attrs = '{"align":"full","className":"entry-header","layout":{"type":"constrained"}}';
	$header_style = '';
	if ( $hero_bottom_align ) {
		$header_attrs = '{"align":"full","className":"entry-header","style":{"spacing":{"padding":{"bottom":"0","top":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}}';
		$header_style = ' style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:0"';
	}

	$eyebrow = '';
	if ( '' !== $hero_overline ) {
		$eyebrow = "\n\n\t\t\t<!-- wp:paragraph {\"className\":\"is-style-eyebrow\",\"fontSize\":\"regular\"} -->\n"
		         . "\t\t\t<p class=\"is-style-eyebrow has-regular-font-size\">" . esc_html( $hero_overline ) . "</p>\n"
		         . "\t\t\t<!-- /wp:paragraph -->";
	}

	$subtitle = '';
	if ( '' !== $hero_subtitle ) {
		$subtitle = "\n\n\t\t\t<!-- wp:paragraph {\"fontSize\":\"large\"} -->\n"
		          . "\t\t\t<p class=\"has-large-font-size\">" . esc_html( $hero_subtitle ) . "</p>\n"
		          . "\t\t\t<!-- /wp:paragraph -->";
	}

	return <<<EOL
<!-- wp:group {$header_attrs} -->
<div class="wp-block-group alignfull entry-header"{$header_style}>

	<!-- wp:group {"className":"header-inner"} -->
	<div class="wp-block-group header-inner">

		<!-- wp:group {"className":"header-media"} -->
		<div class="wp-block-group header-media">
			<!-- wp:post-featured-image /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"header-content"} -->
		<div class="wp-block-group header-content">

			<!-- wp:momentive/breadcrumbs {"lock":{"move":true,"remove":true}} /-->

			<!-- wp:post-terms {"term":"category","separator":"","lock":{"move":true,"remove":true},"className":"taxonomy-category lower-label"} /-->{$eyebrow}

			<!-- wp:post-title {"level":1,"lock":{"move":true,"remove":true}} /-->{$subtitle}

			<!-- wp:momentive/post-cta-button /-->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->

<!-- wp:columns {"isStackedOnMobile":false,"className":"post-layout"} -->
<div class="wp-block-columns is-not-stacked-on-mobile post-layout">

	<!-- wp:column {"className":"post-content"} -->
	<div class="wp-block-column post-content">

		<!-- wp:momentive/post-byline /-->

		{$body}

	</div>
	<!-- /wp:column -->

	<!-- wp:column {"className":"post-sidebar"} -->
	<div class="wp-block-column post-sidebar">

		<!-- wp:group {"className":"sidebar-sticky"} -->
		<div class="wp-block-group sidebar-sticky">

			<!-- wp:momentive/table-of-contents /-->

			<!-- wp:momentive/social-share /-->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:column -->

</div>
<!-- /wp:columns -->
EOL;
}

/* =========================================================================
 * "Already rebuilt" guard
 * ====================================================================== */

/**
 * A press-article post is considered genuinely rebuilt when its content
 * contains the `post-layout` className — the real two-column scaffold, not
 * just the plain paragraph/heading blocks every legacy post already arrived
 * with. This is exactly the CPT-specific check
 * notes/press-article-reference-sheet.md flagged report-rebuild-progress.php
 * as missing (that script's generic "any <!-- wp: --> present" classifier
 * misreports this CPT as 54/54 "rebuilt" when real coverage was 2/64 at the
 * time of writing).
 */
function momentive_pa_already_rebuilt( int $post_id ): bool {
	if ( $post_id <= 0 ) return false;
	$content = get_post_field( 'post_content', $post_id );
	return is_string( $content ) && str_contains( $content, 'post-layout' );
}

/* =========================================================================
 * WXR parser
 * ====================================================================== */

function momentive_pa_parse_wxr( string $path ): array {
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "WXR not found: {$path}" );
	}

	$xml   = file_get_contents( $path );
	$posts = [];

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		return $posts;
	}

	foreach ( $items[1] as $raw ) {
		if ( false === strpos( $raw, '<wp:post_type><![CDATA[press-article]]></wp:post_type>' ) &&
		     false === strpos( $raw, '<wp:post_type>press-article</wp:post_type>' ) ) {
			continue;
		}

		$status = momentive_pa_xml_tag( $raw, 'wp:status' );
		if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) continue;

		$meta = [];
		if ( preg_match_all( '#<wp:postmeta>(.*?)</wp:postmeta>#s', $raw, $pm ) ) {
			foreach ( $pm[1] as $m ) {
				$k = momentive_pa_xml_tag( $m, 'wp:meta_key' );
				$v = momentive_pa_xml_tag( $m, 'wp:meta_value' );
				if ( '' !== $k ) $meta[ $k ] = $v;
			}
		}

		$posts[] = [
			'slug'              => momentive_pa_xml_tag( $raw, 'wp:post_name' ),
			'title'             => momentive_pa_xml_tag( $raw, 'title' ),
			'status'            => $status,
			'post_date'         => momentive_pa_xml_tag( $raw, 'wp:post_date' ),
			'post_date_gmt'     => momentive_pa_xml_tag( $raw, 'wp:post_date_gmt' ),
			'post_modified'     => momentive_pa_xml_tag( $raw, 'wp:post_modified' ),
			'post_modified_gmt' => momentive_pa_xml_tag( $raw, 'wp:post_modified_gmt' ),
			'excerpt'           => momentive_pa_xml_tag( $raw, 'excerpt:encoded' ),
			'content'           => momentive_pa_xml_tag( $raw, 'content:encoded' ),
			'meta'              => $meta,
		];
	}

	return $posts;
}

/* =========================================================================
 * Main post migrator
 * ====================================================================== */

/**
 * Migrate (or update) a single press-article.
 * Returns [ 'status' => 'created'|'updated'|'dry'|'skipped'|'error', 'id' => post_id ].
 */
function momentive_pa_migrate_post( array $post, array $att_map, bool $dry, bool $bypass_guard ): array {
	global $wpdb;

	$slug    = $post['slug'];
	$meta    = $post['meta'];
	$content = trim( $post['content'] );

	WP_CLI::log( "  [{$post['status']}] {$slug}" );

	// ── 1. Resolve existing post by SLUG, not legacy ID ──────────────────
	// Confirmed ID drift on 3 of the first 7 hand-rebuilt posts (see header
	// comment) — matching by legacy post_id would silently create duplicates.
	$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'press-article' LIMIT 1",
		$slug
	) );
	$parent_id = $existing_id;

	// ── 2. "Already rebuilt" guard ────────────────────────────────────────
	if ( $existing_id > 0 && ! $bypass_guard && momentive_pa_already_rebuilt( $existing_id ) ) {
		WP_CLI::log( "    already rebuilt (post-layout present) — skipped. Use force or only={$slug} to override." );
		return [ 'status' => 'skipped', 'id' => $existing_id ];
	}

	$news_category = trim( $meta['news-category'] ?? '' );

	// ── 3. Replace the leftover CTA shortcode, if present (2 posts total) ─
	if ( momentive_pa_truthy( $meta['sc_cta_-_enable_cta_section'] ?? '' ) ) {
		$cta = momentive_pa_cta_block(
			$meta['sc_cta_-_header']       ?? '',
			$meta['sc_cta_-_description']  ?? '',
			$meta['sc_cta_-_button_text']  ?? '',
			$meta['sc_cta_-_button_url']   ?? '',
			momentive_pa_truthy( $meta['sc_cta_-_button_open_in_new_tab'] ?? '' )
		);
		if ( '' !== $cta ) {
			// preg_replace_callback (not preg_replace) — $cta is arbitrary
			// generated HTML that may itself contain literal '$' or '\'
			// characters (e.g. dollar amounts), which preg_replace would
			// misinterpret as backreferences in the replacement string.
			$count   = 0;
			$content = preg_replace_callback(
				'#<!-- wp:shortcode(?:[^>]*)? -->\s*\.?\[elementor-template\s+id="?1458"?\]\s*<!-- /wp:shortcode -->#s',
				function() use ( $cta ) { return $cta; },
				$content, 1, $count
			);
			if ( 0 === $count ) {
				// Fallback: bare bracket text left in content (confirmed on
				// collective-strength-symposium / 2b-raised-2025).
				$content = preg_replace_callback(
					'#\[elementor-template\s+id="?1458"?\]#',
					function() use ( $cta ) { return $cta; },
					$content, 1, $count
				);
			}
			if ( $count > 0 ) {
				WP_CLI::log( '    + CTA section block (sc_cta_-_*)' );
			} else {
				// No shortcode marker found anywhere — append at the end rather
				// than silently drop a populated CTA field.
				$content .= "\n\n" . $cta;
				WP_CLI::log( '    + CTA section block (sc_cta_-_*, appended — no shortcode marker found in content)' );
			}
		}
	}

	// ── 4. Append resource_cta block, if enabled (1 post total) ──────────
	if ( momentive_pa_truthy( $meta['resource_cta_enable_cta'] ?? '' ) ) {
		$rc = momentive_pa_resource_cta_block(
			$meta['resource_cta_ttitle']      ?? '',
			$meta['resource_cta_button_text'] ?? '',
			$meta['resource_cta_button_url']  ?? '',
			momentive_pa_truthy( $meta['resource_cta_button_link_outbound'] ?? '' )
		);
		if ( '' !== $rc ) {
			$content .= "\n\n" . $rc;
			WP_CLI::log( '    + resource_cta block' );
		}
	}

	// ── 5. Outlet attribution block (in-the-news only) ───────────────────
	$op_name = trim( $meta['op_name'] ?? '' );
	$op_link = trim( $meta['op_link'] ?? '' );
	if ( '' !== $op_name && '' !== $op_link ) {
		$logo_att_id    = 0;
		$logo_url       = '';
		$legacy_logo_id = (int) ( $meta['op_logo'] ?? 0 );

		if ( $legacy_logo_id > 0 ) {
			$logo_url = $att_map[ $legacy_logo_id ] ?? '';
			if ( '' !== $logo_url ) {
				$logo_att_id = momentive_pa_sideload( $logo_url, $parent_id, $dry );
				if ( $logo_att_id > 0 ) {
					$logo_url = wp_get_attachment_url( $logo_att_id ) ?: $logo_url;
				}
			} else {
				WP_CLI::warning( "    op_logo legacy ID {$legacy_logo_id} not in attachment map." );
			}
		}

		$attribution = momentive_pa_outlet_attribution_block( $op_name, $op_link, $logo_att_id, $logo_url );
		if ( '' !== $attribution ) {
			$content .= "\n\n" . $attribution;
			WP_CLI::log( '    + outlet attribution block' );
		}
	}

	// ── 6. About section(s) — category-driven synced block, then any ─────
	//        genuinely distinct per-post about content (confirmed order from
	//        4372: attribution → synced "About TA" → per-post bio).
	if ( momentive_pa_should_append_about_ta( $news_category ) ) {
		$content .= "\n\n<!-- wp:block {\"ref\":" . MOMENTIVE_PA_ABOUT_TA_PATTERN_REF . "} /-->";
		WP_CLI::log( '    + synced "About TA" block (category: ' . $news_category . ')' );
	}
	$own_about = momentive_pa_own_about_block( $meta['additional_about_sections'] ?? '' );
	if ( '' !== $own_about ) {
		$content .= "\n\n" . $own_about;
		WP_CLI::log( '    + per-post about section' );
	}

	// ── 7. Wrap in the page scaffold ──────────────────────────────────────
	$hero_overline = trim( $meta['hero_overline'] ?? '' );
	$hero_subtitle = trim( $meta['hero_subtitle'] ?? '' );
	// hero_title is deliberately NOT read — confirmed vestigial, see header comment.
	$hero_bottom = ( 'bottom' === trim( $meta['hero_image_alignment'] ?? '' ) );
	$content     = momentive_pa_scaffold( $content, $hero_overline, $hero_subtitle, $hero_bottom );

	// ── 8. Dry-run exit ───────────────────────────────────────────────────
	if ( $dry ) {
		return [ 'status' => 'dry', 'id' => $existing_id ];
	}

	// ── 9. Write post ─────────────────────────────────────────────────────
	$post_data = [
		'post_title'    => $post['title'],
		'post_name'     => $slug,
		'post_status'   => $post['status'],
		'post_type'     => 'press-article',
		'post_excerpt'  => $post['excerpt'],
		'post_content'  => $content,
		'post_date'     => $post['post_date'],
		'post_date_gmt' => $post['post_date_gmt'],
	];

	if ( $existing_id > 0 ) {
		$post_data['ID'] = $existing_id;
		$new_id = wp_update_post( wp_slash( $post_data ), true );
	} else {
		$new_id = wp_insert_post( wp_slash( $post_data ), true );
	}

	if ( is_wp_error( $new_id ) ) {
		WP_CLI::warning( '    FAILED: ' . $new_id->get_error_message() );
		return [ 'status' => 'error', 'id' => 0 ];
	}

	// Restore original modified date (wp_update/insert_post always forces it to NOW).
	if ( ! empty( $post['post_modified'] ) ) {
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $post['post_modified'],
				'post_modified_gmt' => $post['post_modified_gmt'],
			],
			[ 'ID' => $new_id ]
		);
	}

	update_post_meta( $new_id, MOMENTIVE_PA_RUN_META, momentive_pa_run_id() );

	// ── 10. Featured image ────────────────────────────────────────────────
	$legacy_thumb = (int) ( $meta['_thumbnail_id'] ?? 0 );
	if ( $legacy_thumb > 0 ) {
		$thumb_url = $att_map[ $legacy_thumb ] ?? '';
		if ( '' !== $thumb_url ) {
			$thumb_id = momentive_pa_sideload( $thumb_url, $new_id, false );
			if ( $thumb_id > 0 ) {
				set_post_thumbnail( $new_id, $thumb_id );
			}
		} else {
			WP_CLI::warning( "    thumbnail legacy ID {$legacy_thumb} not in attachment map." );
		}
	}

	// ── 11. hero_image ACF field — only when alternate_hero_image is true ─
	// (confirmed convention, same as webinars/whitepapers: leave empty and
	// let the featured image serve both roles otherwise).
	if ( momentive_pa_truthy( $meta['alternate_hero_image'] ?? '' ) ) {
		$legacy_hero = (int) ( $meta['hero_image'] ?? 0 );
		if ( $legacy_hero > 0 ) {
			$hero_url = $att_map[ $legacy_hero ] ?? '';
			if ( '' !== $hero_url ) {
				$hero_att_id = momentive_pa_sideload( $hero_url, $new_id, false );
				if ( $hero_att_id > 0 ) {
					update_field( 'hero_image', $hero_att_id, $new_id );
				}
			} else {
				WP_CLI::warning( "    hero_image legacy ID {$legacy_hero} not in attachment map." );
			}
		}
	}

	// ── 12. Category (news-category → real term) ─────────────────────────
	$term_id = momentive_pa_resolve_category_term( $news_category );
	if ( $term_id > 0 ) {
		wp_set_post_categories( $new_id, [ $term_id ], false );
	} elseif ( '' !== $news_category ) {
		WP_CLI::warning( "    Unrecognized news-category '{$news_category}' — no category assigned." );
	}

	// ── 13. Author (post_author_ref) ──────────────────────────────────────
	momentive_pa_set_author( $new_id, trim( $meta['ppma_authors_name'] ?? '' ) );

	// ── 14. Old slug (redirect preservation) + breadcrumb title ──────────
	$old_slug = trim( $meta['_wp_old_slug'] ?? '' );
	if ( '' !== $old_slug ) {
		update_post_meta( $new_id, '_wp_old_slug', $old_slug );
	}
	$breadcrumb = trim( $meta['custom_breadcrumb_title'] ?? '' );
	if ( '' !== $breadcrumb ) {
		update_field( 'breadcrumb_title', $breadcrumb, $new_id );
	}

	$verb = $existing_id > 0 ? 'updated' : 'created';
	WP_CLI::log( "    ✓ {$verb} #{$new_id}" );

	return [ 'status' => $verb, 'id' => $new_id ];
}

/* =========================================================================
 * Main
 * ====================================================================== */

$pa_att_map = momentive_pa_build_att_map( $pa_wxr_path, $pa_uploads_base );

$all_posts = momentive_pa_parse_wxr( $pa_wxr_path );
WP_CLI::log( sprintf( 'Found %d published/draft press-article posts in WXR.', count( $all_posts ) ) );

if ( '' !== $only_slug ) {
	$all_posts = array_values( array_filter( $all_posts, fn( $p ) => $p['slug'] === $only_slug ) );
	WP_CLI::log( sprintf( 'Filtered to %d post(s) matching slug "%s".', count( $all_posts ), $only_slug ) );
}
if ( $limit > 0 ) {
	$all_posts = array_slice( $all_posts, 0, $limit );
}

// The "already rebuilt" guard is bypassed when targeting a specific post by
// slug (the whole point of `only=` is to touch that one post) or when
// `force` is passed explicitly.
$bypass_guard = $force || ( '' !== $only_slug );

$counts = [ 'created' => 0, 'updated' => 0, 'dry' => 0, 'skipped' => 0, 'error' => 0 ];

foreach ( $all_posts as $post ) {
	$result = momentive_pa_migrate_post( $post, $pa_att_map, $dry, $bypass_guard );
	$counts[ $result['status'] ] = ( $counts[ $result['status'] ] ?? 0 ) + 1;
}

WP_CLI::log( '─────────────────────────────────────────────────────────────' );
WP_CLI::log( sprintf(
	'Done. created=%d  updated=%d  dry=%d  skipped=%d  errors=%d',
	$counts['created'] ?? 0,
	$counts['updated'] ?? 0,
	$counts['dry']     ?? 0,
	$counts['skipped'] ?? 0,
	$counts['error']   ?? 0
) );

if ( $dry ) {
	WP_CLI::log( 'Re-run with "live" to write. Use "limit=N" for a partial test, "only=slug" for a single post, "force" to overwrite already-rebuilt posts.' );
}
if ( ( $counts['skipped'] ?? 0 ) > 0 ) {
	WP_CLI::log( sprintf(
		'%d post(s) skipped as already-rebuilt (post-layout present). Re-run with "force" or "only=<slug>" to override.',
		$counts['skipped']
	) );
}
