<?php
/**
 * Template Part: Related Posts
 *
 * Up to 3 posts sharing the current post's primary category, excluding the
 * current post. Used on single post / press-article / webinar views AND on the
 * /recordings/{slug} recording view, where the queried object may not register
 * as is_singular('webinar') because of the custom rewrite.
 */


// Resolve the contextual post ID without relying on is_singular(), so this
// works on the recording rewrite route as well as standard singular views.
$post_id = get_queried_object_id();
if ( ! $post_id ) {
	$post_id = get_the_ID();
}
if ( ! $post_id ) {
	return;
}

$post_type = get_post_type( $post_id );
if ( ! in_array( $post_type, [ 'post', 'press-article', 'webinar' ], true ) ) {
	return;
}

$categories = get_the_category( $post_id );
if ( empty( $categories ) && ! in_array( $post_type, [ 'webinar' ], true ) ) {
	return;
}

$rel_head = 'Explore more articles';
if ( 'webinar' === $post_type ) {
	$rel_head = 'Explore more webinars';
} elseif ( 'press-article' === $post_type ) {
	$rel_head = 'Recommended for you';
}

$related_args = [
	'post_type'           => $post_type,
	'posts_per_page'      => 3,
	'post__not_in'        => [ $post_id ],
	'orderby'             => 'date',
	'order'               => 'DESC',
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
];

if( isset( $categories[0]->term_id ) ) {
	$related_args['cat'] = $categories[0]->term_id;
}

$related = new WP_Query( $related_args );

if ( ! $related->have_posts() ) {
	return;
}
?>

<section class="related-posts" aria-labelledby="related-posts-heading">
	<h2 class="related-posts__heading" id="related-posts-heading">
		<?php echo esc_html( $rel_head ); ?>
	</h2>

	<ul class="related-posts__grid" role="list">
		<?php while ( $related->have_posts() ) : $related->the_post(); ?>
		<li class="related-posts__item">
			<?php get_template_part( 'patterns/story-card' ); ?>
		</li>
		<?php endwhile; wp_reset_postdata(); ?>
	</ul>

	<?php
	$archive_links = [
		'press-article' => [ 'url' => get_post_type_archive_link( 'press-article' ) ?: '/newsroom/', 'label' => 'View All' ],
		'case_studies'  => [ 'url' => get_post_type_archive_link( 'case_studies' ) ?: '/case-studies/', 'label' => 'View All' ],
		'webinar'       => [ 'url' => get_post_type_archive_link( 'webinar' ) ?: '/webinars/', 'label' => 'View All' ],
	];
	if ( isset( $archive_links[ $post_type ] ) ) :
		$link = $archive_links[ $post_type ];
	?>
	<div class="related-posts__footer">
		<div class="wp-block-buttons is-content-justification-center">
			<div class="wp-block-button">
				<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $link['url'] ); ?>">
					<?php echo esc_html( $link['label'] ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php endif; ?>
</section>