( function () {
	'use strict';

	var tocBlock = document.getElementById( 'toc-block' );
	if ( ! tocBlock ) return;

	var tocList    = document.getElementById( 'toc-list' );
	var toggleBtn  = tocBlock.querySelector( '.toc-toggle' );
	var storageKey = tocBlock.dataset.storageKey;
	var allLinks   = tocBlock.querySelectorAll( '.toc-link' );
	var sections   = [];
	var lastActive = null;
	var ticking    = false;

	// ── Auto-collapse if TOC exceeds threshold ────────────────────────────────
	// Collapse on load if the list height exceeds 50vh,
	// unless sessionStorage already has an explicit preference.

	var storedPref = storageKey ? sessionStorage.getItem( storageKey ) : null;

	if ( ! storedPref ) {
		var collapseThreshold = window.innerHeight * 0.5;
		if ( tocList.scrollHeight > collapseThreshold ) {
			tocList.setAttribute( 'hidden', '' );
			toggleBtn.setAttribute( 'aria-expanded', 'false' );
			toggleBtn.setAttribute( 'aria-label', 'Expand contents' );
		}
	}

	// ── Restore expand/collapse state from sessionStorage ─────────────────────

	if ( storedPref === 'collapsed' ) {
		tocList.setAttribute( 'hidden', '' );
		toggleBtn.setAttribute( 'aria-expanded', 'false' );
		toggleBtn.setAttribute( 'aria-label', 'Expand contents' );
	} else if ( storedPref === 'expanded' ) {
		tocList.removeAttribute( 'hidden' );
		toggleBtn.setAttribute( 'aria-expanded', 'true' );
		toggleBtn.setAttribute( 'aria-label', 'Collapse contents' );
	}

	// ── Toggle ────────────────────────────────────────────────────────────────

	toggleBtn && toggleBtn.addEventListener( 'click', function () {
		var isExpanded = toggleBtn.getAttribute( 'aria-expanded' ) === 'true';

		if ( isExpanded ) {
			tocList.setAttribute( 'hidden', '' );
			toggleBtn.setAttribute( 'aria-expanded', 'false' );
			toggleBtn.setAttribute( 'aria-label', 'Expand contents' );
			sessionStorage.setItem( storageKey, 'collapsed' );
		} else {
			tocList.removeAttribute( 'hidden' );
			toggleBtn.setAttribute( 'aria-expanded', 'true' );
			toggleBtn.setAttribute( 'aria-label', 'Collapse contents' );
			sessionStorage.setItem( storageKey, 'expanded' );
		}
	} );

	// ── Smooth scroll ─────────────────────────────────────────────────────────

	allLinks.forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			var href = link.getAttribute( 'href' );
			if ( ! href || href === '#' ) return;

			e.preventDefault();

			var target = document.querySelector( href );
			if ( ! target ) return;

			var headerHeight = document.querySelector( '.site-header' )?.offsetHeight ?? 64;
			var barHeight    = parseFloat(
				getComputedStyle( document.documentElement )
					.getPropertyValue( '--announcement-bar-height' )
			) || 0;

			var offset = headerHeight + barHeight + 48; // places heading 2rem below site header

			var top = target.getBoundingClientRect().top + window.scrollY - offset;

			window.scrollTo( { top: top, behavior: 'smooth' } );
			history.pushState( null, null, href );
		} );
	} );

	// ── Build section map ─────────────────────────────────────────────────────

	allLinks.forEach( function ( link ) {
		var anchor  = link.dataset.anchor;
		if ( ! anchor ) return;
		var element = document.getElementById( anchor );
		if ( ! element ) return;
		sections.push( { link: link, element: element } );
	} );

	if ( sections.length === 0 ) return;

	// ── Scroll-spy ────────────────────────────────────────────────────────────

	function getScrollOffset() {
		var headerHeight = document.querySelector( '.site-header' )?.offsetHeight ?? 64;
		var barHeight    = parseFloat(
			getComputedStyle( document.documentElement )
				.getPropertyValue( '--announcement-bar-height' )
		) || 0;

		// Trigger active state when heading reaches 30% from top
		// of the viewport. window.innerHeight * 0.3 is the 30% threshold;
		// the header/bar offset is added so the trigger point
		// is 30% of the visible area below the sticky elements.
		return headerHeight + barHeight + Math.round( window.innerHeight * 0.3 );
	}

	function updateActive() {
		var scrollY = window.scrollY;
		var offset  = getScrollOffset();
		var current = null;

		for ( var i = sections.length - 1; i >= 0; i-- ) {
			var top = sections[i].element.getBoundingClientRect().top + scrollY;
			if ( scrollY >= top - offset ) {
				current = sections[i];
				break;
			}
		}

		// Before first heading: highlight first item
		if ( ! current && scrollY < sections[0].element.getBoundingClientRect().top + scrollY ) {
			current = sections[0];
		}

		if ( current === lastActive ) {
			ticking = false;
			return;
		}

		allLinks.forEach( function ( l ) {
			l.classList.remove( 'is-active' );
			l.removeAttribute( 'aria-current' );
		} );

		if ( current ) {
			current.link.classList.add( 'is-active' );
			current.link.setAttribute( 'aria-current', 'true' );
			scrollTocToActive( current.link );
		}

		lastActive = current;
		ticking = false;
	}

	// ── Scroll TOC list to keep active link visible ───────────────────────────

	function scrollTocToActive( activeLink ) {
		if ( tocList.scrollHeight <= tocList.clientHeight ) return;
		if ( activeLink === lastActive?.link ) return;

		var listTop    = tocList.getBoundingClientRect().top;
		var linkTop    = activeLink.getBoundingClientRect().top;
		var linkHeight = activeLink.offsetHeight;
		var listHeight = tocList.clientHeight;

		var targetScroll = tocList.scrollTop + ( linkTop - listTop ) - ( listHeight / 2 ) + ( linkHeight / 2 );

		tocList.scrollTo( { top: targetScroll, behavior: 'smooth' } );
	}

	window.addEventListener( 'scroll', function () {
		if ( ! ticking ) {
			requestAnimationFrame( updateActive );
			ticking = true;
		}
	}, { passive: true } );

	window.addEventListener( 'resize', updateActive );
	window.addEventListener( 'load',   updateActive );
	updateActive();

} () );