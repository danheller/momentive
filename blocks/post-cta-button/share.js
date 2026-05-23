( function () {
	'use strict';

	document.querySelectorAll( '.social-share__btn--copy' ).forEach( function ( btn ) {
		var tooltip  = btn.querySelector( '.social-share__tooltip' );
		var url      = btn.dataset.url || window.location.href;
		var resetTimer;

		btn.addEventListener( 'click', function () {
			navigator.clipboard.writeText( url ).then( function () {
				// Show confirmation state
				btn.classList.add( 'is-copied' );
				btn.setAttribute( 'aria-label', 'Link copied!' );
				if ( tooltip ) tooltip.textContent = 'Copied!';

				clearTimeout( resetTimer );
				resetTimer = setTimeout( function () {
					btn.classList.remove( 'is-copied' );
					btn.setAttribute( 'aria-label', 'Copy link to clipboard' );
					if ( tooltip ) tooltip.textContent = 'Copy link';
				}, 2000 );

			} ).catch( function () {
				// Fallback for browsers without clipboard API
				var input = document.createElement( 'input' );
				input.value = url;
				input.style.position = 'fixed';
				input.style.opacity  = '0';
				document.body.appendChild( input );
				input.select();
				document.execCommand( 'copy' );
				document.body.removeChild( input );

				btn.classList.add( 'is-copied' );
				if ( tooltip ) tooltip.textContent = 'Copied!';
				clearTimeout( resetTimer );
				resetTimer = setTimeout( function () {
					btn.classList.remove( 'is-copied' );
					if ( tooltip ) tooltip.textContent = 'Copy link';
				}, 2000 );
			} );
		} );
	} );

} () );