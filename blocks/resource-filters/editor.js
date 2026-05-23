( function ( wp ) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var el                = wp.element.createElement;
    var Fragment          = wp.element.Fragment;
    var useState          = wp.element.useState;  // ← needed for textarea
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps     = wp.blockEditor.useBlockProps;
    var PanelBody         = wp.components.PanelBody;
    var ToggleControl     = wp.components.ToggleControl;
    var TextareaControl   = wp.components.TextareaControl;
    var SelectControl     = wp.components.SelectControl;
    var __                = wp.i18n.__;

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

    function postTypesText( postTypes ) {
        return ( postTypes || [] )
            .map( function ( pt ) { return pt.slug + ' | ' + pt.label; } )
            .join( '\n' );
    }

    var POST_TYPE_OPTIONS = [
        { label: 'Blog posts (post)',              value: 'post'            },
        { label: 'Newsroom (press-article)',       value: 'press-article'  },
        { label: 'Case Studies',                   value: 'case_studies'   },
        { label: 'Events',                         value: 'events'         },
        { label: 'Guides',                         value: 'guides'         },
        { label: 'Webinars',                       value: 'webinars'       },
        { label: 'Videos',                         value: 'videos'         },
        { label: 'Whitepapers',                    value: 'whitepapers'    },
        { label: 'Infographics',                   value: 'infographics'   },
        { label: 'Toolkits',                       value: 'toolkits'       },
    ];

    registerBlockType( 'momentive/resource-filters', {
        title:       __( 'Resource Filters', 'momentive' ),
        description: __( 'Filter and sort bar for post query loops.', 'momentive' ),
        category:    'momentive',
        icon:        'filter',
        apiVersion:  3,
        supports:    { html: false },

        // Attributes are authoritative in block.json — listed here only
        // so the editor has defaults when block.json isn't loaded first.
        attributes: {
            defaultPostType: { type: 'string',  default: 'post'  },
            showCategories:  { type: 'boolean', default: true    },
            showPostTypes:   { type: 'boolean', default: false   },
            showSearch:      { type: 'boolean', default: false   },
            showSort:        { type: 'boolean', default: true    },
            postTypes:       { type: 'array',   default: []      },
        },

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;

            // Local state for the textarea so it can update on each keystroke.
            // The parsed result is saved to block attributes on each change.
            var initialText = postTypesText( attributes.postTypes );
            var textState   = useState( initialText );
            var rawText     = textState[0];
            var setRawText  = textState[1];

            var blockProps = useBlockProps( {
                className: 'resource-filter-bar resource-filter-bar--editor',
                style: {
                    padding:      '0.75rem 1rem',
                    background:   'var(--superlight-accent-color, #eff9fd)',
                    border:       '1px dashed var(--accent-color, #0078ff)',
                    borderRadius: '0.5rem',
                    fontSize:     '0.875rem',
                    opacity:      0.8,
                },
            } );

            var activeLabel = POST_TYPE_OPTIONS.find( function ( o ) {
                return o.value === attributes.defaultPostType;
            } );

            var summary = [
                activeLabel && ( 'Post type: ' + activeLabel.label ),
                attributes.showSearch     && 'Search',
                attributes.showCategories && 'Topics',
                attributes.showPostTypes  && 'Resource types',
                attributes.showSort       && 'Sort',
            ].filter( Boolean ).join( ' · ' ) || 'No filters enabled';

            return el( Fragment, null,

                el( InspectorControls, null,
                    el( PanelBody, {
                        title: __( 'Filter Bar Settings', 'momentive' ),
                        initialOpen: true,
                    },

                        el( SelectControl, {
                            label:   __( 'Post type', 'momentive' ),
                            help:    __( 'Sets the default CPT for queries and filters categories to those used by that post type.', 'momentive' ),
                            value:   attributes.defaultPostType,
                            options: POST_TYPE_OPTIONS,
                            onChange: function ( val ) {
                                setAttributes( { defaultPostType: val } );
                            },
                        } ),

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

                        // Textarea uses local useState so typing works correctly.
                        attributes.showPostTypes && el( TextareaControl, {
                            label: __( 'Resource types (one per line: slug | Label)', 'momentive' ),
                            help:  __( 'Example: post | Blogs', 'momentive' ),
                            value: rawText,
                            rows:  8,
                            onChange: function ( text ) {
                                setRawText( text );
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

                el( 'div', blockProps,
                    el( 'strong', null, __( 'Resource Filters', 'momentive' ) ),
                    el( 'span', { style: { marginLeft: '0.5rem', color: '#666' } }, summary )
                )
            );
        },

        save: function () { return null; },
    } );

} ( window.wp ) );