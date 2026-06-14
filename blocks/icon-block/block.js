/**
 * Icon Block — Editor JS
 * blocks/icon-block/block.js
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 *
 * Icon color is driven by CSS `color` (currentColor), not a fill variable.
 * Background and color options map to site CSS custom properties.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	const { createElement: el, Fragment } = element;
	const { registerBlockType } = blocks;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, SelectControl } = components;
	const { __ } = i18n;

	// Populated via wp_localize_script( 'momentive-icon-picker', 'momentiveIcons', … )
	const availableIcons = window.momentiveIcons?.available || {};

	// -------------------------------------------------------------------------
	// Color options — map to CSS custom properties via is-color-* classes.
	// Each value becomes a BEM modifier: .svg-icon.is-color-accent, etc.
	// The stylesheet is responsible for setting `color` on each modifier.
	// -------------------------------------------------------------------------

	const COLOR_OPTIONS = [
		{ label: __( 'Accent (blue)',    'momentive' ), value: 'accent'    },
		{ label: __( 'Secondary (orange)', 'momentive' ), value: 'secondary' },
		{ label: __( 'Dark navy',        'momentive' ), value: 'dark'      },
		{ label: __( 'White',            'momentive' ), value: 'white'     },
		{ label: __( 'Midtone (grey)',   'momentive' ), value: 'midtone'   },
	];

	const BACKGROUND_OPTIONS = [
		{ label: __( 'None',             'momentive' ), value: 'none'      },
		{ label: __( 'Light blue',       'momentive' ), value: 'light'     },
		{ label: __( 'Extra light blue', 'momentive' ), value: 'extralight'},
		{ label: __( 'White',            'momentive' ), value: 'white'     },
		{ label: __( 'Accent (blue)',    'momentive' ), value: 'accent'    },
		{ label: __( 'Dark navy',        'momentive' ), value: 'dark'      },
	];

	const SHAPE_OPTIONS = [
		{ label: __( 'None',          'momentive' ), value: 'none'          },
		{ label: __( 'Circle',        'momentive' ), value: 'circle'        },
		{ label: __( 'Square',        'momentive' ), value: 'square'        },
	];

	// -------------------------------------------------------------------------
	// Block registration
	// -------------------------------------------------------------------------

	registerBlockType( 'momentive/icon-block', {
		title: __( 'Icon', 'momentive' ),
		icon: 'admin-customizer',
		category: 'common',

		attributes: {
			iconId:          { type: 'string', default: ''         },
			shape:           { type: 'string', default: 'circle'   },
			backgroundColor: { type: 'string', default: 'light'    },
			iconColor:       { type: 'string', default: 'accent'   },
		},

		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const IconPicker = window.momentive?.IconPicker;
			const { iconId, shape, backgroundColor, iconColor } = attributes;
			const iconLabel = availableIcons[ iconId ] || __( '(none selected)', 'momentive' );

			// Build class string for the preview span
			const iconClasses = [
				'svg-icon',
				shape     !== 'none' ? `shape-${ shape }`            : '',
				backgroundColor !== 'none' ? `bg-${ backgroundColor }` : '',
				`is-color-${ iconColor }`,
			].filter( Boolean ).join( ' ' );

			return el(
				Fragment,
				null,
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'Icon Settings', 'momentive' ), initialOpen: true },
						IconPicker
							? el( IconPicker, {
								value: iconId,
								onChange: ( value ) => setAttributes( { iconId: value } ),
								icons: availableIcons,
							} )
							: el( 'p', {}, __( 'Icon picker unavailable.', 'momentive' ) ),

						el( SelectControl, {
							label:    __( 'Shape', 'momentive' ),
							value:    shape,
							options:  SHAPE_OPTIONS,
							onChange: ( value ) => setAttributes( { shape: value } ),
						} ),

						el( SelectControl, {
							label:    __( 'Background', 'momentive' ),
							value:    backgroundColor,
							options:  BACKGROUND_OPTIONS,
							onChange: ( value ) => setAttributes( { backgroundColor: value } ),
						} ),

						el( SelectControl, {
							label:    __( 'Icon color', 'momentive' ),
							value:    iconColor,
							options:  COLOR_OPTIONS,
							onChange: ( value ) => setAttributes( { iconColor: value } ),
						} )
					)
				),

				el( 'div', { ...blockProps, className: `momentive-icon-block-preview ${ blockProps.className || '' }`.trim() },
					el( 'span', { className: iconClasses },
						el( 'svg', { 'aria-hidden': 'true', focusable: 'false' },
							el( 'use', { href: `#icon-${ iconId }` } )
						)
					),
					el( 'div', { className: 'icon-label' },
						__( 'Icon: ', 'momentive' ) + iconLabel
					)
				),
			)
		},

		save() {
			return null;
		},
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
