/**
 * Accordion block — frontend JS
 * blocks/accordion/accordion.js
 *
 * No build step. Vanilla JS, no dependencies.
 *
 * Handles:
 *  - Open / close individual items via <button aria-expanded>.
 *  - "Close others" behaviour when .js-close-others is present.
 *  - Keyboard: Arrow keys move focus between triggers; Home/End jump to first/last.
 */

( function () {
	'use strict';

	// ── Per-accordion initialiser ─────────────────────────────────────────────

	function initAccordion( accordion ) {
		const closeOthers = accordion.classList.contains( 'js-close-others' );
		const triggers    = Array.from( accordion.querySelectorAll( '.accordion-trigger' ) );

		triggers.forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				const isOpen = trigger.getAttribute( 'aria-expanded' ) === 'true';

				if ( closeOthers && ! isOpen ) {
					// Close all other items before opening this one.
					triggers.forEach( function ( other ) {
						if ( other !== trigger ) closeItem( other );
					} );
				}

				isOpen ? closeItem( trigger ) : openItem( trigger );
			} );
		} );

		// Arrow-key navigation between triggers.
		accordion.addEventListener( 'keydown', function ( e ) {
			if ( ! [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( e.key ) ) return;
			const focused = document.activeElement;
			if ( ! triggers.includes( focused ) ) return;

			e.preventDefault();
			const index = triggers.indexOf( focused );

			if ( e.key === 'ArrowDown' ) triggers[ ( index + 1 ) % triggers.length ].focus();
			if ( e.key === 'ArrowUp'   ) triggers[ ( index - 1 + triggers.length ) % triggers.length ].focus();
			if ( e.key === 'Home'      ) triggers[ 0 ].focus();
			if ( e.key === 'End'       ) triggers[ triggers.length - 1 ].focus();
		} );
	}


	function openItem( trigger ) {
		const panel = document.getElementById( trigger.getAttribute( 'aria-controls' ) );
		trigger.setAttribute( 'aria-expanded', 'true' );
		trigger.closest( '.accordion-item' )?.classList.add( 'is-open' );
		if ( panel ) panel.removeAttribute( 'hidden' );
	}

	function closeItem( trigger ) {
		const panel = document.getElementById( trigger.getAttribute( 'aria-controls' ) );
		trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.closest( '.accordion-item' )?.classList.remove( 'is-open' );
		if ( panel ) panel.setAttribute( 'hidden', '' );
	}

	/**
	 * Build a single .accordion-item element from a REST API post object.
	 * This mirrors the server-rendered markup in block.php.
	 */
	function buildItemElement( post ) {
		const itemId  = 'accordion-item-' + post.id;
		const panelId = itemId + '-panel';

		const item = document.createElement( 'div' );
		item.className = 'accordion-item';

		item.innerHTML =
			'<button class="accordion-trigger" type="button" aria-expanded="false"' +
			' aria-controls="' + panelId + '" id="' + itemId + '" data-init>' +
			'<span class="accordion-question">' + ( post.title?.rendered || '' ) + '</span>' +
			'<span class="accordion-chevron" aria-hidden="true">' +
			'<svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">' +
			'<path d="M1.5 4L6 8L10.5 4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>' +
			'</svg></span>' +
			'</button>' +
			'<div class="accordion-panel" id="' + panelId + '"' +
			' role="region" aria-labelledby="' + itemId + '" hidden>' +
			'<div class="accordion-panel-inner">' + ( post.content?.rendered || '' ) + '</div>' +
			'</div>';

		return item;
	}


	// ── Boot ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.momentive-accordion' ).forEach( initAccordion );
	} );

} )();
