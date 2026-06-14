( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var SelectControl     = wp.components.SelectControl;
	var __                = wp.i18n.__;

	registerBlockType( 'momentive/table-of-contents', {
		title:       __( 'Table of Contents', 'momentive' ),
		description: __( 'Auto-generated in-page navigation from post headings.', 'momentive' ),
		category:    'momentive',
		icon:        'list-view',
		attributes: {
			title:           { type: 'string',  default: 'Contents' },
			maxLevel:        { type: 'integer', default: 2 },
			defaultExpanded: { type: 'boolean', default: true },
		},
		supports: { html: false },

		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;

			var blockProps = useBlockProps( {
				style: {
					padding:      '1rem',
					background:   'var(--superlight-accent-color, #eff9fd)',
					border:       '1px solid var(--menu-border-color, #dbe1f3)',
					borderRadius: '0.75rem',
					fontSize:     '0.875rem',
				},
			} );

			return el(
				Fragment, null,

				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Contents Settings', 'momentive' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Title', 'momentive' ),
							value:    attributes.title,
							onChange: function ( val ) { setAttributes( { title: val } ); },
						} ),
						el( SelectControl, {
							label:   __( 'Include headings up to', 'momentive' ),
							value:   attributes.maxLevel,
							options: [
								{ label: 'H2 only',    value: 2 },
								{ label: 'H2 and H3',  value: 3 },
							],
							onChange: function ( val ) { setAttributes( { maxLevel: parseInt( val ) } ); },
						} ),
						el( ToggleControl, {
							label:    __( 'Expanded by default', 'momentive' ),
							checked:  attributes.defaultExpanded,
							onChange: function ( val ) { setAttributes( { defaultExpanded: val } ); },
						} )
					)
				),

				el( 'div', blockProps,
					el( 'div', {
						style: {
							display:        'flex',
							justifyContent: 'space-between',
							alignItems:     'center',
							fontWeight:     700,
							marginBottom:   '0.5rem',
						}
					},
						el( 'span', null, attributes.title || 'Contents' ),
						el( 'span', { style: { opacity: 0.4, fontSize: '0.75rem' } }, '▾' )
					),
					el( 'p', { style: { margin: 0, opacity: 0.5 } },
						__( 'Links generated from post headings (H2', 'momentive' ) +
						( attributes.maxLevel >= 3 ? __( ' + H3', 'momentive' ) : '' ) +
						').'
					)
				)
			);
		},

		save: function () { return null; },
	} );

} ( window.wp ) );