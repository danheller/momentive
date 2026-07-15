( function ( wp, $ ) {
	'use strict';

	var registerBlockType  = wp.blocks.registerBlockType;
	var el                 = wp.element.createElement;
	var Fragment           = wp.element.Fragment;
	var useState           = wp.element.useState;
	var useEffect          = wp.element.useEffect;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var PanelBody          = wp.components.PanelBody;
	var SelectControl      = wp.components.SelectControl;
	var __                 = wp.i18n.__;

	// Read all three hidden inputs for the link field.
	// Returns null if no URL is set.
	function getCtaLink() {
		var field = $( '[data-name="cta_button"]' );
		if ( ! field.length ) return null;

		var url    = field.find( '.input-url' ).val();
		var title  = field.find( '.input-title' ).val();
		var target = field.find( '.input-target' ).val();

		if ( ! url ) return null;

		return {
			url:    url,
			title:  title || url,
			target: target || null,
		};
	}

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

			// Initialise from the field's current value so the preview
			// is correct on load without needing a separate on-load call.
			var linkState  = useState( function() { return getCtaLink(); } );
			var ctaLink    = linkState[0];
			var setCtaLink = linkState[1];

			useEffect( function() {
				function handleChange() {
					setCtaLink( getCtaLink() );
				}

				$( document ).on(
					'change',
					'[data-name="cta_button"] input[type="hidden"]',
					handleChange
				);

				// Clean up on unmount.
				return function() {
					$( document ).off(
						'change',
						'[data-name="cta_button"] input[type="hidden"]',
						handleChange
					);
				};
			}, [] );

			var blockProps = useBlockProps( {
				style: {
					padding:      '0.75rem 1rem',
					background:   'var(--superlight-accent-color, #eff9fd)',
					border:       '1px dashed var(--accent-color, #0078ff)',
					borderRadius: '0.5rem',
					fontSize:     '0.875rem',
				},
			} );

			var preview;

			if ( ctaLink ) {
				var isFilled = a.style === 'filled';
				preview = el( 'a', {
					href:    ctaLink.url,
					onClick: function( e ) { e.preventDefault(); },
					style: {
						display:        'inline-block',
						padding:        '0.625rem 1.25rem',
						borderRadius:   '0.375rem',
						fontWeight:     600,
						textDecoration: 'none',
						background:     isFilled ? 'var(--accent-color, #0078ff)' : 'transparent',
						color:          isFilled ? '#fff' : 'var(--accent-color, #0078ff)',
						border:         '2px solid var(--accent-color, #0078ff)',
					},
				},
					ctaLink.title,
					ctaLink.target === '_blank'
						? el( 'span', {
							style: { fontSize: '0.75em', marginLeft: '0.3em', opacity: 0.7 },
							title: __( 'Opens in new tab', 'momentive' ),
						}, '↗' )
						: null
				);
			} else {
				preview = el( 'p', { style: { margin: 0 } },
					el( 'strong', null, __( 'Header CTA Button', 'momentive' ) ),
					el( 'span', { style: { opacity: 0.6, marginLeft: '0.5rem' } },
						__( 'To add, use ACF "cta_button" field — hidden if empty.', 'momentive' )
					)
				);
			}

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Button Style', 'momentive' ), initialOpen: true },
						el( SelectControl, {
							label:    __( 'Style', 'momentive' ),
							value:    a.style,
							options: [
								{ label: 'Filled',  value: 'filled'  },
								{ label: 'Outline', value: 'outline' },
							],
							onChange: function ( v ) { sa( { style: v } ); },
						} )
					)
				),
				el( 'div', blockProps, preview )
			);
		},
		save: function () { return null; },
	} );

} ( window.wp, jQuery ) );