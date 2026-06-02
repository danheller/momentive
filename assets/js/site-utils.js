( function ( window ) {
	'use strict';

	const Utils = window.SiteUtils = window.SiteUtils || {};

	Utils.esc = function ( str ) {
		return String( str ?? '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	};

	// Build a category <a> with the --solution style, if available.
	Utils.renderCategoryLink = function ( cat ) {
		const style = cat.tag_color
			? ` style="--solution:${ Utils.esc( cat.tag_color ) }"`
			: '';
		return `<a href="${ Utils.esc( cat.link ) }" rel="tag"${ style }>${ Utils.esc( cat.name ) }</a>`;
	};

	// Expand/collapse for overflowing .lower-label term lists inside .story-card.
	// Idempotent: safe to call repeatedly on the same root (re-binds via clone).
	Utils.initLowerLabels = function ( container ) {
		const root = container ?? document;

		root.querySelectorAll( '.story-card .lower-label' ).forEach( el => {
			// Drop any prior listeners by replacing the node.
			const fresh = el.cloneNode( true );
			el.replaceWith( fresh );
		} );

		root.querySelectorAll( '.story-card .lower-label' ).forEach( el => {
			if ( el.scrollHeight <= el.clientHeight + 2 ) return;

			el.style.cursor = 'pointer';
			el.setAttribute( 'title', 'Show all categories' );
			el.setAttribute( 'role', 'button' );
			el.setAttribute( 'tabindex', '0' );

			function toggle( e ) {
				// Uncomment if you want clicks on category links to skip the toggle:
				// if ( e.target.tagName === 'A' ) return;
				el.classList.toggle( 'is-expanded' );
				el.setAttribute( 'title',
					el.classList.contains( 'is-expanded' )
						? 'Show fewer categories'
						: 'Show all categories'
				);
			}

			el.addEventListener( 'click', toggle );
			el.addEventListener( 'keydown', e => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					toggle( e );
				}
			} );
		} );
	};
	
	Utils.debounce = function ( fn, ms ) {
		let timer;
		return ( ...args ) => {
			clearTimeout( timer );
			timer = setTimeout( () => fn( ...args ), ms );
		};
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		Utils.initLowerLabels( document );
	} );

} )( window );