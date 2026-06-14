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
	const { SelectControl } = components;
	const { __ } = i18n;

	/**
	 * IconPicker
	 *
	 * @param {Object}   props
	 * @param {string}   props.value     Currently selected icon slug.
	 * @param {Function} props.onChange  Called with the new slug on selection.
	 * @param {Object}   props.icons     { slug: 'Label', … } map from momentiveIcons.available.
	 */
	const IconPicker = ( { value, onChange, icons } ) => {
		const [ viewMode, setViewMode ] = useState( 'grid' );

		const iconKeys = Object.keys( icons );

		return el( 'div', { className: 'momentive-icon-picker' },

			// Header: label + grid/list toggle
			el( 'div', {
				style: {
					marginBottom: '10px',
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
				},
			},
				el( 'label', { style: { fontWeight: '600' } }, __( 'Icon', 'momentive' ) ),
				el( 'button', {
					type: 'button',
					className: 'button button-small',
					onClick: () => setViewMode( viewMode === 'grid' ? 'list' : 'grid' ),
					style: { marginLeft: 'auto' },
				}, viewMode === 'grid'
					? __( 'List View', 'momentive' )
					: __( 'Grid View', 'momentive' )
				)
			),

			viewMode === 'grid'
				? el( 'div', {
					className: 'momentive-icon-grid',
					style: {
						display: 'grid',
						gridTemplateColumns: 'repeat( auto-fill, minmax( 60px, 1fr ) )',
						gap: '8px',
						maxHeight: '300px',
						overflowY: 'auto',
						border: '1px solid #ddd',
						padding: '10px',
						borderRadius: '4px',
						backgroundColor: '#f9f9f9',
					},
				},
					iconKeys.map( key =>
						el( 'button', {
							type: 'button',
							key,
							className: 'momentive-icon-option' + ( value === key ? ' selected' : '' ),
							onClick: () => onChange( key ),
							title: icons[ key ],
							style: {
								padding: '10px',
								border: value === key ? '2px solid #2271b1' : '1px solid #ddd',
								borderRadius: '4px',
								backgroundColor: value === key ? '#f0f6fc' : '#fff',
								cursor: 'pointer',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								aspectRatio: '1',
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
								width: '32',
								height: '32',
								style: { display: 'block' },
							},
								el( 'use', { href: '#icon-' + key } )
							)
						)
					)
				)
				: el( SelectControl, {
					value,
					options: [
						{ label: __( '— Select an icon —', 'momentive' ), value: '' },
						...iconKeys.map( key => ( { label: icons[ key ], value: key } ) ),
					],
					onChange,
				} )
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
