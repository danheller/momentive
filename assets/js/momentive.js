( function () {

	// Swoop path definitions — add more shapes here as needed.
	// Each path's `length` must match the actual SVG path length,
	// or leave it null to have JS measure it automatically.
	const SWOOPS = {
		'swoop-default': {
			viewBox: '0 0 500 150',
			d: 'M7.7,145.6C109,125,299.9,116.2,401,121.3c42.1,2.2,87.6,11.8,87.3,25.7',
		},
		'swoop-straight': {
			viewBox: '0 0 500 50',
			d: 'M0,25 Q250,5 500,25',
		},
		'swoop-double': {
			viewBox: '0 0 500 80',
			d: 'M5,30 Q250,5 495,30 M5,55 Q250,30 495,55',
		},
	};

	function getSwoopKey( el ) {
		for ( const key of Object.keys( SWOOPS ) ) {
			if ( el.classList.contains( key ) ) return key;
		}
		return 'swoop-default';
	}

	function buildSVG( swoop ) {
		const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		svg.setAttribute( 'viewBox', swoop.viewBox );
		svg.setAttribute( 'preserveAspectRatio', 'none' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.classList.add( 'swoop-svg' );

		// Support multiple paths (e.g. swoop-double)
		const paths = Array.isArray( swoop.d ) ? swoop.d : [ swoop.d ];
		paths.forEach( d => {
			const path = document.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
			path.setAttribute( 'd', d );
			svg.appendChild( path );
		} );

		return svg;
	}

	function initSwoop( heading ) {
		const strong = heading.querySelector( 'strong' );
		if ( ! strong ) return;

		const swoopKey = getSwoopKey( heading );
		const swoop    = SWOOPS[ swoopKey ];
		const svg      = buildSVG( swoop );

		strong.style.position = 'relative';
		strong.appendChild( svg );

		// Wait for the browser to paint before measuring path length.
		// Double-rAF ensures we're past the style recalc phase.
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				svg.querySelectorAll( 'path' ).forEach( path => {
					const len = path.getTotalLength();

					// Bail out if measurement still failed
					if ( ! len ) {
						path.style.strokeDasharray  = '';
						path.style.strokeDashoffset = '';
						return;
					}
//					path.style.setProperty( '--swoop-path-length', len );
				} );

				// Start observing only after the path is correctly hidden.
				// This also prevents the "flash" on headings already in the viewport.
				heading.classList.add( 'is-ready' );
				observe( heading );
			} );
		} );
	}

	function observe( heading ) {
		const io = new IntersectionObserver(
			( entries ) => {
				entries.forEach( entry => {
					if ( entry.isIntersecting ) {
						heading.classList.add( 'is-visible' );
						io.unobserve( heading ); // fire once
					}
				} );
			},
			{ threshold: 0.3 }
		);
		io.observe( heading );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '.is-style-has-swoop' ).forEach( heading => {
			initSwoop( heading );
		} );

		moveBlogPrefooter();
	} );

	// ── Megamenu ──────────────────────────────────────────────────────────────
	
	const navItems  = document.querySelectorAll( '.wp-block-navigation-item.has-megamenu' );
	const panels    = document.querySelectorAll( '.megamenu-panel' );
	const container = document.querySelector( '.megamenu-panels' );
	
	// Pending open timer — cancelled if the cursor leaves before it fires.
	let openTimer = null;
	
	function openPanel( name ) {
		if ( window.innerWidth < 1024 ) return;
		closeAll();
	
		const panel   = document.querySelector( `.megamenu-panel[data-menu="${ name }"]` );
		const trigger = document.querySelector(
			`.wp-block-navigation-item.has-megamenu[data-menu="${ name }"], .megamenu--${ name }`
		);
		if ( ! panel ) return;
	
		panel.style.opacity = '0';
		panel.style.display = 'block';
		const targetHeight  = panel.scrollHeight;
		panel.style.display = '';
		panel.style.opacity = '';
	
		container.style.height = `${ targetHeight }px`;
		panel.classList.add( 'is-open' );
		trigger?.setAttribute( 'aria-expanded', 'true' );
	}
	
	function scheduleOpen( name ) {
		const alreadyOpen = container?.querySelector( '.megamenu-panel.is-open' );
	
		// No panel open: open immediately — deliberate hover, no diagonal-travel risk.
		if ( ! alreadyOpen ) {
			cancelOpen();
			openPanel( name );
			return;
		}
	
		// Panel already open: delay to absorb accidental corner-brushing while the
		// user moves their cursor diagonally from the nav toward the panel below.
		cancelOpen();
		openTimer = setTimeout( () => openPanel( name ), 160 );
	}
	
	function cancelOpen() {
		clearTimeout( openTimer );
		openTimer = null;
	}
	
	function closeAll() {
		cancelOpen(); // prevent a delayed open from firing after close
		panels.forEach(   p => p.classList.remove( 'is-open' ) );
		navItems.forEach( n => n.setAttribute( 'aria-expanded', 'false' ) );
		if ( container ) container.style.height = '';
	}
	
	navItems.forEach( item => {
		const name = item.dataset.menu
			?? [ ...item.classList ]
				.find( c => c.startsWith( 'megamenu--' ) )
				?.replace( 'megamenu--', '' );
	
		if ( ! name ) return;
	
		item.addEventListener( 'mouseenter', () => scheduleOpen( name ) );
		item.addEventListener( 'mouseleave', cancelOpen ); // cursor left before delay fired
	
		item.querySelector( 'a' )?.addEventListener( 'keydown', e => {
			if ( e.key !== 'Enter' && e.key !== ' ' ) return;
			const isOpen = item.getAttribute( 'aria-expanded' ) === 'true';
			if ( isOpen ) {
				closeAll();
			} else {
				e.preventDefault();
				openPanel( name );
				document.querySelector( `.megamenu-panel[data-menu="${ name }"]` )
					?.querySelector( 'a, button' )
					?.focus();
			}
		} );
	} );
	
	document.addEventListener( 'keydown', e => {
		if ( e.key === 'Escape' ) closeAll();
	} );
	
	document.addEventListener( 'click', e => {
		if ( ! e.target.closest( '.site-header' ) ) closeAll();
	} );
	
	document.querySelector( '.site-header' )?.addEventListener( 'mouseleave', closeAll );
	
	// Arrow keys within an open panel move between focusable items.
	container?.addEventListener( 'keydown', e => {
		if ( ! [ 'ArrowDown', 'ArrowUp' ].includes( e.key ) ) return;
		const openPanel = container.querySelector( '.megamenu-panel.is-open' );
		if ( ! openPanel ) return;
	
		const focusable = [ ...openPanel.querySelectorAll( 'a, button' ) ];
		const index     = focusable.indexOf( document.activeElement );
	
		if ( e.key === 'ArrowDown' ) {
			e.preventDefault();
			focusable[ Math.min( index + 1, focusable.length - 1 ) ]?.focus();
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			if ( index <= 0 ) {
				const activeMenu = openPanel.dataset.menu;
				closeAll();
				document.querySelector(
					`.wp-block-navigation-item.has-megamenu[data-menu="${ activeMenu }"] a,
					 .megamenu--${ activeMenu } a`
				)?.focus();
			} else {
				focusable[ index - 1 ]?.focus();
			}
		}
	} );

	// ── Prefooter gradient + blog-post relocation ────────────────────────────
	//
	// On non-blog pages the .prefooter block is already adjacent to .site-footer
	// in the template HTML; updateFooterGradient() just sizes the CSS gradient.
	//
	// On single blog posts the block lives inside .wp-block-post-content (it's
	// the last block in post_content). moveBlogPrefooter() lifts it out and
	// inserts it immediately before .site-footer, producing the same visual
	// result as non-blog pages. CSS hides it while it's still in the wrong
	// position (see momentive.scss — .wp-block-post-content .prefooter).

	function moveBlogPrefooter() {
		const prefooter = document.querySelector( '.wp-block-post-content .prefooter' );
		if ( ! prefooter ) return;
		const footer = document.querySelector( '.site-footer' );
		if ( ! footer ) return;

		footer.parentNode.insertBefore( prefooter, footer );

		// Remove the hide rule now that the element is in the right place,
		// then recalculate the gradient with the correct height.
		prefooter.style.removeProperty( 'display' );
		updateFooterGradient();
	}

	function updateFooterGradient() {
		const prefooter = document.querySelector( '.prefooter' );
		const footer    = document.querySelector( '.site-footer' );

		const extraHeight = prefooter ? prefooter.offsetHeight : 0;
		footer?.style.setProperty( '--gradient-overshoot', `calc(100% + ${ extraHeight }px)` );
	}

	updateFooterGradient();
	window.addEventListener( 'resize', updateFooterGradient );


} () );
