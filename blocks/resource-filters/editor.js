( function ( wp ) {
	'use strict';
	
	var registerBlockType  = wp.blocks.registerBlockType;
	var el                 = wp.element.createElement;
	var Fragment           = wp.element.Fragment;
	var useState           = wp.element.useState;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var PanelBody          = wp.components.PanelBody;
	var TextControl        = wp.components.TextControl;
	var ToggleControl      = wp.components.ToggleControl;
	var TextareaControl    = wp.components.TextareaControl;
	var __                 = wp.i18n.__;
	
	function parsePostTypes( text ) {
		return ( text || '' )
			.split( '\n' )
			.map( function ( line ) {
				var parts = line.split( '|' );
				return {
					slug:  ( parts[0] || '' ).trim(),
					label: ( parts.slice(1).join('|') || '' ).trim(),
				};
			} )
			.filter( function ( pt ) { return pt.slug && pt.label; } );
	}
	
	registerBlockType( 'momentive/resource-filters', {
		title:       __( 'Resource Filters', 'momentive' ),
		description: __( 'Filter and sort bar for post query loops.', 'momentive' ),
		category:    'momentive',
		icon:        'filter',
		attributes: {
			queryId:         { type: 'string',  default: '' },
			showCategories:  { type: 'boolean', default: true },
			showPostTypes:   { type: 'boolean', default: false },
			showSearch:      { type: 'boolean', default: false },
			showSort:        { type: 'boolean', default: true },
			postTypes:       { type: 'array',   default: [] },
		},
		supports: {
			html: false,
		},
	
		edit: function ( props ) {
			var attributes   = props.attributes;
			var setAttributes = props.setAttributes;
	
			var blockProps = useBlockProps( {
				className: 'resource-filter-bar resource-filter-bar--editor',
				style: {
					padding:    '0.75rem 1rem',
					background: 'var(--superlight-accent-color, #eff9fd)',
					border:     '1px dashed var(--accent-color, #0078ff)',
					borderRadius: '0.5rem',
					fontSize:   '0.875rem',
					opacity:    0.8,
				},
			} );
	
			var postTypesText = ( attributes.postTypes || [] )
				.map( function ( pt ) { return pt.slug + ' | ' + pt.label; } )
				.join( '\n' );
	
			var summary = [
				attributes.showSearch     && 'Search',
				attributes.showCategories && 'Topics',
				attributes.showPostTypes  && 'Resource types',
				attributes.showSort       && 'Sort',
			].filter( Boolean ).join( ' · ' );
	
			return el(
				Fragment,
				null,
	
				// ── Inspector panel ──────────────────────────────────────────
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Filter Bar Settings', 'momentive' ), initialOpen: true },
	
						el( ToggleControl, {
							label:   __( 'Show category / topics filter', 'momentive' ),
							checked: attributes.showCategories,
							onChange: function ( val ) {
								setAttributes( { showCategories: val } );
							},
						} ),
	
						el( ToggleControl, {
							label:   __( 'Show resource type filter', 'momentive' ),
							checked: attributes.showPostTypes,
							onChange: function ( val ) {
								setAttributes( { showPostTypes: val } );
							},
						} ),
	
						attributes.showPostTypes && el( TextareaControl, {
							label: __( 'Resource types (one per line: slug | Label)', 'momentive' ),
							help:  __( 'Example:  post | Blogs', 'momentive' ),
							value: postTypesText,
							rows:  8,
							onChange: function ( text ) {
								setAttributes( { postTypes: parsePostTypes( text ) } );
							},
						} ),
	
						el( ToggleControl, {
							label:   __( 'Show search', 'momentive' ),
							checked: attributes.showSearch,
							onChange: function ( val ) {
								setAttributes( { showSearch: val } );
							},
						} ),
	
						el( ToggleControl, {
							label:   __( 'Show sort', 'momentive' ),
							checked: attributes.showSort,
							onChange: function ( val ) {
								setAttributes( { showSort: val } );
							},
						} )
					)
				),
	
				// ── Editor preview ───────────────────────────────────────────
				el( 'div', blockProps,
					el( 'strong', null, __( 'Resource Filters', 'momentive' ) ),
					el( 'span', { style: { marginLeft: '0.5rem', color: '#666' } }, summary )
				)
			);
		},
	
		save: function () {
			// Server-rendered.
			return null;
		},
	} );

} ( window.wp ) );