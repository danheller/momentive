( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var __                = wp.i18n.__;

	registerBlockType( 'momentive/post-cta-button', {
		title:      __( 'Post CTA Button', 'momentive' ),
		category:   'momentive',
		icon:       'button',
		apiVersion: 3,
		attributes: {
			style: { type: 'string', default: 'filled' },
		},
		supports: { html: false },

		edit: function ( props ) {
			var a  = props.attributes;
			var sa = props.setAttributes;

			var blockProps = useBlockProps( {
				style: {
					padding:      '0.75rem 1rem',
					background:   'var(--superlight-accent-color, #eff9fd)',
					border:       '1px dashed var(--accent-color, #0078ff)',
					borderRadius: '0.5rem',
					fontSize:     '0.875rem',
				},
			} );

			return el( Fragment, null,

				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Button Style', 'momentive' ), initialOpen: true },
						el( SelectControl, {
							label:   __( 'Style', 'momentive' ),
							value:   a.style,
							options: [
								{ label: 'Filled',  value: 'filled'  },
								{ label: 'Outline', value: 'outline' },
							],
							onChange: function ( v ) { sa( { style: v } ); },
						} )
					)
				),

				el( 'div', blockProps,
					el( 'p', { style: { margin: 0 } },
						el( 'strong', null, __( 'Post CTA Button', 'momentive' ) ),
						el( 'span', { style: { opacity: 0.6, marginLeft: '0.5rem' } },
							__( 'Renders from ACF "cta_button" field — hidden if empty.', 'momentive' )
						)
					)
				)
			);
		},

		save: function () { return null; },
	} );

} ( window.wp ) );