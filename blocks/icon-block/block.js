/**
 * Icon Block — Editor JS
 * blocks/icon-block/block.js
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	const { createElement: el } = element;
	const { registerBlockType } = blocks;
	const { InspectorControls } = blockEditor;
	const { PanelBody, SelectControl } = components;
	const { __ } = i18n;

	// Populated via wp_localize_script( 'momentive-icon-picker', 'momentiveIcons', … )
	const availableIcons = window.momentiveIcons?.available || {};

	// -------------------------------------------------------------------------
	// Block registration
	// -------------------------------------------------------------------------

	registerBlockType( 'momentive/icon-block', {
		title: __( 'Icon', 'momentive' ),
		icon: 'admin-customizer',
		category: 'common',

		attributes: {
			iconId:          { type: 'string', default: '' },
			shape:           { type: 'string', default: 'circle' },
			backgroundColor: { type: 'string', default: 'pink' },
			strokeColor:     { type: 'string', default: 'default' },
			fillColor:       { type: 'string', default: 'dark-purple' },
		},

		edit( { attributes, setAttributes } ) {
			const IconPicker = window.momentive?.IconPicker;
			
			const { iconId, shape, backgroundColor, strokeColor, fillColor } = attributes;
			const iconLabel = availableIcons[ iconId ] || __( '(none selected)', 'momentive' );
		
			return [
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Icon Settings', 'momentive' ), initialOpen: true },
						IconPicker
							? el( IconPicker, {
								value: iconId,
								onChange: ( value ) => setAttributes( { iconId: value } ),
								icons: availableIcons,
							} )
							: el( 'p', {}, __( 'Icon picker unavailable.', 'momentive' ) ),
						el( SelectControl, {
							label: __( 'Shape', 'momentive' ),
							value: shape,
							options: [
								{ label: 'Circle',        value: 'circle' },
								{ label: 'Square',        value: 'square' },
								{ label: 'Tilted Square', value: 'tilted-square' },
								{ label: 'None',          value: 'none' },
							],
							onChange: ( value ) => setAttributes( { shape: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Background Color', 'momentive' ),
							value: backgroundColor,
							options: [
								{ label: 'Pink',         value: 'pink' },
								{ label: 'Light Purple', value: 'light-purple' },
								{ label: 'Sky Blue',     value: 'sky-blue' },
								{ label: 'Mint',         value: 'mint' },
								{ label: 'White',        value: 'white' },
								{ label: 'None',         value: 'none' },
							],
							onChange: ( value ) => setAttributes( { backgroundColor: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Stroke Color', 'momentive' ),
							value: strokeColor,
							options: [
								{ label: 'Default',     value: 'default' },
								{ label: 'Dark Purple', value: 'dark-purple' },
								{ label: 'Pink',        value: 'pink' },
								{ label: 'Sky Blue',    value: 'sky-blue' },
								{ label: 'White',       value: 'white' },
							],
							onChange: ( value ) => setAttributes( { strokeColor: value } ),
						} ),
						el( SelectControl, {
							label: __( 'Fill Color', 'momentive' ),
							value: fillColor,
							options: [
								{ label: 'None',         value: 'none' },
								{ label: 'Dark Purple',  value: 'dark-purple' },
								{ label: 'Pink',         value: 'pink' },
								{ label: 'Light Purple', value: 'light-purple' },
								{ label: 'Sky Blue',     value: 'sky-blue' },
								{ label: 'Mint',         value: 'mint' },
								{ label: 'White',        value: 'white' },
							],
							onChange: ( value ) => setAttributes( { fillColor: value } ),
						} )
					)
				),

				el( 'div', { className: 'momentive-icon-block-preview' },
					el( 'span', {
						className: `svg-icon shape-${ shape } bg-${ backgroundColor }`,
						style: {
							'--icon-stroke': strokeColor !== 'default' ? `var(--${ strokeColor })` : undefined,
							'--icon-fill':   fillColor   !== 'none'    ? `var(--${ fillColor })`   : undefined,
						},
					},
						el( 'svg', { 'aria-hidden': 'true', focusable: 'false' },
							el( 'use', { href: `#icon-${ iconId }` } )
						)
					),
					el( 'div', { className: 'icon-label' },
						__( 'Icon: ', 'momentive' ) + iconLabel
					)
				),
			];
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
