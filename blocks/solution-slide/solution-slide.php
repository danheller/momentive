<?php
global $post;
$post_id        = get_the_ID();
$accent_color   = get_field( 'accent_color', $post_id );
$icon_slug      = get_field( 'solution_icon', $post_id );
$card_label     = get_field( 'card_label', $post_id );
$bg_image_url   = false;
$bg_image_array = get_field( 'background_image', $post_id );
if ( $bg_image_array && isset( $bg_image_array['url'] ) ) {
	$bg_image_url = $bg_image_array['url'];
}
$link    = get_permalink( $post_id );
$title   = get_the_title( $post_id );
$excerpt = get_the_excerpt( $post_id );

$style = '';
if ( $accent_color ) $style .= "--solution: {$accent_color};";
if ( $bg_image_url ) $style .= "--slide-bg-image: url('{$bg_image_url}');";
?>

<div class="solution wp-block-acf-solution-slide" style="<?php echo esc_attr( $style ); ?>">
	<a class="solution-link" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $title ); ?>"></a>

	<?php if ( $card_label ) : ?>
		<span class="solution-label"><?php echo esc_html( $card_label ); ?></span>
	<?php endif; ?>

	<?php if ( $icon_slug ) :
		momentive_use_icon( $icon_slug );
	?>
		<span class="solution-icon" aria-hidden="true">
			<svg focusable="false"><use href="#icon-<?php echo esc_attr( $icon_slug ); ?>"></use></svg>
		</span>
	<?php endif; ?>

	<div class="solution-body">
		<h3 class="solution-title"><?php echo esc_html( $title ); ?></h3>
		<?php if ( $excerpt ) : ?>
			<p class="solution-excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
		<span class="solution-readmore" aria-hidden="true">
			Learn more
		</span>
	</div>
</div>