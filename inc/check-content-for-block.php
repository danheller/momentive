<?php
/**
 * Recursively check if a block (or any nested/referenced block) is present
 * in the current post, including inside synced patterns (wp:block refs).
 */
function momentive_content_has_block( $block_name, $content = null ) {
	if ( $content === null ) {
		if ( ! is_singular() ) return false;
		$content = get_post_field( 'post_content', get_the_ID() );
	}

	if ( ! has_block( $block_name, $content ) && strpos( $content, 'wp:block ' ) === false ) {
		return false;
	}

	$blocks = parse_blocks( $content );

	foreach ( $blocks as $block ) {
		if ( momentive_block_tree_has_block( $block, $block_name ) ) {
			return true;
		}
	}

	return false;
}

function momentive_block_tree_has_block( $block, $block_name ) {
	if ( $block['blockName'] === $block_name ) {
		return true;
	}

	// Reusable block / synced pattern — fetch and recurse.
	if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
		$ref_post = get_post( $block['attrs']['ref'] );
		if ( $ref_post && momentive_content_has_block( $block_name, $ref_post->post_content ) ) {
			return true;
		}
	}

	if ( ! empty( $block['innerBlocks'] ) ) {
		foreach ( $block['innerBlocks'] as $inner ) {
			if ( momentive_block_tree_has_block( $inner, $block_name ) ) {
				return true;
			}
		}
	}

	return false;
}