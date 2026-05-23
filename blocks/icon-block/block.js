/**
 * Icon Block JavaScript
 * Save as: /blocks/icon-block/block.js in your theme
 */

(function(blocks, element, blockEditor, components, i18n) {
    const el = element.createElement;
    const { registerBlockType } = blocks;
    const { InspectorControls } = blockEditor;
    const { PanelBody, SelectControl } = components;
    const { __ } = i18n;
    
    // Get available icons from localized script
    const availableIcons = window.momentiveIcons?.available || {
    };

	// Custom Icon Picker Component
	const IconPicker = ({ value, onChange, icons }) => {
		const [viewMode, setViewMode] = wp.element.useState('grid'); // 'grid' or 'list'
		
		return el('div', { className: 'momentive-icon-picker' },
			// Toggle button
			el('div', { 
				style: { 
					marginBottom: '10px',
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center'
				}
			},
				el('label', { style: { fontWeight: '600' } }, __('Icon', 'momentive')),
				el('button', {
					type: 'button',
					className: 'button button-small',
					onClick: () => setViewMode(viewMode === 'grid' ? 'list' : 'grid'),
					style: { marginLeft: 'auto' }
				}, viewMode === 'grid' ? __('List View', 'momentive') : __('Grid View', 'momentive'))
			),
			
			// Grid view
			viewMode === 'grid' ? el('div', {
				className: 'momentive-icon-grid',
				style: {
					display: 'grid',
					gridTemplateColumns: 'repeat(auto-fill, minmax(60px, 1fr))',
					gap: '8px',
					maxHeight: '300px',
					overflowY: 'auto',
					border: '1px solid #ddd',
					padding: '10px',
					borderRadius: '4px',
					backgroundColor: '#f9f9f9',
				}
			},
				Object.keys(icons).map(key => 
					el('button', {
						type: 'button',
						key: key,
						className: 'momentive-icon-option' + (value === key ? ' selected' : ''),
						onClick: () => onChange(key),
						title: icons[key],
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
							transition: 'all 0.2s'
						},
						onMouseEnter: (e) => {
							if (value !== key) {
								e.currentTarget.style.borderColor = '#2271b1';
								e.currentTarget.style.backgroundColor = '#f9f9f9';
							}
						},
						onMouseLeave: (e) => {
							if (value !== key) {
								e.currentTarget.style.borderColor = '#ddd';
								e.currentTarget.style.backgroundColor = '#fff';
							}
						}
					},
						el('svg', {
							width: '32',
							height: '32',
							style: { display: 'block' }
						},
							el('use', { 
								href: '#icon-' + key,
								style: {
									'--icon-stroke': '#000',
									'--icon-fill': 'var(--light-sky-blue)'
								}
							})
						)
					)
				)
			) :
			// List view (original SelectControl)
			el(SelectControl, {
				value: value,
				options: Object.keys(icons).map(key => ({
					label: icons[key],
					value: key
				})),
				onChange: onChange
			})
		);
	};

    registerBlockType('momentive/icon-block', {
        title: __('Icon', 'momentive'),
        icon: 'admin-customizer',
        category: 'common',
        attributes: {
            iconId: {
                type: 'string',
                default: 'none'
            },
            shape: {
                type: 'string',
                default: 'circle'
            },
            backgroundColor: {
                type: 'string',
                default: 'default'
            },
            strokeColor: {
                type: 'string',
                default: 'default'
            },
            fillColor: {
                type: 'string',
                default: 'dark-purple'
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { iconId, shape, backgroundColor, strokeColor, fillColor } = attributes;
            
            // Convert icons object to array for SelectControl
            const iconOptions = Object.keys(availableIcons).map(key => ({
                label: availableIcons[key],
                value: key
            }));
            
            return [
                // Inspector Controls (Sidebar)
                el(InspectorControls, {},
 					el(PanelBody, { title: __('Icon Settings', 'momentive'), initialOpen: true },
						el(IconPicker, {
							value: iconId,
							onChange: (value) => setAttributes({ iconId: value }),
							icons: availableIcons
						}),
                        el(SelectControl, {
                            label: __('Shape', 'momentive'),
                            value: shape,
                            options: [
                                { label: 'Circle', value: 'circle' },
                                { label: 'Square', value: 'square' },
                                { label: 'Tilted Square', value: 'tilted-square' },
                                { label: 'None', value: 'none' }
                            ],
                            onChange: (value) => setAttributes({ shape: value })
                        }),
                        el(SelectControl, {
                            label: __('Background Color', 'momentive'),
                            value: backgroundColor,
                            options: [
                                { label: 'Pink', value: 'pink' },
                                { label: 'Light Purple', value: 'light-purple' },
                                { label: 'Sky Blue', value: 'sky-blue' },
                                { label: 'Mint', value: 'mint' },
                                { label: 'White', value: 'white' },
                                { label: 'None', value: 'none' }
                            ],
                            onChange: (value) => setAttributes({ backgroundColor: value })
                        }),
                        el(SelectControl, {
                            label: __('Stroke Color', 'momentive'),
                            value: strokeColor,
                            options: [
                                { label: 'Default', value: 'default' },
                                { label: 'Dark Purple', value: 'dark-purple' },
                                { label: 'Pink', value: 'pink' },
                                { label: 'Sky Blue', value: 'sky-blue' },
                                { label: 'White', value: 'white' }
                            ],
                            onChange: (value) => setAttributes({ strokeColor: value })
                        }),
                        el(SelectControl, {
                            label: __('Fill Color', 'momentive'),
                            value: fillColor,
                            options: [
                                { label: 'None', value: 'none' },
                                { label: 'Pink', value: 'pink' },
                                { label: 'Light Purple', value: 'light-purple' },
                                { label: 'Sky Blue', value: 'sky-blue' },
                                { label: 'Mint', value: 'mint' },
                                { label: 'White', value: 'white' }
                            ],
                            onChange: (value) => setAttributes({ fillColor: value })
                        })
                    )
                ),
                
                // Block Preview
                el('div', { className: 'momentive-icon-block-preview' },
                    el('span', {
                        className: `svg-icon shape-${shape} bg-${backgroundColor}`,
                        style: {
                            '--icon-stroke': strokeColor !== 'default' ? `var(--${strokeColor})` : undefined,
                            '--icon-fill': fillColor !== 'none' ? `var(--${fillColor})` : undefined
                        }
                    },
                        el('svg', { 'aria-hidden': 'true' },
                            el('use', { href: `#icon-${iconId}` })
                        )
                    ),
                    el('div', { className: 'icon-label' },
                        __('Icon: ', 'momentive') + availableIcons[iconId]
                    )
                )
            ];
        },
        
        save: function() {
            // Dynamic block - render on server
            return null;
        }
    });
    
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);