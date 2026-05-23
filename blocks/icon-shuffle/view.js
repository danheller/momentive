/**
 * Icon Shuffle Grid – frontend view script
 *
 * Strategy: musical-chairs rotation.
 *   - `active`  : array of { cellIndex, imageIndex } – what's currently displayed
 *   - `offstage`: array of imageIndex values not currently shown
 *
 * Every `interval` ms:
 *   1. Pick a random cell from `active`.
 *   2. Pick a random image from `offstage`.
 *   3. Crossfade the cell to the new image.
 *   4. The evicted image moves to `offstage`; the incoming image leaves it.
 *
 * Entry animation: cells scale in from 0→1 when the block enters the viewport,
 * with a per-cell staggered delay. The shuffle ticker is deferred until after
 * the last cell finishes animating so the two opacity systems don't conflict.
 */

( function () {
	document.querySelectorAll( '.wp-block-momentive-icon-shuffle' ).forEach( initBlock );

	function initBlock( block ) {
		const raw = block.dataset.iconShuffleConfig;
		if ( ! raw ) return;

		let config;
		try {
			config = JSON.parse( raw );
		} catch ( e ) {
			console.warn( 'Icon Shuffle: could not parse config', e );
			return;
		}

		const { images, cellCount, interval, transitionDuration } = config;

		if ( ! images || images.length < 2 ) return;
		if ( images.length < cellCount ) {
			console.warn(
				`Icon Shuffle: pool has ${ images.length } images but cellCount is ${ cellCount }. ` +
				`Reducing cellCount to ${ images.length - 1 } to ensure at least one offstage image.`
			);
			config.cellCount = images.length - 1;
		}

		// Select only shuffle cells — exclude the static center cell.
		const cells = Array.from(
			block.querySelectorAll( '.icon-shuffle-cell:not(.icon-shuffle-cell--center)' )
		);
		if ( cells.length === 0 ) return;


		const gridStyle = window.getComputedStyle(block.querySelector('.icon-shuffle-grid'));
		const columnCount = gridStyle.gridTemplateColumns.split(' ').length;

		let lastActiveIdx = null;

		// ── Assign staggered animation delays ────────────────────────────────
		// Randomise the order so cells don't zoom in left-to-right like a wave.
		const STAGGER_MS    = 40;  // gap between each cell's start
		const ANIM_DURATION = 750; // must match animation-duration in CSS (ms)

		const staggerOrder = shuffleArray( cells.map( ( _, i ) => i ) );
		let maxDelay = 0;

		cells.forEach( ( cell, i ) => {
			const delay = staggerOrder[ i ] * STAGGER_MS;
			cell.style.setProperty( '--icon-shuffle-delay', delay + 'ms' );
			if ( delay > maxDelay ) maxDelay = delay;
		} );

		// Total time until the last cell's animation finishes
		const allDoneMs = maxDelay + ANIM_DURATION;

		// ── Initialise image state (before animation plays) ──────────────────
		const shuffledIndices = shuffleArray( images.map( ( _, i ) => i ) );

		const active   = []; // { cell, imageIndex }
		const offstage = []; // imageIndex[]

		cells.forEach( ( cell, i ) => {
			const imageIndex = shuffledIndices[ i ];
			active.push( { cell, imageIndex } );
			const currentImg = cell.querySelector( '.icon-shuffle-img--current' );
			if ( currentImg ) {
				currentImg.src = images[ imageIndex ].url;
				currentImg.alt = images[ imageIndex ].alt;
			}
		} );

		shuffledIndices.slice( cells.length ).forEach( ( idx ) => offstage.push( idx ) );

		// ── Viewport observer ────────────────────────────────────────────────
		// Fires once when the block is at least 10% visible, then disconnects.
		// Starts the entry animation and defers the shuffle ticker.

		// Check for reduced-motion preference — if set, skip animation entirely
		// and start the ticker immediately.
		const prefersReduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		if ( prefersReduced ) {
			block.classList.add( 'is-visible' );
			setInterval( () => tick( active, offstage, images, transitionDuration ), interval );
			return;
		}

		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( ! entry.isIntersecting ) return;

					// Trigger CSS entry animation
					block.classList.add( 'is-visible' );

					// Start shuffle only after last cell has finished animating
					setTimeout( () => {
						setInterval( () => tick( active, offstage, images, transitionDuration ), interval );
					}, allDoneMs );

					observer.disconnect();
				} );
			},
			{ threshold: 0.1 } // trigger when 10% of the block is visible
		);

		observer.observe( block );
	}

	function tick( active, offstage, images, transitionDuration ) {
		// If there are no offstage images, pick any two active cells and swap them
		if ( offstage.length === 0 ) {
			if ( active.length < 2 ) return;

			const candidates = active
				.map((_, i) => i)
				.filter(i => i !== lastActiveIdx);
			const activeIdx = candidates[randomInt(candidates.length)];
			lastActiveIdx = activeIdx;

			const idxA = randomInt( active.length );
			let idxB;
			do { idxB = randomInt( active.length ); } while ( idxB === idxA );

			const entryA = active[ idxA ];
			const entryB = active[ idxB ];

			crossfade( entryA.cell, images[ entryB.imageIndex ], transitionDuration );
			crossfade( entryB.cell, images[ entryA.imageIndex ], transitionDuration );

			const tmp = entryA.imageIndex;
			active[ idxA ].imageIndex = entryB.imageIndex;
			active[ idxB ].imageIndex = tmp;
			return;
		}

		// Normal case: swap one active cell with a random offstage image
		const activeIdx   = randomInt( active.length );
		const offstageIdx = randomInt( offstage.length );

		const entry         = active[ activeIdx ];
		const incomingImage = offstage[ offstageIdx ];
		const evictedImage  = entry.imageIndex;

		crossfade( entry.cell, images[ incomingImage ], transitionDuration );

		active[ activeIdx ].imageIndex = incomingImage;
		offstage[ offstageIdx ]        = evictedImage;
	}

	/**
	 * Crossfade a cell to a new image using two stacked <img> layers:
	 *   .icon-shuffle-img--current  (the one being replaced, fades out)
	 *   .icon-shuffle-img--incoming (the new one, fades in)
	 *
	 * After the transition, we swap roles so the cell is ready for the next swap.
	 */
	function crossfade(cell, image, duration) {
	
		const current = cell.querySelector('.icon-shuffle-img--current');
		const incoming = cell.querySelector('.icon-shuffle-img--incoming');
		if (!current || !incoming) return;

		// prepare incoming image
		incoming.src = image.url;
		incoming.alt = image.alt;

		// reset classes
		current.classList.remove('is-exiting');
		incoming.classList.remove('is-entering');

		// force reflow so animation can retrigger cleanly
		void incoming.offsetWidth;
		
		// trigger animations
		current.classList.add('is-exiting');
		incoming.classList.add('is-entering');

		// swap roles after animation completes
		setTimeout(() => {
			current.classList.remove('icon-shuffle-img--current', 'is-exiting');
			incoming.classList.remove('icon-shuffle-img--incoming', 'is-entering');
			current.classList.add('icon-shuffle-img--incoming');
			incoming.classList.add('icon-shuffle-img--current');
		}, duration);
	
	}



	function randomInt( max ) {
		return Math.floor( Math.random() * max );
	}

	function shuffleArray( arr ) {
		const a = [ ...arr ];
		for ( let i = a.length - 1; i > 0; i-- ) {
			const j = randomInt( i + 1 );
			[ a[ i ], a[ j ] ] = [ a[ j ], a[ i ] ];
		}
		return a;
	}
} )();
