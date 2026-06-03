<?php
// Get ACF fields
global $post;
$post_id        = get_the_ID();
$accent_color   = get_field( 'accent_color', $post_id );
$icon_slug      = get_field( 'solution_icon', $post_id );
$bg_image_url   = false;
$bg_image_array = get_field( 'background_image', $post_id );
if( $bg_image_array && isset( $bg_image_array['url'] ) ) { 
	$bg_image_url   = $bg_image_array['url'];
}
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
if ( $accent_color )  $style .= "--solution: {$accent_color};";
if ( $bg_image_url )  $style .= "--slide-bg-image: url('{$bg_image_url}');";
?>

<div class="solution wp-block-acf-solution-slide" style="<?php echo esc_attr( $style ); ?>">
	<a class="solution-link" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $title ); ?>"></a>
	
	<?php if ( $icon_slug ) :
		momentive_use_icon( $icon_slug );
	?>
	<span class="solution-icon" aria-hidden="true">
		<svg focusable="false"><use href="#icon-<?php echo esc_attr( $icon_slug ); ?>"></use></svg>
	</span>	
	<?php endif; ?>
	
	<h3 class="solution-title"><?php echo esc_html( $title ); ?></h3>
	
	<?php if ( $excerpt ) : ?>
		<p class="solution-excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php endif; ?>
</div>