/**
 * Accordion Block — Editor JS
 * blocks/accordion/editor.js
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	const { createElement: el, Fragment, useState } = element;
	const { registerBlockType } = blocks;
	const { InspectorControls }  = blockEditor;
	const { useBlockProps }      = blockEditor;
	const {
		PanelBody,
		PanelRow,
		ToggleControl,
		SelectControl,
		TextControl,
		TextareaControl,
		Button,
	} = components;
	const { __ } = i18n;

	const availableIcons = window.momentiveIcons?.available || {};
	const IconPicker = window.momentive?.IconPicker;

	// ── Tiny UUID for new items ───────────────────────────────────────────────

	function uid() {
		return Math.random().toString( 36 ).slice( 2, 9 );
	}

	// ── Empty item factory ────────────────────────────────────────────────────

	function newItem() {
		return { _key: uid(), question: '', answer: '', iconSlug: '', category: '' };
	}

	// =========================================================================
	// Block registration
	// =========================================================================

	registerBlockType( 'momentive/accordion', {

		// Attributes are authoritative in block.json — listed here for editor defaults.
		attributes: {
			style:              { type: 'string',  default: 'default' },
			closeOthers:        { type: 'boolean', default: false },
			queryMode:          { type: 'boolean', default: false },
			items:              { type: 'array',   default: [] },
			queryPostsPerPage:  { type: 'number',  default: 9 },
			queryCategory:      { type: 'string',  default: '' },
			queryLoadMore:      { type: 'boolean', default: true },
		},

		edit( { attributes, setAttributes } ) {
			const {
				style, closeOthers, queryMode,
				items, queryPostsPerPage, queryCategory, queryLoadMore,
			} = attributes;

			const blockProps = useBlockProps( {
				className: 'momentive-accordion-editor-preview',
			} );

			// Track which item is open in the editor preview.
			const [ openIndex, setOpenIndex ] = useState( null );

			// Track which item is being edited in the sidebar.
			const [ editingIndex, setEditingIndex ] = useState( null );

			// ── Item helpers ──────────────────────────────────────────────────

			function updateItem( index, patch ) {
				const next = items.map( ( item, i ) =>
					i === index ? { ...item, ...patch } : item
				);
				setAttributes( { items: next } );
			}

			function addItem() {
				setAttributes( { items: [ ...items, newItem() ] } );
				setEditingIndex( items.length );
			}

			function removeItem( index ) {
				setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
				if ( editingIndex === index ) setEditingIndex( null );
			}

			function moveItem( index, direction ) {
				const next    = [ ...items ];
				const swapIdx = index + direction;
				if ( swapIdx < 0 || swapIdx >= next.length ) return;
				[ next[ index ], next[ swapIdx ] ] = [ next[ swapIdx ], next[ index ] ];
				setAttributes( { items: next } );
				setEditingIndex( swapIdx );
			}

			// ── Style label for the canvas summary ────────────────────────────

			const styleLabels = {
				default:     'Default',
				categorized: 'Categorized',
				icon:        'With icons',
			};

			// ── Sidebar ───────────────────────────────────────────────────────

			const sidebar = el( InspectorControls, {},

				// ── Block options ─────────────────────────────────────────────
				el( PanelBody, { title: __( 'Accordion options', 'momentive' ), initialOpen: true },

					el( SelectControl, {
						label:   __( 'Style', 'momentive' ),
						value:   style,
						options: [
							{ label: __( 'Default',     'momentive' ), value: 'default'     },
							{ label: __( 'Categorized', 'momentive' ), value: 'categorized' },
							{ label: __( 'With icons',  'momentive' ), value: 'icon'        },
						],
						onChange: ( val ) => setAttributes( { style: val } ),
					} ),

					el( ToggleControl, {
						label:    __( 'Close others when one opens', 'momentive' ),
						checked:  closeOthers,
						onChange: ( val ) => setAttributes( { closeOthers: val } ),
					} ),

					el( ToggleControl, {
						label:    __( 'Query FAQ post type', 'momentive' ),
						help:     __( 'Pull items from the FAQ CPT instead of the list below.', 'momentive' ),
						checked:  queryMode,
						onChange: ( val ) => setAttributes( { queryMode: val } ),
					} )
				),

				// ── Query mode options ────────────────────────────────────────
				queryMode && el( PanelBody, { title: __( 'Query settings', 'momentive' ), initialOpen: true },

					el( TextControl, {
						label:    __( 'Category slug (optional)', 'momentive' ),
						help:     __( 'Leave blank to show all FAQ posts.', 'momentive' ),
						value:    queryCategory,
						onChange: ( val ) => setAttributes( { queryCategory: val } ),
					} ),

					el( TextControl, {
						label:    __( 'Posts per page', 'momentive' ),
						type:     'number',
						value:    queryPostsPerPage,
						onChange: ( val ) => setAttributes( { queryPostsPerPage: parseInt( val, 10 ) || 9 } ),
					} ),

					el( ToggleControl, {
						label:    __( 'Show "Load more" button', 'momentive' ),
						checked:  queryLoadMore,
						onChange: ( val ) => setAttributes( { queryLoadMore: val } ),
					} )
				),

				// ── Static item editor ────────────────────────────────────────
				! queryMode && el( PanelBody, {
					title:       __( 'Items', 'momentive' ),
					initialOpen: true,
				},

					// Item list with quick actions.
					items.length > 0 && el( 'div', { className: 'accordion-editor-item-list' },
						items.map( ( item, index ) =>
							el( 'div', {
								key:       item._key || index,
								className: 'accordion-editor-item-row' + ( editingIndex === index ? ' is-editing' : '' ),
							},
								el( 'button', {
									type:      'button',
									className: 'accordion-editor-item-label',
									onClick:   () => setEditingIndex( editingIndex === index ? null : index ),
								}, item.question || __( '(empty)', 'momentive' ) ),

								el( 'span', { className: 'accordion-editor-item-actions' },
									el( Button, {
										icon:           'arrow-up-alt2',
										label:          __( 'Move up', 'momentive' ),
										isSmall:        true,
										disabled:       index === 0,
										onClick:        () => moveItem( index, -1 ),
									} ),
									el( Button, {
										icon:           'arrow-down-alt2',
										label:          __( 'Move down', 'momentive' ),
										isSmall:        true,
										disabled:       index === items.length - 1,
										onClick:        () => moveItem( index, 1 ),
									} ),
									el( Button, {
										icon:           'trash',
										label:          __( 'Remove item', 'momentive' ),
										isSmall:        true,
										isDestructive:  true,
										onClick:        () => removeItem( index ),
									} )
								)
							)
						)
					),

					// Expanded editor for the selected item.
					editingIndex !== null && items[ editingIndex ] && el( 'div', {
						className: 'accordion-editor-item-fields',
						key:       items[ editingIndex ]._key || editingIndex,
					},
						el( TextControl, {
							label:    __( 'Question', 'momentive' ),
							value:    items[ editingIndex ].question,
							onChange: ( val ) => updateItem( editingIndex, { question: val } ),
						} ),
						el( TextareaControl, {
							label:    __( 'Answer', 'momentive' ),
							help:     __( 'Plain text or simple HTML.', 'momentive' ),
							rows:     5,
							value:    items[ editingIndex ].answer,
							onChange: ( val ) => updateItem( editingIndex, { answer: val } ),
						} ),
						style === 'categorized' && el( TextControl, {
							label:    __( 'Category label', 'momentive' ),
							value:    items[ editingIndex ].category,
							onChange: ( val ) => updateItem( editingIndex, { category: val } ),
						} ),
						style === 'icon' && (
							IconPicker
								? el( IconPicker, {
									value:    items[ editingIndex ].iconSlug,
									onChange: ( val ) => updateItem( editingIndex, { iconSlug: val } ),
									icons:    availableIcons,
								} )
								: el( TextControl, {
									label:    __( 'Icon slug', 'momentive' ),
									value:    items[ editingIndex ].iconSlug,
									onChange: ( val ) => updateItem( editingIndex, { iconSlug: val } ),
								} )
						)
					),

					el( PanelRow, {},
						el( Button, {
							variant: 'secondary',
							onClick: addItem,
						}, __( '+ Add item', 'momentive' ) )
					)
				)
			);

			// ── Canvas preview ────────────────────────────────────────────────

			const previewItems = queryMode
				? [ { question: __( 'Items loaded from FAQ post type…', 'momentive' ), answer: '' } ]
				: items;

			const canvas = el( 'div', blockProps,

				queryMode && el( 'p', {
					className: 'accordion-editor-query-notice',
				}, el( 'em', {},
					__(
						`Accordion — query mode (${ queryCategory || 'all categories' }, ${ queryPostsPerPage } per page)`,
						'momentive'
					)
				) ),

				! queryMode && items.length === 0 && el( 'p', {
					className: 'accordion-editor-placeholder',
				}, __( 'Accordion: add items in the block settings panel.', 'momentive' ) ),

				! queryMode && items.length > 0 && el( 'div', {
					className: `momentive-accordion is-style-${ style }`,
				},
					items.map( ( item, index ) =>
						el( 'div', {
							key:       item._key || index,
							className: 'accordion-item' + ( openIndex === index ? ' is-open' : '' ),
						},
							el( 'button', {
								type:      'button',
								className: 'accordion-trigger',
								'aria-expanded': String( openIndex === index ),
								onClick:   () => setOpenIndex( openIndex === index ? null : index ),
							},
								style === 'icon' && item.iconSlug && el( 'span', {
									className: 'accordion-icon',
									'aria-hidden': 'true',
								},
									el( 'svg', { focusable: 'false' },
										el( 'use', { href: `#icon-${ item.iconSlug }` } )
									)
								),
								el( 'span', { className: 'accordion-question' }, item.question || __( '(empty)', 'momentive' ) ),
								style === 'categorized' && item.category && el( 'span', {
									className: 'accordion-category',
									'data-category': item.category ? item.category.toLowerCase().replace( /\s+/g, '-' ) : '',
								}, item.category ),
								el( 'span', { className: 'accordion-chevron', 'aria-hidden': 'true' },
									el( 'svg', { viewBox: '0 0 12 12', xmlns: 'http://www.w3.org/2000/svg' },
										el( 'path', { d: 'M1.5 4L6 8L10.5 4', stroke: 'currentColor', strokeWidth: '1.5', fill: 'none', strokeLinecap: 'round' } )
									)
								)
							),
							openIndex === index && el( 'div', {
								className: 'accordion-panel',
								role:      'region',
							},
								el( 'div', { className: 'accordion-panel-inner' },
									el( 'p', {}, item.answer || el( 'em', {}, __( '(no answer yet)', 'momentive' ) ) )
								)
							)
						)
					)
				)
			);

			return el( Fragment, {}, sidebar, canvas );
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
