( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var ToggleControl     = wp.components.ToggleControl;
	var __                = wp.i18n.__;

	registerBlockType( 'momentive/post-byline', {
		title:      __( 'Post Byline', 'momentive' ),
		category:   'momentive',
		icon:       'admin-users',
		apiVersion: 3,
		attributes: {
			showModified:    { type: 'boolean', default: true },
			showReadingTime: { type: 'boolean', default: true },
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
					display:      'flex',
					alignItems:   'center',
					gap:          '0.75rem',
				},
			} );

			var parts = [
				'Author photo + name',
				a.showModified    && 'Last updated date',
				a.showReadingTime && 'Reading time',
			].filter( Boolean ).join( ' · ' );

			return el( Fragment, null,

				el( InspectorControls, null,
					el( PanelBody, {
						title: __( 'Byline Settings', 'momentive' ),
						initialOpen: true,
					},
						el( ToggleControl, {
							label:    __( 'Show "Last updated" date', 'momentive' ),
							help:     __( 'Only shown when the modified date is more than 24 hours after the publish date.', 'momentive' ),
							checked:  a.showModified,
							onChange: function ( v ) { sa( { showModified: v } ); },
						} ),
						el( ToggleControl, {
							label:    __( 'Show reading time', 'momentive' ),
							checked:  a.showReadingTime,
							onChange: function ( v ) { sa( { showReadingTime: v } ); },
						} )
					)
				),

				el( 'div', blockProps,
					el( 'div', {
						style: {
							width:        '2.5rem',
							height:       '2.5rem',
							borderRadius: '50%',
							background:   'var(--extralight-accent-color, #e0f3fb)',
							flexShrink:   0,
						}
					} ),
					el( 'div', null,
						el( 'strong', null, __( 'Post Byline', 'momentive' ) ),
						el( 'span', { style: { marginLeft: '0.5rem', opacity: 0.6 } }, parts )
					)
				)
			);
		},

		save: function () { return null; },
	} );

} ( window.wp ) );