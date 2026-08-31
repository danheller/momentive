<?php
/**
 * Reviews block — JS-registered dynamic block with InnerBlocks CTA insert.
 *
 * Renders a two-column layout: solution-family filter sidebar + review list.
 * Reviews are testimonials with a review_source field populated.
 * Filtering, search, and load-more are handled client-side via the
 * /momentive/v1/reviews REST endpoint defined below.
 *
 * The InnerBlocks "after review #N" CTA is stored in $content and injected
 * at the configured position in the initial PHP render; JS re-injects it
 * on subsequent filter/load-more operations using the saved outerHTML.
 */

// ── Asset registration ─────────────────────────────────────────────────────────

add_action( 'init', function (): void {

	wp_register_script(
		'momentive-reviews-editor',
		get_template_directory_uri() . '/blocks/reviews/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_script(
		'momentive-reviews',
		get_template_directory_uri() . '/blocks/reviews/reviews.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-reviews',
		get_template_directory_uri() . '/assets/css/reviews.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/reviews/block.json',
		[
			'render_callback' => 'momentive_reviews_render',
			'editor_script'   => 'momentive-reviews-editor',
		]
	);

} );

// Conditionally enqueue front-end assets when the block is on the page.
add_action( 'enqueue_block_assets', function (): void {
	if ( is_admin() ) return;
	if ( ! momentive_content_has_block( 'momentive/reviews' ) ) return;
	wp_enqueue_script( 'momentive-reviews' );
	wp_enqueue_style( 'momentive-reviews' );
} );


// ── REST endpoint ──────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'momentive/v1', '/reviews', [
		'methods'             => 'GET',
		'callback'            => 'momentive_reviews_rest_handler',
		'permission_callback' => '__return_true',
		'args'                => [
			'solution_terms' => [
				'type'    => 'array',
				'items'   => [ 'type' => 'integer' ],
				'default' => [],
			],
			'search'         => [
				'type'    => 'string',
				'default' => '',
			],
			'page'           => [
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			],
			'per_page'       => [
				'type'    => 'integer',
				'default' => 7,
				'minimum' => 1,
				'maximum' => 50,
			],
		],
	] );
} );

function momentive_reviews_rest_handler( WP_REST_Request $request ): WP_REST_Response {
	$terms    = array_map( 'absint', (array) $request->get_param( 'solution_terms' ) );
	$search   = sanitize_text_field( $request->get_param( 'search' ) );
	$page     = (int) $request->get_param( 'page' );
	$per_page = (int) $request->get_param( 'per_page' );

	$query = new WP_Query( momentive_reviews_base_query( $terms, $search, $page, $per_page ) );

	$items = array_map( 'momentive_reviews_format_item', $query->posts );

	return new WP_REST_Response( [
		'items'       => $items,
		'total'       => (int) $query->found_posts,
		'total_pages' => (int) $query->max_num_pages,
		'page'        => $page,
	] );
}


// ── Shared query builder ───────────────────────────────────────────────────────

function momentive_reviews_base_query(
	array $solution_terms,
	string $search,
	int $page,
	int $per_page
): array {
	$args = [
		'post_type'      => 'testimonials',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'meta_query'     => [
			[
				'key'     => 'review_source',
				'value'   => '',
				'compare' => '!=',
			],
		],
	];

	if ( ! empty( $solution_terms ) ) {
		$args['tax_query'] = [
			[
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $solution_terms,
			],
		];
	}

	if ( $search !== '' ) {
		$args['s'] = $search;
	}

	return $args;
}


// ── Card data formatter ────────────────────────────────────────────────────────

function momentive_reviews_format_item( WP_Post $post ): array {
	$id          = $post->ID;
	$source      = (string) ( get_field( 'review_source', $id ) ?: '' );
	$source_link = (string) ( get_field( 'review_source_link', $id ) ?: '' );
	$headline    = (string) ( get_field( 'review_headline', $id ) ?: '' );
	$author      = (string) ( get_field( 'testimonial_author_name', $id ) ?: '' );
	$excerpt     = wp_trim_words( wp_strip_all_tags( $post->post_content ), 45, '…' );

	// Human-readable time ago from post_date.
	$posted   = (int) get_post_time( 'U', false, $post );
	$time_ago = human_time_diff( $posted, current_time( 'timestamp' ) ) . ' ago';

	// Solution family term name + accent color.
	$term_name  = '';
	$term_color = '';
	$terms = get_the_terms( $id, 'category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$term       = $terms[0];
		$term_name  = $term->name;
		$raw_color  = get_solution_color_for_term( $term->term_id );
		$term_color = $raw_color ? (string) sanitize_hex_color( $raw_color ) : '';
	}

	return compact( 'id', 'author', 'source', 'source_link', 'headline', 'excerpt', 'time_ago', 'term_name', 'term_color' );
}


// ── Filter options (terms present on published reviews, alphabetical) ──────────

function momentive_reviews_filter_options(): array {
	static $options = null;
	if ( $options !== null ) return $options;

	$review_ids = get_posts( [
		'post_type'      => 'testimonials',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'     => 'review_source',
				'value'   => '',
				'compare' => '!=',
			],
		],
	] );

	$seen = [];
	foreach ( $review_ids as $pid ) {
		$terms = get_the_terms( $pid, 'category' );
		if ( ! $terms || is_wp_error( $terms ) ) continue;
		foreach ( $terms as $t ) {
			$seen[ $t->term_id ] ??= [ 'term_id' => $t->term_id, 'name' => $t->name ];
		}
	}

	usort( $seen, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
	$options = array_values( $seen );
	return $options;
}


// ── Render callback ────────────────────────────────────────────────────────────

function momentive_reviews_render( array $attributes, string $content ): string {
	$per_page     = max( 1, (int) ( $attributes['perPage']     ?? 7 ) );
	$insert_after = max( 1, (int) ( $attributes['insertAfter'] ?? 3 ) );

	$query          = new WP_Query( momentive_reviews_base_query( [], '', 1, $per_page ) );
	$filter_options = momentive_reviews_filter_options();
	$total          = (int) $query->found_posts;
	$total_pages    = (int) $query->max_num_pages;

	ob_start();
	?>
	<div class="wp-block-momentive-reviews reviews-block"
	     data-per-page="<?php echo esc_attr( $per_page ); ?>"
	     data-insert-after="<?php echo esc_attr( $insert_after ); ?>"
	     data-total="<?php echo esc_attr( $total ); ?>"
	     data-total-pages="<?php echo esc_attr( $total_pages ); ?>"
	     data-filter-options="<?php echo esc_attr( wp_json_encode( $filter_options ) ); ?>"
	     data-rest-url="<?php echo esc_attr( rest_url( 'momentive/v1/reviews' ) ); ?>"
	>

		<?php /* ── Sidebar ─────────────────────────────────────────────────── */ ?>
		<div class="reviews-sidebar">

			<div class="reviews-search">
				<label for="reviews-search-input" class="screen-reader-text">
					<?php esc_html_e( 'Search reviews', 'momentive' ); ?>
				</label>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M10 2a8 8 0 1 1 0 16A8 8 0 0 1 10 2zm0 2a6 6 0 1 0 0 12A6 6 0 0 0 10 4zm7.293 11.293 3.707 3.707-1.414 1.414-3.707-3.707 1.414-1.414z"/>
				</svg>
				<input
					type="search"
					id="reviews-search-input"
					placeholder="<?php esc_attr_e( 'Search', 'momentive' ); ?>"
					autocomplete="off"
				/>
			</div>

			<?php if ( ! empty( $filter_options ) ) : ?>
			<div class="reviews-filters" role="group" aria-label="<?php esc_attr_e( 'Filter by solution family', 'momentive' ); ?>">
				<?php foreach ( $filter_options as $opt ) : ?>
				<label class="reviews-filter-option">
					<input type="checkbox" value="<?php echo esc_attr( $opt['term_id'] ); ?>" />
					<span><?php echo esc_html( $opt['name'] ); ?></span>
				</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

		</div>

		<?php /* ── Main list ───────────────────────────────────────────────── */ ?>
		<div class="reviews-main">

			<ul class="reviews-list" aria-live="polite" aria-busy="false">
				<?php
				$i = 0;
				foreach ( $query->posts as $post ) {
					$i++;
					echo momentive_reviews_card_html( momentive_reviews_format_item( $post ) );

					// Inject InnerBlocks CTA after the nth review.
					if ( $i === $insert_after && $content ) {
						echo '<li class="reviews-list-insert">' . $content . '</li>';
					}
				}
				?>
			</ul>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="reviews-load-more-wrap">
				<button type="button" class="reviews-load-more wp-element-button" data-current-page="1">
					<?php esc_html_e( 'Load More', 'momentive' ); ?>
				</button>
			</div>
			<?php endif; ?>

		</div>

	</div>
	<?php
	return ob_get_clean();
}


// ── Card HTML helper ───────────────────────────────────────────────────────────

function momentive_reviews_card_html( array $item ): string {
	$tag_style = $item['term_color']
		? ' style="--term-color:' . esc_attr( $item['term_color'] ) . '"'
		: '';

	// Five gold stars SVG (reused in reviews.js template too).
	$stars = '';
	for ( $s = 0; $s < 5; $s++ ) {
		$stars .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
	}

	ob_start();
	?>
	<li class="review-card" data-review-id="<?php echo esc_attr( $item['id'] ); ?>">

		<header class="review-card-header">
			<strong class="review-author"><?php echo esc_html( $item['author'] ); ?></strong>
			<div class="review-meta">
				<span class="review-stars" aria-label="<?php esc_attr_e( '5 stars', 'momentive' ); ?>">
					<?php echo $stars; ?>
				</span>
				<?php if ( $item['source'] ) : ?>
					<span class="review-sep" aria-hidden="true">&bull;</span>
					<?php if ( $item['source_link'] ) : ?>
					<a class="review-source-link" href="<?php echo esc_url( $item['source_link'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $item['source'] ); ?>
					</a>
					<?php else : ?>
					<span class="review-source"><?php echo esc_html( $item['source'] ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<span class="review-sep" aria-hidden="true">&bull;</span>
				<span class="review-time"><?php echo esc_html( $item['time_ago'] ); ?></span>
			</div>
		</header>

		<?php if ( $item['term_name'] ) : ?>
		<span class="review-family-tag"<?php echo $tag_style; ?>>
			<?php echo esc_html( $item['term_name'] ); ?>
		</span>
		<?php endif; ?>

		<?php if ( $item['headline'] ) : ?>
		<p class="review-headline">&ldquo;<?php echo esc_html( $item['headline'] ); ?>&rdquo;</p>
		<?php endif; ?>

		<?php if ( $item['excerpt'] ) : ?>
		<p class="review-excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
		<?php endif; ?>

		<?php if ( $item['source_link'] ) : ?>
		<a class="review-read-more" href="<?php echo esc_url( $item['source_link'] ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Read Full Review', 'momentive' ); ?>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
			</svg>
		</a>
		<?php endif; ?>

	</li>
	<?php
	return ob_get_clean();
}
