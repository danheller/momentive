<?php
/**
 * Block: momentive/solution-resources
 *
 * Automatic grid of "resources" (blog posts, case studies, webinars,
 * whitepapers, infographics — see momentive_get_resource_post_types() in
 * inc/resources.php) tagged with the current Solution's linked category
 * term. Deliberately fully automatic — no manual curation field — mirroring
 * product-solution-tabs' behavior rather than linked-products'
 * curate-with-fallback pattern: whether a resource "belongs" to a Solution
 * is already decided on the resource itself, via its own category panel,
 * not from the Solution page.
 *
 * Query logic lives in momentive_query_resources_for_solution()
 * (inc/resources.php) so the same Solution → resources lookup is available
 * to the /momentive/v1/resources REST route without duplicating it.
 */

if ( ! function_exists( 'momentive_register_solution_resources_block' ) ) {

	add_action( 'init', 'momentive_register_solution_resources_block' );

	function momentive_register_solution_resources_block(): void {
		register_block_type( __DIR__ );

		// Front-end styles — registered here, enqueued conditionally below.
		wp_register_style(
			'momentive-solution-resources',
			get_template_directory_uri() . '/blocks/solution-resources/solution-resources.css',
			[],
			wp_get_theme()->get( 'Version' )
		);
	}

	// Conditional enqueue: only when the block is present (singular) — matches
	// the project's enqueue_block_assets + momentive_content_has_block pattern.
	add_action( 'enqueue_block_assets', function (): void {
		if ( is_admin() ) {
			return;
		}
		if ( momentive_content_has_block( 'momentive/solution-resources' ) ) {
			wp_enqueue_style( 'momentive-solution-resources' );
		}
	} );
}

/**
 * Render callback (ACF renderTemplate target).
 *
 * @param array  $block      Block settings and attributes.
 * @param string $content    Block inner content (unused).
 * @param bool   $is_preview True during AJAX editor preview.
 * @param int    $post_id    The post ID this block is rendering on.
 */

$count = (int) get_field( 'count' );
$count = $count > 0 ? $count : 6;

// Optional category override — when set, bypasses the solution-based query
// and queries resources by this category term directly. Allows the block to
// be used on non-Solution pages (e.g. Product Overview).
$filter_category = (int) get_field( 'filter_category' );

// Slider mode — wraps the grid in a Splide-compatible structure.
$as_slider = (bool) get_field( 'as_slider' );
if ( $as_slider && ! is_admin() ) {
	wp_enqueue_script( 'sliders' );
}

// IMPORTANT: use the $post_id ACF passes into the renderTemplate, NOT
// get_the_ID() — inside an FSE template, blocks render outside the main
// query loop, so get_the_ID() is unreliable for resolving the host post.
// Same gotcha documented in linked-products/block.php and person-position.
$host_id = 0;
if ( isset( $post_id ) && $post_id ) {
	$host_id = is_numeric( $post_id ) ? (int) $post_id : 0;
}
if ( ! $host_id ) {
	$host_id = get_the_ID() ?: 0;
}

$host_type = $host_id ? get_post_type( $host_id ) : '';

// Guard: require either a filter_category override OR a solution host.
// When filter_category is set the block works on any post type.
if ( ! $filter_category && 'solutions' !== $host_type ) {
	if ( ! empty( $is_preview ) ) {
		echo '<div class="momentive-block-placeholder"><strong>Solution Resources</strong><p>Set a Category Filter above, or place this block on a Solution page.</p></div>';
	}
	return;
}

if ( $filter_category ) {
	// Direct category query — works on any host post type.
	$query = new WP_Query( [
		'post_type'        => momentive_get_resource_post_types(),
		'post_status'      => 'publish',
		'posts_per_page'   => $count,
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'tax_query'        => [
			[
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $filter_category,
				'include_children' => true,
			],
		],
	] );
} else {
	// Solution-based two-tier query (AI relevance + category fallback).
	$query = momentive_query_resources_for_solution( $host_id, [ 'posts_per_page' => $count ] );
}

if ( ! $query->have_posts() ) {
	if ( ! empty( $is_preview ) ) {
		$msg = $filter_category
			? 'No published resources found in the selected category.'
			: "No resources are currently tagged with this Solution's category yet.";
		echo '<div class="momentive-block-placeholder"><strong>Solution Resources</strong><p>' . esc_html( $msg ) . '</p></div>';
	}
	return; // Front end: render nothing rather than an empty heading + grid.
}

$anchor     = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';
$extra_class = $as_slider ? ' resources-slider' : '';
$wrapper_attributes = get_block_wrapper_attributes();
?>
<div class="solution-resources<?php echo esc_attr( $extra_class ); ?>" <?php echo $wrapper_attributes; echo $anchor; ?>>

	<?php if ( $as_slider ) : ?>
	<div class="solution-resources__track">
	<?php endif; ?>

	<ul class="solution-resources__grid" role="list">
		<?php
		while ( $query->have_posts() ) :
			$query->the_post();
			?>
			<li class="solution-resources__item">
				<?php
				// story-card.php's default per-post-type top label (webinar
				// status badge / press-article category / post type label —
				// see its docblock) is exactly right for a mixed-type grid
				// like this one, so no $card_top_label override is passed
				// here: every card in this grid already shares the same
				// category by construction of the query above, so a plain
				// category-name label would be redundant regardless of type.
				get_template_part( 'patterns/story-card' );
				?>
			</li>
		<?php
		endwhile;
		wp_reset_postdata();
		?>
	</ul>

	<?php if ( $as_slider ) : ?>
	</div><!-- .solution-resources__track -->
	<?php endif; ?>

</div>
