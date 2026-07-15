<?php
/**
 * migrate-posts.php
 *
 * WP-CLI migration script: legacy `post` CPT blog posts.
 *
 * The legacy site injected per-post content sections via Elementor template
 * shortcodes ([elementor-template id="X"]) embedded in post_content. The
 * content for each section was stored in ACF meta fields (sc_tip_N_*,
 * sc_cta_-_*, etc.). Post content is already Gutenberg block markup in the
 * WXR — only the Elementor shortcodes need substitution.
 *
 * Additionally appends resource_cta and prefooter (cta_-_*) blocks when their
 * respective enable flags are set. The prefooter is placed at the end of
 * post_content; moveBlogPrefooter() in momentive.js relocates it before
 * .site-footer at runtime.
 *
 * Run (from the theme directory):
 *
 *   wp eval-file migrations/migrate-posts.php --user=<admin>
 *     → dry run; logs what would change without writing anything
 *
 *   wp eval-file migrations/migrate-posts.php live --user=<admin>
 *     → writes posts, sideloads media
 *
 *   wp eval-file migrations/migrate-posts.php live limit=50 --user=<admin>
 *     → first 50 posts only
 *
 *   wp eval-file migrations/migrate-posts.php live only=my-post-slug --user=<admin>
 *     → single post by slug
 *
 * --user=<admin> is REQUIRED: Safe SVG gates SVG sideloads on capability.
 * Idempotent: upserts by slug; re-running updates in place.
 * Rollback: posts stamped with _momentive_migration_run. Restore from DB backup.
 *
 * Overridable constants: MOMENTIVE_PM_LEGACY_WXR, MOMENTIVE_PM_UPLOADS_BASE
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-posts.php [live] [limit=N] [only=slug] --user=<admin>' . PHP_EOL );
}

/* =========================================================================
 * Run-mode flags (positional args — wp eval-file ignores -- flags)
 * ====================================================================== */

$dry       = true;
$limit     = 0;
$only_slug = '';

if ( ! empty( $args ) && is_array( $args ) ) {
	foreach ( $args as $arg ) {
		if ( 'live' === $arg )               { $dry = false; }
		elseif ( str_starts_with( $arg, 'limit=' ) ) { $limit     = (int) substr( $arg, 6 ); }
		elseif ( str_starts_with( $arg, 'only=' )  ) { $only_slug = substr( $arg, 5 ); }
	}
}

WP_CLI::log( sprintf(
	'migrate-posts: %s  limit=%s  only=%s',
	$dry ? 'DRY RUN' : 'LIVE',
	$limit ?: 'none',
	$only_slug ?: 'all'
) );

/* =========================================================================
 * Constants
 * ====================================================================== */

const MOMENTIVE_PM_RUN_META = '_momentive_migration_run';

$pm_wxr_path = defined( 'MOMENTIVE_PM_LEGACY_WXR' )
	? MOMENTIVE_PM_LEGACY_WXR
	: __DIR__ . '/momentivesoftware.posts.current.2026-07-13.xml';

// Optional supplementary media export (Tools → Export → Media on the legacy site).
// The posts WXR only contains attachments parented to exported posts; standalone
// CTA images (post_parent=0) are absent. Exporting the media library separately
// and dropping it next to the script fills those gaps.
$pm_media_wxr_path = defined( 'MOMENTIVE_PM_MEDIA_WXR' )
	? MOMENTIVE_PM_MEDIA_WXR
	: __DIR__ . '/momentivesoftware.media.current.2026-07-13.xml';

$pm_uploads_base = rtrim(
	defined( 'MOMENTIVE_PM_UPLOADS_BASE' )
		? MOMENTIVE_PM_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/',
	'/'
) . '/';

/**
 * Template ID → [ type, slot ]
 *
 * type:  'cta'       — sc_cta_-_* fields (slots 1 and 2)
 *        'cta_image' — sc_cta_with_image_-_* fields (slots 1 and 2)
 *        'tip'       — sc_tip_N_* fields; slot numbers here are UNUSED.
 *                      Tip templates 1467–1475 are interchangeable — the same
 *                      template ID can render different slots in different posts.
 *                      The correct slot is resolved POSITIONALLY in
 *                      momentive_pm_replace_shortcodes(): the N-th tip template
 *                      in content order maps to the N-th enabled sc_tip_N slot.
 *        'checklist' — enable_checklist_1 / checklist_1 fields
 *        'remove'    — drop the shortcode with no replacement
 *
 * Field naming convention: slot 1 has no suffix; slot 2 appends '_2'.
 * e.g. sc_cta_-_header (slot 1)  vs  sc_cta_-_header_2 (slot 2).
 */
const MOMENTIVE_PM_TPL = [
	1458 => [ 'cta',       1 ],
	1526 => [ 'cta',       2 ],
	1464 => [ 'cta_image', 1 ],
	1527 => [ 'cta_image', 2 ],
	// Tip IDs: slot numbers below are unused — positional resolution in replace_shortcodes().
	1467 => [ 'tip', 0 ],
	1468 => [ 'tip', 0 ],
	1469 => [ 'tip', 0 ],
	1470 => [ 'tip', 0 ],
	1471 => [ 'tip', 0 ],
	1472 => [ 'tip', 0 ],
	1473 => [ 'tip', 0 ],
	1474 => [ 'tip', 0 ],
	1475 => [ 'tip', 0 ],
	8984 => [ 'checklist', 1 ],
	1974 => [ 'remove',    0 ],
];

/* =========================================================================
 * Utilities
 * ====================================================================== */

/** True for 'true', '1', 'yes', 'on'; false for anything else. */
function momentive_pm_truthy( string $v ): bool {
	return in_array( trim( $v ), [ 'true', '1', 'yes', 'on' ], true );
}

/** Stable run timestamp for _momentive_migration_run stamps. */
function momentive_pm_run_id(): string {
	static $id = '';
	if ( '' === $id ) $id = gmdate( 'Y-m-d H:i:s' );
	return $id;
}

/**
 * Strip MS-Word span cruft from HTML.
 * Mirrors momentive_wm_strip_word() from migrate-webinars.php.
 */
function momentive_pm_strip_word( string $html ): string {
	// Spans with Word data-attributes (data-contrast, data-ccp-props, etc.)
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-props|ccp-charstyle)[^>]*>(.*?)</span>#si',
		'$1', $html
	);
	// Spans with Word class tokens (NormalTextRun, SCXW*, BCX*, EOP, etc.)
	$wc = 'NormalTextRun|TextRun|EOP|SpellingError|CommentStart|CommentEnd|SCXW\d+|BCX\d+|ContextualSpellingError';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*(?:' . $wc . ')[^"]*"[^>]*>(.*?)</span>#si',
		'$1', $html
	);
	// Remaining bare spans (no attributes or only whitespace)
	$html = preg_replace( '#<span\b(?:\s*)>(.*?)</span>#si', '$1', $html );
	return $html;
}

/**
 * Strip WordPress block comments embedded inside ACF field values.
 * Some fields were edited in a Gutenberg context and contain
 * <!-- wp:paragraph --> etc. mixed into the HTML.
 */
function momentive_pm_strip_block_comments( string $html ): string {
	return preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $html );
}

/**
 * Extract CDATA-wrapped or plain child-tag text from a raw XML item string.
 */
function momentive_pm_xml_tag( string $xml, string $tag ): string {
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s',
		$xml, $m
	) ) return $m[1];
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s',
		$xml, $m
	) ) return $m[1];
	return '';
}

/* =========================================================================
 * HTML → Gutenberg block converters
 * ====================================================================== */

/**
 * Return a wp:paragraph block.
 * $class: optional CSS class applied to both the block attrs and the <p>.
 */
function momentive_pm_p( string $inner, string $class = '' ): string {
	$inner = trim( $inner );
	if ( '' === $inner ) return '';
	$attrs = $class ? " {\"className\":\"{$class}\"}" : '';
	$p     = $class ? "<p class=\"{$class}\">{$inner}</p>" : "<p>{$inner}</p>";
	return "<!-- wp:paragraph{$attrs} -->\n{$p}\n<!-- /wp:paragraph -->";
}

/**
 * Convert a field's raw HTML to an array of Gutenberg block markup strings.
 * Handles <p>, <h2-6>, <ul>, <ol>, <blockquote>. Strips block comments and
 * Word cruft first. Falls back to a plain paragraph for text fragments.
 *
 * $first_p_class — applied to the first <p> element only (e.g. 'blurb').
 *
 * This mirrors momentive_wm_html_to_blocks() from migrate-webinars.php with
 * the addition of the $first_p_class parameter and nested-list unwrapping
 * (the legacy editor sometimes wraps list items in list-style-type:none lis).
 */
function momentive_pm_desc_blocks( string $raw_html, string $first_p_class = '' ): array {
	$html = momentive_pm_strip_block_comments( $raw_html );
	$html = momentive_pm_strip_word( $html );
	$html = trim( $html );
	if ( '' === $html ) return [];

	// Plain text fragment (no block-level tags): single paragraph.
	if ( ! preg_match( '#<(?:p|ul|ol|h[2-6]|blockquote|table)\b#i', $html ) ) {
		$text = trim( wp_strip_all_tags( $html ) );
		return '' !== $text ? [ momentive_pm_p( $text, $first_p_class ) ] : [];
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>' );
	libxml_clear_errors();

	$root = $doc->getElementById( '__root__' );
	if ( ! $root ) {
		$text = trim( wp_strip_all_tags( $html ) );
		return '' !== $text ? [ momentive_pm_p( $text, $first_p_class ) ] : [];
	}

	$blocks     = [];
	$first_p    = true; // tracks whether we've emitted the first <p>

	foreach ( $root->childNodes as $node ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$t = trim( $node->textContent );
			if ( '' === $t ) continue;
			$class = ( $first_p && $first_p_class ) ? $first_p_class : '';
			$blocks[] = momentive_pm_p( esc_html( $t ), $class );
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
				$blocks[] = momentive_pm_p( $t, $class );
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
				$items   = momentive_pm_li_items( $node, $doc );
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
				$blocks[] = momentive_pm_p( esc_html( $t ), $class );
				$first_p  = false;
		}
	}

	return array_values( array_filter( $blocks ) );
}

/**
 * Extract <li> items from a DOMNode <ul>/<ol>, handling the legacy nested-list
 * pattern where the outer li has list-style-type:none and wraps a single <ul>.
 * Returns concatenated <!-- wp:list-item --> markup.
 */
function momentive_pm_li_items( DOMNode $ul, DOMDocument $doc ): string {
	$items = '';
	foreach ( $ul->childNodes as $li ) {
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) continue;

		// Unwrap "list-style-type: none" outer li that contains only a child <ul>
		$style = method_exists( $li, 'getAttribute' ) ? $li->getAttribute( 'style' ) : '';
		$child_els = array_filter(
			iterator_to_array( $li->childNodes ),
			fn( $c ) => XML_ELEMENT_NODE === $c->nodeType
		);
		if ( strpos( $style, 'list-style-type: none' ) !== false && count( $child_els ) === 1 ) {
			$inner_ul = reset( $child_els );
			if ( $inner_ul && in_array( strtolower( $inner_ul->nodeName ), [ 'ul', 'ol' ], true ) ) {
				$items .= momentive_pm_li_items( $inner_ul, $doc );
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
 * Block emitters — low-level pieces
 * ====================================================================== */

/**
 * Build a single wp:button markup string.
 * $arrow: whether to add the 'has-arrow upward' CTA style.
 * Returns '' when text or url are empty.
 */
function momentive_pm_btn( string $text, string $url, bool $new_tab, bool $arrow ): string {
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

/**
 * Wrap one or more pre-built button markup strings in wp:buttons.
 * Returns '' when all inputs are empty.
 */
function momentive_pm_buttons( string ...$btns ): string {
	$inner = implode( '', array_filter( $btns ) );
	if ( '' === $inner ) return '';
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">{$inner}</div>\n<!-- /wp:buttons -->";
}

/**
 * Wrap inner block markup in is-style-highlight wp:group.
 * $modifier: space-separated additional classes (e.g. 'with-heading with-button').
 * $inner:    pre-concatenated inner block markup (no leading/trailing newlines needed).
 */
function momentive_pm_highlight( string $modifier, string $inner ): string {
	$class = trim( 'is-style-highlight ' . $modifier );
	return "<!-- wp:group {\"className\":\"{$class}\",\"layout\":{\"type\":\"constrained\"}} -->\n"
	     . "<div class=\"wp-block-group {$class}\">\n{$inner}\n</div>\n"
	     . "<!-- /wp:group -->";
}

/* =========================================================================
 * Block emitters — field-level blocks
 * ====================================================================== */

/**
 * CTA highlight block (templates 1458 / 1526).
 *
 * Modifier logic:
 *   header only  → with-heading with-button  (heading para only, no blurb)
 *   desc only    → with-button               (plain para)
 *   header+desc  → with-heading with-button  (heading + blurb para)
 *
 * Buttons use the 'has-arrow upward' style (CTA blocks in post body).
 * Returns '' when both header and desc are empty.
 */
function momentive_pm_cta_block(
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

	$parts = [];

	if ( $has_header ) {
		$parts[] = momentive_pm_p( esc_html( $header ), 'heading' );
	}

	if ( '' !== $desc ) {
		// First paragraph of desc gets 'blurb' class when a heading is present.
		$desc_blocks = momentive_pm_desc_blocks( $desc, $has_header ? 'blurb' : '' );
		array_push( $parts, ...$desc_blocks );
	}

	$btn = momentive_pm_btn( $btn_text, $btn_url, $btn_new_tab, true ); // arrow = true
	if ( '' !== $btn ) {
		$parts[] = momentive_pm_buttons( $btn );
	}

	if ( empty( $parts ) ) return '';

	$modifier = $has_header ? 'with-heading with-button' : 'with-button';
	return momentive_pm_highlight( $modifier, implode( "\n\n", $parts ) );
}

/**
 * CTA-with-image highlight block (templates 1464 / 1527).
 *
 * Modifier logic:
 *   header present → with-heading with-image
 *   no header      → with-image
 *
 * Buttons use plain style (whitepaper/resource download context).
 * $new_att_id = 0 when image sideload failed (img_url used as src fallback).
 * Returns '' when no meaningful content.
 */
function momentive_pm_cta_image_block(
	string $header,
	string $desc,
	int    $new_att_id,
	string $img_url,
	string $btn_text,
	string $btn_url,
	bool   $btn_new_tab
): string {
	$header  = trim( $header );
	$desc    = trim( $desc );
	$img_url = trim( $img_url );

	if ( '' === $header && '' === $desc && '' === $img_url ) return '';

	$has_header = '' !== $header;
	$parts      = [];

	if ( $has_header ) {
		$parts[] = momentive_pm_p( esc_html( $header ), 'heading' );
	}

	if ( '' !== $desc ) {
		$desc_blocks = momentive_pm_desc_blocks( $desc, $has_header ? 'blurb' : '' );
		array_push( $parts, ...$desc_blocks );
	}

	$has_image = '' !== $img_url;

	if ( $has_image ) {
		$id_json   = $new_att_id > 0 ? "\"id\":{$new_att_id}," : '';
		$img_class = $new_att_id > 0 ? " class=\"wp-image-{$new_att_id}\"" : '';
		$parts[]   = "<!-- wp:image {{$id_json}\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n"
		           . "<figure class=\"wp-block-image size-large\">"
		           . "<img src=\"" . esc_url( $img_url ) . "\" alt=\"\"{$img_class}/>"
		           . "</figure>\n<!-- /wp:image -->";
	}

	$btn = momentive_pm_btn( $btn_text, $btn_url, $btn_new_tab, false ); // plain button
	if ( '' !== $btn ) {
		$parts[] = momentive_pm_buttons( $btn );
	}

	if ( empty( $parts ) ) return '';

	// Derive modifier from what's actually present — don't claim with-image if sideload failed.
	$has_btn  = '' !== trim( $btn_text ) && '' !== trim( $btn_url );
	$modifier = trim(
		( $has_header ? 'with-heading ' : '' ) .
		( $has_image  ? 'with-image'    : ( $has_btn ? 'with-button' : '' ) )
	);

	return momentive_pm_highlight( $modifier, implode( "\n\n", $parts ) );
}

/**
 * Tip highlight block (templates 1467–1475).
 *
 * $icon_raw: legacy slug with box- prefix (e.g. 'box-bxs-book-bookmark');
 *            the prefix is stripped to produce the sprite slug.
 *
 * Modifier logic:
 *   icon present  → with-icon   (icon + optional heading + blurb desc)
 *   no icon       → plain       (optional heading + plain desc paragraphs)
 *
 * Returns '' when desc is empty.
 */
function momentive_pm_tip_block( string $icon_raw, string $title, string $desc ): string {
	$desc  = trim( $desc );
	$title = trim( $title );
	if ( '' === $desc ) return '';

	// Strip the legacy 'box-' prefix from the icon slug.
	$icon = preg_replace( '/^box-/', '', trim( $icon_raw ) );

	$has_icon  = '' !== $icon;
	$has_title = '' !== $title;
	$parts     = [];

	if ( $has_icon ) {
		$parts[] = "<!-- wp:momentive/icon-block {\"iconId\":\"" . esc_attr( $icon ) . "\",\"className\":\"inline-block\"} /-->";
	}

	if ( $has_title ) {
		$parts[] = momentive_pm_p( esc_html( $title ), 'heading' );
	}

	// First desc paragraph gets 'blurb' class for with-icon; plain otherwise.
	$desc_blocks = momentive_pm_desc_blocks( $desc, $has_icon ? 'blurb' : '' );
	array_push( $parts, ...$desc_blocks );

	if ( empty( $parts ) ) return '';

	$modifier = $has_icon ? 'with-icon' : '';
	return momentive_pm_highlight( $modifier, implode( "\n\n", $parts ) );
}

/**
 * Checklist block (template 8984).
 * Parses the PHP-serialized checklist_1 meta value (a:N:{s:6:"item-N";a:1:{s:5:"label";s:N:"...";}...}).
 * Returns '' when the serialized string is empty or contains no items.
 */
function momentive_pm_checklist_block( string $serialized ): string {
	if ( '' === trim( $serialized ) ) return '';

	$data = @unserialize( $serialized );
	if ( ! is_array( $data ) || empty( $data ) ) return '';

	$items = '';
	foreach ( $data as $row ) {
		$label = trim( (string) ( $row['label'] ?? '' ) );
		if ( '' === $label ) continue;
		$items .= "<!-- wp:list-item -->\n<li>" . esc_html( $label ) . "</li>\n<!-- /wp:list-item -->\n";
	}

	if ( '' === $items ) return '';

	return "<!-- wp:list {\"className\":\"is-style-checkboxes\"} -->\n"
	     . "<ul class=\"wp-block-list is-style-checkboxes\">{$items}</ul>\n"
	     . "<!-- /wp:list -->";
}

/**
 * Resource CTA block (resource_cta_* meta fields).
 * Emits is-style-highlight with-heading with-button.
 * Uses plain button style (consistent with hand-rebuilt posts).
 * Returns '' when title or button fields are incomplete.
 */
function momentive_pm_resource_cta_block(
	string $title,
	string $btn_text,
	string $btn_url,
	bool   $btn_new_tab
): string {
	$title    = trim( wp_strip_all_tags( $title ) );
	$btn_text = trim( $btn_text );
	$btn_url  = trim( $btn_url );

	if ( '' === $title || '' === $btn_text || '' === $btn_url ) return '';

	$btn   = momentive_pm_btn( $btn_text, $btn_url, $btn_new_tab, false );
	$inner = momentive_pm_p( $title, 'heading' ) . "\n\n" . momentive_pm_buttons( $btn );

	return momentive_pm_highlight( 'with-heading with-button', $inner );
}

/**
 * Prefooter block (cta_-_* meta fields).
 *
 * Emits the full-width rings CTA placed at the end of post_content.
 * moveBlogPrefooter() in momentive.js relocates it before .site-footer.
 * CSS hides .wp-block-post-content .prefooter until JS moves it.
 *
 * Heading gets class="no-toc" so the TOC block ignores it.
 * Supports 1 or 2 buttons (both use 'has-arrow upward' style).
 * Returns '' when title is empty.
 */
function momentive_pm_prefooter_block(
	string $title,
	string $desc,
	string $btn1_text,
	string $btn1_url,
	bool   $btn1_new_tab,
	string $btn2_text  = '',
	string $btn2_url   = '',
	bool   $btn2_new_tab = false
): string {
	$title = trim( $title );
	if ( '' === $title ) return '';

	$heading = "<!-- wp:heading {\"className\":\"no-toc\","
	         . "\"style\":{\"typography\":{\"textAlign\":\"center\"},"
	         . "\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|medium\"}}},"
	         . "\"fontSize\":\"xl\"} -->\n"
	         . "<h2 class=\"wp-block-heading has-text-align-center no-toc has-xl-font-size\""
	         . " style=\"padding-top:var(--wp--preset--spacing--medium)\">"
	         . esc_html( $title ) . "</h2>\n"
	         . "<!-- /wp:heading -->";

	$parts = [ $heading ];

	$desc = trim( $desc );
	if ( '' !== $desc ) {
		$parts[] = "<!-- wp:paragraph {\"style\":{\"typography\":{\"textAlign\":\"center\"}}} -->\n"
		         . "<p class=\"has-text-align-center\">" . esc_html( $desc ) . "</p>\n"
		         . "<!-- /wp:paragraph -->";
	}

	$b1   = momentive_pm_btn( $btn1_text, $btn1_url, $btn1_new_tab, true );
	$b2   = momentive_pm_btn( $btn2_text, $btn2_url, $btn2_new_tab, true );
	$btns = momentive_pm_buttons( $b1, $b2 );
	if ( '' !== $btns ) {
		$parts[] = $btns;
	}

	$inner = implode( "\n\n", $parts );

	return "<!-- wp:group {\"className\":\"prefooter is-style-bg-rings\",\"layout\":{\"type\":\"default\"}} -->\n"
	     . "<div class=\"wp-block-group prefooter is-style-bg-rings\">\n{$inner}\n</div>\n"
	     . "<!-- /wp:group -->";
}

/* =========================================================================
 * Template ID → block
 * ====================================================================== */

/**
 * Convert a single Elementor template ID to its Gutenberg block replacement.
 *
 * $att_map: legacy attachment ID → fetchable URL (for CTA image sideloads).
 * Returns '' for unknown/disabled templates (shortcode silently removed).
 */
function momentive_pm_tpl_block(
	int    $tpl_id,
	array  $meta,
	array  $att_map,
	bool   $dry,
	int    $parent_post_id = 0
): string {
	$tpl = MOMENTIVE_PM_TPL[ $tpl_id ] ?? null;
	if ( null === $tpl ) {
		WP_CLI::warning( "  Unknown template ID {$tpl_id} — shortcode removed." );
		return '';
	}

	[ $type, $slot ] = $tpl;
	// Slot 2 uses '_2' suffix; slot 1 has no suffix.
	$s = ( 2 === $slot ) ? '_2' : '';

	switch ( $type ) {

		/* ── remove ────────────────────────────────────────────── */
		case 'remove':
			return '';

		/* ── CTA (1458 / 1526) ─────────────────────────────────── */
		case 'cta':
			if ( ! momentive_pm_truthy( $meta[ "sc_cta_-_enable_cta_section{$s}" ] ?? '' ) ) return '';

			return momentive_pm_cta_block(
				$meta[ "sc_cta_-_header{$s}" ]                 ?? '',
				$meta[ "sc_cta_-_description{$s}" ]            ?? '',
				$meta[ "sc_cta_-_button_text{$s}" ]            ?? '',
				$meta[ "sc_cta_-_button_url{$s}" ]             ?? '',
				momentive_pm_truthy( $meta[ "sc_cta_-_button_open_in_new_tab{$s}" ] ?? '' )
			);

		/* ── CTA with image (1464 / 1527) ─────────────────────── */
		case 'cta_image':
			if ( ! momentive_pm_truthy( $meta[ "sc_cta_with_image_-_enable_cta_with_image_section{$s}" ] ?? '' ) ) return '';

			$legacy_img_id = (int) ( $meta[ "sc_cta_with_image_-_image{$s}" ] ?? 0 );
			$img_url       = '';
			$new_att_id    = 0;

			if ( $legacy_img_id > 0 ) {
				$img_url = $att_map[ $legacy_img_id ] ?? '';
				if ( '' !== $img_url ) {
					if ( $dry ) {
						WP_CLI::log( "    [dry] would sideload cta-image: {$img_url}" );
					} else {
						$new_att_id = momentive_pm_sideload( $img_url, $parent_post_id, false );
						if ( $new_att_id > 0 ) {
							$img_url = wp_get_attachment_url( $new_att_id ) ?: $img_url;
						}
					}
				} else {
					WP_CLI::warning( "    cta-image legacy ID {$legacy_img_id} not in attachment map." );
				}
			}

			return momentive_pm_cta_image_block(
				$meta[ "sc_cta_with_image_-_header{$s}" ]      ?? '',
				$meta[ "sc_cta_with_image_-_description{$s}" ] ?? '',
				$new_att_id,
				$img_url,
				$meta[ "sc_cta_with_image_-_button_text{$s}" ] ?? '',
				$meta[ "sc_cta_with_image_-_button_url{$s}" ]  ?? '',
				momentive_pm_truthy( $meta[ "sc_cta_with_image_-_button_open_in_new_tab{$s}" ] ?? '' )
			);

		/* ── Tip (1467–1475) ────────────────────────────────────── */
		// NOTE: this case is never reached. Tips are handled positionally in
		// momentive_pm_replace_shortcodes() before momentive_pm_tpl_block() is called.
		case 'tip':
			return '';

		/* ── Checklist (8984) ────────────────────────────────────── */
		case 'checklist':
			if ( ! momentive_pm_truthy( $meta['enable_checklist_1'] ?? '' ) ) return '';
			return momentive_pm_checklist_block( $meta['checklist_1'] ?? '' );

		default:
			return '';
	}
}

/* =========================================================================
 * Shortcode replacement
 * ====================================================================== */

/**
 * Replace all Elementor shortcodes in post_content with block markup.
 *
 * Handles three wrapping patterns found in the legacy WXR:
 *   1. Standard:   <!-- wp:shortcode -->[elementor-template id="X"]<!-- /wp:shortcode -->
 *   2. With attrs: <!-- wp:shortcode {"jetDynamicVisibility":...} -->[elementor-template ...]<!-- /wp:shortcode -->
 *   3. In list-item or similar: [elementor-template id="X"] appearing inline inside
 *      existing block HTML (e.g. inside a <li> tag). These are stripped in a final pass.
 *
 * Also handles a stray leading period before the shortcode (one known post):
 *   <!-- wp:shortcode -->.[elementor-template id="X"]<!-- /wp:shortcode -->
 *
 * The replacement block is returned directly (no extra wrapper). Empty string
 * means the section was disabled or the template is of type 'remove'.
 */
function momentive_pm_replace_shortcodes(
	string $content,
	array  $meta,
	array  $att_map,
	bool   $dry,
	int    $parent_post_id = 0
): string {
	// Pre-build ordered list of enabled tip slot numbers (1–9).
	// The N-th enabled slot corresponds to the N-th tip template in content order.
	$enabled_tips = [];
	for ( $n = 1; $n <= 9; $n++ ) {
		if ( momentive_pm_truthy( $meta[ "sc_tip_{$n}" ] ?? '' ) ) {
			$enabled_tips[] = $n;
		}
	}
	$tip_cursor = 0;

	// All tip template IDs — interchangeable; slot resolved positionally via cursor.
	$tip_tpl_ids = [ 1467, 1468, 1469, 1470, 1471, 1472, 1473, 1474, 1475 ];

	$callback = function ( array $m ) use (
		$meta, $att_map, $dry, $parent_post_id, $enabled_tips, $tip_tpl_ids, &$tip_cursor
	): string {
		$tpl_id = (int) $m[1];

		// Tip templates: N-th occurrence → N-th enabled sc_tip_N slot (positional).
		if ( in_array( $tpl_id, $tip_tpl_ids, true ) ) {
			if ( ! isset( $enabled_tips[ $tip_cursor ] ) ) {
				// Only warn when some slots were enabled but the templates outnumber them.
				// Empty $enabled_tips means all sc_tip_N flags are false (tip section was
				// never populated on this post) — strip silently, same as any disabled block.
				if ( ! empty( $enabled_tips ) ) {
					WP_CLI::warning( "    More tip templates than enabled tip slots (template {$tpl_id}) — stripped." );
				}
				$tip_cursor++;
				return '';
			}
			$n = $enabled_tips[ $tip_cursor++ ];
			return momentive_pm_tip_block(
				$meta[ "sc_tip_{$n}_icon" ]        ?? '',
				$meta[ "sc_tip_{$n}_title" ]       ?? '',
				$meta[ "sc_tip_{$n}_description" ] ?? ''
			);
		}

		return momentive_pm_tpl_block( $tpl_id, $meta, $att_map, $dry, $parent_post_id );
	};

	// Pattern 1: wp:shortcode block (with or without JSON attributes; optional leading .)
	$content = preg_replace_callback(
		'#<!-- wp:shortcode(?:[^>]*)? -->\s*\.?\[elementor-template\s+id="?(\d+)"?\]\s*<!-- /wp:shortcode -->#s',
		$callback,
		$content
	);

	// Pattern 2: wp:paragraph wrapper (fallback)
	$content = preg_replace_callback(
		'#<!-- wp:paragraph -->\s*<p>\[elementor-template\s+id="?(\d+)"?\]</p>\s*<!-- /wp:paragraph -->#s',
		$callback,
		$content
	);

	// Pattern 3: shortcode embedded inline in block HTML (e.g. inside a <li>).
	// Can't insert a block mid-element; strip the shortcode and log what was lost.
	// Also strip a bare <br> immediately before it (legacy editor artifact).
	$content = preg_replace_callback(
		'#(?:<br\s*/?>)?\[elementor-template\s+id="?(\d+)"?\]#i',
		function ( array $m ) use ( $meta, $enabled_tips, $tip_tpl_ids, &$tip_cursor ): string {
			$tpl_id = (int) $m[1];

			if ( in_array( $tpl_id, $tip_tpl_ids, true ) && isset( $enabled_tips[ $tip_cursor ] ) ) {
				$n     = $enabled_tips[ $tip_cursor++ ];
				$icon  = $meta[ "sc_tip_{$n}_icon" ]        ?? '';
				$title = trim( $meta[ "sc_tip_{$n}_title" ] ?? '' );
				$desc  = trim( wp_strip_all_tags( $meta[ "sc_tip_{$n}_description" ] ?? '' ) );
				WP_CLI::warning( sprintf(
					'    Inline tip shortcode (template %d → sc_tip_%d) stripped from mid-element; add manually:',
					$tpl_id, $n
				) );
				if ( '' !== $title ) WP_CLI::warning( "      title: {$title}" );
				if ( '' !== $desc )  WP_CLI::warning( "      desc:  {$desc}" );
				if ( '' !== $icon )  WP_CLI::warning( "      icon:  {$icon}" );
			} else {
				WP_CLI::warning( sprintf(
					'    Inline shortcode [elementor-template id="%d"] stripped from mid-element.',
					$tpl_id
				) );
			}
			return '';
		},
		$content
	);

	return $content;
}

/* =========================================================================
 * Media: attachment map + sideload
 * ====================================================================== */

/**
 * Build legacy attachment ID → fetchable URL map from the WXR.
 * Reads _wp_attached_file postmeta from attachment items.
 */
function momentive_pm_build_att_map( string $path, string $base ): array {
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "WXR not found at {$path}; CTA image sideloading disabled." );
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

/**
 * Sideload a URL into the rebuilt media library. Deduped by _momentive_source_url.
 * Returns new attachment ID, or 0 on failure / dry-run.
 * Mirrors momentive_wm_sideload() from migrate-webinars.php.
 */
function momentive_pm_sideload( string $url, int $parent_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) return 0;

	// Check for existing import.
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

	$file   = [ 'name' => basename( parse_url( $url, PHP_URL_PATH ) ), 'tmp_name' => $tmp ];
	$ext    = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	$is_svg = in_array( $ext, [ 'svg', 'svgz' ], true );
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
 * rank-math/faq-block → momentive/accordion converter
 * ====================================================================== */

/**
 * Convert a single rank-math/faq-block (full block comment, open+close) to a
 * momentive/accordion self-closing block.
 *
 * All data comes from the JSON attrs in the opening comment. The HTML between
 * the block comments is the block's legacy server-side render — we discard it
 * and regenerate from the structured questions array.
 *
 * Answer transformation:
 *   <br><br> → <br>   (double breaks become single)
 *   Entire answer wrapped in <p>…</p>
 *   Inline markup (<strong>, <a>, etc.) kept verbatim.
 *
 * Items with visible:false are skipped.
 */
function momentive_pm_faq_to_accordion( string $block ): string {
	if ( ! preg_match( '#<!-- wp:rank-math/faq-block (\{.*?\}) -->#s', $block, $m ) ) {
		WP_CLI::warning( '    rank-math/faq-block: could not parse JSON attrs — block left as-is.' );
		return $block;
	}

	$attrs = json_decode( $m[1], true );
	if ( ! is_array( $attrs ) || empty( $attrs['questions'] ) ) return '';

	$items = [];
	foreach ( $attrs['questions'] as $q ) {
		if ( isset( $q['visible'] ) && ! $q['visible'] ) continue;

		$answer = $q['content'] ?? '';
		// Collapse double (or multiple) <br> sequences into a single <br>.
		$answer = preg_replace( '#(?:<br\s*/?>)\s*(?:<br\s*/?>)+#i', '<br>', $answer );
		$answer = '<p>' . trim( $answer ) . '</p>';

		$items[] = [
			'_key'     => substr( md5( uniqid( '', true ) ), 0, 7 ),
			'question' => $q['title'] ?? '',
			'answer'   => $answer,
			'iconSlug' => '',
			'category' => '',
		];
	}

	if ( empty( $items ) ) return '';

	$json = json_encode(
		[ 'items' => $items ],
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return "<!-- wp:momentive/accordion {$json} /-->";
}

/* =========================================================================
 * Blog article scaffold
 * ====================================================================== */

/**
 * Wrap post body content in the blog-article-content.php pattern structure.
 *
 * Mirrors patterns/blog-article-content.php exactly. The $body (post body
 * blocks + any appended resource_cta) goes inside the post-content column
 * after the byline. The prefooter is NOT included here — it is appended
 * after this scaffold so it sits outside the two-column layout.
 */
function momentive_pm_blog_scaffold( string $body ): string {
	return <<<EOL
<!-- wp:group {"className":"entry-header","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group entry-header alignfull">

	<!-- wp:group {"className":"header-inner"} -->
	<div class="wp-block-group header-inner">

		<!-- wp:group {"className":"header-media"} -->
		<div class="wp-block-group header-media">
			<!-- wp:post-featured-image {"isLink":false} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"header-content"} -->
		<div class="wp-block-group header-content">

			<!-- wp:momentive/breadcrumbs {"lock":{"move":true,"remove":true}} /-->

			<!-- wp:post-title {"level":1,"lock":{"move":true,"remove":true}} /-->

			<!-- wp:post-terms {"term":"category","separator":"","className":"taxonomy-category lower-label","lock":{"move":true,"remove":true}} /-->

			<!-- wp:momentive/post-cta-button /-->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->

<!-- wp:columns {"className":"post-layout","isStackedOnMobile":false} -->
<div class="wp-block-columns post-layout is-not-stacked-on-mobile">

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
 * Author assignment
 * ====================================================================== */

/**
 * Set the post_author_ref ACF field by matching ppma_authors_name to a
 * People CPT post title. Warns when no match is found; no-ops on empty name.
 */
function momentive_pm_set_author( int $post_id, string $author_name ): void {
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
 * WXR parser
 * ====================================================================== */

/**
 * Parse all published/draft `post` items from the WXR.
 * Returns array of post arrays keyed with slug, title, status, dates,
 * excerpt, content, and a flat meta array.
 */
function momentive_pm_parse_wxr( string $path ): array {
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "WXR not found: {$path}" );
	}

	$xml   = file_get_contents( $path );
	$posts = [];

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		return $posts;
	}

	foreach ( $items[1] as $raw ) {
		// Must be post_type = 'post'
		if ( false === strpos( $raw, '<wp:post_type><![CDATA[post]]></wp:post_type>' ) &&
		     false === strpos( $raw, '<wp:post_type>post</wp:post_type>' ) ) {
			continue;
		}

		$status = momentive_pm_xml_tag( $raw, 'wp:status' );
		if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) continue;

		// Build flat meta map
		$meta = [];
		if ( preg_match_all( '#<wp:postmeta>(.*?)</wp:postmeta>#s', $raw, $pm ) ) {
			foreach ( $pm[1] as $m ) {
				$k = momentive_pm_xml_tag( $m, 'wp:meta_key' );
				$v = momentive_pm_xml_tag( $m, 'wp:meta_value' );
				if ( '' !== $k ) $meta[ $k ] = $v;
			}
		}

		// Category slugs from per-post <category domain="category" nicename="…"> elements.
		$cat_slugs = [];
		if ( preg_match_all( '#<category domain="category" nicename="([^"]+)">#', $raw, $cm ) ) {
			$cat_slugs = $cm[1];
		}

		$posts[] = [
			'slug'              => momentive_pm_xml_tag( $raw, 'wp:post_name' ),
			'title'             => momentive_pm_xml_tag( $raw, 'title' ),
			'status'            => $status,
			'post_date'         => momentive_pm_xml_tag( $raw, 'wp:post_date' ),
			'post_date_gmt'     => momentive_pm_xml_tag( $raw, 'wp:post_date_gmt' ),
			'post_modified'     => momentive_pm_xml_tag( $raw, 'wp:post_modified' ),
			'post_modified_gmt' => momentive_pm_xml_tag( $raw, 'wp:post_modified_gmt' ),
			'excerpt'           => momentive_pm_xml_tag( $raw, 'excerpt:encoded' ),
			'content'           => momentive_pm_xml_tag( $raw, 'content:encoded' ),
			'meta'              => $meta,
			'category_slugs'    => $cat_slugs,
			'author_name'       => trim( $meta['ppma_authors_name'] ?? '' ),
		];
	}

	return $posts;
}

/* =========================================================================
 * Main post migrator
 * ====================================================================== */

/**
 * Migrate (or update) a single blog post.
 * Returns [ 'status' => 'created'|'updated'|'dry'|'error', 'id' => post_id ].
 */
function momentive_pm_migrate_post( array $post, array $att_map, bool $dry ): array {
	global $wpdb;

	$slug      = $post['slug'];
	$meta      = $post['meta'];
	$content   = $post['content'];

	WP_CLI::log( "  [{$post['status']}] {$slug}" );

	// ── 1. Strip Word cruft from content ─────────────────────────────────
	$content = momentive_pm_strip_word( $content );

	// ── 2. Convert rank-math/faq-block → momentive/accordion ─────────────
	$faq_count = preg_match_all( '#<!-- wp:rank-math/faq-block #', $content );
	if ( $faq_count > 0 ) {
		$content = preg_replace_callback(
			'#<!-- wp:rank-math/faq-block \{.*?\} -->.*?<!-- /wp:rank-math/faq-block -->#s',
			fn( $m ) => momentive_pm_faq_to_accordion( $m[0] ),
			$content
		);
		WP_CLI::log( "    faq→accordion: {$faq_count} block(s) converted" );
	}

	// ── 3. Count Elementor shortcodes before replacement ─────────────────
	$sc_before = preg_match_all( '#\[elementor-template\s+id=#', $content );

	// ── 4. Resolve existing post ID (for image parent and upsert) ────────
	$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' LIMIT 1",
		$slug
	) );

	// Use existing ID as image parent so media is correctly associated.
	$parent_id = $existing_id;

	// ── 5. Replace Elementor shortcodes ──────────────────────────────────
	$content = momentive_pm_replace_shortcodes( $content, $meta, $att_map, $dry, $parent_id );

	$sc_after = preg_match_all( '#\[elementor-template\s+id=#', $content );
	if ( $sc_before > 0 ) {
		WP_CLI::log( sprintf( '    shortcodes: %d replaced; %d remaining', $sc_before - $sc_after, $sc_after ) );
	}

	// ── 6. Append resource_cta block (inside body, before scaffold wraps it) ─
	if ( momentive_pm_truthy( $meta['resource_cta_enable_cta'] ?? '' ) ) {
		$rc = momentive_pm_resource_cta_block(
			$meta['resource_cta_ttitle']              ?? '',
			$meta['resource_cta_button_text']         ?? '',
			$meta['resource_cta_button_url']          ?? '',
			momentive_pm_truthy( $meta['resource_cta_button_link_outbound'] ?? '' )
		);
		if ( '' !== $rc ) {
			$content .= "\n\n" . $rc;
			WP_CLI::log( '    + resource_cta block' );
		}
	}

	// ── 7. Wrap body in blog-article-content scaffold ─────────────────────
	// Hero + two-column layout. resource_cta is inside post-content column.
	// Prefooter is appended AFTER the scaffold so it sits outside the columns
	// (moveBlogPrefooter() in momentive.js relocates it before .site-footer).
	$content = momentive_pm_blog_scaffold( $content );

	// ── 8. Append prefooter block (outside scaffold columns) ──────────────
	if ( momentive_pm_truthy( $meta['cta_-_enable_cta_section'] ?? '' ) ) {
		$pf = momentive_pm_prefooter_block(
			$meta['cta_-_title']                             ?? '',
			$meta['cta_-_description']                       ?? '',
			$meta['cta_-_button_1_text']                     ?? '',
			$meta['cta_-_button_1_link']                     ?? '',
			momentive_pm_truthy( $meta['cta_-_button_1_-_open_in_new_tab'] ?? '' ),
			$meta['cta_-_button_2_text']                     ?? '',
			$meta['cta_-_button_2_link']                     ?? '',
			momentive_pm_truthy( $meta['cta_-_button_2_-_open_in_new_tab'] ?? '' )
		);
		if ( '' !== $pf ) {
			$content .= "\n\n" . $pf;
			WP_CLI::log( '    + prefooter block' );
		}
	}

	// ── 9. Dry-run exit ───────────────────────────────────────────────────
	if ( $dry ) {
		return [ 'status' => 'dry', 'id' => $existing_id ];
	}

	// ── 10. Write post ───────────────────────────────────────────────────
	$post_data = [
		'post_title'    => $post['title'],
		'post_name'     => $slug,
		'post_status'   => $post['status'],
		'post_type'     => 'post',
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
		WP_CLI::warning( "    FAILED: " . $new_id->get_error_message() );
		return [ 'status' => 'error', 'id' => 0 ];
	}

	// Restore original modified date (wp_update_post forces it to NOW).
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

	// Stamp migration run.
	update_post_meta( $new_id, MOMENTIVE_PM_RUN_META, momentive_pm_run_id() );

	// ── 13. Featured image ────────────────────────────────────────────────
	$legacy_thumb = (int) ( $meta['_thumbnail_id'] ?? 0 );
	if ( $legacy_thumb > 0 ) {
		$thumb_url = $att_map[ $legacy_thumb ] ?? '';
		if ( '' !== $thumb_url ) {
			$thumb_id = momentive_pm_sideload( $thumb_url, $new_id, false );
			if ( $thumb_id > 0 ) {
				set_post_thumbnail( $new_id, $thumb_id );
			}
		} else {
			WP_CLI::warning( "    thumbnail legacy ID {$legacy_thumb} not in attachment map." );
		}
	}

	// ── 14. Categories ───────────────────────────────────────────────────
	// Map legacy category slugs → rebuilt term IDs. Slug lookup is safe
	// across migrations because slugs are stable; IDs are not.
	$cat_ids = [];
	foreach ( $post['category_slugs'] as $cat_slug ) {
		$term = get_term_by( 'slug', $cat_slug, 'category' );
		if ( $term && ! is_wp_error( $term ) ) {
			$cat_ids[] = $term->term_id;
		} else {
			WP_CLI::warning( "    Category '{$cat_slug}' not found in rebuilt site — skipped." );
		}
	}
	if ( ! empty( $cat_ids ) ) {
		wp_set_post_categories( $new_id, $cat_ids, false );
	}

	// ── 15. Author (post_author_ref) ─────────────────────────────────────
	momentive_pm_set_author( $new_id, $post['author_name'] );

	// ── 16. Old slug (redirect preservation) + breadcrumb title ─────────
	// _wp_old_slug: WordPress's native mechanism for redirecting changed slugs.
	// Migrating it means the rebuilt site automatically 301s any external links
	// that pointed to old URLs — no redirect plugin needed.
	$old_slug = trim( $meta['_wp_old_slug'] ?? '' );
	if ( '' !== $old_slug ) {
		update_post_meta( $new_id, '_wp_old_slug', $old_slug );
	}
	// custom_breadcrumb_title (legacy field name) → breadcrumb_title (ACF field).
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

// Build attachment ID → URL map from the posts WXR, then supplement from the
// media WXR if present (covers standalone CTA images not parented to a post).
$pm_att_map = momentive_pm_build_att_map( $pm_wxr_path, $pm_uploads_base );
if ( file_exists( $pm_media_wxr_path ) ) {
	$pm_media_map = momentive_pm_build_att_map( $pm_media_wxr_path, $pm_uploads_base );
	$added        = count( array_diff_key( $pm_media_map, $pm_att_map ) );
	$pm_att_map  += $pm_media_map; // fill gaps without overwriting
	WP_CLI::log( "Media WXR: {$added} additional attachment IDs added." );
} else {
	WP_CLI::log( "No supplementary media WXR found at: {$pm_media_wxr_path}" );
	WP_CLI::log( '  → CTA images not in the posts export will be skipped.' );
	WP_CLI::log( '  → Export Media from the legacy site and place it next to this script to fix.' );
}

// Parse posts from WXR.
$all_posts = momentive_pm_parse_wxr( $pm_wxr_path );
WP_CLI::log( sprintf( 'Found %d published/draft posts in WXR.', count( $all_posts ) ) );

// Apply filters.
if ( '' !== $only_slug ) {
	$all_posts = array_values( array_filter( $all_posts, fn( $p ) => $p['slug'] === $only_slug ) );
	WP_CLI::log( sprintf( 'Filtered to %d post(s) matching slug "%s".', count( $all_posts ), $only_slug ) );
}
if ( $limit > 0 ) {
	$all_posts = array_slice( $all_posts, 0, $limit );
}

// Migrate.
$counts = [ 'created' => 0, 'updated' => 0, 'dry' => 0, 'error' => 0 ];

foreach ( $all_posts as $post ) {
	$result = momentive_pm_migrate_post( $post, $pm_att_map, $dry );
	$counts[ $result['status'] ] = ( $counts[ $result['status'] ] ?? 0 ) + 1;
}

WP_CLI::log( '─────────────────────────────────────────────────────────────' );
WP_CLI::log( sprintf(
	'Done. created=%d  updated=%d  dry=%d  errors=%d',
	$counts['created'] ?? 0,
	$counts['updated'] ?? 0,
	$counts['dry']     ?? 0,
	$counts['error']   ?? 0
) );

if ( $dry ) {
	WP_CLI::log( 'Re-run with "live" to write. Use "limit=N" for a partial test.' );
}
