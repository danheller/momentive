<?php
/**
 * Unified "Resources" query layer.
 *
 * The legacy site merges several content types into a single "Resources"
 * collection with its own archive: Blogs, Case Studies, Events, Guides &
 * Research, Infographics, Interactive Tools, Product Overviews,
 * Testimonials, Toolkits, Videos, Webinars, and Whitepapers. Not everything
 * on that list has been migrated yet — see CLAUDE.md's "Pending CPT
 * migrations" section — and testimonials are deliberately excluded here
 * (see below).
 *
 * This file provides the plumbing to query across whichever resource post
 * types currently exist on the rebuilt site, optionally scoped to a
 * Solution. No new taxonomy or field was needed to make this possible:
 * post, case-study, webinar, whitepaper, and infographic already all carry
 * the shared `category` taxonomy (see the table in CLAUDE.md's "Post types
 * and taxonomies" section), and a Solution is already linked to a category
 * term via that term's `related_solution` ACF field — the exact mechanism
 * inc/solutions.php's get_terms_for_solution() and
 * momentive_get_solution_term_map() already expose, and that
 * product-solution-tabs already queries against for Products. Solution
 * scoping for resources reuses those same two functions rather than
 * introducing a parallel lookup.
 *
 * Deliberately excludes testimonials, FAQs, products, and press-article
 * (Newsroom): each of those renders as a different card shape (quote,
 * product logo, etc.), and none of them is part of the legacy Resources
 * collection above (press-article/Newsroom never was; testimonials appear
 * in the legacy list but the CPT's solution association is currently
 * broken — see CLAUDE.md's "Known limitations" — and a quote card doesn't
 * fit the title/excerpt/link "resource card" shape the other types share).
 *
 * Two consumers:
 *   1. momentive_query_resources_for_solution() — a direct PHP call used by
 *      the momentive/solution-resources block for a single server-rendered
 *      grid on Solution pages. No REST round-trip needed since the block
 *      renders once, server-side, like product-solution-tabs.
 *   2. The GET /momentive/v1/resources REST route — merges multiple post
 *      types into one paginated, sorted response. This is the piece
 *      blocks/resource-filters/filters.js explicitly flags as missing (see
 *      its "NOTE" comment on true multi-type querying) for an "All
 *      Resources" filter bar; filters.js has been updated to call it
 *      whenever more than one post-type checkbox is active.
 *
 * momentive_query_resources_for_solution() itself is two-tier: it prefers
 * resources directly tagged to the exact (usually child-level) Solution via
 * the `relevant_solutions` field — populated by an LLM shortly after
 * publish/update, see inc/resource-relevance.php — and only tops up the
 * remainder from this file's original category-term mechanism. The REST
 * route above still uses category-term resolution only; it wasn't in scope
 * for the direct-tagging pass.
 */

/**
 * Single source of truth for which registered post types count as a
 * "resource."
 *
 * Add a new slug here — and nowhere else — once a pending CPT migration
 * (toolkit, video, product-overview, interactive-tool, event) ships; both
 * the REST endpoint and the solution-resources block pick it up
 * automatically. Filterable so a future one-off need (e.g. excluding a type
 * from a specific view) doesn't require editing this function directly.
 *
 * `guide` is included even though its content migration hasn't run yet
 * (see notes/guide-reference-sheet.md) — the CPT itself is registered in
 * inc/guides.php, and `post_type_exists()` below means an empty guide CPT
 * simply contributes zero rows until posts exist, with nothing else to
 * update once they do.
 *
 * @return string[] Registered post type slugs.
 */
function momentive_get_resource_post_types(): array {
	$types = [ 'post', 'case-study', 'webinar', 'whitepaper', 'infographic', 'guide', 'video', 'event', 'interactive-tool', 'toolkit' ];

	// Only ever return types that actually exist — a filter adding a
	// not-yet-built slug shouldn't be able to break the query below.
	$types = array_values( array_filter( $types, 'post_type_exists' ) );

	/**
	 * Filters the list of post types treated as "resources."
	 *
	 * @param string[] $types Registered post type slugs.
	 */
	return apply_filters( 'momentive_resource_post_types', $types );
}

/**
 * Resolve the category term(s) to query for a Solution's resources, walking
 * up to the parent Solution when the given post has no linked term of its
 * own.
 *
 * momentive/solution-resources is used mostly on CHILD solution pages,
 * which typically have no category term linked directly — only the
 * top-level parent Solution does (the "Related Solution" ACF field lives on
 * a category term, and child solutions generally don't get their own
 * term). Same one-level "child inherits from parent" fallback
 * inc/solutions.php already uses for accent_color (wp_get_post_parent_id()),
 * kept here rather than folded into get_terms_for_solution() itself so
 * other callers of that function (product-solution-tabs, etc.) are
 * unaffected — they may or may not want the same inheritance, and this
 * keeps that a per-consumer decision rather than a global behavior change.
 *
 * @param int $solution_id Post ID of the Solution post.
 * @return int[] Category term IDs.
 */
function momentive_resolve_resource_term_ids_for_solution( int $solution_id ): array {
	$term_ids = get_terms_for_solution( $solution_id );

	if ( empty( $term_ids ) ) {
		$parent_id = wp_get_post_parent_id( $solution_id );
		if ( $parent_id ) {
			$term_ids = get_terms_for_solution( $parent_id );
		}
	}

	return $term_ids;
}

/**
 * Query resources for a Solution's grid, preferring direct AI/editor-tagged
 * relevance over the category-level fallback.
 *
 * Two-tier resolution:
 *   1. Resources whose `relevant_solutions` field (see
 *      inc/resource-relevance.php) names this exact Solution — the
 *      individual-child-solution-level tagging, auto-populated by an LLM at
 *      publish time and editor-overridable.
 *   2. If that doesn't fill the requested count, top up the remainder from
 *      the existing category-term fallback (a Solution's linked category
 *      term, walking up to the parent for child solutions) — the same
 *      mechanism this function used exclusively before direct tagging
 *      existed. This keeps pages from going empty during the tagging
 *      backfill period, and still degrades gracefully for post types or
 *      posts an editor hasn't gotten around to direct-tagging.
 *
 * Both tiers exclude resources past the freshness cutoff
 * (momentive_resource_freshness_cutoff_months()) unless flagged `evergreen`.
 *
 * @param int   $solution_id Post ID of the Solution post.
 * @param array $args        WP_Query overrides (posts_per_page, etc.).
 * @return WP_Query
 */
function momentive_query_resources_for_solution( int $solution_id, array $args = [] ): WP_Query {
	$count     = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 6;
	$pool_size = max( $count * 3, 12 ); // headroom for freshness filtering below

	$direct_ids = momentive_filter_resources_by_freshness(
		momentive_get_relevant_resource_ids( $solution_id, $pool_size ),
		$count
	);

	$ordered_ids = $direct_ids;

	if ( count( $ordered_ids ) < $count ) {
		$fallback_ids = momentive_filter_resources_by_freshness(
			momentive_get_category_fallback_resource_ids( $solution_id, $pool_size, $ordered_ids ),
			$count - count( $ordered_ids )
		);
		$ordered_ids = array_merge( $ordered_ids, $fallback_ids );
	}

	$defaults = [
		'post_type'           => momentive_get_resource_post_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	];

	// No direct tags and no linked category term → no possible results.
	// Force an empty result set explicitly rather than falling through to a
	// filter-less WP_Query, which would return every resource on the site.
	if ( empty( $ordered_ids ) ) {
		$defaults['post__in'] = [ 0 ];
		return new WP_Query( wp_parse_args( $args, $defaults ) );
	}

	$defaults['post__in'] = array_slice( $ordered_ids, 0, $count );
	$defaults['orderby']  = 'post__in'; // preserve direct-matches-first ordering

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Post IDs directly tagged (by AI or by hand, via inc/resource-relevance.php)
 * as relevant to this Solution, newest first.
 *
 * ACF stores a multi-value post_object field as one postmeta row per
 * selected ID under the same key, so a plain meta_query equality match finds
 * every resource that lists $solution_id — no serialized-value LIKE trick
 * needed.
 *
 * @param int $solution_id Post ID of the Solution post.
 * @param int $limit       Max IDs to return.
 * @return int[]
 */
function momentive_get_relevant_resource_ids( int $solution_id, int $limit ): array {
	$query = new WP_Query( [
		'post_type'           => momentive_get_resource_post_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'fields'              => 'ids',
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'meta_query'          => [
			[
				'key'     => 'relevant_solutions',
				'value'   => $solution_id,
				'compare' => '=',
			],
		],
	] );

	return array_map( 'intval', $query->posts );
}

/**
 * The pre-existing category-term lookup, as a plain ID list (rather than a
 * WP_Query) so it can be merged with direct matches. $exclude keeps
 * already-selected posts from appearing twice.
 *
 * @param int   $solution_id Post ID of the Solution post.
 * @param int   $limit       Max IDs to return.
 * @param int[] $exclude     Post IDs to omit (already selected via direct tagging).
 * @return int[]
 */
function momentive_get_category_fallback_resource_ids( int $solution_id, int $limit, array $exclude = [] ): array {
	$term_ids = momentive_resolve_resource_term_ids_for_solution( $solution_id );
	if ( empty( $term_ids ) ) {
		return [];
	}

	$args = [
		'post_type'           => momentive_get_resource_post_types(),
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'fields'              => 'ids',
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'tax_query'           => [
			[
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $term_ids,
			],
		],
	];

	if ( ! empty( $exclude ) ) {
		$args['post__not_in'] = $exclude;
	}

	$query = new WP_Query( $args );
	return array_map( 'intval', $query->posts );
}

/**
 * Drop posts past the freshness cutoff, except ones flagged `evergreen`.
 * Filters in PHP rather than adding a WP_Query date_query, so the cutoff can
 * be combined with the evergreen escape hatch without needing an OR across a
 * date_query and a meta_query in one WP_Query call.
 *
 * @param int[] $post_ids Candidate IDs, newest first.
 * @param int   $limit    Max IDs to return.
 * @return int[]
 */
function momentive_filter_resources_by_freshness( array $post_ids, int $limit ): array {
	$months = momentive_resource_freshness_cutoff_months();
	$cutoff = $months > 0 ? strtotime( "-{$months} months" ) : 0;
	$kept   = [];

	foreach ( $post_ids as $post_id ) {
		if ( count( $kept ) >= $limit ) {
			break;
		}
		if ( ! $cutoff || strtotime( get_post_field( 'post_date', $post_id ) ) >= $cutoff || get_field( 'evergreen', $post_id ) ) {
			$kept[] = $post_id;
		}
	}

	return $kept;
}

/**
 * How many months old a resource can be before the Solution Resources grid
 * excludes it (unless flagged evergreen). Filterable per the same pattern as
 * momentive_resource_post_types.
 *
 * @return int Months. 0 or less disables the cutoff entirely.
 */
function momentive_resource_freshness_cutoff_months(): int {
	return (int) apply_filters( 'momentive_resource_freshness_cutoff_months', 18 );
}

/**
 * REST route: GET /momentive/v1/resources
 *
 * Merges multiple post types into one paginated, sorted result. Response
 * shape intentionally stays close to core's /wp/v2/{type} list endpoints —
 * id, title, excerpt, link, date, featured image, categories — so the
 * client only needs a small adapter, plus a couple of fields core can't
 * give us for free across mixed types (type/type_label, category tag_color
 * for the solution-color styling momentive_term_link_with_color() already
 * applies elsewhere).
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'momentive/v1', '/resources', [
		'methods'             => 'GET',
		'callback'            => 'momentive_resources_rest_callback',
		'permission_callback' => '__return_true',
		'args'                => [
			'post_type'  => [ 'required' => false ],
			'categories' => [ 'required' => false ],
			'solution'   => [ 'type' => 'integer', 'required' => false ],
			'search'     => [ 'type' => 'string', 'required' => false ],
			'orderby'    => [ 'type' => 'string', 'default' => 'date' ],
			'order'      => [ 'type' => 'string', 'default' => 'desc' ],
			'page'       => [ 'type' => 'integer', 'default' => 1 ],
			'per_page'   => [ 'type' => 'integer', 'default' => 12 ],
			'exclude'    => [ 'required' => false ],
		],
	] );
} );

/**
 * Normalize a REST param that may arrive as a comma-separated string or an
 * array (core REST controllers accept both; this route mirrors that).
 *
 * @param mixed $value Raw param value.
 * @return string[]
 */
function momentive_resources_rest_param_to_array( $value ): array {
	if ( empty( $value ) ) {
		return [];
	}
	$value = is_string( $value ) ? explode( ',', $value ) : (array) $value;
	return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
}

function momentive_resources_rest_callback( WP_REST_Request $request ): WP_REST_Response {
	$available = momentive_get_resource_post_types();

	// post_type: intersect against the allowlist so an arbitrary or
	// unpublished type can never be queried through this endpoint.
	$requested_types = momentive_resources_rest_param_to_array( $request->get_param( 'post_type' ) );
	$post_types      = ! empty( $requested_types ) ? array_values( array_intersect( $requested_types, $available ) ) : $available;
	if ( empty( $post_types ) ) {
		$post_types = $available;
	}

	$tax_query = [];

	// `solution` is a convenience param: resolve straight to its linked
	// category term(s) — the same lookup (with parent-solution fallback)
	// the solution-resources block uses.
	$solution_id = (int) $request->get_param( 'solution' );
	if ( $solution_id ) {
		$term_ids = momentive_resolve_resource_term_ids_for_solution( $solution_id );
		if ( empty( $term_ids ) ) {
			// The solution resolved to no category term — return an empty
			// result rather than silently ignoring the filter.
			return momentive_resources_rest_response( [], 0, max( (int) $request->get_param( 'page' ), 1 ), (int) $request->get_param( 'per_page' ) );
		}
		$tax_query[] = [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $term_ids ];
	}

	$categories = array_map( 'intval', momentive_resources_rest_param_to_array( $request->get_param( 'categories' ) ) );
	if ( ! empty( $categories ) ) {
		$tax_query[] = [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $categories ];
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	$orderby = sanitize_key( (string) $request->get_param( 'orderby' ) ?: 'date' );
	$orderby = in_array( $orderby, [ 'date', 'title', 'menu_order' ], true ) ? $orderby : 'date';
	$order   = 'ASC' === strtoupper( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC';

	$per_page = min( max( (int) ( $request->get_param( 'per_page' ) ?: 12 ), 1 ), 50 );
	$page     = max( (int) ( $request->get_param( 'page' ) ?: 1 ), 1 );

	$exclude = array_map( 'intval', momentive_resources_rest_param_to_array( $request->get_param( 'exclude' ) ) );

	$query_args = [
		'post_type'           => $post_types,
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'paged'               => $page,
		'orderby'             => $orderby,
		'order'               => $order,
		'ignore_sticky_posts' => true,
	];

	if ( ! empty( $tax_query ) ) {
		$query_args['tax_query'] = $tax_query;
	}

	$search = (string) $request->get_param( 'search' );
	if ( '' !== $search ) {
		$query_args['s'] = sanitize_text_field( $search );
	}

	if ( ! empty( $exclude ) ) {
		$query_args['post__not_in'] = $exclude;
	}

	// Exclude posts hidden via the Archive Visibility ACF fields (inc/archive-visibility.php).
	// Posts with no featured image are shown with a default fallback image (see story-card.php /
	// filters.js), so there is no _thumbnail_id EXISTS gate here.
	$query_args['meta_query'] = [
		'relation' => 'AND',
		// hide_from_archives: excludes from CPT archives AND the Resource Center.
		[
			'relation' => 'OR',
			[ 'key' => 'hide_from_archives', 'compare' => 'NOT EXISTS' ],
			[ 'key' => 'hide_from_archives', 'value' => '1', 'compare' => '!=' ],
		],
		// hide_from_resource_center: Resource Center only; post still shows on CPT archives.
		[
			'relation' => 'OR',
			[ 'key' => 'hide_from_resource_center', 'compare' => 'NOT EXISTS' ],
			[ 'key' => 'hide_from_resource_center', 'value' => '1', 'compare' => '!=' ],
		],
	];

	$query = new WP_Query( $query_args );
	$items = array_map( 'momentive_resource_to_rest_item', $query->posts );

	return momentive_resources_rest_response( $items, (int) $query->found_posts, $page, $per_page );
}

/**
 * Build the WP_REST_Response with core-style pagination headers
 * (X-WP-Total / X-WP-TotalPages), so existing client code that reads those
 * headers off core endpoints works unchanged against this one.
 */
function momentive_resources_rest_response( array $items, int $total, int $page, int $per_page = 12 ): WP_REST_Response {
	$response = new WP_REST_Response( $items );
	$response->header( 'X-WP-Total', (string) $total );
	$response->header( 'X-WP-TotalPages', (string) ( $per_page > 0 ? (int) ceil( $total / $per_page ) : 0 ) );
	return $response;
}

/**
 * Shape a WP_Post into the flat structure the REST route returns.
 *
 * Category entries match the shape assets/js/site-utils.js's
 * renderCategoryLink() already expects (name, link, tag_color) so the
 * client can reuse that helper unchanged instead of re-deriving it.
 */
function momentive_resource_to_rest_item( WP_Post $post ): array {
	$post_id  = $post->ID;
	$type_obj = get_post_type_object( $post->post_type );
	$thumb_id = get_post_thumbnail_id( $post_id );

	$categories = [];
	$terms      = get_the_terms( $post_id, 'category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$categories[] = [
				'id'        => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'link'      => get_category_link( $term->term_id ),
				'tag_color' => get_solution_color_for_term( $term->term_id ) ?: null,
			];
		}
	}

	return [
		'id'             => $post_id,
		'type'           => $post->post_type,
		'type_label'     => $type_obj->labels->singular_name ?? $post->post_type,
		'title'          => $post->post_title,
		'excerpt'        => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '…' ),
		'link'           => get_permalink( $post_id ),
		'date'           => get_the_date( 'c', $post_id ),
		'featured_image' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : null,
		'categories'     => $categories,
	];
}
