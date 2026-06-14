/**
 * view.js — Impact Stat count-up animation
 *
 * Registered as the block's viewScript in block.json.
 * Runs only on the frontend; never loaded in the editor.
 *
 * Strategy:
 *  - IntersectionObserver fires once per block when it enters the viewport.
 *  - requestAnimationFrame drives the eased counter increment.
 *  - Integer stats use toLocaleString for thousands separators (1,000s).
 *  - Decimal stats preserve one decimal place throughout the animation.
 */

/**
 * Easing function: ease-out cubic.
 * @param {number} t — progress 0–1
 * @returns {number}
 */
function easeOutCubic( t ) {
	return 1 - Math.pow( 1 - t, 3 );
}

/**
 * Format a number mid-animation, matching the final display format.
 *
 * @param {number}  value     — current animated value
 * @param {boolean} isInteger — whether the target is a whole number
 * @returns {string}
 */
function formatValue( value, isInteger ) {
	if ( isInteger ) {
		return Math.round( value ).toLocaleString( 'en-US' );
	}
	// One decimal place for values like 35.5, 6.4
	return value.toFixed( 1 );
}

/**
 * Animate a single stat block.
 *
 * @param {HTMLElement} block
 */
function animateStat( block ) {
	const targetNumber  = parseFloat( block.dataset.statNumber  ?? 0 );
	const duration      = parseInt(   block.dataset.animationDuration ?? 1800, 10 );
	const isInteger     = block.dataset.statInteger === 'true';

	const numberEl = block.querySelector( '.impact-stat__number' );
	if ( ! numberEl ) return;

	let startTime = null;

	function step( timestamp ) {
		if ( ! startTime ) startTime = timestamp;

		const elapsed  = timestamp - startTime;
		const progress = Math.min( elapsed / duration, 1 );
		const eased    = easeOutCubic( progress );
		const current  = eased * targetNumber;

		numberEl.textContent = formatValue( current, isInteger );

		if ( progress < 1 ) {
			requestAnimationFrame( step );
		} else {
			// Snap to exact final value (avoids floating-point drift)
			numberEl.textContent = formatValue( targetNumber, isInteger );
		}
	}

	requestAnimationFrame( step );
}

// ── Observer setup ──────────────────────────────────────────────────────────

const blocks = document.querySelectorAll( '.wp-block-momentive-impact-stat' );

if ( blocks.length > 0 ) {
	// Respect prefers-reduced-motion: skip animation, show final value immediately.
	const prefersReduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( prefersReduced ) {
		blocks.forEach( ( block ) => {
			const targetNumber = parseFloat( block.dataset.statNumber ?? 0 );
			const isInteger    = block.dataset.statInteger === 'true';
			const numberEl     = block.querySelector( '.impact-stat__number' );
			if ( numberEl ) {
				numberEl.textContent = formatValue( targetNumber, isInteger );
			}
		} );
	} else {
		const observer = new IntersectionObserver(
			( entries, obs ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						animateStat( entry.target );
						obs.unobserve( entry.target ); // fire once only
					}
				} );
			},
			{
				threshold: 0.25, // trigger when 25% of the block is visible
			}
		);

		blocks.forEach( ( block ) => observer.observe( block ) );
	}
}
