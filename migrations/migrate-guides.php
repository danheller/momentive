<?php
/**
 * migrate-guides.php
 *
 * WP-CLI migration script: legacy `guides` CPT → rebuilt `guide` CPT.
 *
 * SCOPE — `guides` subtype only (17 of 25 legacy posts). This script does
 * NOT touch `research-study` posts. All 5 published research-study posts
 * are already hand-built (bespoke insight/stat sections, previous-studies
 * grids, webinar CTA bands — see notes/guide-reference-sheet.md, which
 * concluded that shape needs its own small project rather than a templated
 * migration). The remaining 3 research-study items are draft preview stubs
 * with no settled fate (Daniel's call per the reference sheet). Any legacy
 * post whose `guide_type` meta isn't exactly "guides" is skipped and logged,
 * never written.
 *
 * Reads from a single WXR export file (guides posts + attachments), same
 * pattern as the whitepaper/infographic migrations.
 *
 * Run (from the theme/migrations directory):
 *
 *   wp eval-file migrations/migrate-guides.php --user=<admin>
 *     → dry run (default); shows what would be written
 *
 *   wp eval-file migrations/migrate-guides.php live --user=<admin>
 *     → writes posts, sideloads media, sets ACF fields
 *
 *   wp eval-file migrations/migrate-guides.php live limit=10 --user=<admin>
 *     → first 10 legacy guides posts only
 *
 *   wp eval-file migrations/migrate-guides.php live only=silent-auction --user=<admin>
 *     → single post by legacy slug
 *
 * --user=<admin> is REQUIRED because Safe SVG gates SVG uploads on user
 * capability; WP-CLI has no user by default so hero-image sideloads fail.
 *
 * Overridable constants (define before running via env or a wrapper):
 *   MOMENTIVE_GDE_LEGACY_WXR   — path to legacy guides export
 *   MOMENTIVE_GDE_MEDIA_WXR    — path to a general media-library export, used
 *                                only to fill in attachment IDs the guides
 *                                export itself doesn't include (optional;
 *                                defaults to momentivesoftware.media.current.2026-09-01.xml
 *                                next to this script)
 *   MOMENTIVE_GDE_UPLOADS_BASE — base URL for resolving attachment IDs
 *
 * Idempotent: upserts by slug, so re-running updates in place. Since the
 * script only ever touches guide_type=guides posts, it can never clobber
 * the 5 hand-built research-study posts even if re-run without `only=`/
 * `limit=` filters.
 * Rollback: new posts are stamped with _momentive_migration_run; a pre-run
 *           DB backup is the cleanest restore path.
 *
 * Page structure (per the 5 hand-rebuilt `guides` reference posts) — nearly
 * identical to the whitepaper migration; field names on this subtype match
 * whitepapers' almost exactly (see notes/guide-reference-sheet.md's field
 * map), so this script reuses that one's logic directly. Two things that
 * are genuinely new here:
 *
 *   - `custom_header` → `hero_title` ACF field. Optional on-page H1
 *     override, populated on both subtypes in the legacy data but only
 *     relevant to write when it differs from the post title — several
 *     posts have it set to the same text as the title (no real override),
 *     and it should be left blank there.
 *   - `cta_-_enable_cta_section` / `cta_-_title` / `cta_-_description` /
 *     `cta_-_button_1_*` / `cta_-_button_2_*` → a full-width 2-button
 *     "looking for more?" band (`.prefooter.is-style-bg-rings`), appended
 *     after everything else. This field group exists on whitepapers too
 *     but is always empty there (a dead field in that migration); here
 *     it's genuinely populated (1/17 posts in the reference set). It's
 *     independent of the old-style insights section — both can be present
 *     on the same post, and its presence doesn't change where
 *     `momentive/social-share` goes (see the insights section below).
 *
 * `resource_header_overline` is confirmed dead for this subtype (checked
 * against the live legacy site, not just the export — see the reference
 * sheet) and is deliberately NOT migrated, regardless of value.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-guides.php [live] [limit=N] [only=slug] --user=<admin>' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_GDE_CPT      = 'guide';
const MOMENTIVE_GDE_LEGACY   = 'guides'; // post_type value in legacy WXR
const MOMENTIVE_GDE_RUN_META = '_momentive_migration_run';

// ACF field keys — Back Link block (group_6a44a4078d0f6, shared across CPTs)
const FK_GDE_BL_LABEL = 'field_6a44a408f79e0';
const FK_GDE_BL_URL   = 'field_6a44a420f79e1';

// ACF field keys — HubSpot form block (shared across CPTs)
const FK_GDE_HS_EMBED    = 'field_6a2873ba3bf87'; // hubspot_embed_code
const FK_GDE_HS_TWO_STEP = 'field_6a35626f3a11b'; // two_step

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function momentive_gde_run_id(): string {
	static $id = '';
	if ( '' === $id ) {
		$id = gmdate( 'Y-m-d H:i:s' );
	}
	return $id;
}

/**
 * Extract a CDATA-wrapped or plain child-tag value from an XML item string.
 */
function momentive_gde_xml_tag( string $item, string $tag ): string {
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s',
		$item, $m
	) ) {
		return $m[1];
	}
	if ( preg_match(
		'#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s',
		$item, $m
	) ) {
		return $m[1];
	}
	return '';
}

/**
 * Strip MS-Word span cruft from WYSIWYG HTML.
 * Removes any <span> that carries a Word fingerprint (data-contrast, data-ccp-*,
 * Word class tokens like NormalTextRun/SCXW/BCX/EOP). Keeps inner text and
 * <a> hyperlinks. Same logic as the whitepaper/webinar migrations.
 */
function momentive_gde_strip_word( string $html ): string {
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-props|ccp-charstyle)[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	$word_classes = 'NormalTextRun|TextRun|EOP|SpellingError|CommentStart|CommentEnd|SCXW\d+|BCX\d+|ContextualSpellingError';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*(?:' . $word_classes . ')[^"]*"[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	$html = preg_replace( '#<span\b(?:\s*)>(.*?)</span>#si', '$1', $html );

	return $html;
}

/* -------------------------------------------------------------------------
 * Block emitters
 * ---------------------------------------------------------------------- */

/** Wrap text in a wp:paragraph block. Returns '' when text is blank or nbsp-only. */
function momentive_gde_p_block( string $inner ): string {
	$inner = trim( $inner );
	if ( '' === $inner || '' === trim( html_entity_decode( $inner, ENT_HTML5, 'UTF-8' ) ) ) {
		return '';
	}
	return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
}

/** Wrap text in a wp:heading block. */
function momentive_gde_h_block( string $text, int $level = 2 ): string {
	$text = trim( wp_strip_all_tags( $text ) );
	if ( '' === $text ) {
		return '';
	}
	return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->";
}

/**
 * Convert legacy WYSIWYG HTML to an array of block markup strings.
 * Handles <p>, <h2>–<h6>, <ul>, <ol>, <blockquote>, <table>. Anything else
 * wraps in a paragraph. Skips blank and nbsp-only paragraphs.
 */
function momentive_gde_html_to_blocks( string $html ): array {
	$html = trim( $html );
	if ( '' === $html ) {
		return [];
	}

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>' );
	libxml_clear_errors();

	$root = $doc->getElementById( '__root__' );
	if ( ! $root ) {
		return [ momentive_gde_p_block( wp_strip_all_tags( $html ) ) ];
	}

	$blocks = [];

	foreach ( $root->childNodes as $node ) {
		if ( $node->nodeType === XML_TEXT_NODE ) {
			$text = trim( $node->textContent );
			if ( '' !== $text ) {
				$blocks[] = momentive_gde_p_block( esc_html( $text ) );
			}
			continue;
		}
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			continue;
		}

		$tag        = strtolower( $node->nodeName );
		$inner      = $doc->saveHTML( $node );
		$inner_html = preg_replace(
			'#^<' . preg_quote( $tag, '#' ) . '[^>]*>(.*)</' . preg_quote( $tag, '#' ) . '>$#si',
			'$1',
			trim( $inner )
		);

		switch ( $tag ) {
			case 'p':
				$text  = trim( $inner_html );
				$plain = trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $plain && '<br>' !== $text && '<br/>' !== $text ) {
					$blocks[] = momentive_gde_p_block( $text );
				}
				break;

			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) $tag[1];
				$plain = trim( wp_strip_all_tags( $inner_html ) );
				if ( '' !== $plain ) {
					$blocks[] = momentive_gde_h_block( $plain, $level );
				}
				break;

			case 'ul':
			case 'ol':
				$list_type  = ( 'ol' === $tag );
				$attrs      = $list_type ? ' {"ordered":true}' : '';
				$html_tag   = $list_type ? 'ol' : 'ul';
				$items_html = '';
				foreach ( $node->childNodes as $li ) {
					if ( $li->nodeType !== XML_ELEMENT_NODE || strtolower( $li->nodeName ) !== 'li' ) {
						continue;
					}
					$li_inner    = preg_replace( '#^<li[^>]*>(.*)</li>$#si', '$1', trim( $doc->saveHTML( $li ) ) );
					$items_html .= "<!-- wp:list-item -->\n<li>{$li_inner}</li>\n<!-- /wp:list-item -->\n";
				}
				if ( '' !== $items_html ) {
					$blocks[] = "<!-- wp:list{$attrs} -->\n<{$html_tag} class=\"wp-block-list\">{$items_html}</{$html_tag}>\n<!-- /wp:list -->";
				}
				break;

			case 'blockquote':
				$plain = trim( wp_strip_all_tags( $inner_html ) );
				if ( '' !== $plain ) {
					$blocks[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>" . esc_html( $plain ) . "</p></blockquote>\n<!-- /wp:quote -->";
				}
				break;

			case 'table':
				$blocks[] = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>{$inner_html}</table></figure>\n<!-- /wp:table -->";
				break;

			default:
				$plain = trim( wp_strip_all_tags( $inner_html ) );
				if ( '' !== $plain ) {
					$blocks[] = momentive_gde_p_block( esc_html( $plain ) );
				}
				break;
		}
	}

	return array_values( array_filter( $blocks ) );
}

/** back-link block pointing to /guides/ with full ACF data scaffold. */
function momentive_gde_back_link_block(): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/back-link',
		'data' => [
			'label'  => 'All research & guides',
			'_label' => FK_GDE_BL_LABEL,
			'url'    => '/guides/',
			'_url'   => FK_GDE_BL_URL,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/back-link {$attrs} /-->";
}

/**
 * HubSpot form block with embed code stored INLINE in block data.
 * Same field-key-direct format and wp_slash() requirement as the whitepaper
 * migration — see that script's docblock for the full explanation.
 */
function momentive_gde_hubspot_form_block( string $embed_code ): string {
	$embed_code = trim( $embed_code );
	if (
		'' !== $embed_code
		&& str_contains( $embed_code, 'hbspt.forms.create' )
		&& ! str_contains( $embed_code, 'js.hsforms.net' )
	) {
		$embed_code = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $embed_code;
	}

	$attrs = wp_json_encode( [
		'name' => 'acf/hubspot-form',
		'data' => [
			FK_GDE_HS_EMBED    => $embed_code,
			FK_GDE_HS_TWO_STEP => '0',
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP );
	return "<!-- wp:acf/hubspot-form {$attrs} /-->";
}

/**
 * Button block for additional resource links and not-gated download buttons.
 */
function momentive_gde_button_block( string $url, string $text, bool $new_tab, string $extra_class = '' ): string {
	$url  = esc_url( $url );
	$text = esc_html( $text );
	if ( '' === $url || '' === $text ) {
		return '';
	}

	$link_attrs = "href=\"{$url}\"";
	$btn_attrs  = [];
	if ( $new_tab ) {
		$link_attrs  .= ' target="_blank" rel="noreferrer noopener"';
		$btn_attrs[]  = '"linkTarget":"_blank"';
		$btn_attrs[]  = '"rel":"noreferrer noopener"';
	}
	if ( '' !== $extra_class ) {
		$btn_attrs[] = '"className":"' . $extra_class . '"';
	}

	$btn_json    = $btn_attrs ? ' {' . implode( ',', $btn_attrs ) . '}' : '';
	$div_classes = 'wp-block-button' . ( '' !== $extra_class ? " {$extra_class}" : '' );

	return "<!-- wp:button{$btn_json} -->\n"
		. "<div class=\"{$div_classes}\"><a class=\"wp-block-button__link wp-element-button\" {$link_attrs}>{$text}</a></div>\n"
		. '<!-- /wp:button -->';
}

/** Wraps one or two button blocks in a wp:buttons container. */
function momentive_gde_buttons_block( array $buttons ): string {
	$buttons = array_values( array_filter( $buttons ) );
	if ( empty( $buttons ) ) {
		return '';
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">" . implode( "\n\n", $buttons ) . "</div>\n<!-- /wp:buttons -->";
}

/**
 * Checklist block from the legacy resource_checklist repeater.
 * Each row has a `description` key; items become plain list items.
 */
function momentive_gde_checklist_block( array $items ): string {
	$list_items = '';
	foreach ( $items as $row ) {
		$text = trim( (string) ( $row['description'] ?? '' ) );
		if ( '' === $text ) {
			continue;
		}
		$list_items .= "<!-- wp:list-item -->\n<li>" . esc_html( $text ) . "</li>\n<!-- /wp:list-item -->\n";
	}
	if ( '' === $list_items ) {
		return '';
	}
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$list_items}</ul>\n<!-- /wp:list -->";
}

/**
 * Old-style insights section — a full-width superlight-accent group below
 * the columns, replacing the checklist. Identical shape to the whitepaper
 * migration's version (only 1/17 guides posts uses it). `momentive/social-share`
 * is always embedded inside this group when it's present — unconditionally,
 * not because it happens to be the last section on the page (a `cta_-_*`
 * band, when also present, is appended after this group and doesn't change
 * that placement — see momentive_gde_page() below).
 *
 * @param string $heading H2 text (content_title field; defaults to "What you'll learn").
 * @param array  $items   Unserialized insights_list rows ({insight_title, insight_description}).
 */
function momentive_gde_insights_block( string $heading, array $items ): string {
	if ( empty( $items ) ) {
		return '';
	}

	$list_items = '';
	foreach ( $items as $row ) {
		$title = trim( (string) ( $row['insight_title']       ?? '' ) );
		$desc  = trim( (string) ( $row['insight_description'] ?? '' ) );
		if ( '' === $title && '' === $desc ) {
			continue;
		}
		$li = '';
		if ( '' !== $title ) {
			$li .= '<strong>' . esc_html( $title ) . '</strong>';
		}
		if ( '' !== $desc ) {
			$li .= ( '' !== $title ? '<br>' : '' ) . esc_html( $desc );
		}
		$list_items .= "<!-- wp:list-item -->\n<li>{$li}</li>\n<!-- /wp:list-item -->\n";
	}

	if ( '' === $list_items ) {
		return '';
	}

	$heading = trim( $heading );
	if ( '' === $heading ) {
		$heading = "What you'll learn";
	}

	$inner = "<!-- wp:paragraph {\"className\":\"h2\",\"style\":{\"typography\":{\"textAlign\":\"center\"}}} -->\n"
		. "<p class=\"has-text-align-center h2\">" . esc_html( $heading ) . "</p>\n"
		. "<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:list {\"className\":\"is-style-column-checks two-columns\"} -->\n"
		. "<ul class=\"wp-block-list is-style-column-checks two-columns\">{$list_items}</ul>\n"
		. "<!-- /wp:list -->\n\n"
		. "<!-- wp:momentive/social-share /-->";

	return "<!-- wp:group {\"className\":\"to-edge\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|large\",\"bottom\":\"var:preset|spacing|large\"}}},\"backgroundColor\":\"superlight-accent\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group to-edge has-superlight-accent-background-color has-background\" style=\"padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)\">"
		. $inner
		. "</div>\n<!-- /wp:group -->";
}

/**
 * Full-width 2-button "looking for more?" CTA band (`cta_-_*` fields).
 *
 * NEW for this migration — the field group exists on whitepapers too but is
 * always empty there (a dead field in that migration). Here it's genuinely
 * populated (1/17 posts in the reference set: Campaign Planning Calendar).
 * Markup matches that hand-built post exactly: `.prefooter.is-style-bg-rings`
 * group, centered xl heading, centered paragraph, two buttons (first plain,
 * second `is-style-outline`). Independent of the insights section — both can
 * appear on the same post, always in that order (insights, then this band).
 *
 * @param string $title    cta_-_title.
 * @param string $desc     cta_-_description.
 * @param array  $button1  ['text' => ..., 'url' => ..., 'new_tab' => bool].
 * @param array  $button2  Same shape as $button1.
 */
function momentive_gde_cta_band_block( string $title, string $desc, array $button1, array $button2 ): string {
	$title = trim( $title );
	$desc  = trim( $desc );
	if ( '' === $title && '' === $desc ) {
		return '';
	}

	$parts = [];
	if ( '' !== $title ) {
		$parts[] = "<!-- wp:heading {\"style\":{\"typography\":{\"textAlign\":\"center\"},\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|medium\",\"bottom\":\"var:preset|spacing|x-small\"}}},\"fontSize\":\"xl\"} -->\n"
			. "<h2 class=\"wp-block-heading has-text-align-center has-xl-font-size\" style=\"padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--x-small)\">" . esc_html( $title ) . "</h2>\n"
			. '<!-- /wp:heading -->';
	}
	if ( '' !== $desc ) {
		$parts[] = "<!-- wp:paragraph {\"style\":{\"typography\":{\"textAlign\":\"center\"}}} -->\n"
			. '<p class="has-text-align-center">' . esc_html( $desc ) . "</p>\n"
			. '<!-- /wp:paragraph -->';
	}

	$btn1 = ( '' !== trim( (string) ( $button1['text'] ?? '' ) ) && '' !== trim( (string) ( $button1['url'] ?? '' ) ) )
		? momentive_gde_button_block( $button1['url'], $button1['text'], (bool) ( $button1['new_tab'] ?? false ) )
		: '';
	$btn2 = ( '' !== trim( (string) ( $button2['text'] ?? '' ) ) && '' !== trim( (string) ( $button2['url'] ?? '' ) ) )
		? momentive_gde_button_block( $button2['url'], $button2['text'], (bool) ( $button2['new_tab'] ?? false ), 'is-style-outline' )
		: '';

	$buttons = momentive_gde_buttons_block( [ $btn1, $btn2 ] );
	if ( '' !== $buttons ) {
		$parts[] = $buttons;
	}

	return "<!-- wp:group {\"className\":\"prefooter is-style-bg-rings\",\"layout\":{\"type\":\"default\"}} -->\n"
		. '<div class="wp-block-group prefooter is-style-bg-rings">'
		. implode( "\n\n", $parts )
		. "</div>\n<!-- /wp:group -->";
}

/**
 * Assemble the full guide page from left column, right column, an optional
 * insights block, and an optional CTA band.
 *
 * Social-share placement:
 *   - With insights section: social-share is inside the insights group.
 *   - Without insights:      social-share appears after the columns.
 * The CTA band (if any) always comes last, independent of the above.
 */
function momentive_gde_page(
	string $content_col,
	string $sidebar_col,
	string $insights_block,
	string $cta_band_block
): string {
	$columns = "<!-- wp:columns {\"className\":\"post-layout\"} -->\n"
		. "<div class=\"wp-block-columns post-layout\">"
		. "<!-- wp:column {\"className\":\"post-content no-padding\"} -->\n"
		. "<div class=\"wp-block-column post-content no-padding\">"
		. $content_col
		. "</div>\n<!-- /wp:column -->\n\n"
		. "<!-- wp:column {\"className\":\"post-sidebar\"} -->\n"
		. "<div class=\"wp-block-column post-sidebar\">"
		. $sidebar_col
		. "</div>\n<!-- /wp:column -->"
		. "</div>\n<!-- /wp:columns -->";

	$parts = [ $columns ];

	if ( '' !== $insights_block ) {
		$parts[] = $insights_block;
	} else {
		$parts[] = '<!-- wp:momentive/social-share /-->';
	}

	if ( '' !== $cta_band_block ) {
		$parts[] = $cta_band_block;
	}

	return implode( "\n\n", $parts );
}

/* -------------------------------------------------------------------------
 * Media: attachment map + sideload
 * ---------------------------------------------------------------------- */

/**
 * Parse attachment-ID -> URL pairs out of a single WXR file's own
 * <item post_type="attachment"> entries. Shared by both the primary export
 * (guides) and the supplemental media export below -- same parsing logic,
 * just pointed at a different file.
 */
function momentive_gde_parse_attachment_map( string $path, string $base ): array {
	$map = [];
	if ( ! file_exists( $path ) ) {
		return $map;
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		return $map;
	}

	if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		foreach ( $items[1] as $item ) {
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
			$map[ (int) $pm[1] ] = $base . ltrim( $fm[1], '/' );
		}
	}

	return $map;
}

/**
 * Build the legacy attachment-ID -> URL map.
 *
 * Primary source is the guides WXR itself (same "no separate media export
 * needed" approach as the whitepaper/webinar migrations) -- but unlike
 * those, the guides export doesn't include every attachment referenced by
 * its own posts. Two hero images (10498, 10912) are missing from it
 * entirely; there's no post in the guides export whose own attachment
 * items cover them.
 *
 * MOMENTIVE_GDE_MEDIA_WXR (default: momentivesoftware.media.current.2026-09-01.xml,
 * the general media library export, not guides-specific) is loaded as a
 * SUPPLEMENT, filling in only the IDs the primary export doesn't already
 * have -- it never overrides an ID the guides export itself resolved.
 * Skipped entirely (with a log line, not a warning) if the file isn't
 * present, so this stays optional for anyone re-running the script without
 * it.
 */
function momentive_gde_build_attachment_map(): array {
	$path = defined( 'MOMENTIVE_GDE_LEGACY_WXR' )
		? MOMENTIVE_GDE_LEGACY_WXR
		: __DIR__ . '/exports/momentivesoftware.guides.current.2026-09-01.xml';

	$base = defined( 'MOMENTIVE_GDE_UPLOADS_BASE' )
		? MOMENTIVE_GDE_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/';
	$base = rtrim( $base, '/' ) . '/';

	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Legacy WXR not found at {$path}; media import will be skipped." );
		return [];
	}

	$map = momentive_gde_parse_attachment_map( $path, $base );
	WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs resolved to URLs from the guides export.', count( $map ) ) );

	$media_path = defined( 'MOMENTIVE_GDE_MEDIA_WXR' )
		? MOMENTIVE_GDE_MEDIA_WXR
		: __DIR__ . '/exports/momentivesoftware.media.current.2026-09-01.xml';

	if ( file_exists( $media_path ) ) {
		$media_map = momentive_gde_parse_attachment_map( $media_path, $base );
		$added     = 0;
		foreach ( $media_map as $id => $url ) {
			if ( ! isset( $map[ $id ] ) ) {
				$map[ $id ] = $url;
				$added++;
			}
		}
		WP_CLI::log( sprintf( 'Media export supplement: %d additional legacy IDs filled in from %s.', $added, basename( $media_path ) ) );
	} else {
		WP_CLI::log( 'No supplemental media export found (optional) -- proceeding with the guides export\'s own attachment map only.' );
	}

	return $map;
}

/**
 * Sideload a file by URL into the rebuilt media library, deduped by source URL.
 * Returns attachment ID, or 0 on failure / dry-run.
 */
function momentive_gde_sideload( string $url, int $post_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}

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
		WP_CLI::log( "    [dry-run] would sideload: {$url}" );
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

	$mime_cb = static function( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	};
	if ( $is_svg ) {
		add_filter( 'upload_mimes', $mime_cb, 99 );
	}

	$att_id = media_handle_sideload( $file_array, $post_id );

	if ( $is_svg ) {
		remove_filter( 'upload_mimes', $mime_cb, 99 );
	}

	if ( is_wp_error( $att_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "    media import FAILED: {$url} ({$att_id->get_error_message()})" );
		return 0;
	}

	update_post_meta( $att_id, '_momentive_source_url', $url );
	return (int) $att_id;
}

/* -------------------------------------------------------------------------
 * Legacy WXR parser
 * ---------------------------------------------------------------------- */

/**
 * Parse all legacy guides post items from the WXR export.
 * Returns an array sorted by legacy post ID. Includes BOTH subtypes
 * (guide_type meta is in the returned 'meta' array) — filtering to the
 * `guides` subtype happens in the main run loop, so skips can be logged
 * per-post rather than silently vanishing here.
 */
function momentive_gde_load_legacy_posts(): array {
	$path = defined( 'MOMENTIVE_GDE_LEGACY_WXR' )
		? MOMENTIVE_GDE_LEGACY_WXR
		: __DIR__ . '/exports/momentivesoftware.guides.current.2026-09-01.xml';

	$out = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Legacy WXR not found at {$path}. Place the export next to this script or set MOMENTIVE_GDE_LEGACY_WXR." );
		return $out;
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::error( 'Could not read legacy WXR.' );
		return $out;
	}

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $all_items ) ) {
		return $out;
	}

	foreach ( $all_items[1] as $item ) {
		if ( false === strpos( $item, 'post_type><![CDATA[' . MOMENTIVE_GDE_LEGACY . ']]>' ) ) {
			continue;
		}

		$meta = [];
		if ( preg_match_all(
			'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item, $mm, PREG_SET_ORDER
		) ) {
			foreach ( $mm as $pair ) {
				if ( ! array_key_exists( $pair[1], $meta ) ) {
					$meta[ $pair[1] ] = $pair[2];
				}
			}
		}

		$cats = [];
		if ( preg_match_all( '#<category domain="category" nicename="([^"]*)">#', $item, $cm ) ) {
			$cats = array_values( array_unique( $cm[1] ) );
		}

		$exc = '';
		if ( preg_match( '#<excerpt:encoded><!\[CDATA\[(.*?)\]\]></excerpt:encoded>#s', $item, $em ) ) {
			$exc = trim( $em[1] );
		}

		$out[] = [
			'id'           => (int) momentive_gde_xml_tag( $item, 'wp:post_id' ),
			'title'        => momentive_gde_xml_tag( $item, 'title' ),
			'slug'         => momentive_gde_xml_tag( $item, 'wp:post_name' ),
			'status'       => momentive_gde_xml_tag( $item, 'wp:status' ) ?: 'publish',
			'excerpt'      => $exc,
			'date'         => momentive_gde_xml_tag( $item, 'wp:post_date' ),
			'date_gmt'     => momentive_gde_xml_tag( $item, 'wp:post_date_gmt' ),
			'modified'     => momentive_gde_xml_tag( $item, 'wp:post_modified' ),
			'modified_gmt' => momentive_gde_xml_tag( $item, 'wp:post_modified_gmt' ),
			'meta'         => $meta,
			'cats'         => $cats,
		];
	}

	usort( $out, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
	return $out;
}

/* -------------------------------------------------------------------------
 * Flag handling
 * ---------------------------------------------------------------------- */

/**
 * Parse positional flags from `wp eval-file` $args.
 *
 *   live / go              → write posts (default: dry run)
 *   dry / dry-run          → explicit dry run (default)
 *   only=<slug>            → single post by legacy slug
 *   limit=<N>              → first N posts
 *
 * Environment variable overrides:
 *   MOMENTIVE_LIVE=1       → write posts
 *   MOMENTIVE_DRY=1        → dry run
 *   MOMENTIVE_ONLY=<slug>  → single slug
 *   MOMENTIVE_LIMIT=<N>    → limit
 */
function momentive_gde_get_flags( array $argv = [] ): array {
	$flags = [
		'dry_run' => true, // DRY-RUN BY DEFAULT
		'only'    => '',
		'limit'   => 0,
	];

	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' );
		if ( in_array( $tok, [ 'live', 'go' ], true ) ) {
			$flags['dry_run'] = false;
		} elseif ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) {
			$flags['dry_run'] = true;
		} elseif ( str_starts_with( $tok, 'only=' ) ) {
			$flags['only'] = substr( $tok, 5 );
		} elseif ( str_starts_with( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		}
	}

	if ( getenv( 'MOMENTIVE_LIVE' ) )  { $flags['dry_run'] = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )   { $flags['dry_run'] = true; }
	if ( getenv( 'MOMENTIVE_ONLY' ) )  { $flags['only']    = (string) getenv( 'MOMENTIVE_ONLY' ); }
	if ( getenv( 'MOMENTIVE_LIMIT' ) ) { $flags['limit']   = (int) getenv( 'MOMENTIVE_LIMIT' ); }

	return $flags;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_gde_run( array $argv = [] ): void {
	$flags = momentive_gde_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  Guides & Research migration (guides subtype only)' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( '' !== $flags['only'] )  { WP_CLI::log( '  only:  slug "' . $flags['only'] . '"' ); }
	if ( $flags['limit'] > 0 )    { WP_CLI::log( '  limit: ' . $flags['limit'] . ' posts' ); }
	WP_CLI::log( '====================================================' );

	$attach_map = momentive_gde_build_attachment_map();
	$legacy_all = momentive_gde_load_legacy_posts();
	WP_CLI::log( sprintf( 'Legacy WXR: %d guides items parsed (both subtypes).', count( $legacy_all ) ) );

	if ( '' !== $flags['only'] ) {
		$slug_filter = $flags['only'];
		$legacy_all  = array_values( array_filter(
			$legacy_all,
			static fn( $p ) => $p['slug'] === $slug_filter
		) );
		if ( empty( $legacy_all ) ) {
			WP_CLI::error( "No legacy guides post found with slug \"{$slug_filter}\"." );
			return;
		}
	}
	if ( $flags['limit'] > 0 ) {
		$legacy_all = array_slice( $legacy_all, 0, $flags['limit'] );
	}

	$summary = [
		'processed'              => 0,
		'skipped_research_study' => 0,
		'created'                => 0,
		'updated'                => 0,
		'thumbnails_imported'    => 0,
		'hero_imported'          => 0,
		'hero_title_set'         => 0,
		'gated'                  => 0,
		'not_gated'              => 0,
		'insights_sections'      => 0,
		'cta_bands'              => 0,
		'additional_links'       => 0,
		'cats_linked'            => 0,
	];
	$media_unresolved = [];
	$cat_unresolved   = [];

	foreach ( $legacy_all as $legacy ) {
		$m     = $legacy['meta'];
		$title = $legacy['title'];

		// ---- Subtype gate: only `guides`, never `research-study` ------------
		$guide_type_meta = trim( (string) ( $m['guide_type'] ?? '' ) );
		if ( 'guides' !== $guide_type_meta ) {
			WP_CLI::log( sprintf(
				"\n[%d] %s -- SKIPPED (guide_type=\"%s\", not this script's scope; see notes/guide-reference-sheet.md)",
				$legacy['id'], $title, '' !== $guide_type_meta ? $guide_type_meta : '(empty)'
			) );
			$summary['skipped_research_study']++;
			continue;
		}

		$summary['processed']++;
		WP_CLI::log( sprintf( "\n[%d] %s", $legacy['id'], $title ) );

		// ---- Boolean feature flags ------------------------------------------
		$enable_gated = filter_var(
			$m['enable_gated_content'] ?? 'true',
			FILTER_VALIDATE_BOOLEAN
		);
		$enable_additional_link = filter_var(
			$m['enable_additional_resource_link'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$enable_insights = filter_var(
			$m['enable_insights_section'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$enable_cta_band = filter_var(
			$m['cta_-_enable_cta_section'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);

		// ---- HubSpot embed code ---------------------------------------------
		$raw_form = trim( (string) ( $m['hubspot_form_code'] ?? '' ) );
		if (
			'' !== $raw_form
			&& str_contains( $raw_form, 'hbspt.forms.create' )
			&& ! str_contains( $raw_form, 'js.hsforms.net' )
		) {
			$raw_form = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $raw_form;
		}

		// ---- Content fields -------------------------------------------------
		$resource_details  = momentive_gde_strip_word( (string) ( $m['resource_details']  ?? '' ) );
		$details_cta       = trim( (string) ( $m['details_cta'] ?? '' ) );
		$checklist_title   = trim( (string) ( $m['resource_checklist_title'] ?? '' ) );
		$checklist_raw     = maybe_unserialize( (string) ( $m['resource_checklist'] ?? '' ) );
		$checklist_items   = is_array( $checklist_raw ) ? array_values( $checklist_raw ) : [];
		$after_checklist   = momentive_gde_strip_word( (string) ( $m['resource_details_after_checklist'] ?? '' ) );
		$resource_link     = trim( (string) ( $m['resource_link']     ?? '' ) );
		$resource_link_txt = trim( (string) ( $m['resource_link_text'] ?? '' ) );
		$resource_new_tab  = filter_var(
			$m['resource_link_open_in_new_tab'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$form_heading = trim( (string) ( $m['form_heading'] ?? 'Download now' ) );

		$insights_title = trim( (string) ( $m['content_title'] ?? "What you'll learn" ) );
		$insights_raw   = maybe_unserialize( (string) ( $m['insights_list'] ?? '' ) );
		$insights_items = is_array( $insights_raw ) ? array_values( $insights_raw ) : [];

		// ---- "Looking for more?" CTA band (new for this subtype) -----------
		$cta_title = trim( (string) ( $m['cta_-_title']       ?? '' ) );
		$cta_desc  = trim( (string) ( $m['cta_-_description'] ?? '' ) );
		$cta_btn1  = [
			'text'    => trim( (string) ( $m['cta_-_button_1_text'] ?? '' ) ),
			'url'     => trim( (string) ( $m['cta_-_button_1_link'] ?? '' ) ),
			'new_tab' => filter_var( $m['cta_-_button_1_-_open_in_new_tab'] ?? 'false', FILTER_VALIDATE_BOOLEAN ),
		];
		$cta_btn2 = [
			'text'    => trim( (string) ( $m['cta_-_button_2_text'] ?? '' ) ),
			'url'     => trim( (string) ( $m['cta_-_button_2_link'] ?? '' ) ),
			'new_tab' => filter_var( $m['cta_-_button_2_-_open_in_new_tab'] ?? 'false', FILTER_VALIDATE_BOOLEAN ),
		];

		// ---- Header title override (custom_header -> hero_title) -----------
		// Only relevant when it actually differs from the post title -- several
		// legacy posts populate custom_header with the exact same text as the
		// title (no real override), and hero_title should stay blank there.
		$custom_header = trim( (string) ( $m['custom_header'] ?? '' ) );
		$hero_title    = ( '' !== $custom_header && $custom_header !== trim( $title ) ) ? $custom_header : '';

		// ---- Dry-run summary ------------------------------------------------
		if ( $dry ) {
			$hero_id  = (int) ( $m['resource_hero_image'] ?? 0 );
			$hero_url = ( $hero_id && isset( $attach_map[ $hero_id ] ) ) ? $attach_map[ $hero_id ] : '';
			if ( $hero_id && '' === $hero_url ) {
				$media_unresolved[] = "{$title}: hero ID {$hero_id} not in attachment map";
			}
			$thumb_id  = (int) ( $m['_thumbnail_id'] ?? 0 );
			$thumb_url = ( $thumb_id && isset( $attach_map[ $thumb_id ] ) ) ? $attach_map[ $thumb_id ] : '';
			if ( $thumb_id && '' === $thumb_url ) {
				$media_unresolved[] = "{$title}: thumbnail ID {$thumb_id} not in attachment map";
			}
			WP_CLI::log( sprintf(
				'    [dry-run] gated=%-3s insights=%-3s cta_band=%-3s add_link=%-3s checklist_items=%d hero=%s hero_title=%s',
				$enable_gated    ? 'yes' : 'no',
				$enable_insights ? 'yes' : 'no',
				$enable_cta_band ? 'yes' : 'no',
				$enable_additional_link ? 'yes' : 'no',
				count( $checklist_items ),
				'' !== $hero_url ? 'yes' : 'no',
				'' !== $hero_title ? '"' . $hero_title . '"' : '(none)'
			) );
			if ( $enable_insights ) { $summary['insights_sections']++; }
			if ( $enable_cta_band ) { $summary['cta_bands']++; }
			if ( $enable_additional_link ) { $summary['additional_links']++; }
			if ( '' !== $hero_title ) { $summary['hero_title_set']++; }
			if ( $enable_gated ) { $summary['gated']++; } else { $summary['not_gated']++; }
			continue;
		}

		// ---- Upsert the post shell ------------------------------------------
		$slug = $legacy['slug'];

		$existing = get_posts( [
			'post_type'      => MOMENTIVE_GDE_CPT,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$valid_date = static function( string $d ): string {
			$d = trim( $d );
			return ( '' !== $d && ! str_starts_with( $d, '0000-00-00' ) ) ? $d : '';
		};
		$pd  = $valid_date( $legacy['date'] );
		$pdg = $valid_date( $legacy['date_gmt'] );
		$pm  = $valid_date( $legacy['modified'] );
		$pmg = $valid_date( $legacy['modified_gmt'] );

		$excerpt = trim( (string) ( $legacy['excerpt'] ?? '' ) );

		$shell = [
			'post_type'    => MOMENTIVE_GDE_CPT,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => $legacy['status'],
			'post_excerpt' => $excerpt,
		];
		if ( '' !== $pd )  { $shell['post_date']     = $pd; }
		if ( '' !== $pdg ) { $shell['post_date_gmt'] = $pdg; }

		if ( $existing ) {
			$shell['ID'] = (int) $existing[0];
			$new_id      = wp_update_post( $shell, true );
			$summary['updated']++;
		} else {
			$new_id = wp_insert_post( $shell, true );
			$summary['created']++;
		}

		if ( is_wp_error( $new_id ) ) {
			WP_CLI::warning( '    post write failed: ' . $new_id->get_error_message() );
			continue;
		}
		$new_id = (int) $new_id;

		if ( empty( $existing ) ) {
			update_post_meta( $new_id, MOMENTIVE_GDE_RUN_META, momentive_gde_run_id() );
		}

		// ---- guide_type ACF field --------------------------------------------
		// Always "guides" -- this script never touches research-study posts.
		update_field( 'guide_type', 'guides', $new_id );

		// ---- Header title override -------------------------------------------
		if ( '' !== $hero_title ) {
			update_field( 'hero_title', $hero_title, $new_id );
			$summary['hero_title_set']++;
		}

		// ---- Featured image (legacy _thumbnail_id) --------------------------
		$thumb_legacy_id = (int) ( $m['_thumbnail_id']       ?? 0 );
		$hero_legacy_id  = (int) ( $m['resource_hero_image'] ?? 0 );

		if ( $thumb_legacy_id ) {
			if ( isset( $attach_map[ $thumb_legacy_id ] ) ) {
				$thumb_att = momentive_gde_sideload( $attach_map[ $thumb_legacy_id ], $new_id, false );
				if ( $thumb_att > 0 ) {
					set_post_thumbnail( $new_id, $thumb_att );
					$summary['thumbnails_imported']++;
				}
			} else {
				$media_unresolved[] = "{$title}: thumbnail legacy ID {$thumb_legacy_id} not in attachment map";
			}
		}

		// ---- Hero image ACF field (legacy resource_hero_image) --------------
		// Only set when different from the featured image.
		if ( $hero_legacy_id && $hero_legacy_id !== $thumb_legacy_id ) {
			if ( isset( $attach_map[ $hero_legacy_id ] ) ) {
				$hero_att = momentive_gde_sideload( $attach_map[ $hero_legacy_id ], $new_id, false );
				if ( $hero_att > 0 ) {
					update_field( 'hero_image', $hero_att, $new_id );
					$summary['hero_imported']++;
				}
			} else {
				$media_unresolved[] = "{$title}: hero legacy ID {$hero_legacy_id} not in attachment map";
			}
		}

		// ---- Solution categories --------------------------------------------
		if ( ! empty( $legacy['cats'] ) ) {
			$term_ids = [];
			foreach ( $legacy['cats'] as $cslug ) {
				$term = get_term_by( 'slug', $cslug, 'category' );
				if ( $term ) {
					$term_ids[] = (int) $term->term_id;
				} else {
					$cat_unresolved[] = "{$title}: category slug \"{$cslug}\" has no rebuilt term";
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( $new_id, $term_ids, 'category', false );
				$summary['cats_linked']++;
			}
		}

		// ---- Build post content ---------------------------------------------
		$needs_form_anchor = $enable_additional_link || ! $enable_gated;

		$content_parts   = [];
		$content_parts[] = momentive_gde_back_link_block();
		$content_parts[] = '<!-- wp:query-title {"type":"post-type","showPrefix":false,"className":"top-label"} /-->';
		$content_parts[] = '<!-- wp:post-title {"level":1} /-->';

		foreach ( momentive_gde_html_to_blocks( $resource_details ) as $b ) {
			$content_parts[] = $b;
		}

		if ( '' !== $details_cta ) {
			$content_parts[] = momentive_gde_p_block( '<strong>' . esc_html( $details_cta ) . '</strong>' );
		}

		if ( $enable_gated && ! $enable_insights ) {
			if ( '' !== $checklist_title ) {
				$content_parts[] = momentive_gde_p_block( '<strong>' . esc_html( $checklist_title ) . '</strong>' );
			}
			$clist = momentive_gde_checklist_block( $checklist_items );
			if ( '' !== $clist ) {
				$content_parts[] = $clist;
			}
		}

		foreach ( momentive_gde_html_to_blocks( $after_checklist ) as $b ) {
			$content_parts[] = $b;
		}

		if ( $enable_additional_link && '' !== $resource_link && '' !== $resource_link_txt ) {
			$content_parts[] = momentive_gde_buttons_block( [
				momentive_gde_button_block( $resource_link, $resource_link_txt, $resource_new_tab ),
			] );
			$summary['additional_links']++;
		}

		$left_col = implode( "\n\n", array_filter( $content_parts ) );

		// Right column.
		$sidebar_parts   = [];
		$sidebar_parts[] = '<!-- wp:post-featured-image /-->';

		if ( $enable_gated ) {
			if ( '' !== $form_heading ) {
				if ( $needs_form_anchor ) {
					$sidebar_parts[] = "<!-- wp:paragraph {\"anchor\":\"form\"} -->\n<p id=\"form\"><strong>" . esc_html( $form_heading ) . "</strong></p>\n<!-- /wp:paragraph -->";
				} else {
					$sidebar_parts[] = momentive_gde_p_block( '<strong>' . esc_html( $form_heading ) . '</strong>' );
				}
			}
			$sidebar_parts[] = momentive_gde_hubspot_form_block( $raw_form );
			$summary['gated']++;
		} else {
			if ( '' !== $checklist_title ) {
				$sidebar_parts[] = "<!-- wp:paragraph {\"anchor\":\"form\"} -->\n<p id=\"form\">" . esc_html( $checklist_title ) . "</p>\n<!-- /wp:paragraph -->";
			}
			$clist = momentive_gde_checklist_block( $checklist_items );
			if ( '' !== $clist ) {
				$sidebar_parts[] = $clist;
			}
			if ( '' !== $resource_link && '' !== $resource_link_txt ) {
				$sidebar_parts[] = momentive_gde_buttons_block( [
					momentive_gde_button_block( $resource_link, $resource_link_txt, $resource_new_tab ),
				] );
			}
			$summary['not_gated']++;
		}

		$right_col = implode( "\n\n", array_filter( $sidebar_parts ) );

		// Insights block (full-width, below the columns).
		$insights_block = '';
		if ( $enable_insights && ! empty( $insights_items ) ) {
			$insights_block = momentive_gde_insights_block( $insights_title, $insights_items );
			if ( '' !== $insights_block ) {
				$summary['insights_sections']++;
			}
		}

		// CTA band (full-width, always last).
		$cta_band_block = '';
		if ( $enable_cta_band ) {
			$cta_band_block = momentive_gde_cta_band_block( $cta_title, $cta_desc, $cta_btn1, $cta_btn2 );
			if ( '' !== $cta_band_block ) {
				$summary['cta_bands']++;
			}
		}

		$post_content = momentive_gde_page( $left_col, $right_col, $insights_block, $cta_band_block );

		// wp_update_post calls wp_unslash() internally -- wp_slash() here
		// prevents it from stripping the block comment JSON's backslashes.
		$res = wp_update_post( wp_slash( [ 'ID' => $new_id, 'post_content' => $post_content ] ), true );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( '    content write failed: ' . $res->get_error_message() );
			continue;
		}

		// Restore original modified date.
		if ( '' !== $pm || '' !== $pmg ) {
			global $wpdb;
			$set = [];
			if ( '' !== $pm )  { $set['post_modified']     = $pm; }
			if ( '' !== $pmg ) { $set['post_modified_gmt'] = $pmg; }
			if ( $set ) {
				$wpdb->update( $wpdb->posts, $set, [ 'ID' => $new_id ] );
				clean_post_cache( $new_id );
			}
		}

		WP_CLI::log( "    wrote guide #{$new_id}" );
	}

	/* ---- Summary -------------------------------------------------------- */

	WP_CLI::log( "\n== Summary ==" );
	foreach ( $summary as $k => $v ) {
		WP_CLI::log( sprintf( '  %-24s %d', $k, $v ) );
	}

	if ( $media_unresolved ) {
		WP_CLI::log( sprintf(
			"\n== Unresolved media (slot left empty; add manually) (%d) ==",
			count( $media_unresolved )
		) );
		foreach ( $media_unresolved as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	}

	if ( $cat_unresolved ) {
		WP_CLI::log( "\n== Unresolved categories (no rebuilt term) ==" );
		foreach ( $cat_unresolved as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	}

	WP_CLI::success( $dry ? 'Dry run complete.' : 'Migration complete.' );
}

// `wp eval-file` delivers positional args as a script-scope $args variable.
momentive_gde_run( isset( $args ) && is_array( $args ) ? $args : [] );
