<?php
/**
 * Block: momentive/fundraiser-list
 *
 * Renders all fundraiser posts as a filterable card grid.
 * Pair with the Resource Filters block — filters.js proximity-targets
 * the .wp-block-post-template output here and replaces its innerHTML
 * with JS-rendered cards when the user filters/sorts.
 *
 * ACF block: block.json carries the "acf" renderTemplate key. ACF handles
 * JS-side registration automatically; no editor.js is needed.
 *
 * @package Momentive
 */

// ── Registration ──────────────────────────────────────────────────────────────

if ( ! function_exists( 'momentive_register_fundraiser_list_block' ) ) {

	add_action( 'init', 'momentive_register_fundraiser_list_block' );

	function momentive_register_fundraiser_list_block(): void {
		register_block_type( __DIR__ );
	}
}

// ── Render template ───────────────────────────────────────────────────────────
// ACF calls this file as the renderTemplate. Guard against running when the
// file is require'd by functions.php during normal theme bootstrap (at that
// point $block is not set — ACF only injects it when rendering).

if ( ! isset( $block ) ) return;

// ── Editor placeholder ────────────────────────────────────────────────────────

if ( ! empty( $is_preview ) ) : ?>
	<div class="momentive-block-placeholder">
		<strong>Fundraiser List</strong>
		<p>Renders all fundraiser posts as a card grid. Pair with the Resource Filters block above.</p>
	</div>
<?php
	return;
endif;

// ── Front-end render ──────────────────────────────────────────────────────────

$query = new WP_Query( [
	'post_type'      => 'fundraiser',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
] );

if ( ! $query->have_posts() ) {
	return;
}
?>
<div class="wp-block-query">
	<ul class="wp-block-post-template is-layout-grid columns-3">
	<?php while ( $query->have_posts() ) : $query->the_post(); ?>
		<li class="wp-block-post post-<?php echo get_the_ID(); ?> fundraiser">
			<?php get_template_part( 'patterns/fundraiser-card' ); ?>
		</li>
	<?php endwhile; wp_reset_postdata(); ?>
	</ul>
</div>
