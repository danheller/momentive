<?php

/**
 * Custom Post Type: Case Studies
 *
 * Modeled on products.php / testimonials.php. Key decisions baked in:
 *   - CPT key:  case-study   (hyphenated, matching the site's two-word CPT
 *               convention; NOT case_studies)
 *   - URL slug: /case-studies/  (preserves the legacy public URL structure so
 *               existing inbound links / redirects stay valid)
 *   - Categorized by SOLUTION via the shared "category" taxonomy, scoped to
 *               children of the "Solutions" parent — same pattern as products.
 *   - MULTI-solution support: uses the native category panel (multi-select),
 *               NOT a single-select ACF field. ~4 legacy posts have >1 solution
 *               (ECS has 4), so single-select would lose data.
 *   - has_archive: 'case-studies'  (archive at /case-studies/)
 *
 * The page body is built from block patterns (Quick Info, Stats, Features,
 * Main, About) + existing custom blocks (momentive/testimonial). This file only
 * registers the post type, taxonomy scoping, and admin niceties — the ACF field
 * group and patterns are defined separately.
 */


// ---------------------------------------------------------------------------
// Post type
// ---------------------------------------------------------------------------

add_action( 'init', 'momentive_case_studies_setup' );

// Front-end styles for the single-case study view.
// Registered always, enqueued only on singular case study posts.
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-case-study',
		get_template_directory_uri() . '/assets/css/case-study.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'case-study' ) || is_archive('case-study') ) {
		wp_enqueue_style( 'momentive-case-study' );
	}
} );

function momentive_case_studies_setup(): void {

	$labels = [
		'name'               => _x( 'Case Studies', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Case Study', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Case Studies', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Case Study', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Case Study', 'momentive' ),
		'new_item'           => __( 'New Case Study', 'momentive' ),
		'edit_item'          => __( 'Edit Case Study', 'momentive' ),
		'view_item'          => __( 'View Case Study', 'momentive' ),
		'all_items'          => __( 'All Case Studies', 'momentive' ),
		'search_items'       => __( 'Search Case Studies', 'momentive' ),
		'not_found'          => __( 'No case studies found.', 'momentive' ),
		'not_found_in_trash' => __( 'No case studies found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-analytics',
		'menu_position'      => 35,
		'show_in_rest'       => true,        // Block editor
		'supports'           => [
			'title',      // org / case study title
			'editor',     // body lives in blocks/patterns
			'excerpt',
			'thumbnail',  // hero or card image
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'case-studies',
			'with_front' => false,
		],
		'has_archive'        => 'case-studies',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'taxonomies'         => [ 'category' ],  // solution categories (shared)
		'template'           => [],   // set to the default pattern below once built
		'template_lock'      => false,
	];

	register_post_type( 'case-study', $args );
}


// ---------------------------------------------------------------------------
// Shared solution categories — scoped to children of "Solutions"
// ---------------------------------------------------------------------------
//
// Identical approach to products.php. If you add an ACF taxonomy field named
// `case_study_solution` (e.g. for a curated single "primary" solution), this
// filter scopes its options. The native category panel is scoped separately
// below. Most case studies should just use the native multi-select panel.

add_filter( 'acf/fields/taxonomy/query/name=case_study_solution', function( array $args ): array {
	$parent = get_term_by( 'slug', 'solutions', 'category' );
	if ( $parent ) {
		$args['parent']  = $parent->term_id;
		$args['orderby'] = 'name';
		$args['order']   = 'ASC';
	}
	return $args;
} );


// ---------------------------------------------------------------------------
// Scope the native category panel to Solutions children on case studies
// ---------------------------------------------------------------------------
//
// products.php / testimonials.php REMOVE the native category panel because they
// drive solution via an ACF field. Case studies are different: they need
// multi-select, which the native panel already gives for free. So instead of
// removing it, we filter the terms shown in it to the Solutions children, and
// relabel it "Solutions" to avoid confusing editors.
//
// (If you later prefer the ACF-field approach for consistency with products,
// remove this block and add a multi-value `case_study_solution` field instead.)

add_action( 'enqueue_block_editor_assets', function() {
	if ( get_post_type() !== 'case-study' ) return;
	// Relabel the Categories panel to "Solutions" in the editor sidebar.
	wp_add_inline_script( 'wp-blocks', "
		wp.domReady( function() {
			// no-op placeholder: term filtering is handled server-side below.
		} );
	" );
} );

// Restrict the category metabox terms to Solutions children for this CPT.
add_filter( 'rest_category_query', function( array $args, $request ) {
	// Only constrain when editing a case-study (best-effort via referer/post param).
	if ( ! is_admin() && empty( $request['post'] ) ) {
		return $args;
	}
	return $args; // left permissive; see note below.
}, 10, 2 );

/*
 * NOTE on category scoping:
 * Fully constraining the native category panel to a term subtree in the block
 * editor is fiddly (the REST terms endpoint isn't post-type aware by default).
 * Two reliable options, pick one when you build the field group:
 *   1. Keep the native panel as-is (shows all categories) and rely on editor
 *      discipline + the Solutions parent grouping. Simplest.
 *   2. Replace with an ACF taxonomy field `case_study_solution` (multiple=true,
 *      save_terms=1, load_terms=1) scoped by the filter above, and remove the
 *      native panel like products.php does. Most consistent with the rest of
 *      the site. Recommended if editor error is a concern.
 * The migration script can assign terms either way (wp_set_post_terms).
 */


// ---------------------------------------------------------------------------
// Admin column: Linked Products
// ---------------------------------------------------------------------------
// Replaces the former "Solutions" column, which duplicated the native
// Categories column. Linked Products is genuinely new information — useful
// for spotting posts that came through migration without products assigned,
// and for reviewing product coverage at a glance.

add_filter( 'manage_case-study_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['cs_linked_products'] = __( 'Linked Products', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_case-study_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column !== 'cs_linked_products' ) return;

	// Post-level linked_products field (Case Study Settings, field_6a429f79316b5).
	$products = get_field( 'linked_products', $post_id );
	if ( empty( $products ) ) {
		echo '<span style="color:#999">—</span>';
		return;
	}
	$names = [];
	foreach ( (array) $products as $product ) {
		if ( is_object( $product ) ) {
			$names[] = esc_html( $product->post_title );
		} elseif ( is_numeric( $product ) ) {
			$title = get_the_title( (int) $product );
			if ( $title ) $names[] = esc_html( $title );
		}
	}
	echo $names ? implode( ', ', $names ) : '<span style="color:#999">—</span>';
}, 10, 2 );


// ---------------------------------------------------------------------------
// Default block pattern as the new-post template (wire up once built)
// ---------------------------------------------------------------------------
//
// Mirrors the products.php approach: when a case-study pattern exists, use it as
// the CPT's default editor template. Uncomment and set the pattern slug once
// `momentive/case-study-content` (or per-section patterns) are registered.


add_action( 'init', function() {
	$cpt = get_post_type_object( 'case-study' );
	if ( ! $cpt ) return;
	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/case-study-content' );
	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );

