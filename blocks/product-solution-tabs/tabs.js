/**
 * Product Solution Tabs — tab switching with URL hash sync.
 *
 * - Clicking a tab activates its panel and updates the URL hash
 *   (via replaceState, not pushState, so the back button doesn't
 *   step through tab history).
 * - On load, a matching hash pre-selects that tab.
 * - If the hash changes after load (e.g. an in-page link elsewhere
 *   on the same page points to #fundraising), the matching tab
 *   activates without a page reload.
 *
 * No build step; vanilla JS, consistent with other lightweight blocks.
 */

( function () {
	'use strict';

	function activateTab( root, slug, updateHash ) {
		var tabs   = root.querySelectorAll( '.tab' );
		var panels = root.querySelectorAll( '.panel' );
		var found  = false;
		var label  = '';

		tabs.forEach( function ( tab ) {
			var isMatch = tab.getAttribute( 'data-tab' ) === slug;
			tab.classList.toggle( 'is-active', isMatch );
			// aria-selected only applies to the desktop tab-row buttons
			// (role="tab"); dropdown options don't carry that role, but
			// toggling the attribute on them is harmless.
			tab.setAttribute( 'aria-selected', isMatch ? 'true' : 'false' );
			if ( isMatch ) {
				found = true;
				var labelEl = tab.querySelector( '.tab-label, .dropdown-option-label' );
				if ( labelEl ) label = labelEl.textContent;
			}
		} );

		if ( ! found ) return false;

		panels.forEach( function ( panel ) {
			var isMatch = panel.getAttribute( 'data-tab' ) === slug;
			panel.classList.toggle( 'is-active', isMatch );
			if ( isMatch ) {
				panel.removeAttribute( 'hidden' );
			} else {
				panel.setAttribute( 'hidden', '' );
			}
		} );

		// Keep the mobile dropdown's closed-state label in sync regardless
		// of whether activation came from a tab-row click, a dropdown-row
		// click, or an initial hash match.
		var currentLabel = root.querySelector( '.dropdown-current-label' );
		if ( currentLabel && label ) {
			currentLabel.textContent = label;
		}

		if ( updateHash ) {
			history.replaceState( null, '', '#' + slug );
		}

		return true;
	}

	function initDropdown( root ) {
		var toggle  = root.querySelector( '.dropdown-current' );
		var options = root.querySelector( '.dropdown-options' );

		if ( ! toggle || ! options ) return;

		function close() {
			options.setAttribute( 'hidden', '' );
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		function open() {
			options.removeAttribute( 'hidden' );
			toggle.setAttribute( 'aria-expanded', 'true' );
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = toggle.getAttribute( 'aria-expanded' ) === 'true';
			if ( isOpen ) {
				close();
			} else {
				open();
			}
		} );

		// Dropdown rows reuse activateTab() — same data-tab attribute,
		// same underlying behavior as a desktop tab click — and additionally
		// close the dropdown afterward, which the desktop tabs don't need.
		options.querySelectorAll( '.dropdown-option' ).forEach( function ( option ) {
			option.addEventListener( 'click', function () {
				activateTab( root, option.getAttribute( 'data-tab' ), true );
				close();
			} );
		} );

		// Click anywhere outside the dropdown's own toggle/options closes it
		// — whether that click lands elsewhere on the page or elsewhere
		// inside this same block (e.g. on a product card).
		document.addEventListener( 'click', function ( event ) {
			if ( toggle.contains( event.target ) || options.contains( event.target ) ) return;
			close();
		} );

		// Escape closes it too, for keyboard users.
		options.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				close();
				toggle.focus();
			}
		} );
	}

	function init( root ) {
		root.querySelectorAll( '.tab' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				activateTab( root, tab.getAttribute( 'data-tab' ), true );
			} );
		} );

		initDropdown( root );

		// The server always renders the desktop default as active (first
		// real solution), since it has no way to know the viewport width.
		// If we're actually at mobile width on load, and there's no hash
		// directing us elsewhere, correct to the mobile default (All)
		// instead. NOTE: 960px must match the breakpoint in style.css —
		// there's no way to read that value back out of the stylesheet,
		// so if the CSS breakpoint changes, update this too.
		var initialHash = window.location.hash.replace( '#', '' );

		if ( ! initialHash && window.matchMedia( '(max-width: 960px)' ).matches ) {
			var mobileDefault = root.getAttribute( 'data-mobile-default' );
			if ( mobileDefault ) {
				activateTab( root, mobileDefault, false );
			}
		} else if ( initialHash ) {
			activateTab( root, initialHash, false );
		}

		// Respond to hash changes that happen after load (e.g. an in-page
		// link elsewhere on the page pointing at #some-solution-slug).
		window.addEventListener( 'hashchange', function () {
			var slug = window.location.hash.replace( '#', '' );
			if ( slug ) {
				activateTab( root, slug, false );
			}
		} );
	}

	document.querySelectorAll( '.momentive-product-solution-tabs' ).forEach( init );
} )();