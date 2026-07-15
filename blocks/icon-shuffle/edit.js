( function () {
	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var MediaUpload       = wp.blockEditor.MediaUpload;
	var MediaUploadCheck  = wp.blockEditor.MediaUploadCheck;
	var PanelBody         = wp.components.PanelBody;
	var Button            = wp.components.Button;
	var RangeControl      = wp.components.RangeControl;
	var BaseControl       = wp.components.BaseControl;
	var Flex              = wp.components.Flex;
	var FlexItem          = wp.components.FlexItem;

	wp.blocks.registerBlockType( 'momentive/icon-shuffle', {

		edit: function ( props ) {
			var attributes         = props.attributes;
			var setAttributes      = props.setAttributes;
			var images             = attributes.images             || [];
			var columns            = attributes.columns            || 5;
			var cellCount          = attributes.cellCount          || 14;
			var cellSize           = attributes.cellSize           || 24;
			var interval           = attributes.interval           || 350;
			var transitionDuration = attributes.transitionDuration || 600;
			var centerImage        = attributes.centerImage        || null;
			var centerCellIndex    = ( attributes.centerCellIndex !== undefined ) ? attributes.centerCellIndex : 7;

			var blockProps = useBlockProps();

			// ── Handlers ────────────────────────────────────────────────────

			function onSelectImages( newImages ) {
				setAttributes( {
					images: newImages.map( function ( img ) {
						return { id: img.id, url: img.url, alt: img.alt || '' };
					} )
				} );
			}

			function removeImage( id ) {
				setAttributes( {
					images: images.filter( function ( img ) { return img.id !== id; } )
				} );
			}

			// ── Styles ───────────────────────────────────────────────────────

			var gridStyle = {
				display: 'grid',
				gridTemplateColumns: 'repeat(' + columns + ', ' + cellSize + 'px)',
				gap: '12px',
				padding: '16px',
			};

			var cellStyle = {
				width:           cellSize,
				height:          cellSize,
				borderRadius:    '4px',
				overflow:        'hidden',
				background:      '#f0f0f0',
				display:         'flex',
				alignItems:      'center',
				justifyContent:  'center',
			};

			// ── Inspector ────────────────────────────────────────────────────

			var poolLabel = images.length === 0
				? __( 'Add Icons', 'momentive' )
				: __( 'Edit Pool (' + images.length + ' icons)', 'momentive' );

			var thumbnails = images.map( function ( img ) {
				return el( FlexItem, { key: img.id, style: { position: 'relative' } },
					el( 'img', {
						src:   img.url,
						alt:   img.alt,
						style: {
							width: 32, height: 32, objectFit: 'contain',
							display: 'block', borderRadius: '3px', border: '1px solid #ddd',
						},
					} ),
					el( Button, {
						icon:    'no-alt',
						label:   __( 'Remove', 'momentive' ),
						onClick: function () { removeImage( img.id ); },
						style: {
							position: 'absolute', top: -6, right: -6,
							minWidth: 0, width: 16, height: 16, padding: 0,
							background: '#cc1818', color: '#fff',
							borderRadius: '50%', fontSize: 10,
						},
					} )
				);
			} );

			var inspector = el( InspectorControls, {},

				// ── Images panel ──────────────────────────────────────────
				el( PanelBody, { title: __( 'Images', 'momentive' ), initialOpen: true },
					el( BaseControl, {
						label: __( 'Icon Pool', 'momentive' ),
						help:  __( 'Add all icons to the pool. The grid cycles through them randomly. More icons than cells = more variety.', 'momentive' ),
					},
						el( MediaUploadCheck, {},
							el( MediaUpload, {
								onSelect:     onSelectImages,
								allowedTypes: [ 'image' ],
								multiple:     true,
								value:        images.map( function ( img ) { return img.id; } ),
								render: function ( ref ) {
									return el( Button, {
										variant: 'secondary',
										onClick: ref.open,
										style:   { marginTop: '8px' },
									}, poolLabel );
								},
							} )
						)
					),
					images.length > 0 && el( BaseControl, { label: __( 'Current pool', 'momentive' ) },
						el( Flex, { wrap: true, gap: 2, style: { marginTop: '8px' } },
							thumbnails
						)
					),

					// ── Center image ───────────────────────────────────────
					el( BaseControl, {
						label: __( 'Center image (optional)', 'momentive' ),
						help:  __( 'A static image placed at the center grid position. Typically a logo. Leave empty for a plain shuffle grid.', 'momentive' ),
					},
						el( MediaUploadCheck, {},
							el( MediaUpload, {
								onSelect: function ( img ) {
									setAttributes( {
										centerImage: { id: img.id, url: img.url, alt: img.alt || '' }
									} );
								},
								allowedTypes: [ 'image' ],
								multiple:     false,
								value:        centerImage ? centerImage.id : null,
								render: function ( ref ) {
									return el( 'div', { style: { marginTop: '8px' } },
										centerImage
											? el( 'div', { style: { position: 'relative', display: 'inline-block' } },
												el( 'img', {
													src:   centerImage.url,
													alt:   centerImage.alt,
													style: { width: 48, height: 48, objectFit: 'contain', display: 'block', border: '1px solid #ddd', borderRadius: '3px' },
												} ),
												el( Button, {
													icon:    'no-alt',
													label:   __( 'Remove center image', 'momentive' ),
													onClick: function () { setAttributes( { centerImage: null } ); },
													style: {
														position: 'absolute', top: -6, right: -6,
														minWidth: 0, width: 16, height: 16, padding: 0,
														background: '#cc1818', color: '#fff',
														borderRadius: '50%', fontSize: 10,
													},
												} ),
												el( Button, {
													variant: 'link',
													onClick: ref.open,
													style:   { display: 'block', marginTop: '4px', fontSize: '11px' },
												}, __( 'Replace', 'momentive' ) )
											  )
											: el( Button, { variant: 'secondary', onClick: ref.open },
												__( 'Set center image', 'momentive' )
											  )
									);
								},
							} )
						)
					),
					centerImage && el( wp.components.TextControl, {
						label:    __( 'Center cell position (0-indexed)', 'momentive' ),
						help:     __( 'Default 7 = col 3, row 2 of a 5-column grid. Adjust if you change column count.', 'momentive' ),
						type:     'number',
						value:    centerCellIndex,
						onChange: function ( v ) { setAttributes( { centerCellIndex: parseInt( v, 10 ) || 0 } ); },
						style:    { marginTop: '8px' },
					} )
				),

				// ── Layout panel ──────────────────────────────────────────
				el( PanelBody, { title: __( 'Grid Layout', 'momentive' ), initialOpen: true },
					el( RangeControl, {
						label:    __( 'Columns', 'momentive' ),
						value:    columns,
						onChange: function ( v ) { setAttributes( { columns: v } ); },
						min: 2, max: 10,
					} ),
					el( RangeControl, {
						label:    __( 'Cell count', 'momentive' ),
						help:     __( 'Visible cells. Should be less than your pool size.', 'momentive' ),
						value:    cellCount,
						onChange: function ( v ) { setAttributes( { cellCount: v } ); },
						min: 2, max: 30,
					} ),
					el( RangeControl, {
						label:    __( 'Cell size (px)', 'momentive' ),
						value:    cellSize,
						onChange: function ( v ) { setAttributes( { cellSize: v } ); },
						min: 16, max: 96, step: 4,
					} )
				),

				// ── Animation panel ───────────────────────────────────────
				el( PanelBody, { title: __( 'Animation', 'momentive' ), initialOpen: false },
					el( RangeControl, {
						label:    __( 'Swap interval (ms)', 'momentive' ),
						help:     __( 'How often one cell swaps. 350ms ≈ 3 swaps/sec; each cell changes roughly every 5s with 14 cells.', 'momentive' ),
						value:    interval,
						onChange: function ( v ) { setAttributes( { interval: v } ); },
						min: 100, max: 2000, step: 50,
					} ),
					el( RangeControl, {
						label:    __( 'Transition duration (ms)', 'momentive' ),
						help:     __( 'Crossfade length. Keep shorter than the swap interval.', 'momentive' ),
						value:    transitionDuration,
						onChange: function ( v ) { setAttributes( { transitionDuration: v } ); },
						min: 100, max: 1500, step: 50,
					} )
				)
			);

			// ── Editor preview ────────────────────────────────────────────

			if ( images.length === 0 ) {
				return el( 'div', blockProps,
					inspector,
					el( 'div', { className: 'icon-shuffle-placeholder' },
						el( MediaUploadCheck, {},
							el( MediaUpload, {
								onSelect:     onSelectImages,
								allowedTypes: [ 'image' ],
								multiple:     true,
								render: function ( ref ) {
									return el( Button, { variant: 'primary', onClick: ref.open },
										__( 'Add Icons to Pool', 'momentive' )
									);
								},
							} )
						),
						el( 'p', {}, __( 'No icons added yet.', 'momentive' ) )
					)
				);
			}

			// Static grid preview
			// Build cells in grid order, inserting the center image at its slot.
			var previewCells  = [];
			var shuffleIdx    = 0;
			var totalGridSlots = cellCount + ( centerImage ? 1 : 0 );

			for ( var slot = 0; slot < totalGridSlots; slot++ ) {
				if ( centerImage && slot === centerCellIndex ) {
					// Center image cell
					previewCells.push( el( 'div', {
						key:   'center',
						style: Object.assign( {}, cellStyle, { background: 'transparent', outline: '1px solid #aaa' } ),
					},
						el( 'img', {
							src:   centerImage.url,
							alt:   centerImage.alt,
							style: { width: '100%', height: '100%', objectFit: 'contain' },
						} )
					) );
					continue;
				}

				if ( shuffleIdx < images.length ) {
					var img = images[ shuffleIdx ];
					previewCells.push( el( 'div', { key: 'img-' + img.id + '-' + shuffleIdx, style: cellStyle },
						el( 'img', {
							src:   img.url,
							alt:   img.alt,
							style: { width: '100%', height: '100%', objectFit: 'contain' },
						} )
					) );
					shuffleIdx++;
				} else {
					previewCells.push( el( 'div', {
						key:   'empty-' + slot,
						style: Object.assign( {}, cellStyle, { border: '1px dashed #ccc', background: 'transparent' } ),
					} ) );
				}
			}

			var warning = images.length < cellCount
				? el( 'p', { className: 'icon-shuffle-warning', style: { gridColumn: '1 / -1' } },
					'⚠ Pool has ' + images.length + ' icons but cell count is ' + cellCount + '. Add more icons or reduce cell count.'
				  )
				: null;

			return el( 'div', blockProps,
				inspector,
				el( 'div', { className: 'icon-shuffle-editor-preview', style: gridStyle },
					previewCells,
					warning
				)
			);
		},

		// No save — render_callback handles output.
		save: function () { return null; },
	} );
} )();
