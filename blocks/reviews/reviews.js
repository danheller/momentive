/**
 * Reviews block — front-end JS.
 *
 * Handles:
 *  • Checkbox filter by solution family
 *  • Search (debounced 350ms, server-side)
 *  • Load more (append next page)
 *  • InnerBlocks CTA re-insertion at the configured position
 *
 * The block's PHP render outputs all query state as data-* attributes on the
 * wrapper so no global JS variables are needed.
 */
( function () {
	'use strict';

	// ── Utilities ──────────────────────────────────────────────────────────────

	function esc( str ) {
		return String( str || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function starsHtml() {
		var s = '';
		for ( var i = 0; i < 5; i++ ) {
			s += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
				+ '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'
				+ '</svg>';
		}
		return s;
	}

	// Build a single review card <li> HTML string from a REST item object.
	function cardHtml( item ) {
		var tagStyle  = item.term_color ? ' style="--term-color:' + esc( item.term_color ) + '"' : '';
		var sourceHtml = '';
		if ( item.source ) {
			sourceHtml = '<span class="review-sep" aria-hidden="true">&bull;</span>';
			if ( item.source_link ) {
				sourceHtml += '<a class="review-source-link" href="' + esc( item.source_link ) + '" target="_blank" rel="noopener noreferrer">' + esc( item.source ) + '</a>';
			} else {
				sourceHtml += '<span class="review-source">' + esc( item.source ) + '</span>';
			}
		}

		return '<li class="review-card" data-review-id="' + esc( item.id ) + '">'

			+ '<header class="review-card-header">'
			+   '<strong class="review-author">' + esc( item.author ) + '</strong>'
			+   '<div class="review-meta">'
			+     '<span class="review-stars" aria-label="5 stars">' + starsHtml() + '</span>'
			+     sourceHtml
			+     '<span class="review-sep" aria-hidden="true">&bull;</span>'
			+     '<span class="review-time">' + esc( item.time_ago ) + '</span>'
			+   '</div>'
			+ '</header>'

			+ ( item.term_name
				? '<span class="review-family-tag"' + tagStyle + '>' + esc( item.term_name ) + '</span>'
				: '' )

			+ ( item.headline
				? '<p class="review-headline">“' + esc( item.headline ) + '”</p>'
				: '' )

			+ ( item.excerpt
				? '<p class="review-excerpt">' + esc( item.excerpt ) + '</p>'
				: '' )

			+ ( item.source_link
				? '<a class="review-read-more" href="' + esc( item.source_link ) + '" target="_blank" rel="noopener noreferrer">'
				+   'Read Full Review '
				+   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
				+     '<path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>'
				+   '</svg>'
				+ '</a>'
				: '' )

			+ '</li>';
	}


	// ── Per-block initialiser ──────────────────────────────────────────────────

	function initReviews( block ) {
		var perPage      = parseInt( block.dataset.perPage, 10 )  || 7;
		var insertAfter  = parseInt( block.dataset.insertAfter, 10 ) || 3;
		var totalPages   = parseInt( block.dataset.totalPages, 10 ) || 1;
		var restUrl      = block.dataset.restUrl;

		var list          = block.querySelector( '.reviews-list' );
		var loadMoreWrap  = block.querySelector( '.reviews-load-more-wrap' );
		var loadMoreBtn   = block.querySelector( '.reviews-load-more' );
		var searchInput   = block.querySelector( '#reviews-search-input' );
		var checkboxes    = block.querySelectorAll( '.reviews-filters input[type="checkbox"]' );

		if ( ! list ) return;

		// Snapshot the InnerBlocks CTA insert from the initial PHP render.
		// We detach it from the list and re-insert it programmatically after
		// each render so it always lands at the configured position.
		var insertEl  = list.querySelector( '.reviews-list-insert' );
		var insertHtml = insertEl ? insertEl.outerHTML : '';

		var inFlight   = false;
		var searchTimer;
		var currentPage = 1;

		// ── Query helpers ──────────────────────────────────────────────────────

		function getFilters() {
			var terms = [];
			checkboxes.forEach( function ( cb ) {
				if ( cb.checked ) terms.push( cb.value );
			} );
			return {
				solution_terms: terms,
				search: searchInput ? searchInput.value.trim() : '',
			};
		}

		function buildUrl( filters, page ) {
			var url = new URL( restUrl );
			url.searchParams.set( 'page', page );
			url.searchParams.set( 'per_page', perPage );
			if ( filters.search ) url.searchParams.set( 'search', filters.search );
			filters.solution_terms.forEach( function ( t ) {
				url.searchParams.append( 'solution_terms[]', t );
			} );
			return url.toString();
		}

		// ── DOM helpers ────────────────────────────────────────────────────────

		// Count only review cards (not the insert).
		function cardCount() {
			return list.querySelectorAll( '.review-card' ).length;
		}

		// Remove the insert element from wherever it currently lives.
		function removeInsert() {
			var el = list.querySelector( '.reviews-list-insert' );
			if ( el ) el.remove();
		}

		// Place the saved insert HTML after the Nth card (1-based).
		// No-op if we have fewer than N cards or no insert HTML.
		function placeInsert( afterN ) {
			if ( ! insertHtml ) return;
			var cards = list.querySelectorAll( '.review-card' );
			if ( cards.length < afterN ) return;
			var anchor = cards[ afterN - 1 ]; // 0-based
			if ( ! anchor ) return;
			var temp = document.createElement( 'ul' );
			temp.innerHTML = insertHtml;
			anchor.after( temp.firstElementChild );
		}

		// Full replace: clear list, render items, place insert.
		function renderFull( items ) {
			list.innerHTML = '';
			var html = '';
			items.forEach( function ( item ) { html += cardHtml( item ); } );
			list.innerHTML = html;
			placeInsert( insertAfter );
		}

		// Append mode: add new cards; place insert if it falls in this batch.
		function renderAppend( items ) {
			var before = cardCount();
			items.forEach( function ( item, idx ) {
				var temp = document.createElement( 'ul' );
				temp.innerHTML = cardHtml( item );
				list.appendChild( temp.firstElementChild );

				var globalPos = before + idx + 1;
				// Place insert when we hit its position and it isn't already there.
				if ( insertHtml && globalPos === insertAfter && ! list.querySelector( '.reviews-list-insert' ) ) {
					var temp2 = document.createElement( 'ul' );
					temp2.innerHTML = insertHtml;
					list.appendChild( temp2.firstElementChild );
				}
			} );
		}

		function updateLoadMore( data ) {
			if ( ! loadMoreWrap ) return;
			var hasMore = data.page < data.total_pages;
			loadMoreWrap.style.display = hasMore ? '' : 'none';
			if ( loadMoreBtn ) loadMoreBtn.dataset.currentPage = data.page;
		}

		// ── Fetch wrapper ──────────────────────────────────────────────────────

		function fetchReviews( url, onSuccess ) {
			if ( inFlight ) return;
			inFlight = true;
			list.setAttribute( 'aria-busy', 'true' );

			window.fetch( url )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					inFlight = false;
					list.setAttribute( 'aria-busy', 'false' );
					onSuccess( data );
				} )
				.catch( function () {
					inFlight = false;
					list.setAttribute( 'aria-busy', 'false' );
				} );
		}

		// ── Reload (filter/search changed — full replace from page 1) ──────────

		function reload() {
			currentPage = 1;
			var filters = getFilters();
			fetchReviews( buildUrl( filters, 1 ), function ( data ) {
				renderFull( data.items );
				updateLoadMore( data );
				// Show load more wrapper if hidden and there are more pages.
				if ( loadMoreWrap && data.total_pages > 1 ) {
					loadMoreWrap.style.display = '';
				}
			} );
		}

		// ── Event listeners ────────────────────────────────────────────────────

		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				clearTimeout( searchTimer );
				searchTimer = setTimeout( reload, 350 );
			} );
		}

		checkboxes.forEach( function ( cb ) {
			cb.addEventListener( 'change', reload );
		} );

		if ( loadMoreBtn ) {
			loadMoreBtn.addEventListener( 'click', function () {
				var filters = getFilters();
				var nextPage = parseInt( loadMoreBtn.dataset.currentPage, 10 ) + 1;
				fetchReviews( buildUrl( filters, nextPage ), function ( data ) {
					renderAppend( data.items );
					updateLoadMore( data );
				} );
			} );
		}
	}


	// ── Boot ──────────────────────────────────────────────────────────────────

	document.querySelectorAll( '.wp-block-momentive-reviews' ).forEach( initReviews );

} )();
