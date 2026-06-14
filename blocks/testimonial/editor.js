( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el                = wp.element.createElement;
	var Fragment          = wp.element.Fragment;
	var useState          = wp.element.useState;
	var useEffect         = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var ToggleControl     = wp.components.ToggleControl;
	var ComboboxControl   = wp.components.ComboboxControl;
	var Spinner           = wp.components.Spinner;
	var apiFetch          = wp.apiFetch;
	var __                = wp.i18n.__;

	registerBlockType( 'momentive/testimonial', {
		title:       __( 'Testimonial', 'momentive' ),
		description: __( 'A single testimonial card pulled from the Testimonials CPT.', 'momentive' ),
		category:    'momentive',
		icon:        'format-quote',
		attributes: {
			testimonialId:       { type: 'integer', default: 0 },
			showCaseStudyButton: { type: 'boolean', default: true },
		},
		supports: { html: false },

		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var context     = props.context || {};
			var inQueryLoop = context.postType === 'testimonials';

			var optionsState = useState( [] );
			var options      = optionsState[0];
			var setOptions   = optionsState[1];

			var previewState = useState( null );
			var preview      = previewState[0];
			var setPreview   = previewState[1];

			var loadingState = useState( false );
			var loading      = loadingState[0];
			var setLoading   = loadingState[1];

			// Resolved solution color for the selected testimonial.
			var colorState = useState( '' );
			var solColor   = colorState[0];
			var setSolColor = colorState[1];

			// Populate combobox with all testimonial posts (title = author name).
			useEffect( function () {
				apiFetch( {
					path: '/wp/v2/testimonials?per_page=100&orderby=title&order=asc&_fields=id,title',
				} ).then( function ( posts ) {
					setOptions( posts.map( function ( p ) {
						return { value: p.id, label: p.title.rendered };
					} ) );
				} );
			}, [] );

			// Fetch preview data and solution color when selection changes.
			useEffect( function () {
				if ( ! attributes.testimonialId ) {
					setPreview( null );
					setSolColor( '' );
					return;
				}
				setLoading( true );
				apiFetch( {
					path: '/wp/v2/testimonials/' + attributes.testimonialId + '?_fields=id,title,content,acf,categories',
				} ).then( function ( post ) {
					setPreview( post );

					// Resolve solution color from the testimonial's first category.
					// The tag_color field is exposed on the category REST endpoint.
					var cats = post.categories || [];
					if ( cats.length ) {
						apiFetch( {
							path: '/wp/v2/categories/' + cats[0] + '?_fields=id,tag_color',
						} ).then( function ( term ) {
							setSolColor( term.tag_color || '' );
						} ).catch( function () {
							setSolColor( '' );
						} );
					} else {
						setSolColor( '' );
					}

					setLoading( false );
				} ).catch( function () {
					setLoading( false );
				} );
			}, [ attributes.testimonialId ] );

			var blockProps = useBlockProps( {
				className: 'testimonial',
				style: solColor ? { '--solution': solColor } : {},
			} );

			var acf = ( preview && preview.acf ) ? preview.acf : {};

			// ── Editor preview ────────────────────────────────────────────────

			var previewContent = null;

			if ( ! attributes.testimonialId && ! inQueryLoop ) {
				previewContent = el( 'p', { className: 'testimonial-placeholder', style: { opacity: 0.5 } },
					__( 'Select a testimonial in the block settings.', 'momentive' )
				);
			} else if ( ! attributes.testimonialId && inQueryLoop ) {
				previewContent = el( 'p', { className: 'testimonial-placeholder', style: { opacity: 0.5 } },
					__( 'Testimonial — rendered from query loop.', 'momentive' )
				);
			} else if ( loading ) {
				previewContent = el( Spinner );
			} else if ( preview ) {
				var attributionChildren = [];

				if ( acf.testimonial_author_photo && acf.testimonial_author_photo.url ) {
					attributionChildren.push(
						el( 'figure', { key: 'photo' },
							el( 'img', {
								src: acf.testimonial_author_photo.url,
								alt: acf.testimonial_author_name || '',
							} )
						)
					);
				}

				if ( acf.testimonial_author_name ) {
					attributionChildren.push(
						el( 'p', { key: 'name', className: 'name' }, acf.testimonial_author_name )
					);
				}

				if ( acf.testimonial_author_description ) {
					attributionChildren.push(
						el( 'p', { key: 'org', className: 'organization' }, acf.testimonial_author_description )
					);
				}

				var previewChildren = [
					el( 'blockquote', {
						key: 'quote',
						className: 'testimonial-quote',
						dangerouslySetInnerHTML: { __html: preview.content.rendered },
					} ),
					el( 'div', { key: 'attribution', className: 'attribution' }, attributionChildren ),
				];

				if ( attributes.showCaseStudyButton && acf.related_case_study ) {
					previewChildren.push(
						el( 'p', {
							key: 'cs-note',
							style: { fontSize: '0.8rem', opacity: 0.5, marginTop: '0.75rem' },
						}, __( '→ "Read the Case Study" button will appear here', 'momentive' ) )
					);
				}

				previewContent = previewChildren;
			}

			return el(
				Fragment, null,

				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Testimonial', 'momentive' ), initialOpen: true },
						el( ComboboxControl, {
							label:    __( 'Select testimonial', 'momentive' ),
							value:    attributes.testimonialId,
							options:  options,
							onChange: function ( val ) { setAttributes( { testimonialId: val } ); },
							onFilterValueChange: function () {},
						} )
					),
					el( PanelBody, { title: __( 'Display', 'momentive' ), initialOpen: true },
						el( ToggleControl, {
							label:    __( 'Show case study button', 'momentive' ),
							help:     __( 'Displays a "Read the Case Study" button when a related case study is set on the testimonial post.', 'momentive' ),
							checked:  attributes.showCaseStudyButton,
							onChange: function ( val ) { setAttributes( { showCaseStudyButton: val } ); },
						} )
					)
				),

				el( 'div', blockProps, previewContent )
			);
		},

		save: function () { return null; },
	} );

} ( window.wp ) );
