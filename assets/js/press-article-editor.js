( function() {
	var registerPlugin  = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	var el       = wp.element.createElement;
	var Button   = wp.components.Button;
	var dispatch = wp.data.dispatch;

	registerPlugin( 'momentive-press-article-tools', {
		render: function() {
			return el(
				PluginDocumentSettingPanel,
				{
					name:  'press-article-tools',
					title: 'Layout tools',
				},
				el(
					Button,
					{
						variant: 'secondary',
						isDestructive: true,
						onClick: function() {
							if ( ! window.confirm(
								window.momentivePressArticle.resetLabel + '? ' +
								'The current content will be replaced.'
							) ) return;

							dispatch( 'core/block-editor' ).resetBlocks(
								wp.blocks.parse( window.momentivePressArticle.patternContent )
							);
						},
					},
					window.momentivePressArticle.resetLabel
				)
			);
		},
	} );
} )();


( function( $ ) {
	wp.domReady( function() {

		// ACF triggers a jQuery 'change' event on the hidden input whenever
		// an image is selected or removed via the media library. Delegating
		// from document means it works regardless of when the field renders.
		$( document ).on(
			'change',
			'[data-name="hero_image"] input[type="hidden"]',
			function() {
				var attachmentId = parseInt( $( this ).val(), 10 );

				if ( ! attachmentId ) {
					restoreFeaturedImagePreview();
					return;
				}

				wp.apiFetch( { path: '/wp/v2/media/' + attachmentId } )
					.then( function( media ) {
						swapEditorImage( media.source_url, media.alt_text || '' );
					} )
					.catch( function() {} );
			}
		);

		// On load, check if a hero_image is already set and swap immediately.
		var existingId = parseInt(
			$( '[data-name="hero_image"] input[type="hidden"]' ).val(),
			10
		);
		
		if ( existingId ) {
			var elapsed  = 0;
			var interval = 200; // ms between attempts
			var limit    = 2000; // give up after 2s
		
			var timer = setInterval( function() {
				elapsed += interval;
		
				wp.apiFetch( { path: '/wp/v2/media/' + existingId } )
					.then( function( media ) {
						swapEditorImage( media.source_url, media.alt_text || '' );
						// swapEditorImage bails silently if the <img> isn't in the
						// DOM yet, so only stop polling once it actually found it.
						var doc = getEditorDocument();
						if ( doc.querySelector( '.wp-block-post-featured-image img' ) ) {
							clearInterval( timer );
						}
					} )
					.catch( function() {} );
		
				if ( elapsed >= limit ) {
					clearInterval( timer );
				}
			}, interval );
		}

	} );

	function getEditorDocument() {
		var iframe =
			document.querySelector( 'iframe[name="editor-canvas"]' ) ||
			document.querySelector( 'iframe.editor-canvas__iframe' );
		return ( iframe && iframe.contentDocument ) ? iframe.contentDocument : document;
	}

	function swapEditorImage( src, alt ) {
		var doc = getEditorDocument();
		var img = doc.querySelector( '.wp-block-post-featured-image img' );
		if ( ! img ) return;

		img.src = src;
		img.alt = alt;
		img.removeAttribute( 'srcset' );
		img.removeAttribute( 'sizes' );
	}

	function restoreFeaturedImagePreview() {
		var featuredMediaId = wp.data
			.select( 'core/editor' )
			.getEditedPostAttribute( 'featured_media' );

		if ( ! featuredMediaId ) return;

		wp.apiFetch( { path: '/wp/v2/media/' + featuredMediaId } )
			.then( function( media ) {
				swapEditorImage( media.source_url, media.alt_text || '' );
			} )
			.catch( function() {} );
	}

} )( jQuery );