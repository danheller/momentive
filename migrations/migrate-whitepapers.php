<?php
/**
 * migrate-whitepapers.php
 *
 * WP-CLI migration script: legacy `whitepapers` CPT → rebuilt `whitepaper` CPT.
 *
 * Reads from a single WXR export file (whitepapers posts + attachments).
 * Unlike the webinar migration there is no second assets file — whitepaper
 * content lives entirely in the whitepapers export.
 *
 * Run (from the theme/migrations directory):
 *
 *   wp eval-file migrations/migrate-whitepapers.php --user=<admin>
 *     → dry run (default); shows what would be written
 *
 *   wp eval-file migrations/migrate-whitepapers.php live --user=<admin>
 *     → writes posts, sideloads media, sets ACF fields
 *
 *   wp eval-file migrations/migrate-whitepapers.php live limit=10 --user=<admin>
 *     → first 10 legacy whitepapers only
 *
 *   wp eval-file migrations/migrate-whitepapers.php live only=build-vs-buy-ai-platform --user=<admin>
 *     → single post by legacy slug
 *
 * --user=<admin> is REQUIRED because Safe SVG gates SVG uploads on user
 * capability; WP-CLI has no user by default so hero-image sideloads fail.
 *
 * Overridable constants (define before running via env or a wrapper):
 *   MOMENTIVE_WHP_LEGACY_WXR   — path to legacy whitepapers export
 *   MOMENTIVE_WHP_UPLOADS_BASE — base URL for resolving attachment IDs
 *
 * Idempotent: upserts by slug, so re-running updates in place.
 * Rollback: new posts are stamped with _momentive_migration_run; a pre-run
 *           DB backup is the cleanest restore path.
 *
 * Page structure (per the 5 hand-rebuilt reference posts):
 *
 *   Two-column layout (.post-layout):
 *     Left column (.post-content.no-padding):
 *       back-link → query-title → post-title → resource_details paragraphs
 *       → details_cta (bold paragraph, optional)
 *       → checklist_title + checklist (when gated and not insights mode)
 *       → resource_details_after_checklist (optional)
 *       → additional resource link button (optional)
 *     Right column (.post-sidebar):
 *       post-featured-image → form heading paragraph (bold, anchor="form" when
 *       additional_link is present) → acf/hubspot-form (embed inline)
 *
 *       For NOT GATED posts the right column instead holds:
 *       post-featured-image → checklist_title paragraph (anchor="form")
 *       → checklist list → download button
 *
 *   After columns:
 *     Insights section (optional, 2 posts): full-width superlight-accent group
 *       with content_title heading + column-checks list + social-share inside.
 *     Social-share (all posts without insights section).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-whitepapers.php [live] [limit=N] [only=slug] --user=<admin>' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_WHP_CPT      = 'whitepaper';
const MOMENTIVE_WHP_LEGACY   = 'whitepapers'; // post_type value in legacy WXR
const MOMENTIVE_WHP_RUN_META = '_momentive_migration_run';

// ACF field key — Whitepaper Settings (group_6a45de7a50be6 in acf-groups.php)
const FK_WHP_HERO = 'field_6a45de7b50be7'; // image (attachment ID)

// ACF field keys — back-link block (group_6a44a4078d0f6)
const FK_WHP_BL_LABEL = 'field_6a44a408f79e0';
const FK_WHP_BL_URL   = 'field_6a44a420f79e1';

// ACF field keys — HubSpot form block
const FK_WHP_HS_EMBED    = 'field_6a2873ba3bf87'; // hubspot_embed_code
const FK_WHP_HS_TWO_STEP = 'field_6a35626f3a11b'; // two_step

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function momentive_whp_run_id(): string {
	static $id = '';
	if ( '' === $id ) {
		$id = gmdate( 'Y-m-d H:i:s' );
	}
	return $id;
}

/**
 * Extract a CDATA-wrapped or plain child-tag value from an XML item string.
 */
function momentive_whp_xml_tag( string $item, string $tag ): string {
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
 * <a> hyperlinks. Mirrors momentive_wm_strip_word() from migrate-webinars.php.
 */
function momentive_whp_strip_word( string $html ): string {
	// Remove spans with Word data-attributes.
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-props|ccp-charstyle)[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	// Remove spans with Word class tokens.
	$word_classes = 'NormalTextRun|TextRun|EOP|SpellingError|CommentStart|CommentEnd|SCXW\d+|BCX\d+|ContextualSpellingError';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*(?:' . $word_classes . ')[^"]*"[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	// Remove remaining attribute-less spans (leave <a>, <strong>, <em>, etc.).
	$html = preg_replace( '#<span\b(?:\s*)>(.*?)</span>#si', '$1', $html );

	return $html;
}

/* -------------------------------------------------------------------------
 * Block emitters
 * ---------------------------------------------------------------------- */

/** Wrap text in a wp:paragraph block. Returns '' when text is blank or nbsp-only. */
function momentive_whp_p_block( string $inner ): string {
	$inner = trim( $inner );
	if ( '' === $inner || '' === trim( html_entity_decode( $inner, ENT_HTML5, 'UTF-8' ) ) ) {
		return '';
	}
	return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
}

/** Wrap text in a wp:heading block. */
function momentive_whp_h_block( string $text, int $level = 2 ): string {
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
function momentive_whp_html_to_blocks( string $html ): array {
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
		return [ momentive_whp_p_block( wp_strip_all_tags( $html ) ) ];
	}

	$blocks = [];

	foreach ( $root->childNodes as $node ) {
		if ( $node->nodeType === XML_TEXT_NODE ) {
			$text = trim( $node->textContent );
			if ( '' !== $text ) {
				$blocks[] = momentive_whp_p_block( esc_html( $text ) );
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
				$text = trim( $inner_html );
				// Skip paragraphs that are blank or contain only whitespace/nbsp.
				$plain = trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_HTML5, 'UTF-8' ) );
				if ( '' !== $plain && '<br>' !== $text && '<br/>' !== $text ) {
					$blocks[] = momentive_whp_p_block( $text );
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
					$blocks[] = momentive_whp_h_block( $plain, $level );
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
					$blocks[] = momentive_whp_p_block( esc_html( $plain ) );
				}
				break;
		}
	}

	return array_values( array_filter( $blocks ) );
}

/** back-link block pointing to /whitepapers/ with full ACF data scaffold. */
function momentive_whp_back_link_block(): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/back-link',
		'data' => [
			'label'  => 'All whitepapers',
			'_label' => FK_WHP_BL_LABEL,
			'url'    => '/whitepapers/',
			'_url'   => FK_WHP_BL_URL,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/back-link {$attrs} /-->";
}

/**
 * HubSpot form block with embed code stored INLINE in block data.
 *
 * Whitepapers use a block-level embed (no post-level form fields like
 * webinars). The full <script> embed code is written directly into the
 * block's data object so ACF can read it on the front end without needing
 * a separate post meta lookup.
 *
 * Data format: field keys used directly as the data keys (e.g.
 * "field_6a2873ba3bf87" → embed code) — this is the format Gutenberg writes
 * when an editor saves the block, and is what the block render template
 * expects when the block has no previously-saved post meta.
 *
 * Encoding: JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP escapes <, >, ", &
 * as Unicode sequences (<, >, ", &), matching the output
 * Gutenberg's JSON.stringify produces and preventing those characters in the
 * embed code from breaking the block comment's JSON.
 *
 * Slashing: the block comment is embedded in post_content, which must be
 * wrapped in wp_slash() before passing to wp_update_post — see the post
 * content write below. wp_update_post calls wp_unslash() internally; without
 * wp_slash() first, every backslash in the JSON (including the \u escapes
 * and control-char escapes like \r\n) is stripped, producing invalid JSON.
 *
 * Auto-injects the hsforms loader <script> if the embed code contains only
 * hbspt.forms.create() without it (common copy-paste omission).
 */
function momentive_whp_hubspot_form_block( string $embed_code ): string {
	$embed_code = trim( $embed_code );
	if (
		'' !== $embed_code
		&& str_contains( $embed_code, 'hbspt.forms.create' )
		&& ! str_contains( $embed_code, 'js.hsforms.net' )
	) {
		$embed_code = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $embed_code;
	}

	// Use field keys directly as data keys — no "_fieldname" reference entries.
	$attrs = wp_json_encode( [
		'name' => 'acf/hubspot-form',
		'data' => [
			FK_WHP_HS_EMBED    => $embed_code,
			FK_WHP_HS_TWO_STEP => '0',
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP );
	return "<!-- wp:acf/hubspot-form {$attrs} /-->";
}

/**
 * Button block for additional resource links and not-gated download buttons.
 *
 * @param string $url     Button href (e.g. '#form', or an absolute URL).
 * @param string $text    Button label.
 * @param bool   $new_tab Whether to open in a new tab.
 */
function momentive_whp_button_block( string $url, string $text, bool $new_tab ): string {
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

	$btn_json = '';
	if ( $btn_attrs ) {
		$btn_json = ' {' . implode( ',', $btn_attrs ) . '}';
	}

	return "<!-- wp:buttons -->\n"
		. "<div class=\"wp-block-buttons\"><!-- wp:button{$btn_json} -->\n"
		. "<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" {$link_attrs}>{$text}</a></div>\n"
		. "<!-- /wp:button --></div>\n<!-- /wp:buttons -->";
}

/**
 * Checklist block from the legacy resource_checklist repeater.
 * Each row has a `description` key; items become plain list items.
 */
function momentive_whp_checklist_block( array $items ): string {
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
 * Insights section — a full-width superlight-accent group below the columns.
 *
 * Used by 2/68 whitepaper posts. Whitepapers never have presenters, so
 * social-share is always embedded inside the group (same rule as the webinar
 * migration's "no presenters" path).
 *
 * @param string $heading  H2 text (content_title field; defaults to "What you'll learn").
 * @param array  $items    Unserialized insights_list rows ({insight_title, insight_description}).
 */
function momentive_whp_insights_block( string $heading, array $items ): string {
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
 * Assemble the full whitepaper page from left column, right column, and an
 * optional insights block.
 *
 * Social-share placement:
 *   - With insights section: social-share is inside the insights group.
 *   - Without insights:      social-share appears after the columns.
 */
function momentive_whp_page(
	string $content_col,
	string $sidebar_col,
	string $insights_block
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
		// Social-share is embedded inside the insights group.
		$parts[] = $insights_block;
	} else {
		$parts[] = '<!-- wp:momentive/social-share /-->';
	}

	return implode( "\n\n", $parts );
}

/* -------------------------------------------------------------------------
 * Media: attachment map + sideload
 * ---------------------------------------------------------------------- */

/**
 * Build legacy attachment-ID → fetchable-URL map from the same WXR export.
 * Whitepaper attachment IDs are resolved to URLs using the WXR's
 * _wp_attached_file meta + the uploads base URL.
 */
function momentive_whp_build_attachment_map(): array {
	$path = defined( 'MOMENTIVE_WHP_LEGACY_WXR' )
		? MOMENTIVE_WHP_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.whitepapers.current.2026-07-01.xml';

	$base = defined( 'MOMENTIVE_WHP_UPLOADS_BASE' )
		? MOMENTIVE_WHP_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/';
	$base = rtrim( $base, '/' ) . '/';

	$map = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Legacy WXR not found at {$path}; media import will be skipped." );
		return $map;
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::warning( 'Could not read legacy WXR for attachment map.' );
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

	WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs resolved to URLs.', count( $map ) ) );
	return $map;
}

/**
 * Sideload a file by URL into the rebuilt media library, deduped by source URL.
 * Returns attachment ID, or 0 on failure / dry-run.
 *
 * Run with --user=<admin> so Safe SVG's capability check passes for SVG files.
 */
function momentive_whp_sideload( string $url, int $post_id, bool $dry ): int {
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
 * Parse all legacy whitepaper post items from the WXR export.
 * Returns an array sorted by legacy post ID.
 */
function momentive_whp_load_legacy_posts(): array {
	$path = defined( 'MOMENTIVE_WHP_LEGACY_WXR' )
		? MOMENTIVE_WHP_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.whitepapers.current.2026-07-01.xml';

	$out = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Legacy WXR not found at {$path}. Place the export next to this script or set MOMENTIVE_WHP_LEGACY_WXR." );
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
		if ( false === strpos( $item, 'post_type><![CDATA[whitepapers]]>' ) ) {
			continue;
		}

		$meta = [];
		if ( preg_match_all(
			'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item, $mm, PREG_SET_ORDER
		) ) {
			foreach ( $mm as $pair ) {
				// First occurrence wins.
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
			'id'           => (int) momentive_whp_xml_tag( $item, 'wp:post_id' ),
			'title'        => momentive_whp_xml_tag( $item, 'title' ),
			'slug'         => momentive_whp_xml_tag( $item, 'wp:post_name' ),
			'status'       => momentive_whp_xml_tag( $item, 'wp:status' ) ?: 'publish',
			'excerpt'      => $exc,
			'date'         => momentive_whp_xml_tag( $item, 'wp:post_date' ),
			'date_gmt'     => momentive_whp_xml_tag( $item, 'wp:post_date_gmt' ),
			'modified'     => momentive_whp_xml_tag( $item, 'wp:post_modified' ),
			'modified_gmt' => momentive_whp_xml_tag( $item, 'wp:post_modified_gmt' ),
			'meta'         => $meta,
			'cats'         => $cats,
		];
	}

	// Stable order by legacy ID.
	usort( $out, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
	return $out;
}

/* -------------------------------------------------------------------------
 * Flag handling
 * ---------------------------------------------------------------------- */

/**
 * Parse positional flags from `wp eval-file` $args.
 *
 * `wp eval-file` does NOT accept named --flags; everything after the filename
 * arrives as positional $args (script-scope variable). This parses them:
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
function momentive_whp_get_flags( array $argv = [] ): array {
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

function momentive_whp_run( array $argv = [] ): void {
	$flags = momentive_whp_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  Whitepaper migration' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( '' !== $flags['only'] )  { WP_CLI::log( '  only:  slug "' . $flags['only'] . '"' ); }
	if ( $flags['limit'] > 0 )    { WP_CLI::log( '  limit: ' . $flags['limit'] . ' posts' ); }
	WP_CLI::log( '====================================================' );

	$attach_map = momentive_whp_build_attachment_map();
	$legacy_all = momentive_whp_load_legacy_posts();
	WP_CLI::log( sprintf( 'Legacy WXR: %d whitepaper items parsed.', count( $legacy_all ) ) );

	// Apply only / limit filters.
	if ( '' !== $flags['only'] ) {
		$slug_filter = $flags['only'];
		$legacy_all  = array_values( array_filter(
			$legacy_all,
			static fn( $p ) => $p['slug'] === $slug_filter
		) );
		if ( empty( $legacy_all ) ) {
			WP_CLI::error( "No legacy whitepaper found with slug \"{$slug_filter}\"." );
			return;
		}
	}
	if ( $flags['limit'] > 0 ) {
		$legacy_all = array_slice( $legacy_all, 0, $flags['limit'] );
	}

	$summary = [
		'processed'           => 0,
		'created'             => 0,
		'updated'             => 0,
		'thumbnails_imported' => 0,
		'hero_imported'       => 0,
		'gated'               => 0,
		'not_gated'           => 0,
		'insights_sections'   => 0,
		'additional_links'    => 0,
		'cats_linked'         => 0,
	];
	$media_unresolved = [];
	$cat_unresolved   = [];

	foreach ( $legacy_all as $legacy ) {
		$summary['processed']++;
		WP_CLI::log( sprintf( "\n[%d] %s", $legacy['id'], $legacy['title'] ) );

		$m     = $legacy['meta'];
		$title = $legacy['title'];

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

		// ---- HubSpot embed code ---------------------------------------------
		$raw_form = trim( (string) ( $m['hubspot_form_code'] ?? '' ) );
		// Auto-inject loader if only hbspt.forms.create() is present.
		if (
			'' !== $raw_form
			&& str_contains( $raw_form, 'hbspt.forms.create' )
			&& ! str_contains( $raw_form, 'js.hsforms.net' )
		) {
			$raw_form = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $raw_form;
		}

		// ---- Content fields -------------------------------------------------
		$resource_details  = momentive_whp_strip_word( (string) ( $m['resource_details']  ?? '' ) );
		$details_cta       = trim( (string) ( $m['details_cta'] ?? '' ) );
		$checklist_title   = trim( (string) ( $m['resource_checklist_title'] ?? '' ) );
		$checklist_raw     = maybe_unserialize( (string) ( $m['resource_checklist'] ?? '' ) );
		$checklist_items   = is_array( $checklist_raw ) ? array_values( $checklist_raw ) : [];
		$after_checklist   = momentive_whp_strip_word( (string) ( $m['resource_details_after_checklist'] ?? '' ) );
		$resource_link     = trim( (string) ( $m['resource_link']     ?? '' ) );
		$resource_link_txt = trim( (string) ( $m['resource_link_text'] ?? '' ) );
		$resource_new_tab  = filter_var(
			$m['resource_link_open_in_new_tab'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$form_heading      = trim( (string) ( $m['form_heading'] ?? 'Download now' ) );

		$insights_title = trim( (string) ( $m['content_title'] ?? "What you'll learn" ) );
		$insights_raw   = maybe_unserialize( (string) ( $m['insights_list'] ?? '' ) );
		$insights_items = is_array( $insights_raw ) ? array_values( $insights_raw ) : [];

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
				'    [dry-run] gated=%-3s insights=%-3s add_link=%-3s checklist_items=%d hero=%s',
				$enable_gated    ? 'yes' : 'no',
				$enable_insights ? 'yes' : 'no',
				$enable_additional_link ? 'yes' : 'no',
				count( $checklist_items ),
				'' !== $hero_url ? 'yes' : 'no'
			) );
			if ( $enable_insights ) { $summary['insights_sections']++; }
			if ( $enable_additional_link ) { $summary['additional_links']++; }
			if ( $enable_gated ) { $summary['gated']++; } else { $summary['not_gated']++; }
			continue;
		}

		// ---- Upsert the post shell ------------------------------------------
		$slug = $legacy['slug'];

		$existing = get_posts( [
			'post_type'      => MOMENTIVE_WHP_CPT,
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
			'post_type'    => MOMENTIVE_WHP_CPT,
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
			update_post_meta( $new_id, MOMENTIVE_WHP_RUN_META, momentive_whp_run_id() );
		}

		// ---- Featured image (legacy _thumbnail_id) --------------------------
		// Archive card image, separate from the singular hero.
		$thumb_legacy_id = (int) ( $m['_thumbnail_id']       ?? 0 );
		$hero_legacy_id  = (int) ( $m['resource_hero_image'] ?? 0 );

		if ( $thumb_legacy_id ) {
			if ( isset( $attach_map[ $thumb_legacy_id ] ) ) {
				$thumb_att = momentive_whp_sideload( $attach_map[ $thumb_legacy_id ], $new_id, false );
				if ( $thumb_att > 0 ) {
					set_post_thumbnail( $new_id, $thumb_att );
					$summary['thumbnails_imported']++;
				}
			} else {
				$media_unresolved[] = "{$title}: thumbnail legacy ID {$thumb_legacy_id} not in attachment map";
			}
		}

		// ---- Hero image ACF field (legacy resource_hero_image) --------------
		// Only set when different from the featured image — when they're the same
		// attachment, the featured image handles both and hero_image stays empty.
		// (Reference post #5 has _thumbnail_id === resource_hero_image — this
		// condition correctly skips the hero_image write in that case.)
		if ( $hero_legacy_id && $hero_legacy_id !== $thumb_legacy_id ) {
			if ( isset( $attach_map[ $hero_legacy_id ] ) ) {
				$hero_att = momentive_whp_sideload( $attach_map[ $hero_legacy_id ], $new_id, false );
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

		// Left column.
		// The anchor="form" attribute is added to the right-column heading
		// whenever the left column has an additional-link button pointing to
		// #form or an external URL. This makes the #form anchor available as a
		// scroll target. For not-gated posts it's always set (checklist_title
		// becomes the anchor target in the right column).
		$needs_form_anchor = $enable_additional_link || ! $enable_gated;

		$content_parts = [];
		$content_parts[] = momentive_whp_back_link_block();
		$content_parts[] = '<!-- wp:query-title {"type":"post-type","showPrefix":false,"className":"top-label"} /-->';
		$content_parts[] = '<!-- wp:post-title {"level":1} /-->';

		foreach ( momentive_whp_html_to_blocks( $resource_details ) as $b ) {
			$content_parts[] = $b;
		}

		if ( '' !== $details_cta ) {
			// details_cta renders as a bold paragraph in the left column,
			// placed after the main description and before the checklist.
			$content_parts[] = momentive_whp_p_block( '<strong>' . esc_html( $details_cta ) . '</strong>' );
		}

		if ( $enable_gated && ! $enable_insights ) {
			// Standard gated path: checklist in left column.
			if ( '' !== $checklist_title ) {
				$content_parts[] = momentive_whp_p_block( '<strong>' . esc_html( $checklist_title ) . '</strong>' );
			}
			$clist = momentive_whp_checklist_block( $checklist_items );
			if ( '' !== $clist ) {
				$content_parts[] = $clist;
			}
		}
		// When enable_insights=true the checklist is replaced by the full-width
		// insights section below the columns; nothing extra goes in the left column.

		foreach ( momentive_whp_html_to_blocks( $after_checklist ) as $b ) {
			$content_parts[] = $b;
		}

		if ( $enable_additional_link && '' !== $resource_link && '' !== $resource_link_txt ) {
			$content_parts[] = momentive_whp_button_block( $resource_link, $resource_link_txt, $resource_new_tab );
			$summary['additional_links']++;
		}

		$left_col = implode( "\n\n", array_filter( $content_parts ) );

		// Right column.
		$sidebar_parts   = [];
		$sidebar_parts[] = '<!-- wp:post-featured-image /-->';

		if ( $enable_gated ) {
			// Gated: form heading + HubSpot form.
			if ( '' !== $form_heading ) {
				if ( $needs_form_anchor ) {
					$sidebar_parts[] = "<!-- wp:paragraph {\"anchor\":\"form\"} -->\n<p id=\"form\"><strong>" . esc_html( $form_heading ) . "</strong></p>\n<!-- /wp:paragraph -->";
				} else {
					$sidebar_parts[] = momentive_whp_p_block( '<strong>' . esc_html( $form_heading ) . '</strong>' );
				}
			}
			$sidebar_parts[] = momentive_whp_hubspot_form_block( $raw_form );
			$summary['gated']++;
		} else {
			// Not gated: checklist_title (as anchor paragraph) + checklist list
			// + download button in the right column (no form).
			if ( '' !== $checklist_title ) {
				$sidebar_parts[] = "<!-- wp:paragraph {\"anchor\":\"form\"} -->\n<p id=\"form\">" . esc_html( $checklist_title ) . "</p>\n<!-- /wp:paragraph -->";
			}
			$clist = momentive_whp_checklist_block( $checklist_items );
			if ( '' !== $clist ) {
				$sidebar_parts[] = $clist;
			}
			if ( '' !== $resource_link && '' !== $resource_link_txt ) {
				$sidebar_parts[] = momentive_whp_button_block( $resource_link, $resource_link_txt, $resource_new_tab );
			}
			$summary['not_gated']++;
		}

		$right_col = implode( "\n\n", array_filter( $sidebar_parts ) );

		// Insights block (full-width section below the columns, 2 posts).
		$insights_block = '';
		if ( $enable_insights && ! empty( $insights_items ) ) {
			$insights_block = momentive_whp_insights_block( $insights_title, $insights_items );
			if ( '' !== $insights_block ) {
				$summary['insights_sections']++;
			}
		}

		$post_content = momentive_whp_page( $left_col, $right_col, $insights_block );

		// wp_update_post calls wp_unslash() on all post data internally. Without
		// wp_slash() here, every backslash in the block comment JSON — including
		// the \u escapes from JSON_HEX_TAG/QUOT/AMP and the \r\n line endings in
		// the HubSpot embed code — is stripped, producing invalid block JSON.
		$res = wp_update_post( wp_slash( [ 'ID' => $new_id, 'post_content' => $post_content ] ), true );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( '    content write failed: ' . $res->get_error_message() );
			continue;
		}

		// Restore original modified date. wp_insert/update_post always sets it
		// to "now", so override via a direct DB write after all writes are done.
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

		WP_CLI::log( "    wrote whitepaper #{$new_id}" );
	}

	/* ---- Summary -------------------------------------------------------- */

	WP_CLI::log( "\n== Summary ==" );
	foreach ( $summary as $k => $v ) {
		WP_CLI::log( sprintf( '  %-22s %d', $k, $v ) );
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
momentive_whp_run( isset( $args ) && is_array( $args ) ? $args : [] );
