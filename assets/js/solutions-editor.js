( function() {
	const { subscribe, select } = wp.data;

	let lastParent = null;

	const unsubscribe = subscribe( () => {
		const postParent = select( 'core/editor' ).getEditedPostAttribute( 'parent' );

		if ( postParent === lastParent ) return;
		lastParent = postParent;

		const acfField = document.querySelector( '.acf-field[data-name="accent_color"]' );
		if ( ! acfField ) return;

		acfField.style.display = postParent ? 'none' : '';
	} );
} )();