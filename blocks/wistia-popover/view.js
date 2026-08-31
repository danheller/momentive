( function () {
	'use strict';

	/**
	 * Wire up any .js-video-play button to open the nearest wistia-player popover.
	 *
	 * Editors add the class via the button block's Advanced → Additional CSS class
	 * panel. No markup changes to the button itself are needed.
	 *
	 * Scoping: looks for wistia-player inside the same column first so the right
	 * player opens when multiple video blocks appear on one page. Falls back to
	 * the first player on the page if no column ancestor is found.
	 *
	 * Timing: Wistia's player.js is async. By the time a user clicks the button it
	 * will almost always be loaded, but we guard against the rare case where it
	 * isn't by deferring to customElements.whenDefined().
	 */
	function initVideoTriggers() {
		document.querySelectorAll( '.js-video-play' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				// Walk up to the nearest column/section and search within it;
				// fall back to the first player on the page.
				var container = btn.closest( '.wp-block-column, .post-sidebar, .post-layout' );
				var player    = ( container || document ).querySelector( 'wistia-player' );

				if ( ! player ) return;

				if ( typeof player.play === 'function' ) {
					player.play();
				} else {
					// Wistia still defining the custom element — wait, then play.
					customElements.whenDefined( 'wistia-player' ).then( function () {
						player.play();
					} );
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initVideoTriggers );
	} else {
		initVideoTriggers();
	}
} )();
