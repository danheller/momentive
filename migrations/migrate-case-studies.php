<?php
/**
 * migrate-case-studies.php
 *
 * WP-CLI migration: legacy `case_studies` CPT  ->  rebuilt `case-study` CPT.
 *
 * Mirrors the migrate-testimonials.php style:
 *   - WP-CLI command, --dry-run aware, per-item logging, summary counts.
 *
 * Per post it:
 *   1. Strips MS-Word <span> cruft (data-contrast / data-ccp-props) from the
 *      WYSIWYG prose fields.
 *   2. Maps case_study_products_used (CCT IDs) -> product names -> rebuilt
 *      Product posts, written to the post-level `linked_products` field.
 *   3. Copies the case_study_data stats repeater VERBATIM into a
 *      momentive/stat-columns block (value rendered as-is, no parsing).
 *   4. Normalizes each case_study_features icon (mechanical `box-` strip only)
 *      and emits a momentive/icon-list block. Validates each slug against the
 *      sprite manifest; UNRESOLVED slugs are written as-is AND logged.
 *   5. Runs testimonial create-and-reference: match existing testimonials CPT
 *      by normalized quote text; else create a new testimonial post applying
 *      the agreed name-shortening convention; references via momentive/testimonial.
 *   6. Assembles the prose body (intro / challenge+solution / results /
 *      additional / about) into the block scaffold the 6 coverage posts use.
 *
 * USAGE (flags are POSITIONAL — `wp eval-file` rejects --flags):
 *   wp eval-file migrate-case-studies.php                 # LIVE (writes posts)
 *   wp eval-file migrate-case-studies.php dry-run         # no writes
 *   wp eval-file migrate-case-studies.php dry-run limit=6 # dry run, first 6
 *   wp eval-file migrate-case-studies.php only=1743       # single legacy ID
 *   MOMENTIVE_DRY=1 wp eval-file migrate-case-studies.php # dry run via env
 *
 * SOURCE: legacy case_studies data is read from the WXR export file (the
 * migration runs on the REBUILT site, where legacy posts don't exist in the
 * DB). Set MOMENTIVE_LEGACY_WXR to the export path, MOMENTIVE_UPLOADS_BASE for
 * the media host, MOMENTIVE_PRODUCT_CSV / MOMENTIVE_ICON_DIR as needed.
 * Flags are read in momentive_cs_get_flags().
 *
 * SAFETY: testimonial matching fails toward "harmless duplicate", never
 * "silent wrong content". Name shortening was reviewed/approved out-of-band
 * (see testimonial-name-review.csv). Group attributions are kept verbatim;
 * empty-author quotes still create a CPT post with a blank author.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

const MOMENTIVE_CS_LEGACY_TYPE = 'case_studies';
const MOMENTIVE_CS_NEW_TYPE    = 'case-study';
const MOMENTIVE_TESTIMONIAL_TYPE = 'testimonials';

// Meta key stamped on every post/testimonial this migration creates, set to the
// run timestamp. Lets a future run (or a manual query) identify exactly what a
// given migration run touched, for safe rollback.
const MOMENTIVE_RUN_META = '_momentive_migration_run';

/** Per-run identifier (timestamp), stamped on created posts. Stable per process. */
function momentive_cs_run_id(): string {
	static $id = null;
	if ( null === $id ) {
		$id = gmdate( 'Y-m-d\TH:i:s\Z' );
	}
	return $id;
}

// ACF field keys (from the authoritative export).
const FK_STAT_VALUE   = 'field_6a42c6c8357d9';
const FK_STAT_DESC    = 'field_6a42c6ef357da';
const FK_STAT_REPEAT  = 'field_6a42c667b17bc';

// linked-products block field keys (block-level bindings). These MUST appear in
// the block's inline ACF `data` or ACF can't resolve the block's fields and the
// block renders nothing. Values: heading text, show_heading flag, and the
// block-level (override) linked_products selection.
const FK_LP_HEADING       = 'field_6a429fb9316b6';
const FK_LP_SHOW_HEADING  = 'field_6a42a00e316b7';
const FK_LP_PRODUCTS      = 'field_6a42aac112ead'; // block-level override field

// CCT id -> product NAME map. Populated from the Product Settings CCT CSV at
// runtime (momentive_cs_load_product_map). The low-number IDs in
// case_study_products_used are CCT _ID values.
//
// This is intentionally loaded from data, not hard-coded, so it stays correct
// as products are finalized.

/* -------------------------------------------------------------------------
 * Small helpers
 * ---------------------------------------------------------------------- */

/**
 * Strip MS-Word span cruft from a WYSIWYG value, preserving inner text/markup.
 *
 * Word/Office-online paste leaves <span> wrappers with a range of fingerprints:
 *   - data-* attributes: data-contrast, data-ccp-props, data-ccp-charstyle, …
 *   - class tokens: NormalTextRun, TextRun, EOP, SCXW########, BCX#,
 *     SpellingErrorV#Themed, comment/tracked-change classes, etc.
 * These render as "Invalid content" in the block editor. We remove the opening
 * span tags (keeping inner text) and then drop all now-orphaned </span>.
 *
 * Legitimate styling spans are not used in the legacy case-study body, so
 * removing Word spans and their closers is safe here.
 */
function momentive_cs_strip_word( string $html ): string {
	if ( '' === trim( $html ) ) {
		return '';
	}

	// 1) Opening spans carrying ANY Word data-* attribute (data-contrast,
	//    data-ccp-props, data-ccp-charstyle, data-ccp-*, etc.).
	$html = preg_replace(
		'#<span\b[^>]*\bdata-(?:contrast|ccp-[a-z-]+)\b[^>]*>#i',
		'',
		$html
	);

	// 2) Opening spans whose class contains a Word fingerprint token.
	$word_classes = 'NormalTextRun|TextRun|EOP|SCXW[0-9]+|BCX[0-9]+'
		. '|SpellingError[^"\'\s]*|ContextualSpellingAndGrammarError[^"\'\s]*'
		. '|AdvancedProofingIssue[^"\'\s]*|Comment[A-Za-z]*|TrackedChange'
		. '|Selected|Underlined';
	$html = preg_replace(
		'#<span\b[^>]*\bclass="[^"]*\b(?:' . $word_classes . ')\b[^"]*"[^>]*>#i',
		'',
		$html
	);

	// 3) Any remaining bare/styleless spans that were Word output (no semantic
	//    value). Keep spans that carry a real attribute we might want (none in
	//    this corpus), but drop empty <span> and <span style="..."> wrappers.
	$html = preg_replace( '#<span(?:\s+style="[^"]*")?\s*>#i', '', $html );

	// 4) Drop all now-orphaned closing spans.
	$html = preg_replace( '#</span>#i', '', $html );

	// 5) Collapse the empty/&nbsp;-only spacer paragraphs Word leaves behind.
	$html = preg_replace( '#<p>(?:\s|&nbsp;|\x{00A0})*</p>#iu', '', $html );

	return trim( (string) $html );
}

/**
 * Split a WYSIWYG blob into individual <p>…</p> chunks so each becomes its own
 * wp:paragraph block (matching how the coverage posts are structured).
 *
 * @return string[] Array of inner-HTML strings (without the <p> wrapper).
 */
function momentive_cs_html_to_blocks( string $html ): array {
	$html = momentive_cs_strip_word( $html );
	if ( '' === $html ) {
		return array();
	}

	$blocks = array();

	// Walk top-level block elements in document order. We match the common set
	// found in the legacy WYSIWYG: p, h2-h6, ul, ol, blockquote, table.
	// Anything not matched is wrapped as a paragraph so nothing is dropped.
	$pattern = '#<(p|h[2-6]|ul|ol|blockquote|table)\b[^>]*>(.*?)</\1>#is';

	$offset = 0;
	if ( preg_match_all( $pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$tag      = strtolower( $m[1][0] );
			$inner    = trim( $m[2][0] );
			$full_at  = $m[0][1];

			// Capture any stray text/markup BEFORE this block that wasn't wrapped.
			$gap = trim( substr( $html, $offset, $full_at - $offset ) );
			$gap = trim( preg_replace( '#^(?:\s|&nbsp;|\x{00A0})+$#u', '', $gap ) );
			if ( '' !== $gap ) {
				$blocks[] = momentive_cs_p_block( $gap );
			}
			$offset = $full_at + strlen( $m[0][0] );

			if ( '' === $inner || '&nbsp;' === $inner ) {
				continue;
			}

			switch ( true ) {
				case 'p' === $tag:
					$blocks[] = momentive_cs_p_block( $inner );
					break;

				case preg_match( '#^h([2-6])$#', $tag, $hm ) === 1:
					$level    = (int) $hm[1];
					$blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n"
						. "<h{$level} class=\"wp-block-heading\">{$inner}</h{$level}>\n"
						. "<!-- /wp:heading -->";
					break;

				case 'ul' === $tag || 'ol' === $tag:
					$ordered  = 'ol' === $tag ? 'true' : 'false';
					$wp_attr  = 'ol' === $tag ? ' {"ordered":true}' : '';
					$list_tag = $tag;
					// Normalize inner <li> items (keep them; trim whitespace).
					$lis = '';
					if ( preg_match_all( '#<li\b[^>]*>(.*?)</li>#is', $inner, $lim ) ) {
						foreach ( $lim[1] as $li ) {
							$lis .= "<!-- wp:list-item -->\n<li>" . trim( $li ) . "</li>\n<!-- /wp:list-item -->\n";
						}
					}
					$blocks[] = "<!-- wp:list{$wp_attr} -->\n"
						. "<{$list_tag} class=\"wp-block-list\">\n{$lis}</{$list_tag}>\n"
						. "<!-- /wp:list -->";
					break;

				case 'blockquote' === $tag:
					$blocks[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">{$inner}</blockquote>\n<!-- /wp:quote -->";
					break;

				case 'table' === $tag:
					$blocks[] = "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>{$inner}</table></figure>\n<!-- /wp:table -->";
					break;
			}
		}
		// Trailing stray content after the last matched block.
		$tail = trim( substr( $html, $offset ) );
		$tail = trim( preg_replace( '#^(?:\s|&nbsp;|\x{00A0})+$#u', '', $tail ) );
		if ( '' !== $tail ) {
			$blocks[] = momentive_cs_p_block( $tail );
		}
		return $blocks;
	}

	// No recognized block tags at all: treat the whole blob as one paragraph.
	return array( momentive_cs_p_block( $html ) );
}

/**
 * Legacy WYSIWYG -> array of inner paragraph strings (paragraphs only).
 * Retained for the about-section which only emits paragraphs; for general
 * prose use momentive_cs_html_to_blocks() which preserves headings/lists.
 */
function momentive_cs_paragraphs( string $html ): array {
	$html = momentive_cs_strip_word( $html );
	if ( '' === $html ) {
		return array();
	}

	if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $m ) ) {
		$out = array();
		foreach ( $m[1] as $inner ) {
			$inner = trim( $inner );
			if ( '' !== $inner && '&nbsp;' !== $inner ) {
				$out[] = $inner;
			}
		}
		return $out;
	}

	return array( $html );
}

/** Build a wp:paragraph block string. */
function momentive_cs_p_block( string $inner ): string {
	return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
}

/** Build a wp:heading (h3) block string. */
function momentive_cs_h3_block( string $text ): string {
	$text = esc_html( $text );
	return "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">{$text}</h3>\n<!-- /wp:heading -->";
}

/**
 * Normalize a legacy feature icon: strip the leading `box-` only.
 * No bxs->bx fallback — per the migration decision, write legacy faithfully.
 */
function momentive_cs_normalize_icon( string $icon ): string {
	$icon = trim( $icon );
	if ( 0 === strpos( $icon, 'box-' ) ) {
		$icon = substr( $icon, 4 );
	}
	return $icon;
}

/**
 * Normalize a quote string for fuzzy matching against the testimonials CPT.
 * Mirrors the analysis pass: unescape, strip tags, fold smart quotes/dashes,
 * drop [bracketed] editorial inserts, lowercase, collapse to [a-z0-9 ].
 */
function momentive_cs_norm_quote( string $q ): string {
	if ( '' === trim( $q ) ) {
		return '';
	}
	$q = html_entity_decode( $q, ENT_QUOTES, 'UTF-8' );
	$q = wp_strip_all_tags( $q );
	$map = array(
		"\xE2\x80\x99" => "'", "\xE2\x80\x98" => "'",
		"\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
		"\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
		"\xC2\xA0"     => ' ',
	);
	$q = strtr( $q, $map );
	$q = preg_replace( '#\[[^\]]*\]#', ' ', $q );      // editorial inserts
	$q = strtolower( $q );
	$q = preg_replace( '#[^a-z0-9 ]#', ' ', $q );
	$q = preg_replace( '#\s+#', ' ', $q );
	return trim( (string) $q );
}

/* -------------------------------------------------------------------------
 * Name shortening (approved convention)
 *   - Full first name (incl. multi-word) + last initial.
 *   - Drop leading titles (Dr./Mr./…) and post-comma credentials (CFO/DPA/…).
 *   - Already-abbreviated names ("Douglas G.") kept as-is.
 *   - Group attributions kept verbatim.
 *   - Empty author -> empty string (CPT created with blank author).
 * ---------------------------------------------------------------------- */

function momentive_cs_shorten_name( string $name ): string {
	$raw = trim( $name );
	if ( '' === $raw ) {
		return ''; // empty author, by decision.
	}

	$group_hints = array( 'users', 'team', ' app', 'members', 'attendees', 'staff', 'customers', 'community', 'board' );
	$low = strtolower( $raw );
	foreach ( $group_hints as $hint ) {
		if ( false !== strpos( $low, $hint ) ) {
			return $raw; // keep verbatim.
		}
	}

	$titles  = array( 'dr', 'mr', 'mrs', 'ms', 'prof' );
	$suffix  = array( 'jr', 'sr', 'ii', 'iii', 'iv', 'phd', 'dpa', 'msw', 'cfo', 'ceo', 'cae', 'mba', 'cpa', 'md' );

	$head  = trim( explode( ',', $raw )[0] );          // drop post-comma credentials
	$parts = preg_split( '#\s+#', $head, -1, PREG_SPLIT_NO_EMPTY );

	if ( $parts && in_array( rtrim( strtolower( $parts[0] ), '.' ), $titles, true ) ) {
		array_shift( $parts ); // drop leading title
	}
	if ( empty( $parts ) ) {
		return $raw;
	}
	if ( 1 === count( $parts ) ) {
		return $parts[0]; // single token, no last name.
	}

	$last = $parts[ count( $parts ) - 1 ];
	if ( in_array( rtrim( strtolower( $last ), '.' ), $suffix, true ) && count( $parts ) >= 3 ) {
		array_pop( $parts );
		$last = $parts[ count( $parts ) - 1 ];
	}

	// Already abbreviated ("G." / "P.")
	if ( preg_match( '#^[A-Za-z]\.$#', $last ) ) {
		return $raw;
	}

	// Drop middle single-initial tokens (e.g. "Kevin R. Callahan" -> first="Kevin",
	// "Nicole M. Yates" -> first="Nicole"). A middle token is anything between the
	// first token and the last name; if it's a lone initial (one letter +
	// optional period) it's removed. Genuine multi-word first names (e.g.
	// "Lynn Eve", "J. Rex") keep their second word because it is not a lone
	// initial, so they are preserved.
	$first_tokens = array_slice( $parts, 0, -1 );
	if ( count( $first_tokens ) > 1 ) {
		$kept = array( $first_tokens[0] ); // always keep the first given name
		foreach ( array_slice( $first_tokens, 1 ) as $tok ) {
			if ( ! preg_match( '#^[A-Za-z]\.?$#', $tok ) ) {
				$kept[] = $tok; // keep real name words, drop lone initials
			}
		}
		$first_tokens = $kept;
	}
	$first = implode( ' ', $first_tokens );
	return $first . ' ' . strtoupper( substr( $last, 0, 1 ) ) . '.';
}

/* -------------------------------------------------------------------------
 * Product map
 * ---------------------------------------------------------------------- */

/**
 * Build CCT-_ID => product-name from the Product Settings CCT CSV.
 * Path can be overridden via the MOMENTIVE_PRODUCT_CSV constant.
 *
 * Column detection is case-insensitive and tries a few likely header names for
 * each of the ID and name columns. It logs the detected columns and WARNS (and
 * returns empty, so nothing is mis-linked) if it can't find them — rather than
 * silently falling back to columns 0/1, which could map the wrong field.
 *
 * @return array<int,string>
 */
function momentive_cs_load_product_map(): array {
	$path = defined( 'MOMENTIVE_PRODUCT_CSV' )
		? MOMENTIVE_PRODUCT_CSV
		: __DIR__ . '/product-settings-cct.csv';

	$map = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Product CSV not found at {$path}; product mapping will be skipped." );
		return $map;
	}
	if ( ( $fh = fopen( $path, 'r' ) ) === false ) {
		WP_CLI::warning( "Could not open product CSV at {$path}." );
		return $map;
	}

	$header = fgetcsv( $fh );
	if ( ! is_array( $header ) ) {
		fclose( $fh );
		WP_CLI::warning( 'Product CSV has no header row.' );
		return $map;
	}

	// Normalize headers for matching.
	$norm = array_map( static function ( $h ) {
		return strtolower( trim( (string) $h ) );
	}, $header );

	$find = static function ( array $candidates ) use ( $norm ) {
		foreach ( $candidates as $c ) {
			$i = array_search( $c, $norm, true );
			if ( false !== $i ) {
				return $i;
			}
		}
		return false;
	};

	$id_col   = $find( array( '_id', 'id', 'cct_id', 'cct id' ) );
	$name_col = $find( array( 'name', 'product_name', 'product name', 'title', '_item_name' ) );

	if ( false === $id_col || false === $name_col ) {
		fclose( $fh );
		WP_CLI::warning( sprintf(
			'Product CSV columns not recognized. Headers seen: [%s]. '
			. 'Expected an ID column (_ID/id/cct_id) and a name column (name/title). '
			. 'Product mapping skipped — set MOMENTIVE_PRODUCT_CSV or fix headers.',
			implode( ', ', $header )
		) );
		return $map;
	}

	WP_CLI::log( sprintf(
		'Product CSV: using ID column "%s" (#%d) and name column "%s" (#%d).',
		$header[ $id_col ], $id_col, $header[ $name_col ], $name_col
	) );

	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		if ( ! isset( $row[ $id_col ] ) ) {
			continue;
		}
		$id = (int) $row[ $id_col ];
		if ( $id <= 0 ) {
			continue;
		}
		$map[ $id ] = trim( (string) ( $row[ $name_col ] ?? '' ) );
	}
	fclose( $fh );

	WP_CLI::log( sprintf( 'Product CSV: mapped %d CCT IDs to names.', count( $map ) ) );
	return $map;
}

/* -------------------------------------------------------------------------
 * Media: legacy attachment ID -> URL map, and sideload
 * ---------------------------------------------------------------------- */

/**
 * Build legacy attachment-ID => fetchable-URL from the legacy WXR export.
 * The export contains attachment <item>s with _wp_attached_file (e.g.
 * "2025/12/VECCS-logo.svg"); prepended with the uploads base this yields the
 * canonical URL on momentivesoftware.com.
 *
 * Path overridable via MOMENTIVE_LEGACY_WXR; base via MOMENTIVE_UPLOADS_BASE.
 *
 * @return array<int,string>
 */
function momentive_cs_build_attachment_map(): array {
	$path = defined( 'MOMENTIVE_LEGACY_WXR' )
		? MOMENTIVE_LEGACY_WXR
		: __DIR__ . '/momentivesoftware_current-case-studies_2026-06-29.xml';

	$base = defined( 'MOMENTIVE_UPLOADS_BASE' )
		? MOMENTIVE_UPLOADS_BASE
		: 'https://momentivesoftware.com/wp-content/uploads/';
	$base = rtrim( $base, '/' ) . '/';

	$map = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::warning( "Legacy WXR not found at {$path}; media import will be skipped (slots left empty)." );
		return $map;
	}

	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::warning( 'Could not read legacy WXR for attachment map.' );
		return $map;
	}

	// Walk <item> blocks; capture attachment post_id + _wp_attached_file.
	if ( preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		foreach ( $items[1] as $item ) {
			if ( false === strpos( $item, '<wp:post_type><![CDATA[attachment]]>' ) ) {
				continue;
			}
			if ( ! preg_match( '#<wp:post_id>(\d+)</wp:post_id>#', $item, $pm ) ) {
				continue;
			}
			if ( ! preg_match(
				'#<wp:meta_key><!\[CDATA\[_wp_attached_file\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]>#s',
				$item, $fm ) ) {
				continue;
			}
			$map[ (int) $pm[1] ] = $base . ltrim( $fm[1], '/' );
		}
	}

	WP_CLI::log( sprintf( 'Attachment map: %d legacy IDs resolved to URLs.', count( $map ) ) );
	return $map;
}

/* -------------------------------------------------------------------------
 * Legacy source: parse case_studies posts from the WXR export
 *
 * The migration runs on the REBUILT site, where the legacy `case_studies`
 * posts do not exist in the database. So legacy content is read from the WXR
 * export file, not via get_posts()/get_post_meta(). Each parsed item exposes
 * the same shape the loop needs: id, title, slug, status, excerpt, a meta map
 * (ACF fields, repeaters PHP-serialized as in the DB), and category slugs.
 * ---------------------------------------------------------------------- */

/**
 * Extract a single CDATA/plain child tag value from an item block.
 */
function momentive_cs_xml_tag( string $item, string $tag ): string {
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '><!\[CDATA\[(.*?)\]\]></' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Parse all case_studies items from the legacy WXR into structured arrays.
 *
 * @return array<int,array{
 *   id:int, title:string, slug:string, status:string, excerpt:string,
 *   meta:array<string,string>, cats:array<int,string>
 * }>
 */
function momentive_cs_load_legacy_posts(): array {
	$path = defined( 'MOMENTIVE_LEGACY_WXR' )
		? MOMENTIVE_LEGACY_WXR
		: __DIR__ . '/momentivesoftware_current-case-studies_2026-06-29.xml';

	$out = array();
	if ( ! file_exists( $path ) ) {
		WP_CLI::error( "Legacy WXR not found at {$path}. Set MOMENTIVE_LEGACY_WXR or place the export beside the script." );
		return $out;
	}
	$xml = file_get_contents( $path );
	if ( false === $xml ) {
		WP_CLI::error( 'Could not read legacy WXR.' );
		return $out;
	}

	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $xml, $items ) ) {
		return $out;
	}

	foreach ( $items[1] as $item ) {
		if ( false === strpos( $item, '<wp:post_type><![CDATA[case_studies]]>' ) ) {
			continue;
		}

		// Meta: collect every postmeta key/value pair.
		$meta = array();
		if ( preg_match_all(
			'#<wp:meta_key><!\[CDATA\[(.*?)\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[(.*?)\]\]></wp:meta_value>#s',
			$item, $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $pair ) {
				// First occurrence wins (mirrors single-value get_post_meta).
				if ( ! array_key_exists( $pair[1], $meta ) ) {
					$meta[ $pair[1] ] = $pair[2];
				}
			}
		}

		// Categories (Solutions taxonomy is stored as category domain).
		$cats = array();
		if ( preg_match_all(
			'#<category domain="category" nicename="([^"]*)">#',
			$item, $cm ) ) {
			$cats = array_values( array_unique( $cm[1] ) );
		}

		$out[] = array(
			'id'      => (int) momentive_cs_xml_tag( $item, 'wp:post_id' ),
			'title'   => momentive_cs_xml_tag( $item, 'title' ),
			'slug'    => momentive_cs_xml_tag( $item, 'wp:post_name' ),
			'status'  => momentive_cs_xml_tag( $item, 'wp:status' ) ?: 'publish',
			'excerpt' => momentive_cs_xml_tag( $item, 'excerpt:encoded' ),
			'date'         => momentive_cs_xml_tag( $item, 'wp:post_date' ),
			'date_gmt'     => momentive_cs_xml_tag( $item, 'wp:post_date_gmt' ),
			'modified'     => momentive_cs_xml_tag( $item, 'wp:post_modified' ),
			'modified_gmt' => momentive_cs_xml_tag( $item, 'wp:post_modified_gmt' ),
			'meta'    => $meta,
			'cats'    => $cats,
		);
	}

	// Stable order by legacy ID.
	usort( $out, static function ( $a, $b ) {
		return $a['id'] <=> $b['id'];
	} );

	return $out;
}

/**
 * Read a legacy meta value (single), unserializing if it's a serialized array.
 * Mirrors maybe_unserialize( get_post_meta( id, key, true ) ).
 *
 * @return mixed
 */
function momentive_cs_legacy_meta( array $legacy, string $key ) {
	$raw = $legacy['meta'][ $key ] ?? '';
	if ( '' === $raw ) {
		return '';
	}
	return maybe_unserialize( $raw );
}

/**
 * Sideload a file by URL into the rebuilt media library, deduped by source URL.
 * Modeled on migrate-leaders.php's msw_sideload_unique().
 *
 * @return int Attachment ID, or 0 on failure (logged by caller).
 */
function momentive_cs_sideload( string $url, int $post_id, bool $dry ): int {
	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}

	// Dedup: same source URL already imported?
	$existing = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_momentive_source_url',
		'meta_value'     => $url,
		'no_found_rows'  => true,
	) );
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

	$file_array = array(
		'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
		'tmp_name' => $tmp,
	);

	// SVG note: this site uses the Safe SVG plugin, which gates SVG handling on
	// (a) the sanitizer being able to run and (b) user capability. Under WP-CLI
	// there is no logged-in user, so Safe SVG's capability check fails and the
	// sideload is rejected with "not allowed to upload SVG files". The fix is to
	// run the migration as an admin:  wp eval-file … --user=<admin-id-or-login>
	// With a user set, Safe SVG sanitizes and allows the SVG normally.
	//
	// We still register a plain MIME allowance as a fallback for installs where
	// Safe SVG isn't intercepting the sideload path; harmless when it is.
	$ext    = strtolower( pathinfo( $file_array['name'], PATHINFO_EXTENSION ) );
	$is_svg = ( 'svg' === $ext || 'svgz' === $ext );

	$mime_cb = static function ( $mimes ) {
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

/**
 * Normalize a product name for fuzzy matching: lowercase, strip everything
 * except letters/numbers. "Crowd Wisdom" / "CrowdWisdom" / "crowd-wisdom" all
 * collapse to "crowdwisdom". Handles the inconsistent spacing in product names.
 */
function momentive_cs_norm_product( string $name ): string {
	return preg_replace( '/[^a-z0-9]/', '', strtolower( trim( $name ) ) );
}

/**
 * Resolve a rebuilt Product post ID by title. Exact title first, then a
 * normalized (space/punctuation/case-insensitive) match against all products,
 * to absorb naming inconsistencies (e.g. "Crowd Wisdom" vs "CrowdWisdom",
 * "Path LMS" vs "Path"). Cached per-run.
 */
function momentive_cs_product_post_by_name( string $name ): int {
	static $cache = array();
	static $norm_index = null; // normalized-name => product ID

	$name = trim( $name );
	if ( '' === $name ) {
		return 0;
	}
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	// 1) Exact title match (deprecation-safe query).
	$ids = get_posts( array(
		'post_type'              => 'product',
		'post_status'            => 'any',
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'title'                  => $name,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	) );
	if ( $ids ) {
		return $cache[ $name ] = (int) $ids[0];
	}

	// 2) Normalized match. Build a name->ID index of all products once.
	if ( null === $norm_index ) {
		$norm_index = array();
		$all = get_posts( array(
			'post_type'              => 'product',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		foreach ( $all as $pid ) {
			$key = momentive_cs_norm_product( get_the_title( $pid ) );
			if ( '' !== $key && ! isset( $norm_index[ $key ] ) ) {
				$norm_index[ $key ] = (int) $pid;
			}
		}
	}

	$nkey = momentive_cs_norm_product( $name );
	if ( '' !== $nkey && isset( $norm_index[ $nkey ] ) ) {
		return $cache[ $name ] = $norm_index[ $nkey ];
	}

	// 3) Last resort: normalized containment, but ONLY if exactly one product
	//    matches — an ambiguous containment returns 0 (logged by caller) rather
	//    than risking the wrong product.
	if ( '' !== $nkey && strlen( $nkey ) >= 4 ) {
		$candidates = array();
		foreach ( $norm_index as $k => $pid ) {
			if ( strlen( $k ) >= 4 && ( false !== strpos( $k, $nkey ) || false !== strpos( $nkey, $k ) ) ) {
				$candidates[ $pid ] = true;
			}
		}
		if ( 1 === count( $candidates ) ) {
			return $cache[ $name ] = (int) array_key_first( $candidates );
		}
	}

	return $cache[ $name ] = 0;
}

/* -------------------------------------------------------------------------
 * Testimonial corpus (rebuilt CPT) — built once
 * ---------------------------------------------------------------------- */

/**
 * @return array{ by_norm: array<string,int>, list: array<int,array{norm:string}> }
 */
function momentive_cs_build_testimonial_index(): array {
	$index = array( 'by_norm' => array(), 'list' => array() );

	$q = new WP_Query( array(
		'post_type'      => MOMENTIVE_TESTIMONIAL_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );

	foreach ( $q->posts as $tid ) {
		$content = (string) get_field( 'testimonial_content', $tid );
		$norm    = momentive_cs_norm_quote( $content );
		if ( '' === $norm ) {
			continue;
		}
		$index['list'][ $tid ] = array( 'norm' => $norm );
		if ( ! isset( $index['by_norm'][ $norm ] ) ) {
			$index['by_norm'][ $norm ] = $tid;
		}
	}
	return $index;
}

/**
 * Find an existing testimonial by quote. Exact normalized match first, then a
 * conservative substring match (>= 40 chars overlap). Returns post ID or 0.
 */
function momentive_cs_find_testimonial( string $quote, array $index ): int {
	$nq = momentive_cs_norm_quote( $quote );
	if ( '' === $nq ) {
		return 0;
	}
	if ( isset( $index['by_norm'][ $nq ] ) ) {
		return $index['by_norm'][ $nq ];
	}
	foreach ( $index['list'] as $tid => $row ) {
		$cn = $row['norm'];
		if ( ( false !== strpos( $cn, $nq ) || false !== strpos( $nq, $cn ) )
			&& min( strlen( $nq ), strlen( $cn ) ) >= 40 ) {
			return (int) $tid;
		}
	}
	return 0;
}

/**
 * Create a new testimonial CPT post from legacy author fields.
 * Returns the new post ID (0 on dry-run).
 */
function momentive_cs_create_testimonial( array $legacy, bool $dry_run ): int {
	$quote = (string) $legacy['quote'];
	$name  = momentive_cs_shorten_name( (string) $legacy['name'] );
	$desc  = (string) $legacy['desc'];

	if ( $dry_run ) {
		WP_CLI::log( sprintf(
			'    [dry-run] would CREATE testimonial: name=%s | desc=%s | quote=%.40s…',
			'' !== $name ? $name : '(empty)', $desc, $quote
		) );
		return 0;
	}

	$post_id = wp_insert_post( array(
		'post_type'   => MOMENTIVE_TESTIMONIAL_TYPE,
		'post_status' => 'publish',
		'post_title'  => '' !== $name ? $name : wp_trim_words( wp_strip_all_tags( $quote ), 8, '…' ),
	), true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( '    failed to create testimonial: ' . $post_id->get_error_message() );
		return 0;
	}

	update_field( 'testimonial_content', $quote, $post_id );
	update_field( 'testimonial_author_name', $name, $post_id );
	update_field( 'testimonial_author_description', $desc, $post_id );
	update_post_meta( $post_id, MOMENTIVE_RUN_META, momentive_cs_run_id() );

	return (int) $post_id;
}

/* -------------------------------------------------------------------------
 * Block assembly
 * ---------------------------------------------------------------------- */

/** stat-columns block from the legacy case_study_data repeater (verbatim). */
function momentive_cs_stat_block( array $stats ): string {
	if ( empty( $stats ) ) {
		return '';
	}
	$data = array();
	$i = 0;
	foreach ( $stats as $row ) {
		$val  = trim( (string) ( $row['data_title'] ?? '' ) );
		$desc = trim( (string) ( $row['data_description'] ?? '' ) );
		$data[ "stats_{$i}_stat_value" ]        = $val;
		$data[ "_stats_{$i}_stat_value" ]       = FK_STAT_VALUE;
		$data[ "stats_{$i}_stat_description" ]  = $desc;
		$data[ "_stats_{$i}_stat_description" ] = FK_STAT_DESC;
		$i++;
	}
	$data['stats']  = $i;
	$data['_stats'] = FK_STAT_REPEAT;

	$attrs = wp_json_encode( array(
		'name' => 'momentive/stat-columns',
		'data' => $data,
		'mode' => 'preview',
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return "<!-- wp:momentive/stat-columns {$attrs} /-->";
}

/** icon-list block from the legacy case_study_features repeater. */
function momentive_cs_icon_list_block( array $features, array $manifest, array &$unresolved_log, string $title ): string {
	if ( empty( $features ) ) {
		return '';
	}
	$items = array();
	foreach ( $features as $row ) {
		$slug = momentive_cs_normalize_icon( (string) ( $row['feature_icon'] ?? '' ) );
		$text = trim( (string) ( $row['feature_title'] ?? '' ) );
		if ( '' === $slug && '' === $text ) {
			continue;
		}
		// Validate against manifest; log misses, write as-is regardless.
		if ( '' !== $slug && ! isset( $manifest[ $slug ] ) ) {
			$unresolved_log[] = sprintf( '%s: "%s" (text: %.40s)', $title, $slug, $text );
		}
		$items[] = array( 'iconSlug' => $slug, 'text' => $text );
	}
	if ( empty( $items ) ) {
		return '';
	}
	$attrs = wp_json_encode( array( 'showHeading' => false, 'items' => $items ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return "<!-- wp:momentive/icon-list {$attrs} /-->";
}

/** testimonial reference block. */
function momentive_cs_testimonial_block( int $tid ): string {
	if ( $tid <= 0 ) {
		return '';
	}
	$attrs = wp_json_encode(
		array( 'testimonialId' => $tid, 'showCaseStudyButton' => false ),
		JSON_UNESCAPED_SLASHES
	);
	return "<!-- wp:momentive/testimonial {$attrs} /-->";
}

/** Hero logo image block (small-logo). Empty string if no attachment. */
function momentive_cs_logo_block( int $att_id, string $alt ): string {
	if ( $att_id <= 0 ) {
		return '';
	}
	$src = wp_get_attachment_image_url( $att_id, 'large' );
	if ( ! $src ) {
		return '';
	}
	$alt   = esc_attr( $alt );
	$attrs = wp_json_encode(
		array( 'id' => $att_id, 'sizeSlug' => 'large', 'linkDestination' => 'none', 'className' => 'small-logo' ),
		JSON_UNESCAPED_SLASHES
	);
	return "<!-- wp:image {$attrs} -->\n"
		. "<figure class=\"wp-block-image size-large small-logo\"><img src=\"" . esc_url( $src ) . "\" alt=\"{$alt}\" class=\"wp-image-{$att_id}\"/></figure>\n"
		. "<!-- /wp:image -->";
}

/** Download-PDF button block. Empty string if no URL. */
function momentive_cs_download_block( string $url ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}
	return "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n"
		. "<div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"download\"} -->\n"
		. "<div class=\"wp-block-button download\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $url ) . "\">Download full case study</a></div>\n"
		. "<!-- /wp:button --></div>\n<!-- /wp:buttons -->";
}

/**
 * linked-products sidebar block.
 *
 * CRITICAL: an ACF block needs its field keys present in the inline `data`
 * object, or ACF can't bind the block's fields and the block renders nothing on
 * the front end. So we emit the same scaffold the working (pre-migration) posts
 * had — the three block-level field keys, with show_heading enabled. The
 * block-level products override is left empty; the render falls back to the
 * post-level linked_products field (written separately via update_field).
 */
function momentive_cs_linked_products_block(): string {
	$attrs = wp_json_encode( array(
		'name' => 'momentive/linked-products',
		'data' => array(
			FK_LP_HEADING      => '',
			FK_LP_SHOW_HEADING => '1',
			FK_LP_PRODUCTS     => '',
		),
		'mode' => 'preview',
	), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return "<!-- wp:momentive/linked-products {$attrs} /-->";
}

/**
 * Build the sidebar column inner markup (sticky group). Structure varies:
 *   - linked-products block + separator only when $has_products (Ewald, with no
 *     products, omits both — matching the hand-built coverage post).
 *   - "Key Features" heading + icon-list + separator only when features exist.
 *   - CTA paragraph + button always.
 */
function momentive_cs_sidebar( string $icon_list_block, bool $has_products ): string {
	$sep = "<!-- wp:separator {\"className\":\"is-style-wide\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-wide\"/>\n<!-- /wp:separator -->";

	$parts = array();

	if ( $has_products ) {
		$parts[] = momentive_cs_linked_products_block();
		$parts[] = $sep;
	}

	if ( '' !== $icon_list_block ) {
		$parts[] = "<!-- wp:heading {\"fontSize\":\"large\"} -->\n<h2 class=\"wp-block-heading has-large-font-size\">Key Features</h2>\n<!-- /wp:heading -->";
		$parts[] = $icon_list_block;
		$parts[] = $sep;
	}

	$parts[] = "<!-- wp:paragraph {\"style\":{\"typography\":{\"textAlign\":\"center\"}}} -->\n<p class=\"has-text-align-center\">Ready to get started?</p>\n<!-- /wp:paragraph -->";
	$parts[] = "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/solutions/\">Explore our solutions</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";

	$inner = implode( "\n\n", $parts );

	return "<!-- wp:group {\"className\":\"sidebar-sticky\",\"layout\":{\"type\":\"flex\",\"orientation\":\"vertical\"}} -->\n"
		. "<div class=\"wp-block-group sidebar-sticky\"><!-- wp:group {\"className\":\"solutions-block\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group solutions-block\">" . $inner . "</div>\n"
		. "<!-- /wp:group --></div>\n<!-- /wp:group -->";
}

/**
 * Assemble the full case-study page: breadcrumb bar, hero (logo, title,
 * featured image, download button), and the two-column layout. Matches the
 * structure of the rebuilt coverage posts.
 */
function momentive_cs_page( string $logo_block, string $download_block, string $content_col, string $sidebar ): string {
	$breadcrumb = "<!-- wp:group {\"className\":\"breadcrumb-bar\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group breadcrumb-bar\"><!-- wp:momentive/breadcrumbs /--></div>\n"
		. "<!-- /wp:group -->";

	$hero_inner = array();
	if ( '' !== $logo_block )     { $hero_inner[] = $logo_block; }
	$hero_inner[] = '<!-- wp:post-title {"textAlign":"center","level":1,"fontSize":"display-large"} /-->';
	$hero_inner[] = '<!-- wp:post-featured-image {"className":"rounder"} /-->';
	if ( '' !== $download_block ) { $hero_inner[] = $download_block; }

	$hero = "<!-- wp:group {\"className\":\"hero-background\",\"gradient\":\"vertical\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group hero-background has-vertical-gradient-background has-background\"><!-- wp:group {\"className\":\"hero\",\"layout\":{\"type\":\"constrained\"}} -->\n"
		. "<div class=\"wp-block-group hero\">" . implode( "\n\n", $hero_inner ) . "</div>\n"
		. "<!-- /wp:group --></div>\n<!-- /wp:group -->";

	$columns = "<!-- wp:columns {\"isStackedOnMobile\":false,\"className\":\"post-layout\"} -->\n"
		. "<div class=\"wp-block-columns is-not-stacked-on-mobile post-layout\"><!-- wp:column {\"className\":\"post-content\"} -->\n"
		. "<div class=\"wp-block-column post-content\">" . $content_col . "</div>\n"
		. "<!-- /wp:column -->\n\n<!-- wp:column {\"className\":\"post-sidebar\"} -->\n"
		. "<div class=\"wp-block-column post-sidebar\">" . $sidebar . "</div>\n"
		. "<!-- /wp:column --></div>\n<!-- /wp:columns -->";

	return $breadcrumb . "\n\n" . $hero . "\n\n" . $columns;
}

/* -------------------------------------------------------------------------
 * Flags
 * ---------------------------------------------------------------------- */

/**
 * Flag handling for `wp eval-file`.
 *
 * IMPORTANT: `wp eval-file` does NOT accept registered --assoc flags the way a
 * `wp` command does — passing `--dry-run` errors with "unknown parameter".
 * Everything after the filename is delivered to the script as positional
 * $args. So flags are read as positional tokens (and env vars as a fallback):
 *
 *   wp eval-file migrate-case-studies.php                 # LIVE (writes)
 *   wp eval-file migrate-case-studies.php dry-run         # dry run, no writes
 *   wp eval-file migrate-case-studies.php dry-run limit=6 # dry run, first 6
 *   wp eval-file migrate-case-studies.php only=1743       # single legacy ID
 *   MOMENTIVE_DRY=1 wp eval-file migrate-case-studies.php # dry run via env
 *
 * Default is LIVE (per migration decision); dry-run is explicit opt-in.
 */
function momentive_cs_get_flags( array $argv = array() ): array {
	// DRY-RUN BY DEFAULT. Writing requires an explicit `live` token, so a
	// mis-parsed or omitted flag can never cause an accidental live run.
	$flags = array(
		'dry_run' => true,
		'only'    => 0,
		'limit'   => 0,
	);

	// Positional args delivered to the eval-file script (passed in from file scope).
	foreach ( $argv as $tok ) {
		$tok = ltrim( (string) $tok, '-' ); // tolerate a leading -- if the user adds it
		if ( 'live' === $tok || 'go' === $tok ) {
			$flags['dry_run'] = false;
		} elseif ( 'dry-run' === $tok || 'dry_run' === $tok || 'dry' === $tok ) {
			$flags['dry_run'] = true; // explicit, though already the default
		} elseif ( 0 === strpos( $tok, 'only=' ) ) {
			$flags['only'] = (int) substr( $tok, 5 );
		} elseif ( 0 === strpos( $tok, 'limit=' ) ) {
			$flags['limit'] = (int) substr( $tok, 6 );
		}
	}

	// Env override: MOMENTIVE_LIVE=1 enables writes; MOMENTIVE_DRY=1 forces dry.
	if ( getenv( 'MOMENTIVE_LIVE' ) ) { $flags['dry_run'] = false; }
	if ( getenv( 'MOMENTIVE_DRY' ) )  { $flags['dry_run'] = true; }
	if ( getenv( 'MOMENTIVE_ONLY' ) )  { $flags['only']  = (int) getenv( 'MOMENTIVE_ONLY' ); }
	if ( getenv( 'MOMENTIVE_LIMIT' ) ) { $flags['limit'] = (int) getenv( 'MOMENTIVE_LIMIT' ); }

	return $flags;
}

/* -------------------------------------------------------------------------
 * Main
 * ---------------------------------------------------------------------- */

function momentive_cs_run( array $argv = array() ): void {
	$flags = momentive_cs_get_flags( $argv );
	$dry   = $flags['dry_run'];

	WP_CLI::log( '====================================================' );
	WP_CLI::log( '  Case Study migration' );
	WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING POSTS ***' ) );
	if ( $flags['only'] )  { WP_CLI::log( '  only:  legacy ID ' . $flags['only'] ); }
	if ( $flags['limit'] ) { WP_CLI::log( '  limit: ' . $flags['limit'] . ' posts' ); }
	if ( ! $dry ) {
		WP_CLI::log( '  (pass no token, or "dry-run", to preview without writing)' );
	}
	WP_CLI::log( '====================================================' );

	$product_map = momentive_cs_load_product_map();
	$attach_map  = momentive_cs_build_attachment_map();
	$t_index     = momentive_cs_build_testimonial_index();
	WP_CLI::log( sprintf( 'Testimonial corpus: %d posts indexed.', count( $t_index['list'] ) ) );

	$legacy_all = momentive_cs_load_legacy_posts();
	WP_CLI::log( sprintf( 'Legacy WXR: %d case_studies items parsed.', count( $legacy_all ) ) );

	// Apply only/limit filters (the WXR is the source, so we filter in PHP).
	if ( $flags['only'] > 0 ) {
		$legacy_all = array_values( array_filter( $legacy_all, static function ( $p ) use ( $flags ) {
			return $p['id'] === $flags['only'];
		} ) );
	}
	if ( $flags['limit'] > 0 ) {
		$legacy_all = array_slice( $legacy_all, 0, $flags['limit'] );
	}
	$legacy_posts = $legacy_all;

	$summary = array(
		'processed'       => 0,
		'created'         => 0,
		'updated'         => 0,
		'stats_blocks'    => 0,
		'feature_blocks'  => 0,
		'testimonial_ref' => 0,
		'testimonial_new' => 0,
		'no_testimonial'  => 0,
		'products_linked' => 0,
		'pdf_imported'    => 0,
	);
	$unresolved_icons = array();
	$media_unresolved = array();
	$cat_unresolved   = array();
	$pdf_failed       = array();
	$product_unresolved = array();

	foreach ( $legacy_posts as $legacy ) {
		$summary['processed']++;
		$legacy_id = $legacy['id'];
		$title     = $legacy['title'];
		WP_CLI::log( sprintf( "\n[%d] %s", $legacy_id, $title ) );

		$m = $legacy['meta']; // shorthand for raw meta map

		// ---- prose fields ------------------------------------------------
		$intro_header = (string) ( $m['intro_header'] ?? '' );
		$intro_text   = (string) ( $m['intro_text'] ?? '' );
		$chsol_text   = (string) ( $m['challenge_solution_text'] ?? '' );
		$results_text = (string) ( $m['results_text'] ?? '' );
		$add_text     = (string) ( $m['additional_content'] ?? '' );
		$about_text   = (string) ( $m['about_text'] ?? '' );
		$org_name     = (string) ( $m['organization_name'] ?? '' );

		// ---- structured fields ------------------------------------------
		$stats    = momentive_cs_legacy_meta( $legacy, 'case_study_data' );
		$stats    = is_array( $stats ) ? array_values( $stats ) : array();
		$features = momentive_cs_legacy_meta( $legacy, 'case_study_features' );
		$features = is_array( $features ) ? array_values( $features ) : array();
		$prod_ids = momentive_cs_legacy_meta( $legacy, 'case_study_products_used' );
		$prod_ids = is_array( $prod_ids ) ? array_map( 'intval', array_values( $prod_ids ) ) : array();

		// ---- testimonial ------------------------------------------------
		$quote = (string) ( $m['case_study_author_testimonial'] ?? '' );
		$tid   = 0;
		if ( '' !== trim( $quote ) ) {
			$tid = momentive_cs_find_testimonial( $quote, $t_index );
			if ( $tid > 0 ) {
				$summary['testimonial_ref']++;
				WP_CLI::log( "    testimonial: matched existing #{$tid}" );
			} else {
				$new_id = momentive_cs_create_testimonial( array(
					'quote' => $quote,
					'name'  => (string) ( $m['case_study_author_name'] ?? '' ),
					'desc'  => (string) ( $m['case_study_author_description'] ?? '' ),
				), $dry );
				$summary['testimonial_new']++;
				if ( $new_id > 0 ) {
					// add to live index so a later identical quote reuses it.
					$nq = momentive_cs_norm_quote( $quote );
					$t_index['by_norm'][ $nq ] = $new_id;
					$t_index['list'][ $new_id ] = array( 'norm' => $nq );
					$tid = $new_id;
				}
			}
		} else {
			$summary['no_testimonial']++;
		}

		// ---- products ---------------------------------------------------
		$linked_product_ids = array();
		foreach ( $prod_ids as $cct_id ) {
			$pname = $product_map[ $cct_id ] ?? '';
			if ( '' === $pname ) {
				continue;
			}
			$pid = momentive_cs_product_post_by_name( $pname );
			if ( $pid > 0 ) {
				$linked_product_ids[] = $pid;
			} else {
				WP_CLI::log( "    product unresolved: CCT {$cct_id} -> \"{$pname}\" (no Product post)" );
				$product_unresolved[ $pname ] = true;
			}
		}
		$linked_product_ids = array_values( array_unique( $linked_product_ids ) );
		if ( $linked_product_ids ) {
			$summary['products_linked']++;
		}

		// ---- create-or-find the rebuilt post FIRST -----------------------
		// We need the post ID before sideloading media (media_handle_sideload
		// attaches to a post) and before building content that references the
		// new attachment IDs. So we upsert a shell now, fill content below.
		$slug = $legacy['slug'];

		if ( $dry ) {
			// In dry-run we can't create a post; resolve media URLs for logging only.
			$logo_id   = (int) ( $m['case_study_logo'] ?? 0 );
			$hero_id   = (int) ( $m['hero_image'] ?? 0 );
			$pdf_url   = (string) ( $m['case_study_file'] ?? '' );
			$logo_url  = $logo_id && isset( $attach_map[ $logo_id ] ) ? $attach_map[ $logo_id ] : '';
			$hero_url  = $hero_id && isset( $attach_map[ $hero_id ] ) ? $attach_map[ $hero_id ] : '';
			if ( $logo_id && '' === $logo_url ) {
				$media_unresolved[] = "{$title}: logo ID {$logo_id} not in attachment map";
			}
			if ( $hero_id && '' === $hero_url ) {
				$media_unresolved[] = "{$title}: hero ID {$hero_id} not in attachment map";
			}
			WP_CLI::log( sprintf(
				'    [dry-run] would write: testimonial=%s stats=%d features=%d products=%d logo=%s hero=%s pdf=%s',
				$tid > 0 ? "#{$tid}" : 'none',
				count( $stats ), count( $features ), count( $linked_product_ids ),
				$logo_url ? 'yes' : 'no', $hero_url ? 'yes' : 'no', '' !== trim( $pdf_url ) ? 'yes' : 'no'
			) );
			if ( $stats )    { $summary['stats_blocks']++; }
			if ( $features ) { $summary['feature_blocks']++; }
			continue;
		}

		$existing = get_posts( array(
			'post_type'      => MOMENTIVE_CS_NEW_TYPE,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		$shell = array(
			'post_type'   => MOMENTIVE_CS_NEW_TYPE,
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_status' => $legacy['status'],
			'post_excerpt'=> $legacy['excerpt'],
		);

		// Preserve original posted/modified dates from the legacy export. Guard
		// against empty/zero dates (some WXR rows have 0000-00-00). When the
		// local date is set, WordPress derives _gmt if we omit it, but the WXR
		// gives both, so pass both when valid.
		$valid_date = static function ( $d ) {
			$d = trim( (string) $d );
			return ( '' !== $d && 0 !== strpos( $d, '0000-00-00' ) ) ? $d : '';
		};
		$pd  = $valid_date( $legacy['date'] );
		$pdg = $valid_date( $legacy['date_gmt'] );
		$pm  = $valid_date( $legacy['modified'] );
		$pmg = $valid_date( $legacy['modified_gmt'] );

		if ( '' !== $pd )  { $shell['post_date']     = $pd; }
		if ( '' !== $pdg ) { $shell['post_date_gmt'] = $pdg; }
		// post_modified isn't honored by wp_insert/update_post directly (core
		// overwrites it to "now"), so we set it explicitly after the write.

		if ( $existing ) {
			$shell['ID'] = (int) $existing[0];
			$new_id = wp_update_post( $shell, true );
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
			update_post_meta( $new_id, MOMENTIVE_RUN_META, momentive_cs_run_id() );
		}

		// ---- media: sideload logo, hero (featured), PDF ------------------
		$logo_legacy_id = (int) ( $m['case_study_logo'] ?? 0 );
		$hero_legacy_id = (int) ( $m['hero_image'] ?? 0 );
		$pdf_url        = trim( (string) ( $m['case_study_file'] ?? '' ) );

		$logo_att = 0;
		if ( $logo_legacy_id ) {
			if ( isset( $attach_map[ $logo_legacy_id ] ) ) {
				$logo_att = momentive_cs_sideload( $attach_map[ $logo_legacy_id ], $new_id, false );
			} else {
				$media_unresolved[] = "{$title}: logo ID {$logo_legacy_id} not in attachment map";
			}
		}

		$hero_att = 0;
		if ( $hero_legacy_id ) {
			if ( isset( $attach_map[ $hero_legacy_id ] ) ) {
				$hero_att = momentive_cs_sideload( $attach_map[ $hero_legacy_id ], $new_id, false );
				if ( $hero_att > 0 ) {
					set_post_thumbnail( $new_id, $hero_att ); // featured image
				}
			} else {
				$media_unresolved[] = "{$title}: hero ID {$hero_legacy_id} not in attachment map";
			}
		}

		// PDF: sideload to media library, link to the LOCAL copy.
		$pdf_local = '';
		if ( '' !== $pdf_url ) {
			$pdf_att = momentive_cs_sideload( $pdf_url, $new_id, false );
			if ( $pdf_att > 0 ) {
				$pdf_local = (string) wp_get_attachment_url( $pdf_att );
				$summary['pdf_imported']++;
			} else {
				// Sideload failed (e.g. remote TLS). Keep the original URL in the
				// button rather than dropping it, and record for manual follow-up.
				$pdf_local = $pdf_url;
				$pdf_failed[] = "{$title}: {$pdf_url}";
			}
		}

		// ---- build the post-content column (variable body) ---------------
		$content_col = array();

		$tblock = momentive_cs_testimonial_block( $tid );
		if ( '' !== $tblock ) { $content_col[] = $tblock; }

		$sblock = momentive_cs_stat_block( $stats );
		if ( '' !== $sblock ) { $content_col[] = $sblock; $summary['stats_blocks']++; }

		if ( '' !== trim( $intro_header ) ) {
			$content_col[] = momentive_cs_h3_block( $intro_header );
		}
		foreach ( momentive_cs_html_to_blocks( $intro_text ) as $b ) {
			$content_col[] = $b;
		}
		if ( '' !== trim( $chsol_text ) ) {
			$content_col[] = momentive_cs_h3_block( 'Challenge & Solution' );
			foreach ( momentive_cs_html_to_blocks( $chsol_text ) as $b ) {
				$content_col[] = $b;
			}
		}
		if ( '' !== trim( $results_text ) ) {
			$content_col[] = momentive_cs_h3_block( 'Results' );
			foreach ( momentive_cs_html_to_blocks( $results_text ) as $b ) {
				$content_col[] = $b;
			}
		}
		foreach ( momentive_cs_html_to_blocks( $add_text ) as $b ) {
			$content_col[] = $b;
		}

		// About-organization group (matches coverage posts).
		if ( '' !== trim( $org_name ) || '' !== trim( $about_text ) ) {
			$about_inner = array( '<!-- wp:paragraph {"className":"is-style-eyebrow"} -->' . "\n<p class=\"is-style-eyebrow\">About the Organization</p>\n<!-- /wp:paragraph -->" );
			if ( '' !== trim( $org_name ) ) {
				$about_inner[] = "<!-- wp:paragraph {\"fontSize\":\"x-large\"} -->\n<p class=\"has-x-large-font-size\"><strong>" . esc_html( $org_name ) . "</strong></p>\n<!-- /wp:paragraph -->";
			}
			foreach ( momentive_cs_paragraphs( $about_text ) as $p ) {
				$about_inner[] = momentive_cs_p_block( $p );
			}
			$content_col[] = "<!-- wp:group {\"className\":\"about-organization\",\"layout\":{\"type\":\"constrained\"}} -->\n"
				. "<div class=\"wp-block-group about-organization\">" . implode( "\n\n", $about_inner ) . "</div>\n"
				. "<!-- /wp:group -->";
		}

		$content_col[] = '<!-- wp:momentive/social-share /-->';

		// ---- build the sidebar column ------------------------------------
		$iblock = momentive_cs_icon_list_block( $features, momentive_cs_manifest(), $unresolved_icons, $title );
		if ( '' !== $iblock ) { $summary['feature_blocks']++; }

		$sidebar = momentive_cs_sidebar( $iblock, ! empty( $linked_product_ids ) );

		// ---- assemble the full page scaffold -----------------------------
		$post_content = momentive_cs_page(
			momentive_cs_logo_block( $logo_att, $title . ' logo' ),
			momentive_cs_download_block( $pdf_local ),
			implode( "\n\n", $content_col ),
			$sidebar
		);

		// ---- final write -------------------------------------------------
		$res = wp_update_post( array( 'ID' => $new_id, 'post_content' => $post_content ), true );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( '    content write failed: ' . $res->get_error_message() );
			continue;
		}

		// Restore original modified date. wp_insert_post()/wp_update_post()
		// always overwrite post_modified to "now", and we do a content update
		// above, so the only reliable fix is a direct DB write here, AFTER all
		// post writes for this item are done. (post_date was set via the shell.)
		if ( '' !== $pm || '' !== $pmg ) {
			global $wpdb;
			$set = array();
			if ( '' !== $pm )  { $set['post_modified']     = $pm; }
			if ( '' !== $pmg ) { $set['post_modified_gmt'] = $pmg; }
			if ( $set ) {
				$wpdb->update( $wpdb->posts, $set, array( 'ID' => $new_id ) );
				clean_post_cache( $new_id );
			}
		}

		// Post-level linked products (source of truth).
		if ( $linked_product_ids ) {
			update_field( 'linked_products', $linked_product_ids, $new_id );
		}

		// Breadcrumb title (rebuilt posts carry this; default to plain title).
		// Breadcrumb title: the legacy site shows the organization name in the
		// breadcrumb for case studies, so migrate organization_name into the
		// rebuilt breadcrumb_title. Fall back to legacy short_title, then to the
		// post title only as a last resort.
		$bc = trim( (string) ( $m['organization_name'] ?? '' ) );
		if ( '' === $bc ) { $bc = trim( (string) ( $m['short_title'] ?? '' ) ); }
		if ( '' === $bc ) { $bc = $title; }
		update_post_meta( $new_id, 'breadcrumb_title', $bc );

		// Categories: resolve legacy slugs (from WXR) to REBUILT terms; log misses.
		$cats = $legacy['cats'];
		if ( $cats ) {
			$term_ids = array();
			foreach ( $cats as $cslug ) {
				$term = get_term_by( 'slug', $cslug, 'category' );
				if ( $term ) {
					$term_ids[] = (int) $term->term_id;
				} else {
					$cat_unresolved[] = "{$title}: category slug \"{$cslug}\" has no rebuilt term";
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( $new_id, $term_ids, 'category', false );
			}
		}

		WP_CLI::log( "    wrote case-study #{$new_id}" );
	}

	// ---- summary --------------------------------------------------------
	WP_CLI::log( "\n== Summary ==" );
	foreach ( $summary as $k => $v ) {
		WP_CLI::log( sprintf( '  %-16s %d', $k, $v ) );
	}

	if ( $unresolved_icons ) {
		WP_CLI::log( "\n== Unresolved icons (written as-is, fix manually) ==" );
		foreach ( $unresolved_icons as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	} else {
		WP_CLI::log( "\nNo unresolved icons." );
	}

	if ( $media_unresolved ) {
		WP_CLI::log( "\n== Unresolved media (slot left empty, add manually) ==" );
		foreach ( $media_unresolved as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	}

	if ( $pdf_failed ) {
		WP_CLI::log( sprintf( "\n== PDFs that did not sideload (%d) — kept as external links, re-host manually ==", count( $pdf_failed ) ) );
		foreach ( $pdf_failed as $line ) {
			WP_CLI::log( '  ' . $line );
		}
	}

	if ( $product_unresolved ) {
		WP_CLI::log( sprintf( "\n== Product names with no matching Product post (%d) — check naming ==", count( $product_unresolved ) ) );
		foreach ( array_keys( $product_unresolved ) as $pname ) {
			WP_CLI::log( '  ' . $pname );
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

/**
 * Sprite manifest: slug => true for every available icon.
 * Loaded from the icon folder (filenames without .svg) or a manifest file.
 */
function momentive_cs_manifest(): array {
	static $manifest = null;
	if ( null !== $manifest ) {
		return $manifest;
	}
	$manifest = array();

	$dir = defined( 'MOMENTIVE_ICON_DIR' )
		? MOMENTIVE_ICON_DIR
		: get_stylesheet_directory() . '/assets/icons';

	if ( is_dir( $dir ) ) {
		foreach ( glob( $dir . '/*.svg' ) ?: array() as $file ) {
			$manifest[ basename( $file, '.svg' ) ] = true;
		}
	}
	return $manifest;
}

// `wp eval-file` provides positional args as a script-scope $args variable.
// Capture it here (file scope) and thread it through, since functions can't
// see the eval-file local scope.
momentive_cs_run( isset( $args ) && is_array( $args ) ? $args : array() );
