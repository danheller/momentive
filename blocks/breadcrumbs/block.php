<?php
/**
 * Breadcrumbs block — register block JSON and editor script.
 */
 
add_action( 'init', function () {
    wp_register_script(
        'momentive-breadcrumbs-editor',
        get_template_directory_uri() . '/blocks/breadcrumbs/editor.js',
        [ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
        wp_get_theme()->get( 'Version' ),
        true
    );

    register_block_type(
        get_template_directory() . '/blocks/breadcrumbs/block.json',
        [
            'render_callback' => 'momentive_breadcrumbs_render',
            'editor_script'   => 'momentive-breadcrumbs-editor',
        ]
    );
} );

/**
 * Breadcrumbs block render callback.
 *
 * Breadcrumb label priority for the current item:
 *   1. ACF field 'breadcrumb_title' (if ACF active and field has a value)
 *   2. Post title / term name / post type label
 *
 */

function momentive_breadcrumbs_render( array $attributes ): string {

	// Not useful on static front page or outside the loop.
	if ( is_front_page() ) return '';
	
	$show_home = ! empty( $attributes['showHome'] );
	$home      = esc_html( $attributes['homeLabel'] ?? 'Home' );
	$sep       = esc_html( $attributes['separator']  ?? '›' );
	
	// Build crumbs as [ 'label' => string, 'url' => string|null ]
	// url = null means it's the current (unlinked) item.
	$crumbs = [];
	
	if ( $show_home ) {
		$crumbs[] = [
			'label' => $home,
			'url'   => home_url( '/' ),
		];
	}
	
	// ── Single post / page ────────────────────────────────────────────────────
	if ( is_singular() ) {
		$post      = get_queried_object();
		$post_type = get_post_type_object( $post->post_type );
	
		// Post type archive link (e.g. "Blog" → /blog/)
		if ( $post_type && $post_type->has_archive ) {
			$crumbs[] = [
				'label' => $post_type->labels->name,
				'url'   => get_post_type_archive_link( $post->post_type ),
			];
		} elseif ( $post->post_type === 'post' ) {
			// Standard posts: link to the blog page if one is set.
			$blog_page_id = get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$crumbs[] = [
					'label' => get_the_title( $blog_page_id ),
					'url'   => get_permalink( $blog_page_id ),
				];
			}
		}
	
		// For hierarchical post types (pages, hierarchical CPTs),
		// walk up the ancestor chain.
		if ( is_post_type_hierarchical( $post->post_type ) && $post->post_parent ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$crumbs[] = [
					'label' => get_the_title( $ancestor_id ),
					'url'   => get_permalink( $ancestor_id ),
				];
			}
		}
	
		// Current post — use ACF breadcrumb_title if available, else post title.
		$label = momentive_breadcrumb_label( $post->ID );
	
		$crumbs[] = [
			'label' => $label,
			'url'   => null, // current item — not linked
		];
	}
	
	// ── Category / tag / taxonomy archive ────────────────────────────────────
	elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
	
		// Parent terms
		if ( $term->parent ) {
			$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				$crumbs[] = [
					'label' => $ancestor->name,
					'url'   => get_term_link( $ancestor ),
				];
			}
		}
	
		$crumbs[] = [
			'label' => $term->name,
			'url'   => null,
		];
	}
	
	// ── Post type archive ─────────────────────────────────────────────────────
	elseif ( is_post_type_archive() ) {
		$crumbs[] = [
			'label' => post_type_archive_title( '', false ),
			'url'   => null,
		];
	}
	
	// ── Author archive ────────────────────────────────────────────────────────
	elseif ( is_author() ) {
		$crumbs[] = [
			'label' => get_queried_object()->display_name,
			'url'   => null,
		];
	}
	
	// ── Search results ────────────────────────────────────────────────────────
	elseif ( is_search() ) {
		$crumbs[] = [
			'label' => sprintf( 'Search: "%s"', get_search_query() ),
			'url'   => null,
		];
	}
	
	// ── 404 ───────────────────────────────────────────────────────────────────
	elseif ( is_404() ) {
		$crumbs[] = [
			'label' => 'Page not found',
			'url'   => null,
		];
	}
	
	if ( empty( $crumbs ) ) return '';
	
	// ── Render ────────────────────────────────────────────────────────────────
	
	$last_index = count( $crumbs ) - 1;
	$items_html = '';
	
	foreach ( $crumbs as $index => $crumb ) {
		$is_current = ( $index === $last_index );
		$label      = esc_html( $crumb['label'] );
	
		if ( $is_current ) {
			// Current page — aria-current, not linked.
			$items_html .= sprintf(
				'<li class="breadcrumb-item breadcrumb-item--current">
					<span aria-current="page">%s</span>
				</li>',
				$label
			);
		} else {
			$items_html .= sprintf(
				'<li class="breadcrumb-item">
					<a href="%s">%s</a>
					<span class="breadcrumb-sep" aria-hidden="true">%s</span>
				</li>',
				esc_url( $crumb['url'] ),
				$label,
				$sep
			);
		}
	}
	
	return sprintf(
		'<nav class="wp-block-momentive-breadcrumbs breadcrumbs" aria-label="%s">
			<ol class="breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">
				%s
			</ol>
		</nav>',
		esc_attr__( 'Breadcrumb', 'momentive' ),
		$items_html
	);
}

/**
 * Returns the breadcrumb label for a post.
 * Uses ACF field 'breadcrumb_title' if available and non-empty,
 * otherwise falls back to the post title.
 */
function momentive_breadcrumb_label( int $post_id ): string {
    if ( function_exists( 'get_field' ) ) {
        $short = get_field( 'breadcrumb_title', $post_id );
        if ( $short ) {
            return $short;
        }
    }
    return get_the_title( $post_id );
}
