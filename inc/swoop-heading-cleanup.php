<?php
/**
 * Swoop heading &nbsp; cleanup.
 *
 * Content pasted in from the legacy site frequently carries stray
 * non-breaking spaces (either the literal `&nbsp;` entity or the raw
 * U+00A0 character), invisible in both the visual and code editors. When
 * one lands inside a `.is-style-has-swoop` heading, it glues adjacent
 * words into a single unbreakable run for the browser's line-breaking
 * algorithm (nothing to do with the swoop SVG itself, which is added
 * client-side by momentive.js). On a long/large heading that run can
 * overflow its line with no valid space to break at, and the browser
 * falls back to breaking mid-word — e.g. "Every member" becoming
 * "Every mem" / "ber" across two lines.
 *
 * Fix at the source: strip stray nbsp characters back to normal spaces
 * inside any heading tag carrying the is-style-has-swoop class, at save
 * time, so the stored post_content can't carry the bug forward. Scoped
 * narrowly to swoop headings only — nbsp is used deliberately elsewhere
 * (e.g. hero paragraphs) and should be left alone.
 *
 * Deliberately NOT implemented via parse_blocks()/serialize_blocks(): that
 * round-trip regenerates every block's HTML comment from its parsed attrs
 * (via wp_json_encode()), which doesn't always reproduce the exact bytes
 * the block editor's own JS serializer wrote. On any post containing a
 * swoop heading, that full-document reserialization silently rewrote every
 * OTHER block too, causing them to mismatch their own save() output on
 * next load ("Block contains unexpected or invalid content" on the whole
 * page, not just the swoop heading). Fixed 2026-07-15 — see git history for
 * the original implementation if needed. Instead, operate directly on the
 * raw post_content string and touch only the bytes between a swoop
 * heading's opening and closing tag.
 */

add_filter( 'wp_insert_post_data', function( array $data, array $postarr ): array {
	if ( empty( $data['post_content'] ) || false === strpos( $data['post_content'], 'is-style-has-swoop' ) ) {
		return $data;
	}

	$data['post_content'] = preg_replace_callback(
		'/<(h[1-6])([^>]*\bis-style-has-swoop\b[^>]*)>(.*?)<\/\1>/s',
		function ( array $m ): string {
			// Both the literal HTML entity and the raw UTF-8 nbsp character
			// (\xC2\xA0) can end up in pasted content — normalize both.
			$inner = str_replace( [ '&nbsp;', "\xC2\xA0" ], ' ', $m[3] );

			return '<' . $m[1] . $m[2] . '>' . $inner . '</' . $m[1] . '>';
		},
		$data['post_content']
	);

	return $data;
}, 10, 2 );
