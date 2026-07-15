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
	var __                = wp.i18n.__;

	registerBlockType( 'momentive/social-share', {
		title:       __( 'Social Share', 'momentive' ),
		category:    'momentive',
		icon:        'share',
		apiVersion:  3,
		attributes: {
			heading:      { type: 'string',  default: 'Share this post' },
			showLinkedIn: { type: 'boolean', default: true },
			showX:        { type: 'boolean', default: true },
			showFacebook: { type: 'boolean', default: true },
			showCopyLink: { type: 'boolean', default: true },
		},
		supports: { html: false },

		edit: function ( props ) {
			var a  = props.attributes;
			var sa = props.setAttributes;

			var blockProps = useBlockProps( {
				style: { fontSize: '0.875rem', opacity: 0.8 },
			} );

			var active = [ a.showCopyLink && 'Link', a.showLinkedIn && 'LinkedIn',
						   a.showX && 'X', a.showFacebook && 'Facebook' ]
				.filter( Boolean ).join( ' · ' ) || 'No buttons enabled';

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Share Buttons', 'momentive' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Heading', 'momentive' ),
							value:    a.heading,
							onChange: function ( v ) { sa( { heading: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Copy link button', 'momentive' ),
							checked: a.showCopyLink,
							onChange: function ( v ) { sa( { showCopyLink: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'LinkedIn', 'momentive' ),
							checked: a.showLinkedIn,
							onChange: function ( v ) { sa( { showLinkedIn: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'X (Twitter)', 'momentive' ),
							checked: a.showX,
							onChange: function ( v ) { sa( { showX: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Facebook', 'momentive' ),
							checked: a.showFacebook,
							onChange: function ( v ) { sa( { showFacebook: v } ); },
						} )
					)
				),
				el( 'div', blockProps,
					el( 'p', { style: { margin: 0, fontWeight: 700 } }, a.heading || 'Share this post' ),
					el( 'p', { style: { margin: '0.25rem 0 0', opacity: 0.6 } }, active )
				)
			);
		},

		save: function () { return null; },
	} );

} ( window.wp ) );