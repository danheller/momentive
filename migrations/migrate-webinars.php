<?php
/**
 * migrate-webinars.php
 *
 * WP-CLI migration script: legacy `webinars` CPT → rebuilt `webinar` CPT.
 *
 * Reads from the legacy WXR export (the file named
 * momentivesoftware.assets.current.2026-07-01.xml — the filenames are swapped;
 * that file contains `webinars` posts, not `assets` posts). The attachment map
 * for hero images also comes from that file.
 *
 * Run (from the theme/migrations directory):
 *
 *   wp eval-file migrations/migrate-webinars.php --user=<admin>
 *     → dry run (default); shows what would be written
 *
 *   wp eval-file migrations/migrate-webinars.php live --user=<admin>
 *     → writes posts, sideloads media, creates missing People, sets ACF fields
 *
 *   wp eval-file migrations/migrate-webinars.php live limit=10 --user=<admin>
 *     → first 10 legacy webinars only
 *
 *   wp eval-file migrations/migrate-webinars.php live only=boardroom-ready-building-a-business-case-for-tech-investment --user=<admin>
 *     → single post by legacy slug
 *
 * --user=<admin> is REQUIRED because Safe SVG gates SVG uploads on user
 * capability; WP-CLI has no user by default so hero-image sideloads fail.
 *
 * Overridable constants (define before running via env or a wrapper):
 *   MOMENTIVE_WM_LEGACY_WXR    — path to legacy webinars export
 *   MOMENTIVE_WM_UPLOADS_BASE  — base URL for resolving attachment IDs
 *
 * Idempotent: upserts by slug, so re-running updates in place.
 * Rollback: new posts are stamped with _momentive_migration_run; a pre-run
 *           DB backup is the cleanest restore path.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'Run as: wp eval-file migrations/migrate-webinars.php [live] [limit=N] [only=slug] --user=<admin>' . PHP_EOL );
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */

const MOMENTIVE_WM_CPT      = 'webinar';
const MOMENTIVE_WM_LEGACY   = 'webinars';  // post_type value in legacy WXR
const MOMENTIVE_WM_RUN_META = '_momentive_migration_run';

// ACF field keys — Webinar Settings (from acf-json/group_6a3a318255bf0.json)
const FK_WM_TYPE       = 'field_6a3a3182ba777'; // select: upcoming|on-demand
const FK_WM_IS_SERIES  = 'field_6a3e1db41ee80'; // true_false
const FK_WM_DATE       = 'field_6a3a31bcba778'; // date_picker (Ymd)
const FK_WM_END_DATE   = 'field_6a3a31dbba779'; // date_picker (Ymd)
const FK_WM_TIME_START = 'field_6a3a31f9ba77a'; // time_picker (H:i:s)
const FK_WM_TIME_END   = 'field_6a3a323bba77b'; // time_picker (H:i:s)
const FK_WM_TIMEZONE   = 'field_6a3a3249ba77c'; // text
const FK_WM_FORM_UP    = 'field_6a3a3321ba77f'; // textarea — upcoming embed code
const FK_WM_FORM_OD    = 'field_6a3a3356ba780'; // textarea — on-demand embed code
const FK_WM_VIDEO      = 'field_6a3ef54a65cd6'; // textarea — video embed code
const FK_WM_PRESENTERS = 'field_6a3edd7da2c1f'; // post_object (People IDs)
const FK_WM_HERO       = 'field_6a3eddd24103c'; // image (attachment ID)

// ACF field keys — back-link block (from acf-json/group_6a44a4078d0f6.json)
const FK_BL_LABEL = 'field_6a44a408f79e0';
const FK_BL_URL   = 'field_6a44a420f79e1';

// ACF field key — webinar-form-heading block (from acf-json/group_6a44a695407f9.json)
const FK_FH_OVERRIDE = 'field_6a44a695e649d';

// ACF field keys — HubSpot form block (legacy, kept in data scaffold for
// compatibility with existing rebuilt posts — the render template no longer
// reads form_source, but old posts carry it in their serialized block data)
const FK_HS_FORM_SOURCE = 'field_6a3aa8ba03d5e';
const FK_HS_TWO_STEP    = 'field_6a35626f3a11b';

// ACF field keys — webinar-presenters block (from acf-json/group_6a448a68cf996.json)
const FK_WP_LAYOUT      = 'field_6a448a69ebb4b';
const FK_WP_HEADSHOTS   = 'field_6a4542d50b10a';

/* -------------------------------------------------------------------------
 * Utilities
 * ---------------------------------------------------------------------- */

function momentive_wm_run_id(): string {
	static $id = '';
	if ( '' === $id ) {
		$id = gmdate( 'Y-m-d H:i:s' );
	}
	return $id;
}

/**
 * Extract a CDATA-wrapped or plain child-tag value from an XML item string.
 */
function momentive_wm_xml_tag( string $item, string $tag ): string {
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
 * Removes any <span> that has a Word fingerprint (data-contrast, data-ccp-*,
 * Word class tokens like NormalTextRun/SCXW/BCX/EOP). Keeps inner text and
 * <a> hyperlinks. Mirrors momentive_cs_strip_word() from migrate-case-studies.
 */
function momentive_wm_strip_word( string $html ): string {
	// Remove spans with Word data-attributes.
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-props|ccp-charstyle)[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	// Remove spans with Word class tokens (NormalTextRun, TextRun, SCXW*, BCX*, EOP, spelling, comment).
	$word_classes = 'NormalTextRun|TextRun|EOP|SpellingError|CommentStart|CommentEnd|SCXW\d+|BCX\d+|ContextualSpellingError';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*(?:' . $word_classes . ')[^"]*"[^>]*>(.*?)</span>#si',
		'$1',
		$html
	);

	// Remove remaining inline styleless spans (leave <a>, <strong>, <em>, etc.).
	$html = preg_replace( '#<span\b(?:\s*)>(.*?)</span>#si', '$1', $html );

	return $html;
}

/**
 * Convert a Unix timestamp string to ACF date_picker format (Ymd).
 * Returns '' for empty or zero timestamps.
 */
function momentive_wm_date_from_ts( string $ts ): string {
	$ts = trim( $ts );
	if ( '' === $ts || '0' === $ts ) {
		return '';
	}
	return date( 'Ymd', (int) $ts );
}

/**
 * Normalize a time string to ACF time_picker format (H:i:s).
 * Handles legacy "H:MM" and already-correct "H:MM:SS".
 */
function momentive_wm_normalize_time( string $t ): string {
	$t = trim( $t );
	if ( '' === $t ) {
		return '';
	}
	if ( preg_match( '/^\d{1,2}:\d{2}:\d{2}$/', $t ) ) {
		return $t; // already H:i:s
	}
	if ( preg_match( '/^\d{1,2}:\d{2}$/', $t ) ) {
		return $t . ':00'; // H:MM → H:MM:00
	}
	return $t;
}

/**
 * Auto-inject the HubSpot loader <script> if the embed code contains only
 * hbspt.forms.create() without the library tag (common copy-paste omission).
 */
function momentive_wm_fix_hubspot( string $code ): string {
	$code = trim( $code );
	if ( '' === $code ) {
		return '';
	}
	if ( str_contains( $code, 'hbspt.forms.create' ) && ! str_contains( $code, 'js.hsforms.net' ) ) {
		$code = '<script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>' . "\n" . $code;
	}
	return $code;
}

/**
 * Normalize a string for fuzzy name matching: lowercase, keep only alphanumerics.
 */
function momentive_wm_norm( string $s ): string {
	return preg_replace( '/[^a-z0-9]/', '', strtolower( trim( $s ) ) );
}

/* -------------------------------------------------------------------------
 * HTML → blocks (same pattern as migrate-case-studies.php)
 * ---------------------------------------------------------------------- */

/** Wrap text in a wp:paragraph block. */
function momentive_wm_p_block( string $inner ): string {
	$inner = trim( $inner );
	if ( '' === $inner ) {
		return '';
	}
	return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
}

/** Wrap text in a wp:heading block. */
function momentive_wm_h_block( string $text, int $level = 2 ): string {
	$text = trim( wp_strip_all_tags( $text ) );
	if ( '' === $text ) {
		return '';
	}
	return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->";
}

/**
 * Convert legacy WYSIWYG HTML to an array of block markup strings.
 * Handles <p>, <h2>–<h6>, <ul>, <ol>, <blockquote>, <table>. Anything else
 * is wrapped in a paragraph. Empty results are filtered out.
 *
 * Mirrors momentive_cs_html_to_blocks() from migrate-case-studies.php.
 */
function momentive_wm_html_to_blocks( string $html ): array {
	$html = trim( $html );
	if ( '' === $html ) {
		return [];
	}

	// Wrap in a root node so DOMDocument can parse fragments.
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="__root__">' . $html . '</div>' );
	libxml_clear_errors();

	$root = $doc->getElementById( '__root__' );
	if ( ! $root ) {
		return [ momentive_wm_p_block( wp_strip_all_tags( $html ) ) ];
	}

	$blocks = [];

	foreach ( $root->childNodes as $node ) {
		if ( $node->nodeType === XML_TEXT_NODE ) {
			$text = trim( $node->textContent );
			if ( '' !== $text ) {
				$blocks[] = momentive_wm_p_block( esc_html( $text ) );
			}
			continue;
		}
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			continue;
		}

		$tag   = strtolower( $node->nodeName );
		$inner = $doc->saveHTML( $node );

		// Strip the outer element tag to get inner HTML.
		$inner_html = preg_replace( '#^<' . preg_quote( $tag, '#' ) . '[^>]*>(.*)</' . preg_quote( $tag, '#' ) . '>$#si', '$1', trim( $inner ) );

		switch ( $tag ) {
			case 'p':
				$text = trim( $inner_html );
				if ( '' !== $text && '<br>' !== $text && '<br/>' !== $text ) {
					$blocks[] = momentive_wm_p_block( $text );
				}
				break;

			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level    = (int) $tag[1];
				$text     = trim( $inner_html );
				$plain    = wp_strip_all_tags( $text );
				if ( '' !== $plain ) {
					$blocks[] = momentive_wm_h_block( $plain, $level );
				}
				break;

			case 'ul':
			case 'ol':
				$list_type  = ( 'ol' === $tag ) ? 'ordered' : '';
				$attrs      = $list_type ? " {\"ordered\":true}" : '';
				$html_tag   = $list_type ? 'ol' : 'ul';
				$items_html = '';
				foreach ( $node->childNodes as $li ) {
					if ( $li->nodeType !== XML_ELEMENT_NODE || strtolower( $li->nodeName ) !== 'li' ) {
						continue;
					}
					$li_inner = preg_replace( '#^<li[^>]*>(.*)</li>$#si', '$1', trim( $doc->saveHTML( $li ) ) );
					$items_html .= "<!-- wp:list-item -->\n<li>{$li_inner}</li>\n<!-- /wp:list-item -->\n";
				}
				if ( '' !== $items_html ) {
					$blocks[] = "<!-- wp:list{$attrs} -->\n<{$html_tag} class=\"wp-block-list\">{$items_html}</{$html_tag}>\n<!-- /wp:list -->";
				}
				break;

			case 'blockquote':
				$text = trim( wp_strip_all_tags( $inner_html ) );
				if ( '' !== $text ) {
					$blocks[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>" . esc_html( $text ) . "</p></blockquote>\n<!-- /wp:quote -->";
				}
				break;

			case 'table':
				$blocks[] = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>{$inner_html}</table></figure>\n<!-- /wp:table -->";
				break;

			default:
				$text = trim( wp_strip_all_tags( $inner_html ) );
				if ( '' !== $text ) {
					$blocks[] = momentive_wm_p_block( esc_html( $text ) );
				}
				break;
		}
	}

	return array_values( array_filter( $blocks ) );
}

/* -------------------------------------------------------------------------
 * Block emitters
 * ---------------------------------------------------------------------- */

/** back-link block pointing to /webinars/ with full ACF data scaffold. */
function momentive_wm_back_link_block(): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/back-link',
		'data' => [
			'label'  => 'All webinars',
			'_label' => FK_BL_LABEL,
			'url'    => '/webinars/',
			'_url'   => FK_BL_URL,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/back-link {$attrs} /-->";
}

/** HubSpot form block with the standard data scaffold (form resolved from post fields). */
function momentive_wm_hubspot_form_block(): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/hubspot-form',
		'data' => [
			'form_source'  => 'post',
			'_form_source' => FK_HS_FORM_SOURCE,
			'two_step'     => '0',
			'_two_step'    => FK_HS_TWO_STEP,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/hubspot-form {$attrs} /-->";
}

/** Webinar form-heading block. Pass the override string if the legacy had a custom heading. */
function momentive_wm_form_heading_block( string $override = '' ): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/webinar-form-heading',
		'data' => [
			'form_heading_override'  => $override,
			'_form_heading_override' => FK_FH_OVERRIDE,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/webinar-form-heading {$attrs} /-->";
}

/**
 * Webinar presenters block.
 * Emits a full data scaffold so ACF can read block-level fields on the front end.
 */
function momentive_wm_presenters_block(): string {
	$attrs = wp_json_encode( [
		'name' => 'acf/webinar-presenters',
		'data' => [
			'layout'        => 'two-columns',
			'_layout'       => FK_WP_LAYOUT,
			'show_headshots'=> '1',
			'_show_headshots'=> FK_WP_HEADSHOTS,
		],
		'mode' => 'preview',
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:acf/webinar-presenters {$attrs} /-->";
}

/** Quote block from resource_quote fields. Returns '' when quote is empty. */
function momentive_wm_quote_block( string $quote, string $author_name, string $author_desc ): string {
	$quote = trim( $quote );
	if ( '' === $quote ) {
		return '';
	}
	$citation = trim( $author_name . ( $author_desc ? ', ' . $author_desc : '' ) );
	return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>"
		. esc_html( $quote ) . '</p>'
		. ( $citation ? '<cite>' . esc_html( $citation ) . '</cite>' : '' )
		. "</blockquote>\n<!-- /wp:quote -->";
}

/**
 * Checklist block from the legacy resource_checklist repeater.
 * Each row has a 'description' key; items become plain list items.
 */
function momentive_wm_checklist_block( array $items ): string {
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
 * Insights section (the superlight-accent band).
 *
 * Social-share placement follows the rule applied in the hand-rebuilt posts:
 *   - If $include_social_share is true  (no presenters follow): social-share
 *     lives INSIDE the group, after the list.
 *   - If $include_social_share is false (presenters follow): social-share is
 *     NOT emitted here; the caller emits it after the presenters block.
 *
 * @param string $heading             H2 text (from content_title; defaults to "What you'll learn").
 * @param string $intro_html          Optional paragraph before the list (content_description).
 * @param array  $items               Unserialized insights_list rows ({insight_title, insight_description}).
 * @param bool   $include_social_share Whether to embed the social-share block inside.
 */
function momentive_wm_insights_block(
	string $heading,
	string $intro_html,
	array $items,
	bool $include_social_share
): string {
	if ( empty( $items ) ) {
		return '';
	}

	$list_items = '';
	foreach ( $items as $row ) {
		$item_title = trim( (string) ( $row['insight_title'] ?? '' ) );
		$item_desc  = trim( (string) ( $row['insight_description'] ?? '' ) );
		if ( '' === $item_title && '' === $item_desc ) {
			continue;
		}
		$li = '';
		if ( '' !== $item_title ) {
			$li .= '<strong>' . esc_html( $item_title ) . '</strong>';
		}
		if ( '' !== $item_desc ) {
			$li .= ( '' !== $item_title ? '<br>' : '' ) . esc_html( $item_desc );
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

	$inner_parts = [];

	$inner_parts[] = "<!-- wp:paragraph {\"className\":\"h2\",\"style\":{\"typography\":{\"textAlign\":\"center\"}}} -->\n"
		. "<p class=\"has-text-align-center h2\">" . esc_html( $heading ) . "</p>\n"
		. "<!-- /wp:paragraph -->";

	// Optional intro paragraph (content_description) before the list.
	$intro_html = trim( $intro_html );
	if ( '' !== $intro_html ) {
		$stripped = trim( wp_strip_all_tags( $intro_html ) );
		if ( '' !== $stripped ) {
			$inner_parts[] = momentive_wm_p_block( $stripped );
		}
	}

	$inner_parts[] = "<!-- wp:list {\"className\":\"is-style-column-checks two-columns\"} -->\n"
		. "<ul class=\"wp-block-list is-style-column-checks two-columns\">{$list_items}</ul>\n"
		. "<!-- /wp:list -->";

	if ( $include_social_share ) {
		$inner_parts[] = '<!-- wp:momentive/social-share /-->';
	}

	$inner = implode( "\n\n", $inner_parts );

	return "<!-- wp:group {\"className\":\"to-edge\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|large\",\"bottom\":\"var:preset|spacing|large\"}}},\"backgroundColor\":\"superlight-accent\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group to-edge has-superlight-accent-background-color has-background\" style=\"padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)\">"
		. $inner
		. "</div>\n<!-- /wp:group -->";
}

/**
 * Assemble the full webinar page from its parts.
 *
 * Social-share placement (matches the hand-rebuilt posts):
 *   - No presenters + insights:  social-share inside insights group.
 *   - Presenters + insights:     insights group (no social-share) → presenters → social-share.
 *   - Presenters + no insights:  presenters → social-share.
 *   - No presenters + no insights: just social-share.
 *
 * @param string $content_col   Left column inner blocks.
 * @param string $sidebar_col   Right column inner blocks.
 * @param bool   $has_presenters True when presenter IDs were resolved.
 * @param string $insights_block Pre-built insights group markup ('' = not used).
 */
function momentive_wm_page(
	string $content_col,
	string $sidebar_col,
	bool $has_presenters,
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

	$has_insights = '' !== $insights_block;

	if ( $has_presenters && $has_insights ) {
		// Insights (no share inside) → presenters → share.
		$parts[] = $insights_block; // share was excluded when building the block
		$parts[] = momentive_wm_presenters_block();
		$parts[] = '<!-- wp:momentive/social-share /-->';
	} elseif ( $has_presenters ) {
		// No insights: presenters → share.
		$parts[] = momentive_wm_presenters_block();
		$parts[] = '<!-- wp:momentive/social-share /-->';
	} elseif ( $has_insights ) {
		// No presenters: share lives inside the insights group (already embedded).
		$parts[] = $insights_block;
	} else {
		// Neither.
		$parts[] = '<!-- wp:momentive/social-share /-->';
	}

	return implode( "\n\n", $parts );
}

/* -------------------------------------------------------------------------
 * Media: attachment map + sideload
 * ---------------------------------------------------------------------- */

/**
 * Build legacy attachment-ID → fetchable-URL from the same WXR export.
 * The legacy site stores hero images as attachment IDs; this resolves them to
 * their URLs so we can sideload them into the rebuilt media library.
 */
function momentive_wm_build_attachment_map(): array {
	$path = defined( 'MOMENTIVE_WM_LEGACY_WXR' )
		? MOMENTIVE_WM_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.webinars.current.2026-07-01.xml';

	$base = defined( 'MOMENTIVE_WM_UPLOADS_BASE' )
		? MOMENTIVE_WM_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/';
	$base = rtrim( $base, '/' ) . '/';

	$map = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Legacy WXR not found at {$path}; media import will be skipped (slots left empty)." );
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
 * Mirrors momentive_cs_sideload() from migrate-case-studies.php, including the
 * SVG note: run with --user=<admin> so Safe SVG's capability check passes.
 */
function momentive_wm_sideload( string $url, int $post_id, bool $dry ): int {
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
 * Video embed code (assets WXR lookup)
 * ---------------------------------------------------------------------- */

/**
 * Build slug → video_embed_code map from the assets WXR.
 *
 * The assets export has one `assets` post per webinar landing page (168 total).
 * Every post carries a `video_embed_code` meta key (Wistia embed snippet).
 * Most asset slugs match the webinar slug exactly; a handful differ by a
 * short prefix/suffix (e.g. `webinar-ace-…` or `video-government-…`).
 * The `momentive_wm_find_video()` helper handles both cases.
 *
 * @return array<string, string>  asset-slug → raw embed code
 */
function momentive_wm_build_video_map(): array {
	$path = defined( 'MOMENTIVE_WM_ASSETS_WXR' )
		? MOMENTIVE_WM_ASSETS_WXR
		: __DIR__ . '/momentivesoftware.assets.current.2026-07-01.xml';

	$map = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Assets WXR not found at {$path}; video_embed_code will be skipped." );
		return $map;
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::warning( 'Could not read assets WXR; video_embed_code will be skipped.' );
		return $map;
	}

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $all_items ) ) {
		return $map;
	}

	foreach ( $all_items[1] as $item ) {
		if ( false === strpos( $item, 'post_type><![CDATA[assets]]>' ) ) {
			continue;
		}
		if ( ! preg_match( '#<wp:post_name><!\[CDATA\[(.*?)\]\]>#', $item, $sm ) ) {
			continue;
		}
		$slug  = $sm[1];
		$embed = '';
		if ( preg_match(
			'#<wp:meta_key><!\[CDATA\[video_embed_code\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item,
			$vm
		) ) {
			$embed = trim( $vm[1] );
		}
		$map[ $slug ] = $embed;
	}

	WP_CLI::log( sprintf( 'Assets WXR: %d asset slugs loaded for video_embed_code lookup.', count( $map ) ) );
	return $map;
}

/**
 * Look up the video embed code for a given webinar slug.
 *
 * Strategy:
 *   1. Exact asset-slug match.
 *   2. Normalized containment: strip non-alphanum + lowercase both slugs; if the
 *      webinar slug contains (or is contained by) exactly one asset slug, use it.
 *      Catches prefix variants like `webinar-ace-…` and suffix variants like
 *      `boardroom-ready-building-a-business-case-for-tech-investment` ↔
 *      `building-a-business-case-for-tech-investment`.
 *
 * @param string                $webinar_slug
 * @param array<string, string> $video_map   asset-slug → embed code (from momentive_wm_build_video_map)
 * @return string  embed code, or '' if not found / ambiguous
 */
function momentive_wm_find_video( string $webinar_slug, array $video_map ): string {
	// 1. Exact match.
	if ( array_key_exists( $webinar_slug, $video_map ) ) {
		return $video_map[ $webinar_slug ];
	}

	// 2. Normalized containment — require exactly one candidate.
	$norm = static fn( string $s ): string => (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $s ) );
	$wn   = $norm( $webinar_slug );
	$candidates = [];
	foreach ( $video_map as $aslug => $embed ) {
		$an = $norm( $aslug );
		if ( str_contains( $wn, $an ) || str_contains( $an, $wn ) ) {
			$candidates[ $aslug ] = $embed;
		}
	}

	return ( 1 === count( $candidates ) ) ? array_values( $candidates )[0] : '';
}

/* -------------------------------------------------------------------------
 * Presenter resolution (legacy webinar_presenter repeater → People post IDs)
 * ---------------------------------------------------------------------- */

/**
 * Find a People CPT post by display name.
 * Exact match first; normalized (strip non-alphanum, lowercase) fallback.
 * Cached per run.
 */
function momentive_wm_find_person( string $name ): int {
	static $cache    = [];
	static $norm_idx = null;

	$name = trim( $name );
	if ( '' === $name ) {
		return 0;
	}
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	// 1) Exact title match.
	$ids = get_posts( [
		'post_type'              => 'people',
		'post_status'            => 'any',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'title'                  => $name,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );
	if ( $ids ) {
		return $cache[ $name ] = (int) $ids[0];
	}

	// 2) Normalized match — build an index of all People once.
	if ( null === $norm_idx ) {
		$norm_idx = [];
		$all = get_posts( [
			'post_type'              => 'people',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
		foreach ( $all as $pid ) {
			$k = momentive_wm_norm( get_the_title( $pid ) );
			if ( '' !== $k && ! isset( $norm_idx[ $k ] ) ) {
				$norm_idx[ $k ] = (int) $pid;
			}
		}
	}

	$nk = momentive_wm_norm( $name );
	if ( '' !== $nk && isset( $norm_idx[ $nk ] ) ) {
		return $cache[ $name ] = $norm_idx[ $nk ];
	}

	return $cache[ $name ] = 0;
}

/**
 * Create a People post for a webinar presenter not yet in the database.
 * Returns the new post ID (0 on dry-run or failure).
 */
function momentive_wm_create_person( string $name, string $description, bool $dry ): int {
	if ( $dry ) {
		WP_CLI::log( "    [dry-run] would CREATE person: \"{$name}\" ({$description})" );
		return 0;
	}

	$post_id = wp_insert_post( [
		'post_type'   => 'people',
		'post_status' => 'publish',
		'post_title'  => $name,
	], true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "    failed to create person \"{$name}\": " . $post_id->get_error_message() );
		return 0;
	}

	if ( '' !== $description ) {
		update_field( 'job_position', $description, $post_id );
	}

	wp_set_object_terms( (int) $post_id, [ 'presenter' ], 'person_role', true );
	update_post_meta( (int) $post_id, MOMENTIVE_WM_RUN_META, momentive_wm_run_id() );

	return (int) $post_id;
}

/**
 * Resolve a PHP-serialized webinar_presenter repeater into an array of People
 * post IDs. Creates missing People posts when dry=false.
 *
 * The legacy repeater shape is:
 *   array { "item-N" => array { presenter_name, presenter_description, ... } }
 *
 * @param string   $serialized         Raw meta value from the WXR.
 * @param bool     $dry                Dry-run flag; creation is skipped when true.
 * @param string[] &$unresolved_log    Accumulator for names that couldn't be resolved.
 * @param string   $post_title         Used in log messages.
 * @return int[]
 */
function momentive_wm_resolve_presenters(
	string $serialized,
	bool $dry,
	array &$unresolved_log,
	string $post_title
): array {
	$serialized = trim( $serialized );
	if ( '' === $serialized ) {
		return [];
	}

	$data = @unserialize( $serialized );
	if ( ! is_array( $data ) ) {
		WP_CLI::warning( "    presenter unserialize failed for: {$post_title}" );
		return [];
	}

	$ids      = [];
	$norm_idx = []; // track newly-created within this call to dedup siblings

	foreach ( $data as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$name = trim( (string) ( $row['presenter_name'] ?? '' ) );
		$desc = trim( (string) ( $row['presenter_description'] ?? '' ) );

		if ( '' === $name ) {
			continue;
		}

		$pid = momentive_wm_find_person( $name );

		if ( $pid > 0 ) {
			WP_CLI::log( "    presenter matched:  \"{$name}\" → #{$pid}" );
			$ids[] = $pid;
		} else {
			// Check if we just created this person earlier in the same loop
			// (two rows with the same name in the same webinar's presenter list).
			$nk = momentive_wm_norm( $name );
			if ( isset( $norm_idx[ $nk ] ) ) {
				WP_CLI::log( "    presenter deduped:  \"{$name}\" → #{$norm_idx[$nk]} (already created this run)" );
				$ids[] = $norm_idx[ $nk ];
				continue;
			}

			$new_pid = momentive_wm_create_person( $name, $desc, $dry );
			if ( $new_pid > 0 ) {
				WP_CLI::log( "    presenter CREATED:  \"{$name}\" ({$desc}) → #{$new_pid}" );
				$ids[]          = $new_pid;
				$norm_idx[ $nk ] = $new_pid;
			} else {
				if ( ! $dry ) {
					$unresolved_log[] = "{$post_title}: presenter \"{$name}\" ({$desc})";
				}
			}
		}
	}

	return array_values( array_unique( $ids ) );
}

/* -------------------------------------------------------------------------
 * Legacy WXR parser
 * ---------------------------------------------------------------------- */

/**
 * Parse all legacy webinars post items from the WXR export.
 * Each item returns: id, title, slug, status, date*, meta, cats.
 *
 * @return array[]
 */
function momentive_wm_load_legacy_posts(): array {
	$path = defined( 'MOMENTIVE_WM_LEGACY_WXR' )
		? MOMENTIVE_WM_LEGACY_WXR
		: __DIR__ . '/momentivesoftware.webinars.current.2026-07-01.xml';

	$out = [];
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Legacy WXR not found at {$path}. Place the export next to this script or set MOMENTIVE_WM_LEGACY_WXR." );
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
		// NOTE: the literal string in the file is "webinars" (with the trailing s).
		if ( false === strpos( $item, 'post_type><![CDATA[webinars]]>' ) ) {
			continue;
		}

		$meta = [];
		if ( preg_match_all(
			'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item, $mm, PREG_SET_ORDER
		) ) {
			foreach ( $mm as $pair ) {
				// First occurrence wins — mirrors single-value get_post_meta.
				if ( ! array_key_exists( $pair[1], $meta ) ) {
					$meta[ $pair[1] ] = $pair[2];
				}
			}
		}

		$cats = [];
		if ( preg_match_all( '#<category domain="category" nicename="([^"]*)">#', $item, $cm ) ) {
			$cats = array_values( array_unique( $cm[1] ) );
		}

		$out[] = [
			'id'          => (int) momentive_wm_xml_tag( $item, 'wp:post_id' ),
			'title'       => momentive_wm_xml_tag( $item, 'title' ),
			'slug'        => momentive_wm_xml_tag( $item, 'wp:post_name' ),
			'status'      => momentive_wm_xml_tag( $item, 'wp:status' ) ?: 'publish',
			'excerpt'     => momentive_wm_xml_tag( $item, 'excerpt:encoded' ),
			'date'        => momentive_wm_xml_tag( $item, 'wp:post_date' ),
			'date_gmt'    => momentive_wm_xml_tag( $item, 'wp:post_date_gmt' ),
			'modified'    => momentive_wm_xml_tag( $item, 'wp:post_modified' ),
			'modified_gmt'=> momentive_wm_xml_tag( $item, 'wp:post_modified_gmt' ),
			'meta'        => $meta,
			'cats'        => $cats,
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
function momentive_wm_get_flags( array $argv = [] ): array {
	$flags = [
		'dry_run' => true, // DRY-RUN BY DEFAULT — `live` token required to write
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

function momentive_wm_run( array $argv = [] ): void {
	$flags = momentive_wm_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  Webinar migration' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( '' !== $flags['only'] )   { WP_CLI::log( '  only:  slug "' . $flags['only'] . '"' ); }
	if ( $flags['limit'] > 0 )     { WP_CLI::log( '  limit: ' . $flags['limit'] . ' posts' ); }
	if ( ! $dry ) {
		WP_CLI::log( '  (pass no token, or "dry-run", to preview without writing)' );
	}
	WP_CLI::log( '====================================================' );

	$attach_map = momentive_wm_build_attachment_map();
	$video_map  = momentive_wm_build_video_map();

	$legacy_all = momentive_wm_load_legacy_posts();
	WP_CLI::log( sprintf( 'Legacy WXR: %d webinar items parsed.', count( $legacy_all ) ) );

	// Apply only / limit filters.
	if ( '' !== $flags['only'] ) {
		$slug_filter = $flags['only'];
		$legacy_all  = array_values( array_filter(
			$legacy_all,
			static fn( $p ) => $p['slug'] === $slug_filter
		) );
		if ( empty( $legacy_all ) ) {
			WP_CLI::error( "No legacy webinar found with slug \"{$slug_filter}\"." );
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
		'video_codes_linked'  => 0,
		'presenters_matched' => 0,
		'presenters_created' => 0,
		'insights_sections'  => 0,
		'quote_blocks'       => 0,
		'cats_linked'        => 0,
	];
	$unresolved_presenters = [];
	$media_unresolved      = [];
	$cat_unresolved        = [];

	foreach ( $legacy_all as $legacy ) {
		$summary['processed']++;
		WP_CLI::log( sprintf( "\n[%d] %s", $legacy['id'], $legacy['title'] ) );

		$m     = $legacy['meta'];
		$title = $legacy['title'];

		// ---- Date/time fields --------------------------------------------
		$webinar_date  = momentive_wm_date_from_ts( $m['webinar_date']     ?? '' );
		$webinar_end   = momentive_wm_date_from_ts( $m['webinar_end_date'] ?? '' );
		$time_start    = momentive_wm_normalize_time( $m['webinar_time_start'] ?? '' );
		$time_end      = momentive_wm_normalize_time( $m['webinar_time_end']   ?? '' );
		$timezone      = trim( (string) ( $m['webinar_timezone'] ?? 'ET' ) );
		$webinar_type  = trim( (string) ( $m['webinar_type']     ?? 'on-demand' ) );

		// Series: has a non-empty end date (multi-day/multi-session event).
		$is_series = '' !== $webinar_end;

		// ---- HubSpot form ------------------------------------------------
		$raw_form = momentive_wm_fix_hubspot( (string) ( $m['hubspot_form_code'] ?? '' ) );

		// ---- Presenter resolution ----------------------------------------
		$presenter_ids  = momentive_wm_resolve_presenters(
			(string) ( $m['webinar_presenter'] ?? '' ),
			$dry,
			$unresolved_presenters,
			$title
		);
		$has_presenters = ! empty( $presenter_ids );
		$summary['presenters_matched'] += count( $presenter_ids );

		// ---- Content fields ----------------------------------------------
		$resource_details  = momentive_wm_strip_word( (string) ( $m['resource_details'] ?? '' ) );
		$checklist_title   = trim( (string) ( $m['resource_checklist_title'] ?? '' ) );
		$checklist_raw     = maybe_unserialize( (string) ( $m['resource_checklist'] ?? '' ) );
		$checklist_items   = is_array( $checklist_raw ) ? array_values( $checklist_raw ) : [];
		$after_checklist   = momentive_wm_strip_word( (string) ( $m['resource_details_after_checklist'] ?? '' ) );

		$enable_quote = filter_var(
			$m['resource_enable_quote_box'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$quote_text  = trim( (string) ( $m['resource_quote']              ?? '' ) );
		$quote_name  = trim( (string) ( $m['resource_quote_source_name']  ?? '' ) );
		$quote_desc  = trim( (string) ( $m['resource_quote_source_description'] ?? '' ) );

		$enable_insights = filter_var(
			$m['enable_insights_section'] ?? 'false',
			FILTER_VALIDATE_BOOLEAN
		);
		$insights_title  = trim( (string) ( $m['content_title']       ?? "What you'll learn" ) );
		$insights_desc   = trim( (string) ( $m['content_description'] ?? '' ) );
		$insights_raw    = maybe_unserialize( (string) ( $m['insights_list'] ?? '' ) );
		$insights_items  = is_array( $insights_raw ) ? array_values( $insights_raw ) : [];

		$form_heading = trim( (string) ( $m['form_heading'] ?? '' ) );

		// ---- Dry-run summary ---------------------------------------------
		if ( $dry ) {
			$hero_id  = (int) ( $m['resource_hero_image'] ?? 0 );
			$hero_url = ( $hero_id && isset( $attach_map[ $hero_id ] ) )
				? $attach_map[ $hero_id ]
				: '';
			if ( $hero_id && '' === $hero_url ) {
				$media_unresolved[] = "{$title}: hero ID {$hero_id} not in attachment map";
			}
			WP_CLI::log( sprintf(
				'    [dry-run] type=%-10s series=%-3s presenters=%d insights=%-3s quote=%-3s hero=%s',
				$webinar_type,
				$is_series ? 'yes' : 'no',
				count( $presenter_ids ),
				$enable_insights ? 'yes' : 'no',
				( $enable_quote && '' !== $quote_text ) ? 'yes' : 'no',
				'' !== $hero_url ? 'yes' : 'no'
			) );
			if ( $enable_insights && ! empty( $insights_items ) ) {
				$summary['insights_sections']++;
			}
			if ( $enable_quote && '' !== $quote_text ) {
				$summary['quote_blocks']++;
			}
			continue;
		}

		// ---- Upsert the post shell ----------------------------------------
		$slug = $legacy['slug'];

		$existing = get_posts( [
			'post_type'      => MOMENTIVE_WM_CPT,
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
		$shell   = [
			'post_type'    => MOMENTIVE_WM_CPT,
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
			update_post_meta( $new_id, MOMENTIVE_WM_RUN_META, momentive_wm_run_id() );
		}

		// ---- Featured image (legacy _thumbnail_id) ----------------------
		// This is the archive card image, separate from the singular hero.
		$thumb_legacy_id = (int) ( $m['_thumbnail_id']      ?? 0 );
		$hero_legacy_id  = (int) ( $m['resource_hero_image'] ?? 0 );
		$thumb_att       = 0;

		if ( $thumb_legacy_id ) {
			if ( isset( $attach_map[ $thumb_legacy_id ] ) ) {
				$thumb_att = momentive_wm_sideload( $attach_map[ $thumb_legacy_id ], $new_id, false );
				if ( $thumb_att > 0 ) {
					set_post_thumbnail( $new_id, $thumb_att );
					$summary['thumbnails_imported']++;
				}
			} else {
				$media_unresolved[] = "{$title}: thumbnail legacy ID {$thumb_legacy_id} not in attachment map";
			}
		}

		// ---- Hero image ACF field (legacy resource_hero_image) -----------
		// Optional singular-view override. Only set when different from the
		// featured image — when they're the same, the featured image handles it
		// and there's no need for an explicit override.
		if ( $hero_legacy_id && $hero_legacy_id !== $thumb_legacy_id ) {
			if ( isset( $attach_map[ $hero_legacy_id ] ) ) {
				$hero_att = momentive_wm_sideload( $attach_map[ $hero_legacy_id ], $new_id, false );
				if ( $hero_att > 0 ) {
					update_field( 'hero_image', $hero_att, $new_id );
					$summary['hero_imported']++;
				}
			} else {
				$media_unresolved[] = "{$title}: hero legacy ID {$hero_legacy_id} not in attachment map";
			}
		}

		// ---- ACF Webinar Settings fields ----------------------------------
		update_field( 'webinar_type', $webinar_type, $new_id );
		update_field( 'is_series', $is_series ? 1 : 0, $new_id );
		if ( '' !== $webinar_date ) { update_field( 'webinar_date', $webinar_date, $new_id ); }
		if ( '' !== $webinar_end )  { update_field( 'webinar_end_date', $webinar_end, $new_id ); }
		if ( '' !== $time_start )   { update_field( 'webinar_time_start', $time_start, $new_id ); }
		if ( '' !== $time_end )     { update_field( 'webinar_time_end', $time_end, $new_id ); }
		if ( '' !== $timezone )     { update_field( 'webinar_timezone', $timezone, $new_id ); }

		// The legacy site has one form code field; the rebuilt site has separate
		// form_upcoming and form_ondemand. Store in the matching field. For
		// upcoming webinars that will later transition to on-demand, the render
		// template's fallback (form_upcoming → form_ondemand) means the same
		// code will continue to work after the transition — no manual update needed.
		if ( '' !== $raw_form ) {
			if ( 'upcoming' === $webinar_type ) {
				update_field( 'form_upcoming', $raw_form, $new_id );
			} else {
				update_field( 'form_ondemand', $raw_form, $new_id );
			}
		}

		// ---- Video embed code ------------------------------------------------
		$video_embed = momentive_wm_find_video( $slug, $video_map );
		if ( '' !== $video_embed ) {
			update_field( 'video_embed_code', $video_embed, $new_id );
			$summary['video_codes_linked']++;
		} else {
			WP_CLI::log( "    [video] no embed code found for slug: {$slug}" );
		}

		// Presenters (array of People post IDs).
		if ( ! empty( $presenter_ids ) ) {
			update_field( 'presenters', $presenter_ids, $new_id );
		}

		// ---- webinar_type_tax taxonomy ------------------------------------
		$tax_term = get_term_by( 'slug', $webinar_type, 'webinar_type_tax' );
		if ( $tax_term ) {
			wp_set_object_terms( $new_id, [ (int) $tax_term->term_id ], 'webinar_type_tax' );
		}

		// ---- Solution categories ------------------------------------------
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

		// ---- Build post content ------------------------------------------

		// Left column.
		$content_parts = [];
		$content_parts[] = momentive_wm_back_link_block();
		$content_parts[] = '<!-- wp:acf/webinar-status {"name":"acf/webinar-status","mode":"preview"} /-->';
		$content_parts[] = '<!-- wp:post-title {"level":1} /-->';
		$content_parts[] = '<!-- wp:acf/webinar-schedule {"name":"acf/webinar-schedule","mode":"preview"} /-->';

		foreach ( momentive_wm_html_to_blocks( $resource_details ) as $b ) {
			$content_parts[] = $b;
		}

		if ( '' !== $checklist_title ) {
			// The checklist title is treated as a lead-in paragraph, matching
			// the pattern seen in the hand-rebuilt posts (plain bold paragraph,
			// not a heading block).
			$content_parts[] = momentive_wm_p_block(
				'<strong>' . esc_html( $checklist_title ) . '</strong>'
			);
		}

		$clist = momentive_wm_checklist_block( $checklist_items );
		if ( '' !== $clist ) {
			$content_parts[] = $clist;
		}

		foreach ( momentive_wm_html_to_blocks( $after_checklist ) as $b ) {
			$content_parts[] = $b;
		}

		if ( $enable_quote && '' !== $quote_text ) {
			$qblock = momentive_wm_quote_block( $quote_text, $quote_name, $quote_desc );
			if ( '' !== $qblock ) {
				$content_parts[] = $qblock;
				$summary['quote_blocks']++;
			}
		}

		$left_col = implode( "\n\n", array_filter( $content_parts ) );

		// Right column (sidebar).
		$sidebar_parts   = [];
		$sidebar_parts[] = '<!-- wp:post-featured-image /-->';
		$sidebar_parts[] = momentive_wm_form_heading_block( $form_heading );
		$sidebar_parts[] = momentive_wm_hubspot_form_block();
		$right_col       = implode( "\n\n", $sidebar_parts );

		// Insights block — social-share placement depends on whether presenters follow.
		$insights_block = '';
		if ( $enable_insights && ! empty( $insights_items ) ) {
			// Share lives INSIDE the group only when no presenters follow it.
			$insights_block = momentive_wm_insights_block(
				$insights_title,
				$insights_desc,
				$insights_items,
				! $has_presenters  // include_social_share
			);
			if ( '' !== $insights_block ) {
				$summary['insights_sections']++;
			}
		}

		$post_content = momentive_wm_page( $left_col, $right_col, $has_presenters, $insights_block );

		$res = wp_update_post( [ 'ID' => $new_id, 'post_content' => $post_content ], true );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( '    content write failed: ' . $res->get_error_message() );
			continue;
		}

		// Restore original modified date. wp_insert/update_post always sets it
		// to "now", so we override via a direct DB write after all post writes
		// for this item are done (same pattern as migrate-case-studies.php).
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

		WP_CLI::log( "    wrote webinar #{$new_id}" );
	}

	/* ---- Summary -------------------------------------------------------- */

	WP_CLI::log( "\n== Summary ==" );
	foreach ( $summary as $k => $v ) {
		WP_CLI::log( sprintf( '  %-22s %d', $k, $v ) );
	}

	if ( $unresolved_presenters ) {
		WP_CLI::log( sprintf(
			"\n== Presenter names with no People post and no creation (%d) ==",
			count( $unresolved_presenters )
		) );
		foreach ( $unresolved_presenters as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	} else {
		WP_CLI::log( "\nAll presenters resolved (matched or created)." );
	}

	if ( $media_unresolved ) {
		WP_CLI::log( "\n== Unresolved hero images (slot left empty, add manually) ==" );
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
momentive_wm_run( isset( $args ) && is_array( $args ) ? $args : [] );
