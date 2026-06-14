<?php
/**
 * Template Part: Story Card
 *
 * Handles display differences between 'post' and 'press-article' post types.
 *
 * Expected to run inside a WP_Query loop (the_post() already called).
 *
 * @var int    $card_heading_level  Optional. Heading level for post title. Default 3.
 */
global $post;
$post_type     = get_post_type();
$is_blog       = $post_type === 'post';
$heading_level = isset( $card_heading_level ) ? (int) $card_heading_level : 3;
$permalink     = get_permalink();
$title         = get_the_title();
?>
<div class="story-card">

    <?php // ── Top label ──────────────────────────────────────────────────── ?>

    <?php if ( $is_blog ) : ?>
        <p class="top-label wp-block-paragraph">Blog</p>
    <?php else : ?>
        <?php
        // Press articles and other CPTs: show first category name, unlinked.
        $cats = get_the_category();
        if ( ! empty( $cats ) ) :
        ?>
        <p class="top-label wp-block-paragraph"><?php echo esc_html( $cats[0]->name ); ?></p>
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

        <?php // Lower label: categories, linked — blog posts only ?>
        <?php if ( $is_blog ) : ?>
            <?php
            $cats = get_the_category();
            if ( ! empty( $cats ) ) :
                $cat_links = array_map( function ( $cat ) {
                    return sprintf(
                        '<a href="%s" rel="tag">%s</a>',
                        esc_url( get_category_link( $cat->term_id ) ),
                        esc_html( $cat->name )
                    );
                }, $cats );
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