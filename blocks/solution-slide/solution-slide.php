<?php
// Get ACF fields
global $post;
$post_id        = get_the_ID();
$bg_color       = get_field( 'background_color', $post_id );
$icon_url       = get_field( 'icon', $post_id );
$bg_image_url   = get_field( 'background_image', $post_id );
$link           = get_permalink( $post_id );
$title          = get_the_title( $post_id );
$excerpt        = get_the_excerpt( $post_id );

/*
// turn on when icon is switched from URL field to a picker
global $momentive_icons_used;
if ( ! isset( $momentive_icons_used ) ) $momentive_icons_used = [];

if ( $icon\ ) $momentive_icons_used[] = $icon;
*/

// Build inline styles
$style = '';
if ( $bg_color )     $style .= "background-color: {$bg_color};";
if ( $bg_image_url ) $style .= "--slide-bg-image: url('{$bg_image_url}');";
?>

<div class="solution wp-block-acf-solution-slide" style="<?php echo esc_attr( $style ); ?>">
	<a class="solution-link" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $title ); ?>"></a>
	
	<?php if ( $icon_url ) : ?>
		<img class="solution-icon" src="<?php echo esc_url( $icon_url ); ?>" alt="" />
	<?php endif; ?>
	
	<h3 class="solution-title"><?php echo esc_html( $title ); ?></h3>
	
	<?php if ( $excerpt ) : ?>
		<p class="solution-excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php endif; ?>
</div>