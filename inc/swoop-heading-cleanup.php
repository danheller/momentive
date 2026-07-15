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
 * inside any core/heading block carrying the is-style-has-swoop class,
 * at save time, so the stored post_content can't carry the bug forward.
 * Scoped narrowly to swoop headings only — nbsp is used deliberately
 * elsewhere (e.g. hero paragraphs) and should be left alone.
 */

add_filter( 'wp_insert_post_data', function( array $data, array $postarr ): array {
	if ( empty( $data['post_content'] ) || false === strpos( $data['post_content'], 'is-style-has-swoop' ) ) {
		return $data;
	}

	$blocks               = parse_blocks( $data['post_content'] );
	$data['post_content'] = serialize_blocks( momentive_clean_swoop_nbsp_in_blocks( $blocks ) );

	return $data;
}, 10, 2 );

/**
 * Recursively walk a parsed block tree and normalize stray nbsp characters
 * inside any core/heading block that carries the is-style-has-swoop class.
 *
 * @param array $blocks Array of blocks as returned by parse_blocks().
 * @return array Modified blocks array.
 */
function momentive_clean_swoop_nbsp_in_blocks( array $blocks ): array {
	foreach ( $blocks as &$block ) {
		$class_name = $block['attrs']['className'] ?? '';

		if (
			'core/heading' === ( $block['blockName'] ?? '' ) &&
			is_string( $class_name ) &&
			false !== strpos( $class_name, 'is-style-has-swoop' )
		) {
			// Both the literal HTML entity and the raw UTF-8 nbsp character
			// (\xC2\xA0) can end up in pasted content — normalize both.
			$block['innerHTML'] = str_replace( [ '&nbsp;', "\xC2\xA0" ], ' ', $block['innerHTML'] );

			if ( ! empty( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as &$chunk ) {
					if ( is_string( $chunk ) ) {
						$chunk = str_replace( [ '&nbsp;', "\xC2\xA0" ], ' ', $chunk );
					}
				}
				unset( $chunk );
			}
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = momentive_clean_swoop_nbsp_in_blocks( $block['innerBlocks'] );
		}
	}
	unset( $block );

	return $blocks;
}
