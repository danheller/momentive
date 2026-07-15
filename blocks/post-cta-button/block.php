<?php
/**
 * Post CTA Button block — register block JSON and editor script.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-post-cta-button-editor',
		get_template_directory_uri() . '/blocks/post-cta-button/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_template_directory() . '/blocks/post-cta-button/block.json',
		[
			'render_callback' => 'momentive_post_cta_button_render',
			'editor_script'   => 'momentive-post-cta-button-editor',
		]
	);
} );

function momentive_resolve_post_cta_button( int $post_id ): array {
	// 1. Per-post override.
	$link = get_field( 'cta_button', $post_id );
	if ( ! empty( $link['url'] ) ) return $link;

	// 2. Category-based — first category with a button wins.
	$term_ids = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
	foreach ( $term_ids as $term_id ) {
		$link = get_field( 'blog_hero_button', "term_{$term_id}" );
		if ( ! empty( $link['url'] ) ) return $link;
	}

	// 3. Site-wide fallback.
	$link = get_field( 'site_wide_blog_hero_button', 'option' );
	if ( ! empty( $link['url'] ) ) return $link;

	return [];
}

/**
 * Post CTA Button block — render callback.
 *
 * Reads the ACF 'cta_button' field (Link field type, array format)
 * from the current post and renders a button if the field has a value.
 * Returns empty string if ACF isn't active, the field is empty,
 * or we're not on a singular post.
 */

function momentive_post_cta_button_render( array $attributes ): string {
	if ( ! is_singular() ) return '';
	if ( ! function_exists( 'get_field' ) ) return '';

	$link = momentive_resolve_post_cta_button( get_the_ID() );

	// get_field returns false/null when empty, or an array with
	// 'url', 'title', and 'target' keys for Link fields.
	if ( empty( $link ) || empty( $link['url'] ) ) return '';

	$url    = esc_url( $link['url'] );
	$label  = esc_html( $link['title'] ?: get_the_title() );
	$target = ! empty( $link['target'] ) ? ' target="' . esc_attr( $link['target'] ) . '"' : '';
	$rel    = ! empty( $link['target'] ) ? ' rel="noopener noreferrer"' : '';
	$style  = $attributes['style'] ?? 'filled';

	$btn_class = 'wp-block-button__link wp-element-button';
	if ( $style === 'outline' ) {
		$btn_class .= ' is-style-outline';
	}

	return sprintf(
		'<div class="wp-block-buttons">
			<div class="wp-block-button%s">
				<a class="%s" href="%s"%s%s>%s</a>
			</div>
		</div>',
		$style === 'outline' ? ' is-style-outline' : '',
		esc_attr( $btn_class ),
		$url,
		$target,
		$rel,
		$label
	);
}