/**
 * Shared Icon Picker component
 * assets/js/icon-picker.js
 *
 * Registered as 'momentive-icon-picker' script handle.
 * Exposes window.momentive.IconPicker for use by any block that needs
 * an icon selection UI.
 *
 * Depends on: wp-element, wp-components, wp-i18n
 */

( function ( element, components, i18n ) {
	const { createElement: el, useState } = element;
	const { SelectControl, TextControl, Modal } = components;
	const { __, sprintf } = i18n;

	/**
	 * Renders a scrollable icon grid. Used in both the inline picker and the modal.
	 *
	 * @param {Object}   props
	 * @param {Object}   props.icons      { slug: 'Label', … } — already filtered.
	 * @param {string}   props.value      Currently selected icon slug.
	 * @param {Function} props.onSelect   Called with slug on click.
	 * @param {boolean}  props.showLabels Whether to show the icon label below the glyph.
	 * @param {string}   props.cellSize   CSS minmax size for grid cells (default '60px').
	 * @param {number}   props.iconSize   SVG width/height in px (default 32).
	 * @param {Object}   props.gridStyle  Extra style overrides for the grid container.
	 */
	const IconGrid = ( { icons, value, onSelect, showLabels = false, cellSize = '60px', iconSize = 32, gridStyle = {} } ) => {
		const iconKeys = Object.keys( icons );

		if ( ! iconKeys.length ) {
			return el( 'p', {
				style: { textAlign: 'center', color: '#757575', fontSize: '12px', margin: '16px 0' },
			}, __( 'No icons match your search.', 'momentive' ) );
		}

		return el( 'div', {
			className: 'momentive-icon-grid',
			style: {
				display: 'grid',
				gridTemplateColumns: 'repeat( auto-fill, minmax( ' + cellSize + ', 1fr ) )',
				gap: '8px',
				border: '1px solid #ddd',
				padding: '10px',
				borderRadius: '4px',
				backgroundColor: '#f9f9f9',
				...gridStyle,
			},
		},
			iconKeys.map( key =>
				el( 'button', {
					type: 'button',
					key,
					className: 'momentive-icon-option' + ( value === key ? ' selected' : '' ),
					onClick: () => onSelect( key ),
					title: icons[ key ],
					style: {
						padding: showLabels ? '10px 6px 6px' : '10px',
						border: value === key ? '2px solid #2271b1' : '1px solid #ddd',
						borderRadius: '4px',
						backgroundColor: value === key ? '#f0f6fc' : '#fff',
						cursor: 'pointer',
						display: 'flex',
						flexDirection: showLabels ? 'column' : 'row',
						alignItems: 'center',
						justifyContent: 'center',
						gap: showLabels ? '4px' : '0',
						transition: 'all 0.2s',
					},
					onMouseEnter: ( e ) => {
						if ( value !== key ) {
							e.currentTarget.style.borderColor = '#2271b1';
							e.currentTarget.style.backgroundColor = '#f9f9f9';
						}
					},
					onMouseLeave: ( e ) => {
						if ( value !== key ) {
							e.currentTarget.style.borderColor = '#ddd';
							e.currentTarget.style.backgroundColor = '#fff';
						}
					},
				},
					el( 'svg', {
						width: String( iconSize ),
						height: String( iconSize ),
						style: { display: 'block', flexShrink: '0' },
					},
						el( 'use', { href: '#icon-' + key } )
					),
					showLabels && el( 'span', {
						style: {
							fontSize: '9px',
							lineHeight: '1.2',
							color: '#444',
							textAlign: 'center',
							wordBreak: 'break-word',
							maxWidth: '100%',
						},
					}, icons[ key ] )
				)
			)
		);
	};

	/**
	 * Filters an icons map by a search query string.
	 * Matches against slug and label, case-insensitive.
	 *
	 * @param {Object} icons  { slug: 'Label', … }
	 * @param {string} query
	 * @returns {Object}
	 */
	const filterIcons = ( icons, query ) => {
		if ( ! query ) return icons;
		const q = query.trim().toLowerCase();
		return Object.fromEntries(
			Object.entries( icons ).filter(
				( [ key, label ] ) => key.includes( q ) || label.toLowerCase().includes( q )
			)
		);
	};

	/**
	 * IconPicker
	 *
	 * @param {Object}   props
	 * @param {string}   props.value     Currently selected icon slug.
	 * @param {Function} props.onChange  Called with the new slug on selection.
	 * @param {Object}   props.icons     { slug: 'Label', … } map from momentiveIcons.available.
	 */
	const IconPicker = ( { value, onChange, icons } ) => {
		const [ viewMode,    setViewMode    ] = useState( 'grid' );
		const [ searchQuery, setSearchQuery ] = useState( '' );
		const [ modalOpen,   setModalOpen   ] = useState( false );
		const [ modalSearch, setModalSearch ] = useState( '' );

		const isGrid      = viewMode === 'grid';
		const filtered    = filterIcons( icons, searchQuery );
		const allKeys     = Object.keys( icons );
		const resultCount = Object.keys( filtered ).length;

		const handleSelect = ( key ) => {
			onChange( key );
			if ( modalOpen ) setModalOpen( false );
		};

		return el( 'div', { className: 'momentive-icon-picker' },

			// ── Header: label + expand button + grid/list toggle ──────────────────
			el( 'div', {
				style: {
					marginBottom: '8px',
					display: 'flex',
					alignItems: 'center',
					gap: '6px',
				},
			},
				el( 'label', { style: { fontWeight: '600', marginRight: 'auto' } }, __( 'Icon', 'momentive' ) ),

				// Expand to lightbox (grid mode only)
				isGrid && el( 'button', {
					type: 'button',
					className: 'button button-small',
					onClick: () => { setModalSearch( searchQuery ); setModalOpen( true ); },
					title: __( 'Browse all icons', 'momentive' ),
					style: { fontSize: '11px' },
				}, __( 'Browse…', 'momentive' ) ),

				// Grid / List toggle
				el( 'button', {
					type: 'button',
					className: 'button button-small',
					onClick: () => setViewMode( isGrid ? 'list' : 'grid' ),
					style: { fontSize: '11px' },
				}, isGrid
					? __( 'List View', 'momentive' )
					: __( 'Grid View', 'momentive' )
				)
			),

			// ── Search (grid mode only) ────────────────────────────────────────────
			isGrid && el( TextControl, {
				placeholder: __( 'Search icons…', 'momentive' ),
				value: searchQuery,
				onChange: setSearchQuery,
				style: { marginBottom: searchQuery ? '4px' : '8px' },
			} ),

			isGrid && searchQuery && el( 'p', {
				style: { fontSize: '11px', color: '#757575', margin: '0 0 6px' },
			}, resultCount
				? sprintf( __( '%d icon(s) found', 'momentive' ), resultCount )
				: __( 'No icons match your search.', 'momentive' )
			),

			// ── Inline compact grid ────────────────────────────────────────────────
			isGrid && el( IconGrid, {
				icons: filtered,
				value,
				onSelect: handleSelect,
				gridStyle: { maxHeight: '300px', overflowY: 'auto' },
			} ),

			// ── Dropdown list ──────────────────────────────────────────────────────
			! isGrid && el( SelectControl, {
				value,
				options: [
					{ label: __( '— Select an icon —', 'momentive' ), value: '' },
					...allKeys.map( key => ( { label: icons[ key ], value: key } ) ),
				],
				onChange,
			} ),

			// ── Lightbox modal ─────────────────────────────────────────────────────
			modalOpen && el( Modal, {
				title: __( 'Select an Icon', 'momentive' ),
				onRequestClose: () => setModalOpen( false ),
				style: { width: '80vw', maxWidth: '1100px' },
			},
				el( 'div', { style: { marginBottom: '12px' } },
					el( TextControl, {
						placeholder: __( 'Search icons…', 'momentive' ),
						value: modalSearch,
						onChange: setModalSearch,
						autoFocus: true,
					} ),
					modalSearch && el( 'p', {
						style: { fontSize: '12px', color: '#757575', margin: '4px 0 0' },
					}, Object.keys( filterIcons( icons, modalSearch ) ).length
						? sprintf(
							__( '%d icon(s) found', 'momentive' ),
							Object.keys( filterIcons( icons, modalSearch ) ).length
						)
						: __( 'No icons match your search.', 'momentive' )
					)
				),
				el( IconGrid, {
					icons: filterIcons( icons, modalSearch ),
					value,
					onSelect: handleSelect,
					showLabels: true,
					cellSize: '80px',
					iconSize: 36,
				} )
			)
		);
	};

	// Attach to shared namespace so blocks can reference window.momentive.IconPicker.
	window.momentive = window.momentive || {};
	window.momentive.IconPicker = IconPicker;

} )(
	window.wp.element,
	window.wp.components,
	window.wp.i18n
);
