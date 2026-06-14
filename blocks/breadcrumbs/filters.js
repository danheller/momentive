( function () {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────────────────────

    function debounce( fn, ms ) {
        let timer;
        return ( ...args ) => {
            clearTimeout( timer );
            timer = setTimeout( () => fn( ...args ), ms );
        };
    }

    // ── Per-bar initialisation ────────────────────────────────────────────────

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
	
	function findNearestMoreButton( bar ) {
		let el = bar.nextElementSibling;
		while ( el ) {
			const btn = el.querySelector( '.wp-block-query-pagination-next' );
			if ( btn ) return btn;
			el = el.nextElementSibling;
		}
		return bar.closest( '.wp-block-group' )
			?.querySelector( '.wp-block-query-pagination-next' ) ?? null;
	}
	
	function initFilterBar( bar ) {
		const grid    = findNearestQuery( bar );
		const moreBtn = findNearestMoreButton( bar );
	
		if ( ! grid ) return;

        // State
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

        // Elements
        const toggle      = bar.querySelector( '.filter-toggle' );
        const panel       = bar.querySelector( '.filter-panel' );
        const resetBtn    = bar.querySelector( '.filter-reset' );
        const countBadge  = bar.querySelector( '.filter-count' );
        const activeTags  = bar.querySelector( '.filter-active-tags' );
        const searchInput = bar.querySelector( '.filter-search' );
        const sortSelect  = bar.querySelector( '.filter-sort' );

        // ── Panel toggle ──────────────────────────────────────────────────────

        toggle?.addEventListener( 'click', () => {
            const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
            toggle.setAttribute( 'aria-expanded', String( ! open ) );
            panel?.toggleAttribute( 'hidden', open );
        } );

        // ── Collapsible filter groups ─────────────────────────────────────────

        bar.querySelectorAll( '.filter-group-toggle' ).forEach( legend => {
            legend.addEventListener( 'click', () => {
                const expanded = legend.getAttribute( 'aria-expanded' ) !== 'false';
                legend.setAttribute( 'aria-expanded', String( ! expanded ) );
                legend.nextElementSibling?.toggleAttribute( 'hidden', expanded );
            } );
        } );

        // ── Checkboxes ────────────────────────────────────────────────────────

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

        // ── Search ────────────────────────────────────────────────────────────

        const debouncedSearch = debounce( () => {
            state.search = searchInput?.value.trim() ?? '';
            state.page   = 1;
            syncUI();
            fetchPosts();
        }, 350 );

        searchInput?.addEventListener( 'input', debouncedSearch );

        // ── Sort ──────────────────────────────────────────────────────────────

        sortSelect?.addEventListener( 'change', () => {
            const [ orderby, order ] = ( sortSelect.value || 'date-desc' ).split( '-' );
            state.orderby = orderby;
            state.order   = order;
            state.page    = 1;
            fetchPosts();
        } );

        // ── Reset ─────────────────────────────────────────────────────────────

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

        // ── Load more ─────────────────────────────────────────────────────────

        moreBtn?.addEventListener( 'click', e => {
            e.preventDefault();
            if ( state.loading ) return;
            state.page++;
            fetchPosts( true );
        } );

        // ── Fetch ─────────────────────────────────────────────────────────────

        async function fetchPosts( append = false ) {
            if ( state.loading ) return;
            state.loading = true;
            grid.setAttribute( 'aria-busy', 'true' );

            const params = new URLSearchParams( {
                per_page: 10,
                page:     state.page,
                orderby:  state.orderby,
                order:    state.order,
                _embed:   true,
            } );

            // Categories — REST API takes IDs (already stored as IDs from PHP output)
            if ( state.categories.length ) {
                params.set( 'categories', state.categories.join( ',' ) );
            }

            // Post type — single active type or default to 'posts'
            // The REST API uses different endpoints per post type.
            // We build the endpoint URL accordingly.
            const postType    = state.postTypes.length === 1 ? state.postTypes[0] : 'post';
            const endpoint    = postTypeEndpoint( postType );

            if ( state.search ) {
                params.set( 'search', state.search );
            }

            try {
                const res        = await fetch( `${ endpoint }?${ params }` );
                state.totalPages = parseInt( res.headers.get( 'X-WP-TotalPages' ) || '1', 10 );
                const posts      = await res.json();

                const html = posts.map( renderCard ).join( '' );

                if ( append ) {
                    grid.insertAdjacentHTML( 'beforeend', html );
                } else {
                    grid.innerHTML = html;
                }

                // Load more visibility
                if ( moreBtn ) {
                    moreBtn.hidden = state.page >= state.totalPages;
                }
            } catch ( err ) {
                console.error( 'Resource filter fetch error:', err );
            } finally {
                state.loading = false;
                grid.removeAttribute( 'aria-busy' );
            }
        }

        // ── Post type → REST endpoint ─────────────────────────────────────────
        // Extend this map as CPTs are added to the site.

        function postTypeEndpoint( slug ) {
            const map = {
                'post':               '/wp-json/wp/v2/posts',
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
        // Matches the .story-card markup in the block template.

        function renderCard( post ) {
            const terms  = post._embedded?.[ 'wp:term' ] ?? [];
            const cats   = terms.find( group => group[0]?.taxonomy === 'category' ) ?? [];
            const cat    = cats[0];
            const media  = post._embedded?.[ 'wp:featuredmedia' ]?.[0];
            const date   = new Date( post.date ).toLocaleDateString( 'en-US', {
                month: 'long', day: 'numeric', year: 'numeric',
            } );
            const excerpt = ( post.excerpt?.rendered ?? '' )
                .replace( /<[^>]+>/g, '' )
                .replace( /&hellip;/g, '…' )
                .slice( 0, 140 );

            return `<li class="wp-block-post">
                <div class="wp-block-group story-card">
                    ${ cat ? `<div class="taxonomy-category">
                        <a href="${ esc( cat.link ) }">${ esc( cat.name ) }</a>
                    </div>` : '' }
                    ${ media ? `<figure class="wp-block-post-featured-image" style="aspect-ratio:16/9">
                        <a href="${ esc( post.link ) }">
                            <img
                                src="${ esc( media.source_url ) }"
                                alt="${ esc( media.alt_text ) }"
                                loading="lazy"
                                style="width:100%;height:100%;object-fit:cover;"
                            >
                        </a>
                    </figure>` : '' }
                    <h3 class="wp-block-post-title">
                        <a href="${ esc( post.link ) }">${ post.title.rendered }</a>
                    </h3>
                    <div class="wp-block-post-excerpt">
                        <p>${ excerpt }…</p>
                    </div>
                    <div class="wp-block-group meta">
                        <a class="wp-block-read-more" href="${ esc( post.link ) }">
                            Read more<span class="screen-reader-text">: ${ post.title.rendered }</span>
                        </a>
                        <div class="wp-block-post-date">
                            <time datetime="${ esc( post.date ) }">${ date }</time>
                        </div>
                    </div>
                </div>
            </li>`;
        }

        // Minimal HTML escaping for interpolated values.
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

            // Badge
            if ( countBadge ) {
                countBadge.textContent = totalActive;
                countBadge.hidden      = ! hasActive;
            }

            // Toggle label
            const label = toggle?.querySelector( '.filter-toggle-label' );
            if ( label ) {
                label.textContent = hasActive
                    ? `Filters (${ totalActive })`
                    : 'Filters';
            }

            // Reset button
            if ( resetBtn ) resetBtn.hidden = ! hasActive;

            // Active tags
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
                    <button
                        type="button"
                        data-name="${ esc( tag.name ) }"
                        data-value="${ esc( tag.value ) }"
                        aria-label="Remove filter: ${ esc( tag.label ) }"
                    >×</button>
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
                            return; // change handler calls syncUI + fetchPosts
                        }
                    }
                    state.page = 1;
                    syncUI();
                    fetchPosts();
                } );
            } );
        }
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    document.addEventListener( 'DOMContentLoaded', () => {
        document.querySelectorAll( '.resource-filter-bar' ).forEach( initFilterBar );
    } );

} () );