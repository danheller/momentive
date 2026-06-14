<?php
/**
 * Social share block — register block JSON, editor script, and frontend javascript and CSS.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-social-share-editor',
		get_template_directory_uri() . '/blocks/social-share/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_script(
		'momentive-social-share',
		get_template_directory_uri() . '/blocks/social-share/share.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-social-share',
		get_template_directory_uri() . '/blocks/social-share/share.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/social-share/block.json',
		[
			'render_callback' => 'momentive_social_share_render',
			'editor_script'   => 'momentive-social-share-editor',
			'script'          => 'momentive-social-share',
			'style'           => 'momentive-social-share',
		]
	);
} );

/**
 * Social Share block render callback.
 * Share URLs are built server-side using get_permalink(),
 * so they're always correct even if the post slug changes.
 */

function momentive_social_share_render( array $attributes ): string {
	if ( ! is_singular() ) return '';

	$heading      = esc_html( $attributes['heading']      ?? 'Share this post' );
	$show_linkedin = ! empty( $attributes['showLinkedIn'] );
	$show_x        = ! empty( $attributes['showX'] );
	$show_facebook = ! empty( $attributes['showFacebook'] );
	$show_copy     = ! empty( $attributes['showCopyLink'] );

	$url       = get_permalink();
	$enc_url   = rawurlencode( $url );
	$enc_title = rawurlencode( get_the_title() );

	$linkedin_url  = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $enc_url;
	$x_url         = 'https://x.com/intent/tweet?url=' . $enc_url . '&text=' . $enc_title;
	$facebook_url  = 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url;

	ob_start();
	?>
	<div class="social-share">

		<?php if ( $heading ) : ?>
		<p class="social-share__heading"><?php echo $heading; ?></p>
		<?php endif; ?>

		<ul class="social-share__buttons" role="list">

			<?php if ( $show_copy ) : ?>
			<li class="social-share__item">
				<button
					class="social-share__btn social-share__btn--copy"
					type="button"
					data-url="<?php echo esc_attr( $url ); ?>"
					aria-label="Copy link to clipboard"
				>
					<?php /* Chain link icon */ ?>
					<svg class="social-share__icon social-share__icon--link" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
						<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
					</svg>
					<?php /* Checkmark — shown briefly after copy */ ?>
					<svg class="social-share__icon social-share__icon--check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
					<span class="social-share__tooltip" aria-live="polite">Copy link</span>
				</button>
			</li>
			<?php endif; ?>

			<?php if ( $show_linkedin ) : ?>
			<li class="social-share__item">
				
					<a class="social-share__btn social-share__btn--linkedin"
					href="<?php echo esc_url( $linkedin_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="Share on LinkedIn"
					onclick="window.open(this.href,'share','width=600,height=600,menubar=no,toolbar=no,location=no');return false;"
				>
					<svg class="social-share__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
						<path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
					</svg>
				</a>
			</li>
			<?php endif; ?>

			<?php if ( $show_x ) : ?>
			<li class="social-share__item">
				
					<a class="social-share__btn social-share__btn--x"
					href="<?php echo esc_url( $x_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="Share on X"
					onclick="window.open(this.href,'share','width=600,height=600,menubar=no,toolbar=no,location=no');return false;"
				>
					<svg class="social-share__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
						<path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
					</svg>
				</a>
			</li>
			<?php endif; ?>

			<?php if ( $show_facebook ) : ?>
			<li class="social-share__item">
				
					<a class="social-share__btn social-share__btn--facebook"
					href="<?php echo esc_url( $facebook_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="Share on Facebook"
					onclick="window.open(this.href,'share','width=600,height=600,menubar=no,toolbar=no,location=no');return false;"
				>
					<svg class="social-share__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
						<path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
					</svg>
				</a>
			</li>
			<?php endif; ?>

		</ul>
	</div>
	<?php
	return ob_get_clean();
}