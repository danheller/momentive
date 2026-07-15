( function () {
	wp.blocks.registerBlockType( 'momentive/back-link', {
		edit: function ( props ) {
			var url   = props.attributes.url   || '';
			var label = props.attributes.label || 'All posts';
			return wp.element.createElement(
				'div',
				wp.blockEditor.useBlockProps( { className: 'back-link' } ),
				url
					? wp.element.createElement( 'a', { href: url }, label )
					: wp.element.createElement( 'span', { style: { opacity: 0.5, fontStyle: 'italic' } }, 'Back Link — set URL in block.json or code view' )
			);
		},
		save: function () { return null; },
	} );
} )();
