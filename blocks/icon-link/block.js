/**
 * Icon Link Block — Editor JS
 * blocks/icon-link/block.js
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	const { createElement: el, Fragment } = element;
	const { registerBlockType } = blocks;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, PanelRow, TextControl, SelectControl, ColorPicker, Dropdown, Button } = components;
	const { __ } = i18n;

	// Populated via wp_localize_script( 'momentive-icon-picker', 'momentiveIcons', … )
	const availableIcons = window.momentiveIcons?.available || {};
	const svgData = window.momentiveIcons?.svgData || {};

	function renderIconInline( slug ) {
		const data = svgData[ slug ];
		if ( ! data || ! data.inner ) return null;
	
		return el( 'svg', {
			viewBox: data.viewBox,
			'aria-hidden': 'true',
			focusable: 'false',
			dangerouslySetInnerHTML: { __html: data.inner },
		} );
	}

	// -------------------------------------------------------------------------
	// Block registration
	// -------------------------------------------------------------------------

	registerBlockType( 'momentive/icon-link', {
		title: __( 'Icon Link', 'momentive' ),
		icon: 'admin-links',
		category: 'common',
		description: __( 'A link with an icon, title, and optional tagline.', 'momentive' ),

		attributes: {
			url:         { type: 'string',  default: '' },
			title:       { type: 'string',  default: '' },
			tagline:     { type: 'string',  default: '' },
			iconSlug:    { type: 'string',  default: '' },
			iconSize:    { type: 'string',  default: 'small' },
			accentColor: { type: 'string',  default: '' },
		},

		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const { url, title, tagline, iconSlug, iconSize, accentColor } = attributes;

			const IconPicker = window.momentive?.IconPicker;

			const hasContent  = title || iconSlug;

			// Inline style for accent color preview in the canvas
			const accentStyle = accentColor
				? { '--icon-link-accent': accentColor }
				: {};

			return el( Fragment, null,

				// -----------------------------------------------------------------
				// Sidebar
				// -----------------------------------------------------------------
				el( InspectorControls, { key: 'inspector' },

					// Link settings
					el( PanelBody, { title: __( 'Link', 'momentive' ), initialOpen: true },
						el( TextControl, {
							label: __( 'URL', 'momentive' ),
							value: url,
							onChange: ( value ) => setAttributes( { url: value } ),
							type: 'url',
							placeholder: 'https://',
						} ),
						el( TextControl, {
							label: __( 'Title', 'momentive' ),
							value: title,
							onChange: ( value ) => setAttributes( { title: value } ),
						} ),
						el( TextControl, {
							label: __( 'Tagline', 'momentive' ),
							help: __( 'Optional sentence displayed below the title.', 'momentive' ),
							value: tagline,
							onChange: ( value ) => setAttributes( { tagline: value } ),
						} )
					),

					// Icon settings
					el( PanelBody, { title: __( 'Icon', 'momentive' ), initialOpen: true },
						IconPicker
							? el( IconPicker, {
								value: iconSlug,
								onChange: ( value ) => setAttributes( { iconSlug: value } ),
								icons: availableIcons,
							} )
							: el( 'p', {}, __( 'Icon picker unavailable.', 'momentive' ) ),
						el( SelectControl, {
							label: __( 'Icon Size', 'momentive' ),
							value: iconSize,
							options: [
								{ label: __( 'Small (24px)', 'momentive' ),  value: 'small' },
								{ label: __( 'Large (48px)', 'momentive' ),  value: 'large' },
							],
							onChange: ( value ) => setAttributes( { iconSize: value } ),
						} )
					),

					// Accent color
					// TODO: when Solutions CPT / shared taxonomy accent colors are in place,
					// look up the linked post's accent here and pre-populate accentColor if blank.
					el( PanelBody, { title: __( 'Accent Color', 'momentive' ), initialOpen: false },
						el( 'p', {
							style: { fontSize: '12px', color: '#757575', marginBottom: '8px' },
						}, __( 'Controls the icon and title color on hover. Leave blank to use the theme default.', 'momentive' ) ),
						el( PanelRow, {},
							el( Dropdown, {
								renderToggle: ( { isOpen, onToggle } ) =>
									el( Button, {
										onClick: onToggle,
										'aria-expanded': isOpen,
										variant: 'secondary',
										style: {
											display: 'flex',
											alignItems: 'center',
											gap: '8px',
										},
									},
										accentColor && el( 'span', {
											style: {
												display: 'inline-block',
												width: '16px',
												height: '16px',
												borderRadius: '50%',
												backgroundColor: accentColor,
												border: '1px solid #ddd',
											},
										} ),
										accentColor
											? accentColor
											: __( 'Choose color…', 'momentive' )
									),
								renderContent: () =>
									el( 'div', { style: { padding: '8px' } },
										el( ColorPicker, {
											color: accentColor,
											onChange: ( value ) => setAttributes( { accentColor: value } ),
											enableAlpha: false,
										} ),
										accentColor && el( Button, {
											variant: 'tertiary',
											isDestructive: true,
											onClick: () => setAttributes( { accentColor: '' } ),
											style: { marginTop: '8px', width: '100%' },
										}, __( 'Clear color', 'momentive' ) )
									),
							} )
						)
					)
				),

				// -----------------------------------------------------------------
				// Canvas preview
				// -----------------------------------------------------------------
				el( 'div', {
					...blockProps,
					className: `momentive-icon-link-preview wp-block-momentive-icon-link${ blockProps.className ? ' ' + blockProps.className : '' }`,
					style: { ...blockProps.style, ...accentStyle },
				},
					! hasContent
						? el( 'p', { className: 'momentive-icon-link-placeholder' },
							__( 'Icon Link: configure in the block settings panel.', 'momentive' )
						)
						: el( 'a', {
							className: `icon-link icon-link--${ iconSize }`,
							href: url || '#',
							onClick: ( e ) => e.preventDefault(),
						},
							iconSlug && el( 'span', {
								className: 'icon-link__icon',
								'aria-hidden': 'true',
							},
								renderIconInline( iconSlug )
							),
							el( 'span', { className: 'icon-link__text' },
								el( 'span', { className: 'icon-link__title' }, title ),
								tagline && el( 'span', { className: 'icon-link__tagline' }, tagline )
							)
						)
				),
			);
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