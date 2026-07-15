<?php

/**
 * Custom Post Type: Solutions
 */

function momentive_solutions_setup() {
	$labels = array(
		'name'                  => _x( 'Solutions', 'Post type general name', 'momentive' ),
		'singular_name'         => _x( 'Solution', 'Post type singular name', 'momentive' ),
		'menu_name'             => _x( 'Solutions', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'        => _x( 'Solution', 'Add New on Toolbar', 'momentive' ),
		'add_new'               => __( 'Add New', 'momentive' ),
		'add_new_item'          => __( 'Add New Solution', 'momentive' ),
		'new_item'              => __( 'New Solution', 'momentive' ),
		'edit_item'             => __( 'Edit Solution', 'momentive' ),
		'view_item'             => __( 'View Solution', 'momentive' ),
		'all_items'             => __( 'All Solutions', 'momentive' ),
		'search_items'          => __( 'Search Solutions', 'momentive' ),
		'parent_item_colon'     => __( 'Parent Solutions:', 'momentive' ),
		'not_found'             => __( 'No solutions found.', 'momentive' ),
		'not_found_in_trash'    => __( 'No solutions found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => true, // Makes it behave like pages
		'menu_icon'          => 'dashicons-portfolio',
		// Gutenberg + FSE friendly
		'show_in_rest'       => true,
		// Supports similar to Pages
		'supports'           => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'page-attributes', // Enables parent/child + order
			'revisions',
		),
		// URL structure
		'rewrite'            => array(
			'slug'       => 'solutions',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => 'solutions',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,

		// Capabilities (page-like behavior)
		'capability_type'    => 'page',

		// Template support (important for FSE )
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'solutions', $args );
	
	// Register Custom Taxonomy: Solution Tags
	$labels = array(
		'name'                       => _x( 'Solution Tags', 'taxonomy general name', 'momentive' ),
		'singular_name'              => _x( 'Solution Tag', 'taxonomy singular name', 'momentive' ),
		'search_items'               => __( 'Search Solution Tags', 'momentive' ),
		'popular_items'              => __( 'Popular Solution Tags', 'momentive' ),
		'all_items'                  => __( 'All Solution Tags', 'momentive' ),
		'edit_item'                  => __( 'Edit Solution Tag', 'momentive' ),
		'update_item'                => __( 'Update Solution Tag', 'momentive' ),
		'add_new_item'               => __( 'Add New Solution Tag', 'momentive' ),
		'new_item_name'              => __( 'New Solution Tag Name', 'momentive' ),
		'separate_items_with_commas' => __( 'Separate tags with commas', 'momentive' ),
		'add_or_remove_items'        => __( 'Add or remove tags', 'momentive' ),
		'choose_from_most_used'      => __( 'Choose from the most used tags', 'momentive' ),
		'menu_name'                  => __( 'Solution Tags', 'momentive' ),
	);

	$args = array(
		'hierarchical'          => false, // This makes it tag-like
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'show_in_rest'          => true, // Required for block editor / FSE
		'public'                => true,
		// URL structure
		'rewrite'               => array(
			'slug' => 'solution-tag',
		),
	);
	register_taxonomy( 'solution_tag', array( 'solutions' ), $args );
}
add_action( 'init', 'momentive_solutions_setup' );


/*
 * For solution singular posts, apply the accent color as a root-level variable.
 * If the solution is a child page, use the parent solution's accent color.
 */

add_action( 'wp_head', function() {
	if ( ! is_singular( 'solutions' ) ) return;

	$post_id = get_the_ID();
	$parent_id = wp_get_post_parent_id( $post_id );

	// If this is a child post, use the parent's accent color
	$source_id = $parent_id ? $parent_id : $post_id;
	$color = get_field( 'accent_color', $source_id );

	if ( ! $color ) return;
	echo '<style>body { --page-accent-color: ' . esc_attr( $color ) . '; }</style>';
} );

add_filter( 'acf/prepare_field/name=accent_color', function( $field ) {
	// Only run in the admin editor context
	if ( ! is_admin() ) return $field;

	$post_id = acf_get_valid_post_id();
	if ( ! $post_id ) return $field;

	$post = get_post( $post_id );
	if ( $post && $post->post_parent ) {
		return false; // Returning false hides the field entirely
	}

	return $field;
} );

/*
 * Add editor javascript to show/hide accent color field based on whether there is a parent post.
 */

add_action( 'enqueue_block_editor_assets', function() {
	$screen = get_current_screen();
	if ( ! $screen ) return;

	$post_type = $screen->post_type;

	if ( 'solutions' != $post_type ) return;

	wp_enqueue_script(
		'momentive-solutions-editor',
		get_theme_file_uri( 'assets/js/solutions-editor.js' ),
		[ 'wp-data' ],
		filemtime( get_theme_file_path( 'assets/js/solutions-editor.js' ) ),
		true
	);
} );


// ---------------------------------------------------------------------------
// Admin column: show Accent Color swatch in the posts list
// ---------------------------------------------------------------------------
//
// Child solutions inherit accent_color from their parent (the field is
// hidden on child posts in the editor — see acf/prepare_field/name=accent_color
// above), so this column resolves the same way wp_head does: walk up to the
// parent when the post has one.

add_filter( 'manage_solutions_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['accent_color'] = __( 'Accent Color', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_solutions_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column !== 'accent_color' ) return;

	$parent_id = wp_get_post_parent_id( $post_id );
	$source_id = $parent_id ? $parent_id : $post_id;
	$color     = get_field( 'accent_color', $source_id );

	if ( ! $color ) {
		echo '<span style="color:#999">—</span>';
		return;
	}

	printf(
		'<span style="display:inline-flex;align-items:center;gap:6px;">
			<span style="display:inline-block;width:16px;height:16px;border-radius:3px;border:1px solid rgba(0,0,0,0.15);background:%s;"></span>
			<code>%s</code>
		</span>',
		esc_attr( $color ),
		esc_html( $color )
	);
}, 10, 2 );


/**
 * Resolve the accent color for a category term by hopping through
 * its related Solution post (ACF relationship field).
 *
 * Falls back to the term's own tag_color field if no solution is linked,
 * so existing data continues to work during any transition period.
 */
function get_solution_color_for_term( int $term_id ): string {
	static $cache = [];
	if ( isset( $cache[ $term_id ] ) ) return $cache[ $term_id ];

	$solution = get_field( 'solution_relationship', 'category_' . $term_id );
	$post     = is_array( $solution ) ? ( $solution[0] ?? null ) : $solution;
	$color    = $post ? (string) get_field( 'accent_color', $post->ID ) : '';

	// Fallback: read tag_color directly off the term (remove once all terms have a linked solution).
	if ( ! $color ) {
		$color = (string) get_field( 'tag_color', 'category_' . $term_id );
	}

	$cache[ $term_id ] = $color;
	return $cache[ $term_id ];
}

/**
 * Resolve the category term(s) linked to a given Solution post.
 *
 * @param int $solution_id Post ID of the Solution post.
 * @return int[] Matching category term IDs (usually 0 or 1).
 */
function get_terms_for_solution( int $solution_id ): array {
	$matches = [];
	foreach ( momentive_get_solution_term_map() as $term_id => $mapped_solution_id ) {
		if ( $mapped_solution_id === $solution_id ) {
			$matches[] = $term_id;
		}
	}
	return $matches;
}

/**
 * Solution ⇄ category term lookups.
 *
 * Both functions below share one underlying cache, built by walking the
 * category terms that are direct children of "Solutions" once per request
 * and recording each one's related_solution link. There's no native
 * taxonomy relationship to query directly (related_solution is a post_object
 * field on the term, not a taxonomy term itself), so this loop-and-cache
 * approach is the simplest way to support lookups in either direction
 * without re-querying.
 */

/**
 * Internal: build (once) and return the term → solution map.
 *
 * @return array<int,int> term_id => solution post ID
 */
function momentive_get_solution_term_map(): array {
	static $map = null;

	if ( $map !== null ) {
		return $map;
	}

	$map = [];

	$parent = get_term_by( 'slug', 'solutions', 'category' );
	$terms  = $parent
		? get_terms( [
			'taxonomy'   => 'category',
			'parent'     => $parent->term_id,
			'hide_empty' => false,
		] )
		: [];

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$related = get_field( 'related_solution', 'category_' . $term->term_id );
			$post    = is_array( $related ) ? ( $related[0] ?? null ) : $related;
			$id      = $post ? (int) ( is_object( $post ) ? $post->ID : $post ) : 0;

			if ( $id ) {
				$map[ (int) $term->term_id ] = $id;
			}
		}
	}

	return $map;
}


/**
 * Every Solution post that has at least one category term linked to it —
 * i.e. every Solution with potential products to show. Used to drive the
 * tab list without requiring a manually curated field.
 *
 * @return int[] Solution post IDs, deduplicated, unordered (sort by
 *               solution_order at the call site).
 */
function get_solutions_with_products(): array {
	return array_values( array_unique( array_values( momentive_get_solution_term_map() ) ) );
}

/**
 * Build a category term <a> with the per-term --solution CSS variable.
 *
 * @param WP_Term $term The category term.
 * @return string Anchor HTML.
 */
function momentive_term_link_with_color( WP_Term $term ): string {
	$style = '';
	$color = get_solution_color_for_term( $term->term_id );
	if ( $color ) {
		$color = sanitize_hex_color( $color );
		if ( $color ) {
			$style = sprintf( ' style="--solution:%s"', esc_attr( $color ) );
		}
	}

	return sprintf(
		'<a href="%s" rel="tag"%s>%s</a>',
		esc_url( get_category_link( $term->term_id ) ),
		$style,
		esc_html( $term->name )
	);
}


/**
 * Inject per-term --solution CSS variable onto each <a> in
 * the core/post-terms block (category taxonomy only).
 *
 * Requires: ACF field "tag_color" registered on the category taxonomy.
 */
add_filter( 'render_block', function ( string $html, array $block, WP_Block $instance ): string {

	// Only touch post-terms blocks showing the category taxonomy.
	if ( 'core/post-terms' !== ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	if ( 'category' !== ( $block['attrs']['term'] ?? '' ) ) {
		return $html;
	}

	// Resolve the post ID from block context (works inside Query Loop).
	$post_id = $instance->context['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return $html;
	}

	$terms = get_the_terms( $post_id, 'category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $html;
	}

	foreach ( $terms as $term ) {
		// ACF stores term meta with the "category_" prefix on term IDs.
		$color = get_solution_color_for_term( $term->term_id );
		if ( ! $color ) {
			continue;
		}

		$color = esc_attr( sanitize_hex_color( $color ) );
		$slug  = preg_quote( $term->slug, '/' );

		// Match the <a> whose href contains this category's slug segment.
		// The slug always appears as /slug/ in WP category permalinks.
		$html = preg_replace(
			'/(<a\b)([^>]*href=["\'][^"\']*\/' . $slug . '\/["\'][^>]*)(>)/',
			'$1$2 style="--solution:' . $color . '"$3',
			$html
		);
	}

	return $html;
}, 10, 3 );

add_action( 'rest_api_init', function () {
	register_rest_field( 'category', 'tag_color', [
		'get_callback' => function ( $term ) {
			$color = get_solution_color_for_term( (int) $term['id'] );
			return $color ? sanitize_hex_color( $color ) : null;
		},
		'schema' => [
			'description' => 'Hex color for category tag.',
			'type'        => [ 'string', 'null' ],
			'context'     => [ 'view', 'embed' ],
		],
	] );
} );


/* 
 * For new 'solution' posts, use "patterns/solution-content.php" pattern. 
 */

add_action( 'init', function() {
	$cpt = get_post_type_object( 'solutions' );
	if ( ! $cpt ) return;
	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/solution-content' );
	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );


/*
 * Enqueue solutions CSS conditionally
 */
 
add_action( 'enqueue_block_assets', function() {
	if ( ! momentive_content_has_block( 'acf/solution-slide' ) ) return;

	wp_enqueue_style(
		'momentive-solutions',
		get_template_directory_uri() . '/assets/css/solutions.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );

/*
 * Populate "icons" select list with options from the icon system
 */

add_action( 'acf/render_field/name=solution_icon', function( $field ) {
	$slug = $field['value'];
	// Only render preview for slug-like values, not legacy attachment IDs
	if ( ! $slug || is_numeric( $slug ) ) return;
	echo '<div style="margin-top:8px; width:48px; height:48px;">';
	momentive_output_svg_symbols( [ $slug ] );
	echo '<svg style="width:100%;height:100%;"><use href="#icon-' . esc_attr( $slug ) . '"></use></svg>';
	echo '</div>';
} );

add_filter( 'acf/load_field/name=solution_icon', function( $field ) {
	$field['choices'] = array_merge(
		[ '' => '— None —' ],
		momentive_get_available_icons()
	);
	return $field;
} );
