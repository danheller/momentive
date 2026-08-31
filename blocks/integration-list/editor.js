/**
 * Integration List block — editor registration (no build step, plain wp.* globals).
 *
 * Provides the Gutenberg editor experience: a block-level placeholder describing
 * the integration grid + an InnerBlocks CTA slot that renders above the grid on
 * the front end (passed as $content to the PHP render callback).
 */
( function () {
	'use strict';

	var el               = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InnerBlocks      = wp.blockEditor.InnerBlocks;
	var useBlockProps    = wp.blockEditor.useBlockProps;

	var TEMPLATE = [
		[ 'core/heading',   { level: 3, placeholder: 'CTA heading…' } ],
		[ 'core/paragraph', { placeholder: 'Optional description…' } ],
		[ 'core/buttons', {}, [
			[ 'core/button', { text: 'Get started' } ],
		] ],
	];

	var PLACEHOLDER_STYLE = {
		padding:      'var(--wp--preset--spacing--small, 1.5rem)',
		border:       '1.5px dashed var(--accent-color)',
		borderRadius: '1rem',
		color:        'var(--wp--preset--color--contrast, #555)',
		textAlign:    'center',
		fontSize:     'var(--wp--preset--font-size--medium)',
		lineHeight:   '1.4',
		marginBottom: '0.75rem',
	};

	var INNERBLOCKS_STYLE = {
		border:       '1.5px dashed var(--light-accent-color)',
		borderRadius: '1rem',
		padding:      'var(--wp--preset--spacing--small, 1.5rem)',
		background:   'var(--wp--preset--color--superlight-accent, #f0f7ff)',
	};

	var LABEL_STYLE = {
		margin:      '0 0 0.5rem',
		fontSize:    '0.8125rem',
		color:       '#888',
		fontFamily:  'sans-serif',
	};

	registerBlockType( 'momentive/integration-list', {

		edit: function () {
			var blockProps = useBlockProps();

			return el(
				'div', blockProps,

				// Block-level placeholder.
				el( 'div', { style: PLACEHOLDER_STYLE },
					el( 'strong', null, 'Integration List' ),
					el( 'p', { style: { margin: '0.25rem 0 0', fontSize: '0.9375rem', color: '#666' } },
						'Integration grid with Type & Capabilities filters — renders on the front end.'
					)
				),

				// InnerBlocks CTA slot.
				el( 'div', { style: INNERBLOCKS_STYLE },
					el( 'p', { style: LABEL_STYLE }, 'CTA card (shown above the grid):' ),
					el( InnerBlocks, {
						template:     TEMPLATE,
						templateLock: false,
					} )
				)
			);
		},

		save: function () {
			return el( InnerBlocks.Content, null );
		},

	} );

}() );
