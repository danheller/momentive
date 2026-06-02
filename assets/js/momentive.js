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
	} );

	// megamenus
	const navItems = document.querySelectorAll('.wp-block-navigation-item.has-megamenu');
	const panels = document.querySelectorAll('.megamenu-panel');
	
	function openPanel(name) {
		if (window.innerWidth < 1024) return;
		closeAll();
		
		const panel = document.querySelector(`.megamenu-panel.megamenu--${name}`);
		const trigger = document.querySelector(`.megamenu--${name}`);
		if (!panel) return;
		
		// Measure the incoming panel's natural height
		panel.style.opacity = '0';
		panel.style.display = 'block';
		const targetHeight = panel.scrollHeight;
		panel.style.display = '';
		panel.style.opacity = '';
		
		// Animate the container to that height
		const container = document.querySelector('.megamenu-panels');
		container.style.height = `${targetHeight}px`;
		
		panel.classList.add('is-open');
		trigger?.setAttribute('aria-expanded', 'true');
	}

	function closeAll() {
		panels.forEach(p => p.classList.remove('is-open'));
		navItems.forEach(n => n.setAttribute('aria-expanded', 'false'));
		document.querySelector('.megamenu-panels').style.height = '';
	}
	
	navItems.forEach(item => {
		const name = [...item.classList]
			.find(c => c.startsWith('megamenu--'))
			?.replace('megamenu--', '');
		
		item.addEventListener('mouseenter', () => openPanel(name));
		item.addEventListener('focus', () => openPanel(name), true);
	});
	
	document.addEventListener('keydown', e => {
		if (e.key === 'Escape') closeAll();
	});
	
	document.addEventListener('click', e => {
		if (!e.target.closest('.site-header')) closeAll();
	});

	document.querySelector('.site-header')?.addEventListener('mouseleave', closeAll);

	// Replace the focus listener with a keydown listener on nav items
	navItems.forEach(item => {
		const name = [...item.classList]
			.find(c => c.startsWith('megamenu--'))
			?.replace('megamenu--', '');
		
		item.addEventListener('mouseenter', () => openPanel(name));
		
		// Open panel on Enter/Space; allow the link to be followed with click
		item.querySelector('a')?.addEventListener('keydown', e => {
		if (e.key === 'Enter' || e.key === ' ') {
			const isOpen = item.getAttribute('aria-expanded') === 'true';
			if (isOpen) {
				closeAll();
			} else {
				e.preventDefault();
				openPanel(name);
				// Move focus to first focusable element in panel
				const panel = document.querySelector(`.megamenu-panel.megamenu--${name}`);
				panel?.querySelector('a, button')?.focus();
			}
		}
		});
	});
	
	// Arrow keys within an open panel move between focusable items
	document.querySelector('.megamenu-panels')?.addEventListener('keydown', e => {
		if (!['ArrowDown', 'ArrowUp', 'Tab'].includes(e.key)) return;
		const openPanel = document.querySelector('.megamenu-panel.is-open');
		if (!openPanel) return;
		
		const focusable = [...openPanel.querySelectorAll('a, button')];
		const current = document.activeElement;
		const index = focusable.indexOf(current);
		
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			focusable[Math.min(index + 1, focusable.length - 1)]?.focus();
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			if (index <= 0) {
				// Return focus to the triggering nav item
				closeAll();
				document.querySelector(`.wp-block-navigation-item.has-megamenu[aria-expanded="false"] a`)?.focus();
			} else {
				focusable[index - 1]?.focus();
			}
		}
	});

	// prefooter: calculate the footer gradient based on its height
	
	function updateFooterGradient() {
		const prefooter = document.querySelector('.prefooter');
		const footer = document.querySelector('.site-footer');
		
		const extraHeight = prefooter ? prefooter.offsetHeight : 0;
		footer.style.setProperty('--gradient-overshoot', `calc(100% + ${extraHeight}px)`);
	}
	
	updateFooterGradient();
	window.addEventListener('resize', updateFooterGradient);

} () );