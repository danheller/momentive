<?php
/**
 * Resource Filters block — register block JSON, editor script, and frontend javascript and CSS.
 */

add_action( 'init', function () {

	// Register the editor script manually so we can declare dependencies.
	wp_register_script(
		'momentive-resource-filters-editor',
		get_template_directory_uri() . '/blocks/resource-filters/editor.js',
		array(
			'wp-blocks',
			'wp-block-editor',
			'wp-components',
			'wp-element',
			'wp-i18n',
		),
		wp_get_theme()->get( 'Version' ),
		true
	);
	
	// Register the front-end script (no WP dependencies needed).
	wp_register_script(
		'momentive-resource-filters',
		get_template_directory_uri() . '/blocks/resource-filters/filters.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
	
	// Register the stylesheet.
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
    $show_categories = ! empty( $attributes['showCategories'] );
    $show_post_types = ! empty( $attributes['showPostTypes'] );
    $show_search     = ! empty( $attributes['showSearch'] );
    $show_sort       = ! empty( $attributes['showSort'] );
    $post_types      = array_map( 'sanitize_text_field', $attributes['postTypes'] ?? [] );

	// Categories — shared taxonomy across all post types.
	$categories = [];
	if ( $show_categories ) {
		$categories = get_terms( [
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'exclude'    => [ get_option( 'default_category' ) ],
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( is_wp_error( $categories ) ) {
			$categories = [];
		}
	}
	
	// Post types — built from block attribute list.
	// Each entry: [ 'slug' => 'post', 'label' => 'Blogs' ]
	// Populated in editor; stored in block attributes so no DB query needed here.
	
	ob_start();
	?>
	<div class="resource-filter-bar">
		<?php /* ── Filter toggle button (mobile) ── */ ?>
		<div class="filter-bar-top">
			<button
				class="filter-toggle"
				aria-expanded="false"
				aria-controls="filter-panel-<?php echo esc_attr( $query_id ); ?>"
				type="button"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
					<path d="M7.26452 18V18.8172L7.99213 18.4452L12.4631 16.1595L12.7355 16.0202V15.7143V11.7143C12.7355 11.3593 12.8719 10.835 13.1358 10.2682C13.3961 9.70937 13.7573 9.15905 14.1578 8.7496L18.8523 3.9496C19.1045 3.69167 19.2985 3.42196 19.4061 3.14571C19.5155 2.86479 19.5435 2.55103 19.4135 2.25573C19.2845 1.96251 19.0367 1.77172 18.7642 1.65925C18.4943 1.54786 18.1727 1.5 17.8242 1.5H2.17584C1.82731 1.5 1.50571 1.54786 1.23585 1.65925C0.96334 1.77172 0.715542 1.96251 0.586492 2.25573C0.456528 2.55103 0.484536 2.86479 0.593927 3.14571C0.701495 3.42196 0.895473 3.69167 1.14774 3.9496L5.84223 8.7496C6.23046 9.14656 6.59027 9.74135 6.85306 10.3997C7.1157 11.0576 7.26452 11.7366 7.26452 12.2857V18Z" fill="currentColor"/>
				</svg>
				<span class="filter-toggle-label">Filters</span>
				<span class="filter-count" hidden>0</span>
			</button>
	
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
			<select class="filter-sort" aria-label="Sort results">
				<option value="">Sort by</option>
				<option value="date-desc">Latest</option>
				<option value="date-asc">Oldest</option>
				<option value="title-asc">Title A–Z</option>
				<option value="title-desc">Title Z–A</option>
			</select>
			<?php endif; ?>
	
			<button class="filter-reset" type="button" hidden>
				Remove filters
			</button>
		</div>
	
		<?php /* ── Expandable panel ── */ ?>
		<div
			class="filter-panel"
			id="filter-panel-<?php echo esc_attr( $query_id ); ?>"
			hidden
		>
			<?php if ( $show_post_types && ! empty( $post_types ) ) : ?>
			<fieldset class="filter-group">
				<legend class="filter-group-toggle" aria-expanded="true">
					Resource type
					<svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
						<path d="M1.5 4L6 8L10.5 4" stroke="currentColor" stroke-width="1.5"/>
					</svg>
				</legend>
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
				<legend class="filter-group-toggle" aria-expanded="true">
					Topics
					<svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
						<path d="M1.5 4L6 8L10.5 4" stroke="currentColor" stroke-width="1.5"/>
					</svg>
				</legend>
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
	
		<?php /* ── Active filter tags ── */ ?>
		<div class="filter-active-tags" aria-live="polite" aria-label="Active filters"></div>
	
	</div><!-- .resource-filter-bar -->
	<?php
	return ob_get_clean();
}