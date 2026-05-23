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

if ( empty( $categories ) ) return;

// Use the first category as the primary one.
$category_id = $categories[0]->term_id;

$related = new WP_Query( [
	'post_type'           => 'post',
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
	<div class="related-posts__inner">

		<h2 class="related-posts__heading" id="related-posts-heading">
			Recommended for you
		</h2>

		<ul class="related-posts__grid" role="list">
			<?php while ( $related->have_posts() ) : $related->the_post(); ?>
			<li class="related-posts__item">
				<div class="story-card">

					<?php
					$cats = get_the_category();
					if ( ! empty( $cats ) ) :
					?>
					<div class="taxonomy-category top-label">
						<a href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
							<?php echo esc_html( $cats[0]->name ); ?>
						</a>
					</div>
					<?php endif; ?>

					<?php if ( has_post_thumbnail() ) : ?>
					<figure style="aspect-ratio:16/9;">
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'large', [
								'style' => 'width:100%;height:100%;object-fit:cover;',
								'alt'   => get_the_title(),
							] ); ?>
						</a>
					</figure>
					<?php endif; ?>
					<div class="story-content">
						<h3 class="wp-block-post-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>
	
						<div class="wp-block-post-excerpt">
							<p><?php echo wp_trim_words( get_the_excerpt(), 20, '…' ); ?></p>
						</div>
	
						<div class="meta">
							<a class="wp-block-read-more" href="<?php the_permalink(); ?>">
								Read more
								<span class="screen-reader-text">: <?php the_title(); ?></span>
							</a>
							<div class="wp-block-post-date">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
									<?php echo esc_html( get_the_date( 'F j, Y' ) ); ?>
								</time>
							</div>
						</div>
					</div>
				</div>
			</li>
			<?php endwhile; wp_reset_postdata(); ?>
		</ul>

	</div>
</section>