/**
 * Accordion Block — Editor JS
 * blocks/accordion/editor.js
 *
 * Depends on: momentive-icon-picker (loaded first), plus wp-* handles.
 *
 * Answer editing: inline RichText in the canvas panel (click a question to open).
 * Question / icon / category / reorder: sidebar panel as before.
 */

( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	const { createElement: el, Fragment, useState } = element;
	const { registerBlockType }                     = blocks;
	const { InspectorControls, RichText }           = blockEditor;
	const { useBlockProps }                         = blockEditor;
	const {
		PanelBody,
		PanelRow,
		ToggleControl,
		SelectControl,
		TextControl,
		Button,
	} = components;
	const { __ } = i18n;

	const availableIcons = window.momentiveIcons?.available || {};
	const IconPicker     = window.momentive?.IconPicker;

	// ── Tiny UUID for new items ───────────────────────────────────────────────

	function uid() {
		return Math.random().toString( 36 ).slice( 2, 9 );
	}

	// ── Empty item factory ────────────────────────────────────────────────────

	function newItem() {
		return { _key: uid(), question: '', answer: '', iconSlug: '', category: '' };
	}

	// ── Legacy answer normalizer ──────────────────────────────────────────────
	//
	// Accordion items saved before the "code in answer" update stored `answer`
	// as bare plain text (no wrapping tag). RichText below uses
	// `multiline: 'p'`, and Gutenberg's rich-text `create()` only reads content
	// nested inside the multiline tag — a bare text node with no <p> wrapper is
	// silently dropped, so legacy answers render as an empty panel in the
	// editor even though block.php's wp_kses_post() prints them fine on the
	// front end. Wrap plain text in <p> before handing it to RichText so old
	// items display and remain editable. Anything that already starts with a
	// tag (new-format items) is passed through untouched.

	function ensureRichTextValue( value ) {
		if ( ! value ) {
			return value || '';
		}
		if ( /^\s*</.test( value ) ) {
			return value;
		}
		return '<p>' + value + '</p>';
	}

	// =========================================================================
	// Block registration
	// =========================================================================

	registerBlockType( 'momentive/accordion', {

		// Attributes are authoritative in block.json.
		attributes: {
			style:              { type: 'string',  default: 'default' },
			closeOthers:        { type: 'boolean', default: false },
			openFirst:          { type: 'boolean', default: false },
			queryMode:          { type: 'boolean', default: false },
			items:              { type: 'array',   default: [] },
			queryPostsPerPage:  { type: 'number',  default: 9 },
			queryCategory:      { type: 'string',  default: '' },
		},

		edit( { attributes, setAttributes } ) {
			const {
				style, closeOthers, openFirst, queryMode,
				items, queryPostsPerPage, queryCategory,
			} = attributes;

			const blockProps = useBlockProps( {
				className: 'momentive-accordion-editor-preview',
			} );

			// Single index drives both the open canvas panel and the sidebar form.
			// null = nothing selected / all collapsed.
			const [ activeIndex, setActiveIndex ] = useState( null );

			// ── Item helpers ──────────────────────────────────────────────────

			function updateItem( index, patch ) {
				const next = items.map( ( item, i ) =>
					i === index ? { ...item, ...patch } : item
				);
				setAttributes( { items: next } );
			}

			function addItem() {
				setAttributes( { items: [ ...items, newItem() ] } );
				setActiveIndex( items.length );
			}

			function removeItem( index ) {
				setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
				if ( activeIndex === index ) setActiveIndex( null );
			}

			function moveItem( index, direction ) {
				const next    = [ ...items ];
				const swapIdx = index + direction;
				if ( swapIdx < 0 || swapIdx >= next.length ) return;
				[ next[ index ], next[ swapIdx ] ] = [ next[ swapIdx ], next[ index ] ];
				setAttributes( { items: next } );
				setActiveIndex( swapIdx );
			}

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
						label:    __( 'Open first item by default', 'momentive' ),
						checked:  openFirst,
						onChange: ( val ) => setAttributes( { openFirst: val } ),
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
								className: 'accordion-editor-item-row' + ( activeIndex === index ? ' is-editing' : '' ),
							},
								el( 'button', {
									type:      'button',
									className: 'accordion-editor-item-label',
									onClick:   () => setActiveIndex( activeIndex === index ? null : index ),
								}, item.question || __( '(empty)', 'momentive' ) ),

								el( 'span', { className: 'accordion-editor-item-actions' },
									el( Button, {
										icon:          'arrow-up-alt2',
										label:         __( 'Move up', 'momentive' ),
										isSmall:       true,
										disabled:      index === 0,
										onClick:       () => moveItem( index, -1 ),
									} ),
									el( Button, {
										icon:          'arrow-down-alt2',
										label:         __( 'Move down', 'momentive' ),
										isSmall:       true,
										disabled:      index === items.length - 1,
										onClick:       () => moveItem( index, 1 ),
									} ),
									el( Button, {
										icon:          'trash',
										label:         __( 'Remove item', 'momentive' ),
										isSmall:       true,
										isDestructive: true,
										onClick:       () => removeItem( index ),
									} )
								)
							)
						)
					),

					// Sidebar form for the selected item — question, icon, category only.
					// Answer is edited inline via RichText in the canvas panel below.
					activeIndex !== null && items[ activeIndex ] && el( 'div', {
						className: 'accordion-editor-item-fields',
						key:       items[ activeIndex ]._key || activeIndex,
					},
						el( TextControl, {
							label:    __( 'Question', 'momentive' ),
							value:    items[ activeIndex ].question,
							onChange: ( val ) => updateItem( activeIndex, { question: val } ),
						} ),

						el( 'p', { className: 'components-base-control__help' },
							__( 'Click the item in the preview to edit its answer with rich text formatting.', 'momentive' )
						),

						style === 'categorized' && el( TextControl, {
							label:    __( 'Category label', 'momentive' ),
							value:    items[ activeIndex ].category,
							onChange: ( val ) => updateItem( activeIndex, { category: val } ),
						} ),

						style === 'icon' && (
							IconPicker
								? el( IconPicker, {
									value:    items[ activeIndex ].iconSlug,
									onChange: ( val ) => updateItem( activeIndex, { iconSlug: val } ),
									icons:    availableIcons,
								} )
								: el( TextControl, {
									label:    __( 'Icon slug', 'momentive' ),
									value:    items[ activeIndex ].iconSlug,
									onChange: ( val ) => updateItem( activeIndex, { iconSlug: val } ),
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
			//
			// Clicking a question trigger opens that item's panel for editing
			// (sets activeIndex). The open panel renders a RichText component so
			// the editor can format the answer directly in context.

			const canvas = el( 'div', blockProps,

				queryMode && el( 'p', {
					className: 'accordion-editor-query-notice',
				}, el( 'em', {},
					`Accordion — query mode (${ queryCategory || 'all categories' }, ${ queryPostsPerPage } per page)`
				) ),

				! queryMode && items.length === 0 && el( 'p', {
					className: 'accordion-editor-placeholder',
				}, __( 'Accordion: add items in the block settings panel.', 'momentive' ) ),

				! queryMode && items.length > 0 && el( 'div', {
					className: `momentive-accordion is-style-${ style }`,
				},
					items.map( ( item, index ) => {
						const isActive = activeIndex === index;

						return el( 'div', {
							key:       item._key || index,
							className: 'accordion-item' + ( isActive ? ' is-open' : '' ),
						},

							// ── Trigger ───────────────────────────────────────
							el( 'button', {
								type:           'button',
								className:      'accordion-trigger',
								'aria-expanded': String( isActive ),
								onClick:        () => setActiveIndex( isActive ? null : index ),
							},
								style === 'icon' && item.iconSlug && el( 'span', {
									className:   'accordion-icon',
									'aria-hidden': 'true',
								},
									el( 'svg', { focusable: 'false' },
										el( 'use', { href: `#icon-${ item.iconSlug }` } )
									)
								),

								el( 'span', { className: 'accordion-question' },
									item.question || __( '(empty)', 'momentive' )
								),

								style === 'categorized' && item.category && el( 'span', {
									className:       'accordion-category',
									'data-category': item.category
										? item.category.toLowerCase().replace( /\s+/g, '-' )
										: '',
								}, item.category ),

								el( 'span', { className: 'accordion-chevron', 'aria-hidden': 'true' },
									el( 'svg', { viewBox: '0 0 12 12', xmlns: 'http://www.w3.org/2000/svg' },
										el( 'path', {
											d:              'M1.5 4L6 8L10.5 4',
											stroke:         'currentColor',
											strokeWidth:    '1.5',
											fill:           'none',
											strokeLinecap:  'round',
										} )
									)
								)
							),

							// ── Panel — RichText when active ──────────────────
							isActive && el( 'div', {
								className: 'accordion-panel',
								role:      'region',
							},
								el( 'div', { className: 'accordion-panel-inner' },
									el( RichText, {
										tagName:        'div',
										multiline:      'p',
										value:          ensureRichTextValue( item.answer ),
										onChange:       ( val ) => updateItem( index, { answer: val } ),
										allowedFormats: [
											'core/bold',
											'core/italic',
											'core/link',
											'core/strikethrough',
										],
										placeholder:    __( 'Enter answer…', 'momentive' ),
										className:      'accordion-rich-answer',
									} )
								)
							)
						);
					} )
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