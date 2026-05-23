<?php
/**
 * Template Part: Related Posts
 *
 * Displays up to 3 posts sharing the current post's primary category.
 * Excluded the current post from results.
 *
 * Usage in single.html via a Custom HTML block:
 *   Not possible directly — include via hook instead.
 */

if ( ! is_singular( 'post' ) ) return;

$post_id    = get_the_ID();
$categories = get_the_category( $post_id );
$post_type  = get_post_type();
$rel_head   = 'Explore more articles';

if ( 'press-article' == $post_type ) {
	$rel_head = 'Recommended for you';
}

if ( empty( $categories ) ) return;

// Use the first category as the primary one.
$category_id = $categories[0]->term_id;

$related = new WP_Query( [
	'post_type'           => $post_type,
	'posts_per_page'      => 3,
	'post__not_in'        => [ $post_id ],
	'cat'                 => $category_id,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'no_found_rows'       => true, // skip COUNT() query — we don't need pagination
	'ignore_sticky_posts' => true,
] );

if ( ! $related->have_posts() ) return;
?>

<section class="related-posts" aria-labelledby="related-posts-heading">
	<h2 class="related-posts__heading" id="related-posts-heading">
		<?php echo $rel_head; ?>
	</h2>

	<ul class="related-posts__grid" role="list">
		<?php while ( $related->have_posts() ) : $related->the_post(); ?>
		<li class="related-posts__item">
			<?php 
			get_template_part( 'patterns/story-card' ); 
			?>
		</li>
		<?php endwhile; wp_reset_postdata(); ?>
	</ul>

	<?php
	// Map post types to their archive URL and label.
	// get_post_type_archive_link() returns false if the post type
	// has no archive, so we can extend this to other CPTs safely.
	$archive_links = [
		'press-article' => [
			'url'   => get_post_type_archive_link( 'press-article' ) ?: '/newsroom/',
			'label' => 'View All',
		],
		'case_studies' => [
			'url'   => get_post_type_archive_link( 'case_studies' ) ?: '/case-studies/',
			'label' => 'View All',
		],
	];
	if ( isset( $archive_links[ $post_type ] ) ) :
		$link = $archive_links[ $post_type ];
	?>
	<div class="related-posts__footer">
		<div class="wp-block-buttons is-content-justification-center">
			<div class="wp-block-button is-style-outline">
				<a class="wp-block-button__link wp-element-button"
				   href="<?php echo esc_url( $link['url'] ); ?>">
					<?php echo esc_html( $link['label'] ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php endif; ?>
</section>