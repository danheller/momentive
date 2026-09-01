/**
 * Consolidated HubSpot handler. Covers:
 *  1. UTM / landing page field population (all forms, via sessionStorage)
 *  2. utm_content population from page title (via both postMessage and onFormReady)
 *  3. Two-step modal flow for acf/hubspot-form blocks with `two_step` enabled
 *     – Preloads the HubSpot embed on first keypress in the email field
 *     – Opens the modal instantly (no spinner) with the email pre-filled
 *     – Advances focus to the first non-email field after pre-fill
 *     – Fires GA4 events for step-1 and step-2 to measure drop-off
 *
 * Enqueued by block.php only when acf/hubspot-form is present on the page.
 * The block's own /blocks/hubspot-form/hubspot.js is no longer needed and
 * can be deleted.
 */

( function () {
	'use strict';

	// =========================================================================
	// 1. UTM field population
	//    Runs on DOMContentLoaded and watches for dynamically injected forms.
	// =========================================================================

	const UTM_FIELDS = [
		'utm_source', 'utm_medium', 'utm_campaign',
		'utm_term', 'utm_content', 'landing_page',
	];

	function populateUtmFields() {
		UTM_FIELDS.forEach( function ( key ) {
			const field = document.querySelector( `input[name="${ key }"]` );
			const value = sessionStorage.getItem( key );
			if ( field && value ) {
				field.value = value;
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		populateUtmFields();

		// Watch for HubSpot injecting forms into the DOM (standard embeds + modal).
		const observer = new MutationObserver( function ( mutations ) {
			for ( const mutation of mutations ) {
				if ( mutation.type !== 'childList' ) continue;
				for ( const node of mutation.addedNodes ) {
					if ( node.nodeType !== 1 ) continue;
					if ( node.tagName === 'FORM' || node.querySelector?.( 'form' ) ) {
						populateUtmFields();
						break;
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	} );

	// =========================================================================
	// 2. utm_content ← page title
	//
	//    Kept via postMessage for any legacy iframe-embedded forms, AND wired
	//    into onFormReady below for same-page embeds (where postMessage won't
	//    fire). Both paths set utm_content to the page's <h1> text.
	// =========================================================================

	function setUtmContentFromTitle() {
		const h1        = document.querySelector( 'h1' );
		const postTitle = h1 ? h1.textContent.trim() : '';
		if ( ! postTitle ) return;

		const utmContentField = document.getElementsByName( 'utm_content' )[ 0 ];
		if ( utmContentField ) {
			utmContentField.value = postTitle;
		}
	}

	// Legacy postMessage path (iframe embeds).
	window.addEventListener( 'message', function ( event ) {
		if (
			event.data?.type      === 'hsFormCallback' &&
			event.data?.eventName === 'onFormReady'
		) {
			setUtmContentFromTitle();
		}
	} );

	// =========================================================================
	// 3. Two-step modal blocks
	// =========================================================================

	// -------------------------------------------------------------------------
	// HubSpot script loader — shared across all blocks on the page.
	// -------------------------------------------------------------------------

	let hsScriptPromise = null;

	function loadHubSpotScript() {
		if ( hsScriptPromise ) return hsScriptPromise;

		hsScriptPromise = new Promise( ( resolve, reject ) => {
			if ( window.hbspt ) {
				resolve();
				return;
			}
			const script    = document.createElement( 'script' );
			script.src      = '//js.hsforms.net/forms/embed/v2.js';
			script.async    = true;
			script.onload   = resolve;
			script.onerror  = reject;
			document.head.appendChild( script );
		} );

		return hsScriptPromise;
	}

	// -------------------------------------------------------------------------
	// GA4 helper — silently no-ops if gtag isn't present.
	// -------------------------------------------------------------------------

	function gtagEvent( eventName, params ) {
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', eventName, params );
		}
	}

	// -------------------------------------------------------------------------
	// Shared helpers (used by both modal modes)
	// -------------------------------------------------------------------------

	function visibleInputs( container ) {
		return Array.from(
			container.querySelectorAll( 'input:not([type="hidden"]), textarea, select' )
		).filter( el => el.offsetParent !== null );
	}

	function focusFirstInput( container ) {
		const first = visibleInputs( container )[ 0 ];
		if ( first ) first.focus();
	}

	// -------------------------------------------------------------------------
	// Button-modal mode: single button → opens full form in modal, no email step
	// -------------------------------------------------------------------------

	function initButtonModal( block ) {
		const portalId           = block.dataset.portalId;
		const formId             = block.dataset.formId;
		const redirectToThankYou = block.dataset.redirectToThankYou === 'true';
		if ( ! portalId || ! formId ) return;

		const btn       = block.querySelector( '.hubspot-form__modal-btn' );
		const modal     = block.querySelector( '.hubspot-form__modal' );
		const modalBody = block.querySelector( '.hubspot-form__modal-body' );
		const closeBtn  = block.querySelector( '.hubspot-form__modal-close' );
		if ( ! btn || ! modal || ! modalBody ) return;

		document.body.appendChild( modal );

		let formRendered = false;

		function renderForm() {
			if ( formRendered ) return;
			formRendered = true;
			loadHubSpotScript().then( () => {
				const formOptions = {
					region:   'na1',
					portalId: portalId,
					formId:   formId,
					target:   '#' + modal.id + ' .hubspot-form__modal-body',
					onFormReady: function () {
						setUtmContentFromTitle();
						populateUtmFields();
					},
					onFormSubmit: function ( $form ) {
						gtagEvent( 'form_submitted', {
							event_category: 'HubSpot Form',
							event_label:    formId,
						} );
						if ( redirectToThankYou ) {
							setTimeout( function () {
								window.location = '/demo/thank-you/?' + $form.serialize();
							}, 250 );
						}
					},
				};
				if ( redirectToThankYou ) {
					formOptions.inlineMessage = 'Thank you for contacting us. Your scheduler is loading.';
				}
				window.hbspt.forms.create( formOptions );
			} ).catch( ( err ) => {
				console.warn( '[momentive/hubspot-form] Failed to load HubSpot script:', err );
			} );
		}

		function openModal() {
			renderForm();
			modal.removeAttribute( 'hidden' );
			modal.classList.add( 'is-open' );
			document.body.classList.add( 'hubspot-modal-open' );
			// Give the form a moment to render before stealing focus.
			setTimeout( () => focusFirstInput( modalBody ), 300 );
		}

		function closeModal() {
			modal.setAttribute( 'hidden', '' );
			modal.classList.remove( 'is-open' );
			document.body.classList.remove( 'hubspot-modal-open' );
			btn.focus();
		}

		btn.addEventListener( 'click', openModal );
		closeBtn && closeBtn.addEventListener( 'click', closeModal );
		modal.addEventListener( 'click', ( e ) => { if ( e.target === modal ) closeModal(); } );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && modal.classList.contains( 'is-open' ) ) closeModal();
		} );
	}

	// -------------------------------------------------------------------------
	// Per-block initialisation (two-step mode)
	// -------------------------------------------------------------------------

	function initBlock( block ) {
		const portalId           = block.dataset.portalId;
		const formId             = block.dataset.formId;
		const redirectToThankYou = block.dataset.redirectToThankYou === 'true';
		if ( ! portalId || ! formId ) return;

		const emailInput = block.querySelector( '.hubspot-form__email-input' );
		const submitBtn  = block.querySelector( '.hubspot-form__submit' );
		const modal      = block.querySelector( '.hubspot-form__modal' );
		const modalBody  = block.querySelector( '.hubspot-form__modal-body' );
		const closeBtn   = block.querySelector( '.hubspot-form__modal-close' );
		if ( ! emailInput || ! submitBtn || ! modal || ! modalBody ) return;

		// Move the modal to <body> so it sits outside any stacking context
		// created by ancestor elements (hero sections, sticky header, etc.).
		// z-index only competes within a stacking context, so a modal nested
		// inside the hero can never reliably sit above a sticky nav — even at
		// z-index:9999 — until it is a direct child of <body>.
		document.body.appendChild( modal );

		let formRendered = false;  // true once hbspt.forms.create() has been called
		let step1Fired   = false;  // prevents duplicate GA4 step-1 events

		// -- Preload --------------------------------------------------------
		// Kick off the HubSpot script fetch + form render as soon as the visitor
		// starts typing, so the modal can open without any perceptible delay.

		function preloadForm() {
			if ( formRendered ) return;
			formRendered = true;

			loadHubSpotScript().then( () => {
				const formOptions = {
					region:   'na1',   // change to 'eu1' for EU portals
					portalId: portalId,
					formId:   formId,
					target:   '#' + modal.id + ' .hubspot-form__modal-body',

					onFormReady: function () {
						// Wire up utm_content for this same-page embed.
						setUtmContentFromTitle();
						// UTM fields from sessionStorage (MutationObserver may have
						// already caught this, but calling again is idempotent).
						populateUtmFields();
					},

					onFormSubmit: function ( $form ) {
						gtagEvent( 'demo_form_submitted', {
							event_category: 'HubSpot Form',
							event_label:    formId,
						} );
						if ( redirectToThankYou ) {
							setTimeout( function () {
								window.location = '/demo/thank-you/?' + $form.serialize();
							}, 250 );
						}
					},
				};
				if ( redirectToThankYou ) {
					formOptions.inlineMessage = 'Thank you for contacting us. Your scheduler is loading.';
				}
				window.hbspt.forms.create( formOptions );
			} ).catch( ( err ) => {
				console.warn( '[momentive/hubspot-form] Failed to load HubSpot script:', err );
			} );
		}

		emailInput.addEventListener( 'input', preloadForm, { once: true } );

		// -- Open modal -----------------------------------------------------

		function openModal() {
			const email = emailInput.value.trim();

			// GA4: step 1 — visitor submitted the inline email field.
			if ( ! step1Fired && email ) {
				step1Fired = true;
				gtagEvent( 'demo_email_submitted', {
					event_category: 'HubSpot Form',
					event_label:    formId,
					// Email value deliberately omitted — PII.
				} );
			}

			// Ensure preload has started even if they clicked without typing.
			preloadForm();

			modal.removeAttribute( 'hidden' );
			modal.classList.add( 'is-open' );
			document.body.classList.add( 'hubspot-modal-open' );

			if ( email ) {
				prefillEmail( email );
			} else {
				// No email to pre-fill: focus the first visible input directly.
				focusFirstInput( modalBody );
			}
		}

		// -- Email pre-fill + focus advance ---------------------------------
		// After filling the email field, move focus to the next input so the
		// visitor can continue tabbing through the form naturally.
		// Guard: only advance focus if email is the *first* visible input in
		// the form; if the form layout differs, fall back to focusing email.

		function prefillEmail( email ) {
			function attempt( tries ) {
				const inputs   = visibleInputs( modalBody );
				const hsEmail  = inputs.find( el => el.name === 'email' );

				if ( hsEmail ) {
					hsEmail.value = email;
					hsEmail.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
					hsEmail.dispatchEvent( new Event( 'change', { bubbles: true } ) );

					// Advance focus only when email is the first field.
					if ( inputs[ 0 ] === hsEmail && inputs[ 1 ] ) {
						inputs[ 1 ].focus();
					} else {
						hsEmail.focus();
					}
					return;
				}

				if ( tries > 0 ) {
					setTimeout( () => attempt( tries - 1 ), 100 );
				} else {
					// Form didn't render in time — focus whatever is first.
					focusFirstInput( modalBody );
				}
			}
			attempt( 15 ); // polls up to ~1.5 s; preload gives a head start
		}

		// -- Close modal ----------------------------------------------------

		function closeModal() {
			modal.setAttribute( 'hidden', '' );
			modal.classList.remove( 'is-open' );
			document.body.classList.remove( 'hubspot-modal-open' );
			emailInput.focus();
		}

		submitBtn.addEventListener( 'click', openModal );

		emailInput.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				openModal();
			}
		} );

		closeBtn.addEventListener( 'click', closeModal );

		// Backdrop click closes.
		modal.addEventListener( 'click', ( e ) => {
			if ( e.target === modal ) closeModal();
		} );

		// Escape closes — scoped to when this modal is open.
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Boot — initialize all modal-mode blocks on the page
	// -------------------------------------------------------------------------

	document.querySelectorAll( '[data-button-modal="true"]' ).forEach( initButtonModal );
	document.querySelectorAll( '[data-two-step="true"]' ).forEach( initBlock );

} )();