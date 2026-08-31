/**
 * Reviews block — editor registration (no build step, plain wp.* globals).
 *
 * Uses InnerBlocks for the "after review #N" CTA insert slot.
 * save() returns InnerBlocks.Content so WP serialises inner blocks into
 * post_content; the PHP render_callback receives that HTML as $content.
 */
( function () {
	'use strict';

	var el          = wp.element.createElement;
	var Fragment    = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody   = wp.components.PanelBody;
	var __          = wp.i18n.__;

	registerBlockType( 'momentive/reviews', {

		edit: function ( props ) {
			var attributes   = props.attributes;
			var setAttributes = props.setAttributes;
			var perPage      = attributes.perPage;
			var insertAfter  = attributes.insertAfter;

			var blockProps = useBlockProps( { className: 'reviews-editor-preview' } );

			return el(
				Fragment,
				null,

				// ── Inspector panel ──────────────────────────────────────────
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Reviews Settings', 'momentive' ), initialOpen: true },

						el( 'div', { style: { marginBottom: '16px' } },
							el( 'label', {
								style: { display: 'block', marginBottom: '4px', fontWeight: '600', fontSize: '11px', textTransform: 'uppercase' }
							}, __( 'Reviews per page', 'momentive' ) ),
							el( 'input', {
								type: 'number',
								value: perPage,
								min: 1,
								max: 30,
								style: { width: '80px' },
								onChange: function ( e ) {
									var v = parseInt( e.target.value, 10 );
									if ( v > 0 ) setAttributes( { perPage: v } );
								},
							} )
						),

						el( 'div', null,
							el( 'label', {
								style: { display: 'block', marginBottom: '4px', fontWeight: '600', fontSize: '11px', textTransform: 'uppercase' }
							}, __( 'Insert CTA after review #', 'momentive' ) ),
							el( 'input', {
								type: 'number',
								value: insertAfter,
								min: 1,
								max: 20,
								style: { width: '80px' },
								onChange: function ( e ) {
									var v = parseInt( e.target.value, 10 );
									if ( v > 0 ) setAttributes( { insertAfter: v } );
								},
							} )
						)
					)
				),

				// ── Editor canvas ────────────────────────────────────────────
				el( 'div', blockProps,

					// Block-level placeholder — matches .momentive-block-placeholder.
					el( 'div', {
						style: {
							padding:      'var(--wp--preset--spacing--small, 1.5rem)',
							border:       '1.5px dashed var(--accent-color)',
							borderRadius: '1rem',
							color:        'var(--wp--preset--color--contrast, #555)',
							textAlign:    'center',
							fontSize:     'var(--wp--preset--font-size--medium)',
							lineHeight:   '1.4',
							marginBottom: '0.75rem',
						}
					},
						el( 'strong', null, 'Reviews' ),
						el( 'p', { style: { margin: '0.25rem 0 0', fontSize: '0.9375rem', color: '#666' } },
							perPage + ' per page · CTA after review #' + insertAfter
						)
					),

					// InnerBlocks CTA slot — superlight-accent background to distinguish it.
					el( 'div', {
						style: {
							border:       '1.5px dashed var(--light-accent-color)',
							borderRadius: '1rem',
							padding:      'var(--wp--preset--spacing--small, 1.5rem)',
							background:   'var(--wp--preset--color--superlight-accent, #f0f7ff)',
						}
					},
						el( 'p', {
							style: { margin: '0 0 0.5rem', fontSize: '0.8125rem', color: '#888', fontFamily: 'sans-serif' }
						}, 'CTA inserted after review #' + insertAfter + ':' ),
						el( InnerBlocks, null )
					)
				)
			);
		},

		save: function () {
			return el( InnerBlocks.Content, null );
		},

	} );
} )();
