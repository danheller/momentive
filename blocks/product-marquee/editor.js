/**
 * Product Marquee — block editor registration
 *
 * No configurable attributes exist, so the editor UI is a simple
 * informational placeholder. The render_callback handles all output.
 */

( function () {
	var el            = wp.element.createElement;
	var __            = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;

	wp.blocks.registerBlockType( 'momentive/product-marquee', {

		edit: function () {
			var blockProps = useBlockProps( { className: 'product-marquee-editor-placeholder' } );

			return el( 'div', blockProps,
				el( 'div', { className: 'product-marquee-editor-placeholder__inner' },
					el( 'svg', {
						xmlns:   'http://www.w3.org/2000/svg',
						viewBox: '0 0 24 24',
						width:   32,
						height:  32,
						fill:    'currentColor',
						'aria-hidden': 'true',
					},
						el( 'path', { d: 'M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z' } )
					),
					el( 'p', { className: 'product-marquee-editor-placeholder__label' },
						__( 'Product Marquee', 'momentive' )
					),
					el( 'p', { className: 'product-marquee-editor-placeholder__hint' },
						__( 'Displays all Products as two auto-scrolling rows. Content is drawn live from the Products CPT — no configuration needed.', 'momentive' )
					)
				)
			);
		},

		// Server-side render — no client-side save needed.
		save: function () { return null; },
	} );
} )();
