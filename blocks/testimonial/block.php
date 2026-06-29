<?php
/**
 * Testimonial block — register block JSON, editor script, and render callback.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-testimonial-editor',
		get_template_directory_uri() . '/blocks/testimonial/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-testimonial',
		get_template_directory_uri() . '/assets/css/testimonial.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/testimonial/block.json',
		[
			'render_callback' => 'momentive_testimonial_render',
			'editor_script'   => 'momentive-testimonial-editor',
			'style'           => 'momentive-testimonial',
		]
	);

} );

// ── Render callback ───────────────────────────────────────────────────────────
    
function momentive_testimonial_render( array $attributes, string $content, WP_Block $block ): string {
	// Use context postId only when inside a Query Loop (which provides a 'query' context).
	// Outside a query loop, context['postId'] is the current page — not a testimonial.
	$in_query_loop  = ( $block->context['postType'] ?? '' ) === 'testimonials';
	$testimonial_id = $in_query_loop
		? (int) ( $block->context['postId'] ?? 0 )
		: (int) ( $attributes['testimonialId'] ?? 0 );

	$show_case_study_btn = ! empty( $attributes['showCaseStudyButton'] );

	if ( ! $testimonial_id ) return '';

	$post = get_post( $testimonial_id );
	if ( ! $post || $post->post_status !== 'publish' ) return '';

	// ── Field values ──────────────────────────────────────────────────────────

	$raw = $post->post_content;
	$quote = has_blocks( $raw )
		? do_blocks( $raw )
		: wpautop( wp_kses_post( $raw ) );
	$author_name  = get_field( 'testimonial_author_name', $testimonial_id );
	$author_desc  = get_field( 'testimonial_author_description', $testimonial_id );
	$author_photo = get_field( 'testimonial_author_photo', $testimonial_id ); // ACF image array

	// Case study: prefer related post permalink, fall back to plain URL field.
	$case_study_post = get_field( 'related_case_study', $testimonial_id );
	$case_study_url  = $case_study_post
		? get_permalink( $case_study_post )
		: get_field( 'case_study_url', $testimonial_id );

	// ── Solution color ────────────────────────────────────────────────────────
	// Look up the testimonial's assigned solution family category and resolve
	// its accent color via the same get_solution_color_for_term() used elsewhere.

	$color = '';
	$terms = get_the_terms( $testimonial_id, 'category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$color = get_solution_color_for_term( $terms[0]->term_id );
		if ( $color ) {
			$color = sanitize_hex_color( $color );
		}
	}

	// ── Wrapper ───────────────────────────────────────────────────────────────

	$wrapper_attrs = get_block_wrapper_attributes( [
		'class' => 'testimonial',
	] );

	// Inject --solution into the wrapper style attribute.
	// get_block_wrapper_attributes() may already output style="..." so merge
	// rather than append to avoid duplicate style attributes.
	if ( $color ) {
		$css_var = '--solution:' . esc_attr( $color ) . ';';
		if ( str_contains( $wrapper_attrs, 'style="' ) ) {
			$wrapper_attrs = preg_replace( '/style="/', 'style="' . $css_var, $wrapper_attrs, 1 );
		} else {
			$wrapper_attrs .= ' style="' . $css_var . '"';
		}
	}

	ob_start();
	?>
	<div <?php echo $wrapper_attrs; ?>>

		<blockquote class="testimonial-quote">
			<?php echo wp_kses_post( $quote ); ?>
		</blockquote>

		<div class="attribution">
			<?php if ( ! empty( $author_photo['url'] ) ) : ?>
				<figure>
					<img
						src="<?php echo esc_url( $author_photo['url'] ); ?>"
						alt="<?php echo esc_attr( $author_name ); ?>"
						loading="lazy"
						<?php if ( ! empty( $author_photo['width'] ) ) : ?>width="<?php echo (int) $author_photo['width']; ?>"<?php endif; ?>
						<?php if ( ! empty( $author_photo['height'] ) ) : ?>height="<?php echo (int) $author_photo['height']; ?>"<?php endif; ?>
					/>
				</figure>
			<?php endif; ?>

			<?php if ( $author_name ) : ?>
				<p class="name"><?php echo esc_html( $author_name ); ?></p>
			<?php endif; ?>

			<?php if ( $author_desc ) : ?>
				<p class="organization"><?php echo esc_html( $author_desc ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $show_case_study_btn && $case_study_url ) : ?>
			<a
				href="<?php echo esc_url( $case_study_url ); ?>"
				class="case-study-button wp-element-button"
			>
				<?php esc_html_e( 'Read the Case Study', 'momentive' ); ?>
			</a>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

/* WP Editor Improvements
 *
 * 1. Prevent testimonials from appearing within query loops in the WP editor context. 
 * Added because WP was unable to display testimonials in that context, and instead
 * was showing a spinner.
 *
 * 2. Randomize the slides here, since the WP block editor can't handle a random orderby 
 * parameter in a WP query yet.
*/

add_filter( 'render_block', function( string $html, array $block ): string {
	if ( $block['blockName'] !== 'momentive/testimonial' ) return $html;
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		// Return a zero-size placeholder so WP still enqueues the block stylesheet.
		return '<div class="wp-block-momentive-testimonial" style="display:none"></div>';
	}
	return $html;
}, 10, 2 );

add_filter( 'query_loop_block_query_vars', function( array $query, WP_Block $block ): array {
	if ( ( $block->context['query']['postType'] ?? '' ) !== 'testimonials' ) {
		return $query;
	}
	$query['orderby'] = 'rand';
	return $query;
}, 10, 2 );
