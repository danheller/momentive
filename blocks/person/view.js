/**
 * Front-end behavior for momentive/person.
 *
 * Progressive enhancement only — every card is a working link to the person's
 * permalink without this script. Here we intercept the click to open the
 * profile in a modal <dialog> instead of navigating, and we auto-open a profile
 * when the page is loaded with a matching hash (deep link).
 *
 * Deep-link contract: the card anchor's id is `person-{slug}`, so a URL ending
 * in `#person-mike-shea` opens Mike's profile on load. The shareable/canonical
 * URL remains the person's own permalink (the anchor's href); the hash is just
 * the on-page open mechanism.
 */
( function () {
	'use strict';

	function openDialog( dialog, opener ) {
		if ( ! dialog || typeof dialog.showModal !== 'function' ) {
			return false;
		}
		dialog._opener = opener || document.activeElement;
		dialog.showModal();
	
		// Reflect the open profile in the URL (no history entry, no scroll jump),
		// so the address bar is shareable. The opener card's id is `person-{slug}`.
		if ( opener && opener.id ) {
			history.replaceState( null, '', '#' + opener.id );
		}
	
		var closeBtn = dialog.querySelector( '.momentive-person__close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}
		return true;
	}
	
	function closeDialog( dialog ) {
		if ( ! dialog || ! dialog.open ) {
			return;
		}
		dialog.close();
	
		// Clear the hash on close so the URL returns to the clean page. Guard so we
		// only strip a person hash we set, not some unrelated hash on the page.
		if ( window.location.hash.indexOf( '#person-' ) === 0 ) {
			history.replaceState( null, '', window.location.pathname + window.location.search );
		}
	}

	function wireCard( card ) {
		var targetId = card.getAttribute( 'data-person-target' );
		var dialog = targetId ? document.getElementById( targetId ) : null;
		if ( ! dialog ) {
			return; // No dialog (shouldn't happen) — leave the link as-is.
		}

		card.addEventListener( 'click', function ( e ) {
			// Honor new-tab / modified clicks — let the browser navigate.
			if ( e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0 ) {
				return;
			}
			var opened = openDialog( dialog, card );
			if ( opened ) {
				e.preventDefault();
			}
			// If it didn't open (no dialog support), the click falls through
			// and the browser follows the href to the real profile page.
		} );

		// Close interactions.
		var closeBtn = dialog.querySelector( '.momentive-person__close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				closeDialog( dialog );
			} );
		}

		// Backdrop click (clicks on the dialog element itself, outside inner).
		dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === dialog ) {
				closeDialog( dialog );
			}
		} );

		// Native <dialog> already closes on Esc and fires 'close'; restore focus.
		dialog.addEventListener( 'close', function () {
			if ( dialog._opener && typeof dialog._opener.focus === 'function' ) {
				dialog._opener.focus();
			}
		} );
	}

	function init() {
		var cards = document.querySelectorAll( '.momentive-person__card[data-person-target]' );
		cards.forEach( wireCard );

		// Deep-link: if the URL hash matches a card on this page, open it.
		if ( window.location.hash ) {
			var id = window.location.hash.slice( 1 );
			var card = document.getElementById( id );
			if ( card && card.classList.contains( 'momentive-person__card' ) ) {
				var targetId = card.getAttribute( 'data-person-target' );
				var dialog = targetId ? document.getElementById( targetId ) : null;
				if ( dialog ) {
					// Defer so layout is ready before showModal scrolls/focuses.
					window.requestAnimationFrame( function () {
						openDialog( dialog, card );
					} );
				}
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
