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

	// Subtle parallax for the "Glow Lights" dark-mode hero background
	// (assets/sass/solutions-dark.scss, .is-style-bg-glow-lights) — nudges
	// each glow blob vertically by a fraction of scroll distance via the
	// --glow-parallax custom property the SCSS already reads (defaults to
	// 0px, so the glow is simply static if this never runs). Skipped
	// entirely under prefers-reduced-motion.
	Utils.initGlowParallax = function ( container ) {
		const root = container ?? document;
		const els = root.querySelectorAll( '.is-style-bg-glow-lights' );
		if ( ! els.length ) return;
		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) return;

		const SPEED = 0.15; // fraction of scroll distance the glow travels
		let ticking = false;

		function update() {
			els.forEach( el => {
				const offset = -el.getBoundingClientRect().top * SPEED;
				el.style.setProperty( '--glow-parallax', `${ offset }px` );
			} );
			ticking = false;
		}

		window.addEventListener( 'scroll', () => {
			if ( ticking ) return;
			ticking = true;
			requestAnimationFrame( update );
		}, { passive: true } );

		update(); // set initial position without waiting for the first scroll
	};

	// Scroll-reveal: observes .animate-on-scroll elements and direct children of
	// .animate-on-scroll--stagger wrappers, adding .is-visible when they enter
	// the viewport. Staggered children receive an incremental --reveal-delay so
	// they cascade in. Each element is unobserved after its first reveal.
	Utils.initScrollReveal = function () {
		const STAGGER_MS = 80;
		const observer = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => {
				if ( ! entry.isIntersecting ) return;
				entry.target.classList.add( 'is-visible' );
				observer.unobserve( entry.target );
			} );
		}, { threshold: 0.1 } );

		// Plain .animate-on-scroll elements
		document.querySelectorAll( '.animate-on-scroll' ).forEach( ( el ) => {
			observer.observe( el );
		} );

		// Stagger wrappers: observe each direct child with a cascading delay
		document.querySelectorAll( '.animate-on-scroll--stagger' ).forEach( ( wrapper ) => {
			Array.from( wrapper.children ).forEach( ( child, i ) => {
				child.style.setProperty( '--reveal-delay', `${ i * STAGGER_MS }ms` );
				observer.observe( child );
			} );
		} );
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		Utils.initLowerLabels( document );
		Utils.initGlowParallax( document );
		Utils.initScrollReveal();
	} );

} )( window );