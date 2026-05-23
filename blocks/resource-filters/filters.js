( function () {
	'use strict';

	function debounce( fn, ms ) {
		let timer;
		return ( ...args ) => {
			clearTimeout( timer );
			timer = setTimeout( () => fn( ...args ), ms );
		};
	}

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

	function findNearestQuery( bar ) {
		let el = bar.nextElementSibling;
		while ( el ) {
			const grid = el.querySelector( '.wp-block-post-template' );
			if ( grid ) return grid;
			el = el.nextElementSibling;
		}
		return bar.closest( '.wp-block-group' )
			?.querySelector( '.wp-block-post-template' ) ?? null;
	}

	function initFilterBar( bar ) {
		const grid = findNearestQuery( bar );
		if ( ! grid ) return;

		const moreBtn = initLoadMore( grid );

		// ── Read the default post type from a data attribute set by PHP ───────
		// This drives renderCard's top-label logic when no post_type filter
		// is actively selected by the user.
		const defaultPostType = bar.dataset.defaultPostType || 'post';

		const state = {
			categories:  [],
			postTypes:   [],
			search:      '',
			orderby:     'date',
			order:       'desc',
			page:        1,
			totalPages:  1,
			loading:     false,
		};

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
			grid.setAttribute( 'aria-busy', 'true' );

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
				const res        = await fetch( `${ endpoint }?${ params }` );
				state.totalPages = parseInt( res.headers.get( 'X-WP-TotalPages' ) || '1', 10 );
				const posts      = await res.json();

				// ── Pass activePostType into renderCard ───────────────────────
				// This fixes the ReferenceError: postType was previously used
				// inside renderCard but was only defined in fetchPosts's scope.
				// Passing it explicitly makes the dependency clear.
				const html = posts.map( post => renderCard( post, activePostType ) ).join( '' );

				if ( append ) {
					grid.insertAdjacentHTML( 'beforeend', html );
				} else {
					grid.innerHTML = html;
					initLowerLabels( grid );
				}

			} catch ( err ) {
				console.error( 'Resource filter fetch error:', err );
			} finally {
				state.loading = false;
				grid.removeAttribute( 'aria-busy' );

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

			const catLinks = cats.map( cat =>
				`<a href="${ esc( cat.link ) }" rel="tag">${ esc( cat.name ) }</a>`
			).join( '<span class="wp-block-post-terms__separator"> </span>' );

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

		function esc( str ) {
			return String( str ?? '' )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
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
	}

	// ── Lower-label expand behaviour ──────────────────────────────────────────

	function initLowerLabels( container ) {
		( container ?? document ).querySelectorAll( '.lower-label' ).forEach( el => {
			el.replaceWith( el.cloneNode( true ) );
		} );

		( container ?? document ).querySelectorAll( '.lower-label' ).forEach( el => {
			if ( el.scrollHeight <= el.clientHeight + 2 ) return;

			el.style.cursor = 'pointer';
			el.setAttribute( 'title', 'Show all categories' );
			el.setAttribute( 'role', 'button' );
			el.setAttribute( 'tabindex', '0' );

			function toggle( e ) {
				if ( e.target.tagName === 'A' ) return;
				el.classList.toggle( 'is-expanded' );
				el.setAttribute( 'title',
					el.classList.contains( 'is-expanded' )
						? 'Show fewer categories'
						: 'Show all categories'
				);
			}

			el.addEventListener( 'click', toggle );
			el.addEventListener( 'keydown', e => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					toggle( e );
				}
			} );
		} );
	}

	// ── Boot ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '.resource-filter-bar' ).forEach( initFilterBar );
		initLowerLabels( document );
	} );

} () );