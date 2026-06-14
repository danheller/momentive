( function ( wp ) {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var el                = wp.element.createElement;
    var Fragment          = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps     = wp.blockEditor.useBlockProps;
    var PanelBody         = wp.components.PanelBody;
    var ToggleControl     = wp.components.ToggleControl;
    var TextControl       = wp.components.TextControl;
    var __                = wp.i18n.__;

    registerBlockType( 'momentive/breadcrumbs', {
        title:       __( 'Breadcrumbs', 'momentive' ),
        description: __( 'Breadcrumb trail for the current post or page.', 'momentive' ),
        category:    'momentive',
        icon:        'admin-links',
        attributes: {
            showHome:  { type: 'boolean', default: true },
            homeLabel: { type: 'string',  default: 'Home' },
            separator: { type: 'string',  default: '›' },
        },
        supports: { html: false },

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;

            var blockProps = useBlockProps( {
                className: 'breadcrumbs breadcrumbs--editor',
                style: { fontSize: '0.875rem', opacity: 0.7 },
            } );

            // Preview trail shown in editor.
            var sep      = attributes.separator || '›';
            var preview  = ( attributes.showHome ? ( attributes.homeLabel || 'Home' ) + ' ' + sep + ' ' : '' )
                         + 'Category ' + sep + ' Post title';

            return el(
                Fragment, null,

                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Breadcrumb Settings', 'momentive' ), initialOpen: true },
                        el( ToggleControl, {
                            label:   __( 'Show home link', 'momentive' ),
                            checked: attributes.showHome,
                            onChange: function ( val ) { setAttributes( { showHome: val } ); },
                        } ),
                        attributes.showHome && el( TextControl, {
                            label:   __( 'Home label', 'momentive' ),
                            value:   attributes.homeLabel,
                            onChange: function ( val ) { setAttributes( { homeLabel: val } ); },
                        } ),
                        el( TextControl, {
                            label:   __( 'Separator', 'momentive' ),
                            value:   attributes.separator,
                            onChange: function ( val ) { setAttributes( { separator: val } ); },
                        } )
                    )
                ),

                el( 'div', blockProps,
                    el( 'nav', { 'aria-label': 'Breadcrumb preview' },
                        el( 'span', null, preview )
                    )
                )
            );
        },

        save: function () { return null; },
    } );

} ( window.wp ) );