/**
 * Editor UI for momentive/client-marquee.
 *
 * No build step — uses wp.* globals only.
 * ServerSideRender provides a live preview in the editor canvas.
 */

( function () {
	'use strict';

	const { registerBlockType }         = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, ToggleControl,
	        RangeControl }               = wp.components;
	const { useState, useEffect, createElement: el, Fragment } = wp.element;

	registerBlockType( 'momentive/client-marquee', {

		edit: function ( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const {
				mode, logoVariant, gridSize,
				count, showName, fadedLogos, twoRow, showMask, grayscaleHover,
				filterByCategory, filterByTag,
			} = attributes;

			// Lazy-load taxonomy terms for the filter selects.
			const [ categories, setCategories ] = useState( null );
			const [ tags,       setTags       ] = useState( null );

			useEffect( function () {
				wp.apiFetch( { path: '/wp/v2/categories?per_page=100&orderby=name&order=asc&hide_empty=false' } )
					.then( function ( data ) {
						const opts = [ { label: '— All clients —', value: 0 } ];
						data.forEach( function ( t ) { opts.push( { label: t.name, value: t.id } ); } );
						setCategories( opts );
					} )
					.catch( function () { setCategories( [] ); } );

				wp.apiFetch( { path: '/wp/v2/tags?per_page=100&orderby=name&order=asc&hide_empty=false' } )
					.then( function ( data ) {
						const opts = [ { label: '— All clients —', value: 0 } ];
						data.forEach( function ( t ) { opts.push( { label: t.name, value: t.id } ); } );
						setTags( opts );
					} )
					.catch( function () { setTags( [] ); } );
			}, [] );

			return el(
				Fragment,
				null,

				// ── Inspector sidebar ─────────────────────────────────────────

				el( InspectorControls, null,

					el( PanelBody, { title: 'Display', initialOpen: true },

						el( SelectControl, {
							label:    'Layout',
							value:    mode,
							options:  [
								{ label: 'Marquee (auto-scroll)',  value: 'marquee' },
								{ label: 'Grid (static)',          value: 'grid'    },
							],
							onChange: function ( v ) { setAttributes( { mode: v } ); },
						} ),

						mode === 'marquee' && el( ToggleControl, {
							label:    'Two rows',
							checked:  twoRow,
							help:     'Splits logos across two rows — first scrolls left, second scrolls right.',
							onChange: function ( v ) { setAttributes( { twoRow: v } ); },
						} ),

						mode === 'marquee' && el( ToggleControl, {
							label:    'Fade edges',
							checked:  showMask,
							help:     'Fades logos in and out at the left and right edges.',
							onChange: function ( v ) { setAttributes( { showMask: v } ); },
						} ),

						el( SelectControl, {
							label:    'Logo version',
							value:    logoVariant,
							options:  [
								{ label: 'Monochrome (logo_mono field)',  value: 'mono'  },
								{ label: 'Full color (featured image)',   value: 'color' },
							],
							help: 'Monochrome falls back to the color logo when logo_mono is not set.',
							onChange: function ( v ) { setAttributes( { logoVariant: v } ); },
						} ),

						mode === 'grid' && el( ToggleControl, {
							label:    'Grayscale → color on hover',
							checked:  grayscaleHover,
							help:     'Logos are desaturated by default and reveal full color on hover. Use with "Full color" logo version.',
							onChange: function ( v ) { setAttributes( { grayscaleHover: v } ); },
						} ),

						mode === 'grid' && el( SelectControl, {
							label:    'Grid density',
							value:    gridSize,
							options:  [
								{ label: 'Large  — wide columns, 4 rem gap', value: 'large'  },
								{ label: 'Medium — 2 rem gap',               value: 'medium' },
								{ label: 'Small  — narrow columns, 1 rem gap', value: 'small' },
							],
							onChange: function ( v ) { setAttributes( { gridSize: v } ); },
						} ),
					),

					el( PanelBody, { title: 'Content', initialOpen: true },

						el( RangeControl, {
							label:    'Max logos to show',
							value:    count,
							min:      4,
							max:      60,
							step:     1,
							onChange: function ( v ) { setAttributes( { count: v } ); },
						} ),

						el( ToggleControl, {
							label:    'Faded logos (legacy style)',
							checked:  fadedLogos,
							help:     'Reduces logo opacity to ~40%, matching the softer grey treatment on the legacy site.',
							onChange: function ( v ) { setAttributes( { fadedLogos: v } ); },
						} ),

						el( ToggleControl, {
							label:    'Show name on hover',
							checked:  showName,
							help:     'Displays the organisation name as a subtle overlay when hovering a logo. Useful when logos are acronyms or less well-known marks.',
							onChange: function ( v ) { setAttributes( { showName: v } ); },
						} ),
					),

					el( PanelBody, { title: 'Filter', initialOpen: false },

						el( SelectControl, {
							label:    'Filter by category',
							value:    filterByCategory,
							options:  categories || [ { label: 'Loading…', value: 0 } ],
							help:     'Filter to clients tagged with a specific solution/industry category.',
							onChange: function ( v ) {
								setAttributes( { filterByCategory: parseInt( v, 10 ) || 0 } );
							},
						} ),

						el( SelectControl, {
							label:    'Filter by tag',
							value:    filterByTag,
							options:  tags || [ { label: 'Loading…', value: 0 } ],
							help:     'Filter to clients tagged with a specific tag (e.g. a product name or context).',
							onChange: function ( v ) {
								setAttributes( { filterByTag: parseInt( v, 10 ) || 0 } );
							},
						} ),
					),
				),

				// ── Editor canvas placeholder ─────────────────────────────────
				// The marquee CSS doesn't load inside the editor canvas, so
				// SSR produces an unstyled doubled list. A static placeholder
				// is clearer and avoids the unnecessary REST round-trip.

				el( 'div', blockProps,
					el( 'div', { className: 'momentive-block-placeholder' },
						el( 'strong', null, 'Client Marquee' ),
						el( 'p', null,
							'Up to ' + count + ' logos · ' +
							( mode === 'grid' ? 'grid' : ( twoRow ? 'two-row marquee' : 'marquee' ) ) +
							' · preview on the front end'
						),
					),
				),
			);
		},

		// Dynamic block — no static save output.
		save: function () { return null; },

	} );

} () );
