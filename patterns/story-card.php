<?php
/**
 * Template Part: Story Card
 *
 * Top label rule (default, per post type): webinar → live status badge
 * (upcoming/on-demand/series, same markup as acf/webinar-status); press
 * article → first category name, unlinked; everything else → the post
 * type's own singular label (post's is rewritten to "Blog" by
 * inc/rename-posts-to-blog.php, so no special case is needed for it here).
 *
 * Expected to run inside a WP_Query loop (the_post() already called).
 *
 * @var int    $card_heading_level  Optional. Heading level for post title. Default 3.
 * @var string $card_top_label      Optional escape hatch: overrides the
 *                                  per-post-type logic above with a literal
 *                                  string. Pass '' to suppress the top label
 *                                  entirely. Leave unset for the default
 *                                  behavior described above.
 */
global $post;
$post_type     = get_post_type();
$is_blog       = $post_type === 'post';
$heading_level = isset( $card_heading_level ) ? (int) $card_heading_level : 3;
$top_label     = isset( $card_top_label ) ? (string) $card_top_label : null;
$permalink     = get_permalink();
$title         = get_the_title();
?>
<div class="story-card">

    <?php // ── Top label ──────────────────────────────────────────────────── ?>

    <?php if ( null !== $top_label ) : ?>
        <?php if ( '' !== $top_label ) : ?>
        <p class="top-label wp-block-paragraph"><?php echo esc_html( $top_label ); ?></p>
        <?php endif; ?>
    <?php elseif ( 'webinar' === $post_type ) : ?>
        <?php
        // Webinars: the live upcoming/on-demand/series status badge — the
        // same markup acf/webinar-status renders on a webinar's own page —
        // rather than a plain category name or post-type label. This file
        // (the actual renderTemplate body, not the ACF block wiring) reads
        // the current global $post via get_the_ID(), so it works correctly
        // here even though story-card.php always runs inside a secondary
        // WP_Query loop, never the main query.
        get_template_part( 'blocks/webinar-status/webinar-status' );
        ?>
    <?php elseif ( 'press-article' === $post_type ) : ?>
        <?php
        // Press articles: first category name, unlinked.
        $cats = get_the_category();
        if ( ! empty( $cats ) ) :
        ?>
        <p class="top-label wp-block-paragraph"><?php echo esc_html( $cats[0]->name ); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <?php
        // Everything else — including 'post', whose singular label is
        // rewritten to "Blog" by inc/rename-posts-to-blog.php, so no
        // special case is needed for it here: the post type's own
        // singular label (e.g. "Case Study", "Whitepaper", "Infographic").
        $type_obj   = get_post_type_object( $post_type );
        $type_label = $type_obj->labels->singular_name ?? '';
        if ( $type_label ) :
        ?>
        <p class="top-label wp-block-paragraph"><?php echo esc_html( $type_label ); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php // ── Featured image ─────────────────────────────────────────────── ?>

    <?php if ( has_post_thumbnail() ) : ?>
    <figure style="aspect-ratio:16/9;">
        <a href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
            <?php the_post_thumbnail( 'large', [
                'style' => 'width:100%;height:100%;object-fit:cover;',
                'alt'   => '',  // decorative — title link below is the accessible label
            ] ); ?>
        </a>
    </figure>
    <?php endif; ?>

    <?php // ── Card body ──────────────────────────────────────────────────── ?>

    <div class="story-content">
		<?php // Lower label: categories, linked — blog posts only 
			if ( $is_blog ) :
			$cats = get_the_category();
			if ( ! empty( $cats ) ) :
				$cat_links = array_map( 'momentive_term_link_with_color', $cats );
			?>
			<div class="taxonomy-category lower-label wp-block-post-terms">
				<?php echo implode(
					'<span class="wp-block-post-terms__separator"> </span>',
					$cat_links
				); ?>
			</div>
			<?php endif; ?>
		<?php endif; ?>

        <<?php echo 'h' . $heading_level; ?> class="wp-block-post-title">
            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
        </<?php echo 'h' . $heading_level; ?>>

        <div class="wp-block-post-excerpt">
            <p><?php echo wp_trim_words( get_the_excerpt(), 20, '…' ); ?></p>
        </div>

        <div class="meta">
            <a class="wp-block-read-more" href="<?php echo esc_url( $permalink ); ?>">
                Read more
                <span class="screen-reader-text">: <?php echo esc_html( $title ); ?></span>
            </a>
            <div class="wp-block-post-date">
                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                    <?php echo esc_html( get_the_date( 'F j, Y' ) ); ?>
                </time>
            </div>
        </div>

    </div>
</div>