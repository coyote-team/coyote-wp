//wp.data.subscribe(function () { let select = wp.data.select('core/block-editor'); console.debug(select) });
var el = wp.element.createElement;
 
var withInspectorControls = wp.compose.createHigherOrderComponent( function( BlockEdit ) {
    return function( props ) {
        console.debug([props, BlockEdit]);
        return el(
            wp.element.Fragment,
            {},
            el(
                BlockEdit,
                props
            ),
            el(
                wp.blockEditor.InspectorControls,
                {},
                el(
                    wp.components.PanelBody,
                    {},
                    'My custom control'
                )
            )
        );
    };
}, 'withInspectorControls' );
 
wp.hooks.addFilter( 'editor.BlockEdit', 'my-plugin/with-inspector-controls', withInspectorControls );

