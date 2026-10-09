(function (wp) {
    'use strict';
    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var TextControl = wp.components.TextControl;
    var PanelBody = wp.components.PanelBody;
    var InspectorControls = wp.blockEditor.InspectorControls;

    registerBlockType('base3/display', {
        apiVersion: 2,
        title: 'BASE3 Display',
        description: 'Render a BASE3 output or display in this page.',
        icon: 'admin-generic',
        category: 'widgets',
        attributes: {
            name: { type: 'string', default: '' },
            out: { type: 'string', default: 'html' }
        },
        edit: function (props) {
            var inspector = el(InspectorControls, {},
                el(PanelBody, { title: 'BASE3', initialOpen: true },
                    el(TextControl, {
                        label: 'Technical BASE3 display name',
                        value: props.attributes.name,
                        onChange: function (value) { props.setAttributes({ name: value }); }
                    }),
                    el(TextControl, {
                        label: 'Output format',
                        value: props.attributes.out,
                        onChange: function (value) { props.setAttributes({ out: value }); }
                    })
                )
            );
            return el(wp.element.Fragment, {}, inspector,
                el('div', { className: 'base3-block-preview' },
                    props.attributes.name ? 'BASE3: ' + props.attributes.name : 'Enter a BASE3 display name in the block settings.'
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp);
