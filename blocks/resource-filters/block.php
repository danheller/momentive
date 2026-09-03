<?php
/**
 * Resource Filters block — register block JSON, editor script, and frontend javascript and CSS.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-resource-filters-editor',
		get_template_directory_uri() . '/blocks/resource-filters/editor.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_script(
		'momentive-resource-filters',
		get_template_directory_uri() . '/blocks/resource-filters/filters.js',
		array( 'site-utils' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-resource-filters',
		get_template_directory_uri() . '/blocks/resource-filters/filters.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/resource-filters/block.json',
		array(
			'render_callback' => 'momentive_resource_filters_render',
			'editor_script'   => 'momentive-resource-filters-editor',
			'script'          => 'momentive-resource-filters',
			'style'           => 'momentive-resource-filters',
		)
	);
} );

/**
 * Localize the post-type map onto whichever of our scripts are registered.
 *
 * Done on enqueue hooks (NOT on init) for two reasons:
 *  1. Timing: post type labels are finalized by other init callbacks (e.g.
 *     rename-posts-to-blog.php renames `post` → "Blog" on init). Building the
 *     map on init races those; building it at enqueue time reads final labels.
 *  2. Correctness: wp_localize_script data is only emitted when the script is
 *     actually enqueued and printed. Attaching it on the enqueue hooks (front
 *     end + block editor) guarantees the `momentiveResourceFilters` global is
 *     present wherever the scripts run — which is why the editor select was
 *     empty and the front-end labels were stale before.
 */
function momentive_resource_filters_localize(): void {
	$data = array(
		'postTypes' => momentive_resource_filters_post_type_map(),
		'themeUri'  => get_template_directory_uri(),
	);

	foreach ( array( 'momentive-resource-filters', 'momentive-resource-filters-editor' ) as $handle ) {
		if ( wp_script_is( $handle, 'registered' ) ) {
			// Remove any prior copy (in case both hooks fire) then attach fresh.
			wp_localize_script( $handle, 'momentiveResourceFilters', $data );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'momentive_resource_filters_localize', 20 );
add_action( 'enqueue_block_editor_assets', 'momentive_resource_filters_localize', 20 );

/**
 * Build a slug => [ label, singular, endpoint ] map of post types the filter
 * bar can query. Public, REST-enabled post types only. `singular` is used for
 * the card's top-label; `endpoint` is the REST route (from rest_base).
 *
 * @return array<string,array{label:string,singular:string,endpoint:string}>
 */
function momentive_resource_filters_post_type_map(): array {
	$map = array();

	// Include `publicly_queryable` types even when `public` is false — this
	// covers CPTs like `donation-example` that expose a REST endpoint and can
	// be queried via the filter bar but don't need public singular pages.
	$types = get_post_types(
		array( 'publicly_queryable' => true, 'show_in_rest' => true ),
		'objects'
	);

	foreach ( $types as $slug => $obj ) {
		// Skip types that don't make sense as filterable resources.
		if ( in_array( $slug, array( 'attachment', 'page' ), true ) ) {
			continue;
		}
		$rest_base = ! empty( $obj->rest_base ) ? $obj->rest_base : $slug;
		$singular  = ! empty( $obj->labels->singular_name ) ? $obj->labels->singular_name : $obj->label;

		$map[ $slug ] = array(
			'label'    => $obj->label,            // plural admin label (for pickers)
			'singular' => $singular,              // singular label (for top-label)
			'endpoint' => '/wp-json/wp/v2/' . ltrim( (string) $rest_base, '/' ),
		);
	}

	return $map;
}


function momentive_resource_filters_render( array $attributes, string $content ): string {
	$show_categories  = ! empty( $attributes['showCategories'] );
	$show_post_types  = ! empty( $attributes['showPostTypes'] );
	$show_search      = ! empty( $attributes['showSearch'] );
	$show_sort        = ! empty( $attributes['showSort'] );

	// Build the post-type list for the filter panel.
	// Each item: { slug: string, label: string }.
	//
	// When the editor has configured an explicit list (postTypes attribute is non-empty),
	// use that — sanitize each item individually since array_map( 'sanitize_text_field', … )
	// on an array of arrays is a no-op in PHP and would silently produce empty strings.
	//
	// When showPostTypes is true but the attribute is empty (the default), auto-populate
	// from momentive_get_resource_post_types() so dropping the block on the Resources page
	// "just works" without per-instance configuration.
	$post_types_raw = $attributes['postTypes'] ?? [];
	if ( ! empty( $post_types_raw ) ) {
		$post_types = [];
		foreach ( $post_types_raw as $pt ) {
			if ( is_array( $pt ) && ! empty( $pt['slug'] ) ) {
				$post_types[] = [
					'slug'  => sanitize_key( $pt['slug'] ),
					'label' => sanitize_text_field( $pt['label'] ?? '' ),
				];
			}
		}
	} elseif ( $show_post_types && function_exists( 'momentive_get_resource_post_types' ) ) {
		$post_types = [];
		foreach ( momentive_get_resource_post_types() as $slug ) {
			$obj = get_post_type_object( $slug );
			if ( $obj ) {
				$post_types[] = [
					'slug'  => $slug,
					'label' => $obj->labels->singular_name ?? $obj->label,
				];
			}
		}
	} else {
		$post_types = [];
	}

	// Custom taxonomies — when set, these replace the standard `category` panel.
	// Each entry is { slug: string, label: string }.
	// PHP builds the term list; JS reads the slugs from data-custom-taxonomies.
	$custom_taxonomies_attr = $attributes['customTaxonomies'] ?? [];
	$custom_taxonomies      = [];  // resolved: [ { slug, label, terms[] } ]

	if ( ! empty( $custom_taxonomies_attr ) ) {
		$show_categories = false; // custom taxs take over the filter panel

		$posts_of_type = get_posts( [
			'post_type'      => sanitize_key( $attributes['defaultPostType'] ?? 'post' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		] );

		foreach ( $custom_taxonomies_attr as $tax_config ) {
			$tax_slug  = sanitize_key( $tax_config['slug'] ?? '' );
			$tax_label = sanitize_text_field( $tax_config['label'] ?? $tax_slug );

			if ( ! $tax_slug || ! taxonomy_exists( $tax_slug ) ) continue;

			$terms = [];
			if ( ! empty( $posts_of_type ) ) {
				$terms = get_terms( [
					'taxonomy'   => $tax_slug,
					'object_ids' => $posts_of_type,
					'hide_empty' => true,
					'orderby'    => 'name',
					'order'      => 'ASC',
				] );
				if ( is_wp_error( $terms ) ) $terms = [];
			}

			$custom_taxonomies[] = [
				'slug'  => $tax_slug,
				'label' => $tax_label,
				'terms' => $terms,
			];
		}
	}

	// The post type this filter bar is configured for.
	// Defaults to 'post' so the blog archive works without configuration.
	$default_post_type = sanitize_key( $attributes['defaultPostType'] ?? 'post' );

	// Archive auto-detect: on a CPT archive, if the block was left at the bare
	// 'post' default, target the archive's own post type instead — so dropping
	// the filter bar on a Case Studies / Webinars / etc. archive "just works"
	// without per-block configuration. A block explicitly set to another type
	// keeps its setting (we only override the un-configured default).
	if ( 'post' === $default_post_type && is_post_type_archive() ) {
		$archive_type = get_query_var( 'post_type' );
		if ( is_array( $archive_type ) ) {
			$archive_type = reset( $archive_type );
		}
		if ( is_string( $archive_type ) && '' !== $archive_type && post_type_exists( $archive_type ) ) {
			$default_post_type = sanitize_key( $archive_type );
		}
	}

	// Categories — filtered to only those used by the configured post type.
	// This means the newsroom filter shows press-article categories,
	// the blog shows post categories, etc.
	$categories = [];
	
	if ( $show_categories ) {
		$categories = [];
		$solutions_parent = get_term_by( 'slug', 'solutions', 'category' );
	
		// Post types whose categories are scoped under the 'solutions' parent term.
		$solution_scoped_post_types = [ 'faq', 'testimonials' ];
		$use_solution_scope = $solutions_parent
			&& in_array( $default_post_type, $solution_scoped_post_types, true );
	
		if ( $use_solution_scope ) {
			$solution_term_ids = get_terms( [
				'taxonomy'   => 'category',
				'parent'     => $solutions_parent->term_id,
				'fields'     => 'ids',
				'hide_empty' => false,
			] );
	
			if ( ! empty( $solution_term_ids ) && ! is_wp_error( $solution_term_ids ) ) {
				$posts_of_type = get_posts( [
					'post_type'      => $default_post_type,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_status'    => 'publish',
				] );
	
				if ( ! empty( $posts_of_type ) ) {
					$categories = get_terms( [
						'taxonomy'   => 'category',
						'include'    => $solution_term_ids,
						'object_ids' => $posts_of_type,
						'hide_empty' => true,
						'orderby'    => 'name',
						'order'      => 'ASC',
					] );
					if ( is_wp_error( $categories ) ) $categories = [];
				}
			}
		} else {
			// For post types with their own flat category structure (e.g. press-article),
			// just return categories actually used by posts of this type.
			$posts_of_type = get_posts( [
				'post_type'      => $default_post_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
			] );
	
			if ( ! empty( $posts_of_type ) ) {
				$categories = get_terms( [
					'taxonomy'   => 'category',
					'object_ids' => $posts_of_type,
					'hide_empty' => true,
					'exclude'    => [ get_option( 'default_category' ) ],
					'orderby'    => 'name',
					'order'      => 'ASC',
				] );
				if ( is_wp_error( $categories ) ) $categories = [];
			}
		}
	}

	$has_custom_taxs = ! empty( $custom_taxonomies );
	$first_filter    = ! $show_categories && ! $show_post_types && ! $has_custom_taxs;
	$bar_top_class   = 'filter-bar-top' . ( $first_filter ? ' filter-bar-top--search-first' : '' );

	// Slim JSON for JS: just slug + label; JS builds state and listeners from this.
	$custom_tax_json = $has_custom_taxs
		? wp_json_encode( array_map( fn( $t ) => [ 'slug' => $t['slug'], 'label' => $t['label'] ], $custom_taxonomies ) )
		: '';

	ob_start();
	?>
	<div
		class="resource-filter-bar"
		data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>"
		<?php if ( $custom_tax_json ) : ?>
		data-custom-taxonomies="<?php echo esc_attr( $custom_tax_json ); ?>"
		<?php endif; ?>
		<?php if ( $show_post_types && count( $post_types ) > 1 ) : ?>
		data-auto-query="true"
		<?php endif; ?>
	>
			<div class="<?php echo esc_attr( $bar_top_class ); ?>">

			<?php foreach ( $custom_taxonomies as $tax ) : ?>
			<?php if ( ! empty( $tax['terms'] ) ) : ?>
			<div class="filter-dropdown" data-tax="<?php echo esc_attr( $tax['slug'] ); ?>">
				<button
					class="filter-dropdown-toggle"
					type="button"
					aria-expanded="false"
					aria-controls="filter-dropdown-panel-<?php echo esc_attr( $tax['slug'] ); ?>"
				>
					<span class="filter-dropdown-label"><?php echo esc_html( $tax['label'] ); ?></span>
					<span class="filter-count" hidden></span>
				</button>
				<div
					class="filter-dropdown-panel"
					id="filter-dropdown-panel-<?php echo esc_attr( $tax['slug'] ); ?>"
					hidden
				>
					<fieldset class="filter-group">
						<div class="filter-group-items">
							<?php foreach ( $tax['terms'] as $term ) : ?>
							<label class="filter-item">
								<input
									type="checkbox"
									name="<?php echo esc_attr( $tax['slug'] ); ?>"
									value="<?php echo esc_attr( $term->term_id ); ?>"
									data-slug="<?php echo esc_attr( $term->slug ); ?>"
									aria-label="<?php echo esc_attr( $term->name ); ?>"
								>
								<span class="filter-item-label"><?php echo esc_html( $term->name ); ?></span>
							</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				</div>
			</div>
			<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $show_post_types && ! empty( $post_types ) ) : ?>
			<div class="filter-dropdown" data-tax="post_type">
				<button
					class="filter-dropdown-toggle"
					type="button"
					aria-expanded="false"
					aria-controls="filter-dropdown-panel-post_type"
				>
					<span class="filter-dropdown-label">All resources</span>
					<span class="filter-count" hidden></span>
				</button>
				<div class="filter-dropdown-panel" id="filter-dropdown-panel-post_type" hidden>
					<fieldset class="filter-group">
						<div class="filter-group-items">
							<?php foreach ( $post_types as $pt ) : ?>
							<label class="filter-item">
								<input
									type="checkbox"
									name="post_type"
									value="<?php echo esc_attr( $pt['slug'] ); ?>"
									aria-label="<?php echo esc_attr( $pt['label'] ); ?>"
								>
								<span class="filter-item-label"><?php echo esc_html( $pt['label'] ); ?></span>
							</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $show_categories && ! empty( $categories ) ) : ?>
			<div class="filter-dropdown" data-tax="category">
				<button
					class="filter-dropdown-toggle"
					type="button"
					aria-expanded="false"
					aria-controls="filter-dropdown-panel-category"
				>
					<span class="filter-dropdown-label">All topics</span>
					<span class="filter-count" hidden></span>
				</button>
				<div class="filter-dropdown-panel" id="filter-dropdown-panel-category" hidden>
					<fieldset class="filter-group">
						<div class="filter-group-items">
							<?php foreach ( $categories as $cat ) : ?>
							<label class="filter-item">
								<input
									type="checkbox"
									name="category"
									value="<?php echo esc_attr( $cat->term_id ); ?>"
									data-slug="<?php echo esc_attr( $cat->slug ); ?>"
									aria-label="<?php echo esc_attr( $cat->name ); ?>"
								>
								<span class="filter-item-label"><?php echo esc_html( $cat->name ); ?></span>
							</label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $show_search ) : ?>
			<div class="filter-search-wrapper">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" id="search"><path fill="currentColor" d="M3.624,15a8.03,8.03,0,0,0,10.619.659l5.318,5.318a1,1,0,0,0,1.414-1.414l-5.318-5.318A8.04,8.04,0,0,0,3.624,3.624,8.042,8.042,0,0,0,3.624,15Zm1.414-9.96a6.043,6.043,0,1,1-1.77,4.274A6,6,0,0,1,5.038,5.038Z"></path></svg>
				<input
					class="filter-search"
					type="search"
					placeholder="Search"
					aria-label="Search resources"
					autocomplete="off"
				>
			</div>
			<?php endif; ?>

			<div class="is-style-button is-style-outline">
				<button class="filter-reset" type="button" hidden>
					Remove filters
				</button>
			</div>
			
			<?php if ( $show_sort ) : ?>
			<div class="filter-sort-wrapper">
				<svg viewBox="0 0 53.994804 62" id="up-and-down-arrows" version="1.1" width="53.994804" height="62" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"><defs id="defs1" /><path d="m 0.65740253,46.27 c -0.82,0.74 -0.88,2 -0.14,2.82 l 11.06000047,12.25 0.03,0.03 c 0.06,0.06 0.12,0.12 0.19,0.17 0.04,0.03 0.08,0.07 0.12,0.1 0.07,0.05 0.15,0.09 0.23,0.14 0.04,0.02 0.07,0.04 0.11,0.06 0.1,0.04 0.2,0.07 0.31,0.1 0.03,0.01 0.05,0.02 0.08,0.02 0.13,0.03 0.27,0.04 0.41,0.04 0.14,0 0.28,-0.02 0.41,-0.04 0.03,-0.01 0.05,-0.02 0.08,-0.02 0.1,-0.03 0.21,-0.06 0.31,-0.1 0.04,-0.02 0.07,-0.04 0.11,-0.06 0.08,-0.04 0.16,-0.08 0.23,-0.14 0.04,-0.03 0.08,-0.06 0.12,-0.1 0.07,-0.05 0.13,-0.11 0.19,-0.17 l 0.03,-0.03 11.06,-12.25 c 0.74,-0.82 0.68,-2.08 -0.14,-2.82 -0.82,-0.74 -2.09,-0.68 -2.82,0.14 l -7.57,8.39 V 16 c 0,-1.1 -0.9,-2 -2,-2 -1.1,0 -2,0.9 -2,2 V 54.8 L 3.4974025,46.41 c -0.75,-0.82 -2.02,-0.88 -2.83999997,-0.14 z M 40.937403,48 c 1.1,0 2,-0.9 2,-2 V 7.2 l 7.57,8.39 c 0.39,0.44 0.94,0.66 1.49,0.66 0.48,0 0.96,-0.17 1.34,-0.52 0.82,-0.74 0.88,-2 0.14,-2.82 l -11.05,-12.25 -0.03,-0.03 c -0.08,-0.09 -0.17,-0.16 -0.27,-0.24 -0.01,0 -0.01,-0.01 -0.02,-0.01 -0.33,-0.24 -0.73,-0.38 -1.17,-0.38 -0.44,0 -0.84,0.14 -1.17,0.38 -0.01,0 -0.01,0.01 -0.02,0.01 -0.1,0.07 -0.19,0.15 -0.27,0.24 l -0.03,0.03 -11.05,12.25 c -0.74,0.82 -0.68,2.08 0.14,2.82 0.82,0.74 2.08,0.67 2.82,-0.14 l 7.57,-8.39 V 46 c 0.01,1.1 0.91,2 2.01,2 z" id="path1" /></svg>
				<select class="filter-sort" aria-label="Sort results">
					<option value="">Sort by</option>
					<option value="date-desc">Latest</option>
					<option value="date-asc">Oldest</option>
					<option value="title-asc">Title A–Z</option>
					<option value="title-desc">Title Z–A</option>
				</select>
			</div>
			<?php endif; ?>
		</div>

		<div class="filter-active-tags" aria-live="polite" aria-label="Active filters"></div>

	</div>
	<?php
	return ob_get_clean();
}