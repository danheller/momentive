import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Save — renders the panel wrapper with its data attributes, then
 * lets WordPress serialize and output the InnerBlocks content directly.
 *
 * The wrapper div mirrors the markup that the existing template parts
 * produced so that existing CSS and megamenu JS targeting
 * .megamenu-panel and [data-menu] continue to work without changes.
 */
export default function Save( { attributes } ) {
	const { menuSlug } = attributes;

	const blockProps = useBlockProps.save( {
		className: 'megamenu-panel',
		// data-menu is the hook the existing megamenu JS uses to match
		// a panel to its nav trigger. Keep this in sync with menuSlug.
		'data-menu': menuSlug || undefined,
	} );

	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
}
