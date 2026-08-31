( function() {
	const { subscribe, select } = wp.data;

	// Both fields are parent-inherited (see momentive_solution_inherited_field()
	// in inc/solutions.php) — hide both the same way when a parent is set.
	const INHERITED_FIELD_NAMES = [ 'accent_color', 'dark_mode' ];

	let lastParent = null;

	const unsubscribe = subscribe( () => {
		const postParent = select( 'core/editor' ).getEditedPostAttribute( 'parent' );

		if ( postParent === lastParent ) return;
		lastParent = postParent;

		INHERITED_FIELD_NAMES.forEach( ( name ) => {
			const acfField = document.querySelector( `.acf-field[data-name="${ name }"]` );
			if ( ! acfField ) return;

			acfField.style.display = postParent ? 'none' : '';
		} );
	} );
} )();