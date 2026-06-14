<?php
/**
 * Front-end "Edit Header / Footer" hover buttons for logged-in users.
 */
add_action( 'wp_footer', 'momentive_fse_edit_buttons' );

function momentive_fse_edit_buttons() {
    if ( ! current_user_can( 'edit_theme_options' ) ) return;

    // Get the template part post IDs so we can link directly to the block editor.
    $header_part = get_posts( [
        'post_type'      => 'wp_template_part',
        'name'           => 'header',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ] );

    $footer_part = get_posts( [
        'post_type'      => 'wp_template_part',
        'name'           => 'footer',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ] );

    // Build editor URLs.
    // FSE template parts use this URL pattern:
    // /wp-admin/site-editor.php?postType=wp_template_part&postId=THEME//SLUG
    $theme_slug  = get_stylesheet(); // child theme slug if using one
    $header_url  = admin_url( 'site-editor.php?postType=wp_template_part&postId=' . $theme_slug . '//header' );
    $footer_url  = admin_url( 'site-editor.php?postType=wp_template_part&postId=' . $theme_slug . '//footer' );

    ?>
    <div id="momentive-fse-edit-ui" aria-hidden="true">
        <button class="fse-edit-trigger" data-target="header">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Header
        </button>
        <button class="fse-edit-trigger" data-target="footer">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Footer
        </button>
    </div>

    <style>
    .fse-edit-trigger {
        position: fixed;
        left: 50%;
        right: auto;
        z-index: 99998; /* just below WP admin bar */
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #1e1e1e;
        color: #fff;
        font: 600 11px/1 -apple-system, sans-serif;
        letter-spacing: .03em;
        text-transform: uppercase;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease, transform .2s ease;
        transform: translateX( -50% );
    }
    
	@media screen and (min-width: 782px) {
		.fse-edit-trigger {
			left: auto;
			right: 16px;
		}
	}


    .fse-edit-trigger[data-target="header"] {
        top: calc( var( --announcement-bar-height, 0px ) + 32px + 12px );
        /* 32px = WP admin bar height */
    }

    .fse-edit-trigger[data-target="footer"] {
        bottom: 16px;
    }

    /* Show when hovering the relevant region */
    body.hovering-header .fse-edit-trigger[data-target="header"],
    body.hovering-footer .fse-edit-trigger[data-target="footer"] {
        opacity: 1;
        pointer-events: auto;
    }

    /* Outline the region being hovered */
    body.hovering-header .site-header,
    body.hovering-footer .site-footer {
        outline: 2px dashed rgba( 0, 123, 255, .5 );
        outline-offset: -2px;
    }
    </style>

    <script>
    ( function () {
        var header = document.querySelector( '.site-header, .wp-block-template-part[data-slug="header"]' );
        var footer = document.querySelector( '.site-footer, .wp-block-template-part[data-slug="footer"]' );

        var headerBtn = document.querySelector( '.fse-edit-trigger[data-target="header"]' );
        var footerBtn = document.querySelector( '.fse-edit-trigger[data-target="footer"]' );

        var headerUrl = <?php echo json_encode( $header_url ); ?>;
        var footerUrl = <?php echo json_encode( $footer_url ); ?>;

        function bindRegion( el, bodyClass, btn, url ) {
            if ( ! el || ! btn ) return;

            el.addEventListener( 'mouseenter', function () {
                document.body.classList.add( bodyClass );
            } );

            el.addEventListener( 'mouseleave', function ( e ) {
                // Don't hide if moving mouse to the button itself
                if ( e.relatedTarget && e.relatedTarget.closest( '.fse-edit-trigger' ) ) return;
                document.body.classList.remove( bodyClass );
            } );

            btn.addEventListener( 'mouseleave', function ( e ) {
                if ( e.relatedTarget && e.relatedTarget.closest( '[data-slug]' ) ) return;
                document.body.classList.remove( bodyClass );
            } );

            btn.addEventListener( 'click', function () {
                window.location.href = url;
            } );
        }

        bindRegion( header, 'hovering-header', headerBtn, headerUrl );
        bindRegion( footer, 'hovering-footer', footerBtn, footerUrl );
    } () );
    </script>
    <?php
}