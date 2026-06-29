/**
 * Icon List Block — Editor JS
 * blocks/icon-list/block.js
 *
 * A repeater of { iconSlug, text } rows. Each row uses the shared visual
 * icon picker (window.momentive.IconPicker), the same control used by
 * icon-block and icon-link.
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 *
 * Stored data: the `items` array attribute. Front-end output is produced
 * by the PHP render_callback (render.php) — save() returns null.
 *
 * Visual treatment matches the hand-built features section: each icon is
 * rendered with no shape, no background, secondary color. That treatment
 * lives in style.css (.icon-list__icon), not in per-row attributes.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	const { createElement: el, Fragment } = element;
	const { registerBlockType } = blocks;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, TextControl, ToggleControl, Button } = components;
	const { __ } = i18n;

	// Populated via wp_localize_script( 'momentive-icon-picker', 'momentiveIcons', … )
	const availableIcons = window.momentiveIcons?.available || {};
	const svgData = window.momentiveIcons?.svgData || {};

	// Inline SVG for canvas preview (same approach as icon-link).
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

	// Immutably replace one row in the items array.
	function updateItem( items, index, patch ) {
		return items.map( ( item, i ) =>
			i === index ? { ...item, ...patch } : item
		);
	}

	// -------------------------------------------------------------------------
	// Block registration
	// -------------------------------------------------------------------------

	registerBlockType( 'momentive/icon-list', {
		title: __( 'Icon List', 'momentive' ),
		icon: 'list-view',
		category: 'momentive',
		description: __( 'A list of items, each with an icon and a short description.', 'momentive' ),

		attributes: {
			items:       { type: 'array',   default: [] },
		},

		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			const { items } = attributes;

			const IconPicker = window.momentive?.IconPicker;

			// ---- Row mutators -------------------------------------------------
			const addItem = () =>
				setAttributes( { items: [ ...items, { iconSlug: '', text: '' } ] } );

			const removeItem = ( index ) =>
				setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );

			const moveItem = ( index, delta ) => {
				const target = index + delta;
				if ( target < 0 || target >= items.length ) return;
				const next = items.slice();
				const [ moved ] = next.splice( index, 1 );
				next.splice( target, 0, moved );
				setAttributes( { items: next } );
			};

			const setRow = ( index, patch ) =>
				setAttributes( { items: updateItem( items, index, patch ) } );

			// ---- Sidebar: per-row editor -------------------------------------
			const rowPanels = items.map( ( item, index ) =>
				el( PanelBody, {
					key: index,
					title: ( item.text || __( '(empty)', 'momentive' ) ) + '  —  ' + ( index + 1 ),
					initialOpen: false,
				},
					IconPicker
						? el( IconPicker, {
							value: item.iconSlug || '',
							onChange: ( value ) => setRow( index, { iconSlug: value } ),
							icons: availableIcons,
						} )
						: el( 'p', {}, __( 'Icon picker unavailable.', 'momentive' ) ),

					el( TextControl, {
						label: __( 'Description', 'momentive' ),
						value: item.text || '',
						onChange: ( value ) => setRow( index, { text: value } ),
					} ),

					el( 'div', { style: { display: 'flex', gap: '4px', marginTop: '8px' } },
						el( Button, {
							variant: 'secondary',
							onClick: () => moveItem( index, -1 ),
							disabled: index === 0,
						}, __( '↑ Up', 'momentive' ) ),
						el( Button, {
							variant: 'secondary',
							onClick: () => moveItem( index, 1 ),
							disabled: index === items.length - 1,
						}, __( '↓ Down', 'momentive' ) ),
						el( Button, {
							variant: 'tertiary',
							isDestructive: true,
							onClick: () => removeItem( index ),
							style: { marginLeft: 'auto' },
						}, __( 'Remove', 'momentive' ) )
					)
				)
			);

			// ---- Canvas preview ----------------------------------------------
			const previewRows = items.length
				? items.map( ( item, index ) =>
					el( 'li', { key: index, className: 'icon-list__item' },
						el( 'span', { className: 'icon-list__icon', 'aria-hidden': 'true' },
							item.iconSlug ? renderIconInline( item.iconSlug ) : null
						),
						el( 'span', { className: 'icon-list__text' }, item.text || '' )
					)
				)
				: el( 'p', { className: 'icon-list__placeholder' },
					__( 'Icon List: add items in the block settings panel.', 'momentive' )
				);

			return el( Fragment, null,

				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: __( 'List Settings', 'momentive' ), initialOpen: true },
					),

					rowPanels,

					el( PanelBody, { initialOpen: true },
						el( Button, {
							variant: 'primary',
							onClick: addItem,
							style: { width: '100%', justifyContent: 'center' },
						}, __( '+ Add item', 'momentive' ) )
					)
				),

				el( 'div', blockProps,
					el( 'ul', { className: 'icon-list' }, previewRows )
				)
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
