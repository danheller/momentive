/**
 * Product Marquee – frontend view script
 *
 * Finds each .product-marquee block and mounts two Splide instances:
 *   Row 1 (.product-marquee__row--left)  → scrolls left  (positive speed)
 *   Row 2 (.product-marquee__row--right) → scrolls right (negative speed)
 *
 * Both rows pause together on hover (either row), and defer autoScroll
 * until the block enters the viewport via the Intersection extension.
 *
 * Depends on: Splide core + AutoScroll extension + Intersection extension,
 * all of which are already bundled in sliders.bundle.js.
 */

( function () {

	// Safety guard — bail if Splide isn't loaded yet.
	if ( typeof Splide === 'undefined' || ! window.splide?.Extensions ) {
		console.warn( 'Product Marquee: Splide or its extensions are not loaded.' );
		return;
	}

	const SCROLL_SPEED = 0.6; // px/frame, positive = left, negative = right

	document.querySelectorAll( '.wp-block-momentive-product-marquee' ).forEach( initMarquee );

	function initMarquee( block ) {
		const rowLeft  = block.querySelector( '.product-marquee__row--left' );
		const rowRight = block.querySelector( '.product-marquee__row--right' );

		if ( ! rowLeft || ! rowRight ) return;

		const splideLeft  = mountRow( rowLeft,  SCROLL_SPEED );
		const splideRight = mountRow( rowRight, -SCROLL_SPEED );

		if ( ! splideLeft || ! splideRight ) return;

		// Pause / resume both rows together when hovering either one.
		// The autoScroll extension exposes pause() / play() on the Components.
		function pauseBoth() {
			splideLeft.Components.AutoScroll?.pause();
			splideRight.Components.AutoScroll?.pause();
		}

		function resumeBoth() {
			// Only resume if the block is still in the viewport.
			// (The Intersection extension handles the inView/outView toggle
			// independently, so we just call play() here — if out of view,
			// autoScroll.autoStart was already false and play() is a no-op.)
			splideLeft.Components.AutoScroll?.play();
			splideRight.Components.AutoScroll?.play();
		}

		[ rowLeft, rowRight ].forEach( row => {
			row.addEventListener( 'mouseenter', pauseBoth );
			row.addEventListener( 'mouseleave', resumeBoth );
			// Touch devices: pause on touchstart, resume after a short delay.
			row.addEventListener( 'touchstart', pauseBoth, { passive: true } );
			row.addEventListener( 'touchend',   () => setTimeout( resumeBoth, 1200 ), { passive: true } );
		} );
	}

	function mountRow( el, speed ) {
		// Assign a unique ID if not already set.
		if ( ! el.id ) {
			el.id = 'product-marquee-' + Math.random().toString( 36 ).slice( 2, 7 );
		}

		const options = {
			type:              'loop',
			drag:              false,         // marquee — no manual dragging
			arrows:            false,
			pagination:        false,
			autoWidth:         true,
			gap:               '1rem',
			focus:             0,
			trimSpace:         false,
			// autoScroll handled by extension below
			autoScroll: {
				speed:        speed,
				autoStart:    false,          // Intersection extension starts it
				pauseOnHover: false,          // we coordinate hover manually above
			},
			intersection: {
				inView:  { autoScroll: true },
				outView: { autoScroll: false },
				threshold: 0.1,
			},
		};

		try {
			const sp = new Splide( '#' + el.id, options );
			sp.mount( window.splide.Extensions );
			return sp;
		} catch ( err ) {
			console.warn( 'Product Marquee: could not mount Splide on', el.id, err );
			return null;
		}
	}

} )();
