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
		array(),
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


function momentive_resource_filters_render( array $attributes, string $content ): string {
	$show_categories  = ! empty( $attributes['showCategories'] );
	$show_post_types  = ! empty( $attributes['showPostTypes'] );
	$show_search      = ! empty( $attributes['showSearch'] );
	$show_sort        = ! empty( $attributes['showSort'] );
	$post_types       = array_map( 'sanitize_text_field', $attributes['postTypes'] ?? [] );

	// The post type this filter bar is configured for.
	// Defaults to 'post' so the blog archive works without configuration.
	$default_post_type = sanitize_key( $attributes['defaultPostType'] ?? 'post' );

	// Categories — filtered to only those used by the configured post type.
	// This means the newsroom filter shows press-article categories,
	// the blog shows post categories, etc.
	$categories = [];
	if ( $show_categories ) {
		$query_args = [
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'exclude'    => [ get_option( 'default_category' ) ],
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		// When a specific non-'post' post type is set, restrict categories
		// to those that have at least one post of that type.
		if ( $default_post_type && $default_post_type !== 'post' ) {
			$posts_of_type = get_posts( [
				'post_type'      => $default_post_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
			] );

			if ( ! empty( $posts_of_type ) ) {
				$query_args['object_ids'] = $posts_of_type;
			} else {
				// No posts of this type — return no categories.
				$categories = [];
				$show_categories = false;
			}
		}

		if ( $show_categories ) {
			$categories = get_terms( $query_args );
			if ( is_wp_error( $categories ) ) {
				$categories = [];
			}
		}
	}

	ob_start();
	?>
	<div
		class="resource-filter-bar"
		data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>"
	>
		<div class="filter-bar-top">
			<button
				class="filter-toggle"
				aria-expanded="false"
				aria-controls="filter-panel"
				type="button"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
					<path d="M7.26452 18V18.8172L7.99213 18.4452L12.4631 16.1595L12.7355 16.0202V15.7143V11.7143C12.7355 11.3593 12.8719 10.835 13.1358 10.2682C13.3961 9.70937 13.7573 9.15905 14.1578 8.7496L18.8523 3.9496C19.1045 3.69167 19.2985 3.42196 19.4061 3.14571C19.5155 2.86479 19.5435 2.55103 19.4135 2.25573C19.2845 1.96251 19.0367 1.77172 18.7642 1.65925C18.4943 1.54786 18.1727 1.5 17.8242 1.5H2.17584C1.82731 1.5 1.50571 1.54786 1.23585 1.65925C0.96334 1.77172 0.715542 1.96251 0.586492 2.25573C0.456528 2.55103 0.484536 2.86479 0.593927 3.14571C0.701495 3.42196 0.895473 3.69167 1.14774 3.9496L5.84223 8.7496C6.23046 9.14656 6.59027 9.74135 6.85306 10.3997C7.1157 11.0576 7.26452 11.7366 7.26452 12.2857V18Z" fill="currentColor"/>
				</svg>
				<span class="filter-toggle-label">Filters</span>
				<span class="filter-count" hidden>0</span>
			</button>
			<div class="is-style-button is-style-outline">
				<button class="filter-reset" type="button" hidden>
					Remove filters
				</button>
			</div>

			<?php if ( $show_search ) : ?>
			<div class="filter-search-wrapper">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
				</svg>
				<input
					class="filter-search"
					type="search"
					placeholder="Search"
					aria-label="Search resources"
					autocomplete="off"
				>
			</div>
			<?php endif; ?>

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

		<div class="filter-panel" id="filter-panel" hidden>

			<?php if ( $show_post_types && ! empty( $post_types ) ) : ?>
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
			<?php endif; ?>

			<?php if ( $show_categories && ! empty( $categories ) ) : ?>
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
			<?php endif; ?>

		</div>

		<div class="filter-active-tags" aria-live="polite" aria-label="Active filters"></div>

	</div>
	<?php
	return ob_get_clean();
}