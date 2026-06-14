( function () {
	'use strict';

	const { esc, initLowerLabels, renderCategoryLink, debounce } = window.SiteUtils;

	function initLoadMore( grid ) {
		const queryBlock = grid.closest( '.wp-block-query' );
		const pagination = queryBlock?.querySelector( '.wp-block-query-pagination' );

		if ( ! pagination ) return null;

		const btn = document.createElement( 'div' );
		btn.className = 'load-more-wrapper';
		btn.innerHTML = `
			<div class="wp-block-buttons is-content-justification-center">
				<div class="wp-block-button">
					<button class="wp-block-button__link wp-element-button load-more-btn" type="button">
						Load More
					</button>
				</div>
			</div>
		`;

		pagination.insertAdjacentElement( 'afterend', btn );
		pagination.hidden = true;

		return btn.querySelector( '.load-more-btn' );
	}

	function initAccordionLoadMore( accordion ) {
		const btn = document.createElement( 'div' );
		btn.className = 'load-more-wrapper';
		btn.innerHTML = `
			<div class="wp-block-buttons is-content-justification-center">
				<div class="wp-block-button">
					<button class="wp-block-button__link wp-element-button load-more-btn" type="button">
						Load More
					</button>
				</div>
			</div>
		`;
		accordion.insertAdjacentElement( 'afterend', btn );
		return btn.querySelector( '.load-more-btn' );
	}

	function findNearestQuery( bar ) {
		let el = bar.nextElementSibling;
		while ( el ) {
			const grid = el.querySelector( '.wp-block-post-template' );
			if ( grid ) return { type: 'grid', el: grid };
	
			const accordion = el.querySelector( '.momentive-accordion.is-query-mode' );
			if ( accordion ) return { type: 'accordion', el: accordion };
	
			el = el.nextElementSibling;
		}
		const fallbackGrid = bar.closest( '.wp-block-group' )
			?.querySelector( '.wp-block-post-template' ) ?? null;
		if ( fallbackGrid ) return { type: 'grid', el: fallbackGrid };
	
		const fallbackAccordion = bar.closest( '.wp-block-group' )
			?.querySelector( '.momentive-accordion.is-query-mode' ) ?? null;
		if ( fallbackAccordion ) return { type: 'accordion', el: fallbackAccordion };
	
		return null;
	}

	function initFilterBar( bar ) {
		const queryTarget = findNearestQuery( bar );
		if ( ! queryTarget ) return;
		
		const isAccordion = queryTarget.type === 'accordion';
		const grid        = isAccordion ? null : queryTarget.el;
		const accordion   = isAccordion ? queryTarget.el : null;
		
		const moreBtn = isAccordion ? initAccordionLoadMore( accordion ) : initLoadMore( grid );

		// ── Read the default post type from a data attribute set by PHP ───────
		// This drives renderCard's top-label logic when no post_type filter
		// is actively selected by the user.
		const defaultPostType = bar.dataset.defaultPostType || 'post';

		const state = {
			categories:  [],
			postTypes:   [],
			search:      '',
			orderby:     isAccordion ? 'menu_order' : 'date',
			order:       isAccordion ? 'asc' : 'desc',
			page:        1,
			totalPages:  1,
			loading:     false,
		};

		// Seed totalPages from the data attribute PHP already wrote on the accordion wrapper,
		// then immediately show or hide the button. Without this, the button stays hidden
		// until the first fetch fires (which only happens on user interaction).
		if ( isAccordion && moreBtn ) {
			state.totalPages = parseInt( accordion.dataset.totalPages || '1', 10 );
			moreBtn.closest( '.load-more-wrapper' ).hidden = state.totalPages <= 1;
		}

		const toggle      = bar.querySelector( '.filter-toggle' );
		const panel       = bar.querySelector( '.filter-panel' );
		const resetBtn    = bar.querySelector( '.filter-reset' );
		const countBadge  = bar.querySelector( '.filter-count' );
		const activeTags  = bar.querySelector( '.filter-active-tags' );
		const searchInput = bar.querySelector( '.filter-search' );
		const sortSelect  = bar.querySelector( '.filter-sort' );

		toggle?.addEventListener( 'click', () => {
			const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! open ) );
			panel?.toggleAttribute( 'hidden', open );
		} );

		// Close the panel when clicking outside the filter bar.
		document.addEventListener( 'click', ( e ) => {
			if ( toggle?.getAttribute( 'aria-expanded' ) !== 'true' ) return;
			if ( bar.contains( e.target ) ) return;
		
			toggle.setAttribute( 'aria-expanded', 'false' );
			panel?.toggleAttribute( 'hidden', true );
		} );

		bar.querySelectorAll( '.filter-group-toggle' ).forEach( legend => {
			legend.addEventListener( 'click', () => {
				const expanded = legend.getAttribute( 'aria-expanded' ) !== 'false';
				legend.setAttribute( 'aria-expanded', String( ! expanded ) );
				legend.nextElementSibling?.toggleAttribute( 'hidden', expanded );
			} );
		} );

		bar.querySelectorAll( 'input[name="category"]' ).forEach( input => {
			input.addEventListener( 'change', () => {
				state.categories = Array.from(
					bar.querySelectorAll( 'input[name="category"]:checked' )
				).map( el => el.value );
				state.page = 1;
				syncUI();
				fetchPosts();
			} );
		} );

		bar.querySelectorAll( 'input[name="post_type"]' ).forEach( input => {
			input.addEventListener( 'change', () => {
				state.postTypes = Array.from(
					bar.querySelectorAll( 'input[name="post_type"]:checked' )
				).map( el => el.value );
				state.page = 1;
				syncUI();
				fetchPosts();
			} );
		} );

		const debouncedSearch = debounce( () => {
			state.search = searchInput?.value.trim() ?? '';
			state.page   = 1;
			syncUI();
			fetchPosts();
		}, 350 );

		searchInput?.addEventListener( 'input', debouncedSearch );

		sortSelect?.addEventListener( 'change', () => {
			const [ orderby, order ] = ( sortSelect.value || 'date-desc' ).split( '-' );
			state.orderby = orderby;
			state.order   = order;
			state.page    = 1;
			fetchPosts();
		} );

		resetBtn?.addEventListener( 'click', () => {
			bar.querySelectorAll( 'input[type="checkbox"]' ).forEach( el => {
				el.checked = false;
			} );
			if ( searchInput ) searchInput.value = '';
			state.categories = [];
			state.postTypes  = [];
			state.search     = '';
			state.page       = 1;
			syncUI();
			fetchPosts();
		} );

		moreBtn?.addEventListener( 'click', () => {
			if ( state.loading ) return;
			state.page++;
			fetchPosts( true );
		} );

		// ── Fetch ─────────────────────────────────────────────────────────────
		async function fetchPosts( append = false ) {
			if ( state.loading ) return;
			state.loading = true;

			// aria-busy on whichever container is active.
			const busyTarget = isAccordion ? accordion : grid;
			busyTarget.setAttribute( 'aria-busy', 'true' );

			if ( moreBtn ) {
				moreBtn.disabled    = true;
				moreBtn.textContent = 'Loading…';
			}

			const params = new URLSearchParams( {
				per_page: 12,
				page:     state.page,
				orderby:  state.orderby,
				order:    state.order,
				_embed:   true,
			} );

			if ( state.categories.length ) {
				params.set( 'categories', state.categories.join( ',' ) );
			}

			// ── Resolve which post type to query ──────────────────────────────
			// If the user has selected exactly one post_type filter, use that.
			// Otherwise fall back to the block's configured defaultPostType.
			const activePostType = state.postTypes.length === 1
				? state.postTypes[0]
				: defaultPostType;

			const endpoint = postTypeEndpoint( activePostType );

			if ( state.search ) {
				params.set( 'search', state.search );
			}

			try {
				// Single fetch — result used by both grid and accordion paths.
				const res        = await fetch( `${ endpoint }?${ params }` );
				state.totalPages = parseInt( res.headers.get( 'X-WP-TotalPages' ) || '1', 10 );
				const posts      = await res.json();

				if ( isAccordion ) {
					const items = posts.map( buildAccordionItem ).join( '' );

					if ( append ) {
						accordion.insertAdjacentHTML( 'beforeend', items );
						accordion.querySelectorAll( '.accordion-trigger:not([data-init])' )
							.forEach( t => { t.setAttribute( 'data-init', '' ); wireAccordionTrigger( t ); } );
					} else {
						accordion.innerHTML = items;
						accordion.querySelectorAll( '.accordion-trigger' )
							.forEach( t => { t.setAttribute( 'data-init', '' ); wireAccordionTrigger( t ); } );
					}
				} else {
					const html = posts.map( post => renderCard( post, activePostType ) ).join( '' );

					if ( append ) {
						grid.insertAdjacentHTML( 'beforeend', html );
					} else {
						grid.innerHTML = html;
					}
					initLowerLabels( grid );
				}

			} catch ( err ) {
				console.error( 'Resource filter fetch error:', err );
			} finally {
				state.loading = false;
				busyTarget.removeAttribute( 'aria-busy' );

				if ( moreBtn ) {
					moreBtn.disabled    = false;
					moreBtn.textContent = 'Load More';
					moreBtn.closest( '.load-more-wrapper' ).hidden =
						state.page >= state.totalPages;
				}
			}
		}

		// ── Post type → REST endpoint ─────────────────────────────────────────

		function postTypeEndpoint( slug ) {
			const map = {
				'post':               '/wp-json/wp/v2/posts',
				'press-article':      '/wp-json/wp/v2/press-article',
				'case_studies':       '/wp-json/wp/v2/case_studies',
				'events':             '/wp-json/wp/v2/events',
				'faq':                '/wp-json/wp/v2/faq',
				'guides':             '/wp-json/wp/v2/guides',
				'infographics':       '/wp-json/wp/v2/infographics',
				'interactive-tools':  '/wp-json/wp/v2/interactive-tools',
				'product-overviews':  '/wp-json/wp/v2/product-overviews',
				'video-testimonials': '/wp-json/wp/v2/video-testimonials',
				'toolkits':           '/wp-json/wp/v2/toolkits',
				'videos':             '/wp-json/wp/v2/videos',
				'webinars':           '/wp-json/wp/v2/webinars',
				'whitepapers':        '/wp-json/wp/v2/whitepapers',
			};
			return map[ slug ] ?? '/wp-json/wp/v2/posts';
		}

		// ── Card renderer ─────────────────────────────────────────────────────
		// activePostType is now passed as a parameter instead of being
		// referenced from an outer scope — this was the source of the
		// ReferenceError when renderCard was called via posts.map().

		function renderCard( post, activePostType ) {
			const terms   = post._embedded?.[ 'wp:term' ] ?? [];
			const cats    = terms.find( group => group[0]?.taxonomy === 'category' ) ?? [];
			const media   = post._embedded?.[ 'wp:featuredmedia' ]?.[0];
			const date    = new Date( post.date ).toLocaleDateString( 'en-US', {
				month: 'long', day: 'numeric', year: 'numeric',
			} );
			const excerpt = ( post.excerpt?.rendered ?? '' )
				.replace( /<[^>]+>/g, '' )
				.replace( /&hellip;/g, '…' )
				.slice( 0, 140 );

			const catLinks = cats.map( renderCategoryLink ).join( '<span class="wp-block-post-terms__separator"> </span>' );

			const isPost = activePostType === 'post';

			return `<li class="wp-block-post">
				<div class="wp-block-group story-card">

					${ isPost
						? `<p class="top-label wp-block-paragraph">Blog</p>`
						: ( cats[0]
							? `<p class="top-label wp-block-paragraph">${ esc( cats[0].name ) }</p>`
							: '' )
					}

					${ media ? `<figure class="wp-block-post-featured-image" style="aspect-ratio:16/9">
						<a href="${ esc( post.link ) }" tabindex="-1" aria-hidden="true">
							<img src="${ esc( media.source_url ) }"
								 alt=""
								 loading="lazy"
								 style="width:100%;height:100%;object-fit:cover;">
						</a>
					</figure>` : '' }

					<div class="story-content">

						${ isPost && catLinks
							? `<div class="taxonomy-category lower-label wp-block-post-terms">
								${ catLinks }
							   </div>`
							: ''
						}

						<h3 class="wp-block-post-title">
							<a href="${ esc( post.link ) }">${ post.title.rendered }</a>
						</h3>
						<div class="wp-block-post-excerpt">
							<p>${ excerpt }…</p>
						</div>
						<div class="wp-block-group meta">
							<a class="wp-block-read-more" href="${ esc( post.link ) }">
								Read more
								<span class="screen-reader-text">: ${ post.title.rendered }</span>
							</a>
							<div class="wp-block-post-date">
								<time datetime="${ esc( post.date ) }">${ date }</time>
							</div>
						</div>

					</div>
				</div>
			</li>`;
		}


		// ── UI sync ───────────────────────────────────────────────────────────

		function syncUI() {
			const totalActive = state.categories.length + state.postTypes.length +
								( state.search ? 1 : 0 );
			const hasActive   = totalActive > 0;

			if ( countBadge ) {
				countBadge.textContent = totalActive;
				countBadge.hidden      = ! hasActive;
			}

			const label = toggle?.querySelector( '.filter-toggle-label' );
			if ( label ) {
				label.textContent = hasActive ? `Filters (${ totalActive })` : 'Filters';
			}

			if ( resetBtn ) resetBtn.hidden = ! hasActive;

			renderActiveTags();
		}

		function renderActiveTags() {
			if ( ! activeTags ) return;

			const tags = [];

			bar.querySelectorAll( 'input[name="category"]:checked' ).forEach( el => {
				tags.push( {
					label: el.closest( 'label' )?.querySelector( '.filter-item-label' )?.textContent?.trim(),
					value: el.value,
					name:  'category',
				} );
			} );

			bar.querySelectorAll( 'input[name="post_type"]:checked' ).forEach( el => {
				tags.push( {
					label: el.closest( 'label' )?.querySelector( '.filter-item-label' )?.textContent?.trim(),
					value: el.value,
					name:  'post_type',
				} );
			} );

			if ( state.search ) {
				tags.push( { label: `"${ state.search }"`, value: '__search__', name: 'search' } );
			}

			activeTags.innerHTML = tags.map( tag => `
				<span class="active-tag">
					${ esc( tag.label ) }
					<button type="button"
							data-name="${ esc( tag.name ) }"
							data-value="${ esc( tag.value ) }"
							aria-label="Remove filter: ${ esc( tag.label ) }">×</button>
				</span>
			` ).join( '' );

			activeTags.querySelectorAll( 'button' ).forEach( btn => {
				btn.addEventListener( 'click', () => {
					const { name, value } = btn.dataset;
					if ( name === 'search' ) {
						if ( searchInput ) searchInput.value = '';
						state.search = '';
					} else {
						const input = bar.querySelector(
							`input[name="${ name }"][value="${ value }"]`
						);
						if ( input ) {
							input.checked = false;
							input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
							return;
						}
					}
					state.page = 1;
					syncUI();
					fetchPosts();
				} );
			} );
		}

		function buildAccordionItem( post ) {
			const itemId  = 'accordion-item-' + post.id;
			const panelId = itemId + '-panel';

			// Pull category name from embedded terms (requires _embed=true in the fetch).
			const terms   = post._embedded?.[ 'wp:term' ] ?? [];
			const cats    = terms.find( group => group[0]?.taxonomy === 'category' ) ?? [];
			const catName = cats[0]?.name ?? '';
			const catSlug = catName ? catName.toLowerCase().replace( /\s+/g, '-' ) : '';
			const color   = cats[0]?.tag_color ?? '';

			const catHtml = catName
				? `<span class="accordion-category" data-category="${ esc( catSlug ) }">${ esc( catName ) }</span>`
				: '';

			return `<div class="accordion-item"${ color ? ` style="--category-color: ${ esc( color ) }"` : '' }>
				<button class="accordion-trigger" type="button"
					aria-expanded="false"
					aria-controls="${ esc( panelId ) }"
					id="${ esc( itemId ) }" data-init>
					<span class="accordion-question">${ post.title?.rendered ?? '' }</span>
					${ catHtml }
					<span class="accordion-chevron" aria-hidden="true">
						<svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg">
							<path d="M1.5 4L6 8L10.5 4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
						</svg>
					</span>
				</button>
				<div class="accordion-panel" id="${ esc( panelId ) }"
					role="region" aria-labelledby="${ esc( itemId ) }" hidden>
					<div class="accordion-panel-inner">${ post.content?.rendered ?? '' }</div>
				</div>
			</div>`;
		}
		
		function wireAccordionTrigger( trigger ) {
			trigger.addEventListener( 'click', function () {
				const isOpen = trigger.getAttribute( 'aria-expanded' ) === 'true';
				isOpen ? closeItem( trigger ) : openItem( trigger );
			} );
		}

	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '.resource-filter-bar' ).forEach( initFilterBar );
		initLowerLabels( document );
	} );

} () );