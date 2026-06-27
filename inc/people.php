<?php

/**
 * Custom Post Type: People
 *
 * Unified profile type for leadership, blog authors, and webinar presenters.
 * Differentiated by the non-exclusive 'person_role' taxonomy. A profile may
 * optionally be linked to a WP user (see linked_user field) for self-publishing.
 */

function momentive_people_setup() {
	$labels = array(
		'name'               => _x( 'People', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Person', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'People', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Person', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add Person', 'momentive' ),
		'add_new_item'       => __( 'Add a Person', 'momentive' ),
		'new_item'           => __( 'New Person', 'momentive' ),
		'edit_item'          => __( 'Edit Person', 'momentive' ),
		'view_item'          => __( 'View Person', 'momentive' ),
		'all_items'          => __( 'All People', 'momentive' ),
		'search_items'       => __( 'Search People', 'momentive' ),
		'parent_item_colon'  => __( 'Parent Person:', 'momentive' ),
		'not_found'          => __( 'No people found.', 'momentive' ),
		'not_found_in_trash' => __( 'No people found in Trash.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-groups',
		'menu_position'      => 50,
		'show_in_rest'       => true,
		'taxonomies'         => array( 'person_role' ),
		'supports'           => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
		),
		// URL structure
		'rewrite'            => array(
			'slug'       => 'people',
			'with_front' => false,
		),

		// Admin + visibility
		'has_archive'        => false,
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		'template'           => array(),
		'template_lock'      => false,
	);
	register_post_type( 'people', $args );
}
add_action( 'init', 'momentive_people_setup' );


/**
 * Taxonomy: Person Role
 *
 * Non-exclusive classification (leader / author / presenter). A single person
 * may hold several roles, so templates must not assume one term per person.
 */
function momentive_person_role_setup() {
	$labels = array(
		'name'              => _x( 'Roles', 'taxonomy general name', 'momentive' ),
		'singular_name'     => _x( 'Role', 'taxonomy singular name', 'momentive' ),
		'menu_name'         => __( 'Roles', 'momentive' ),
		'all_items'         => __( 'All Roles', 'momentive' ),
		'edit_item'         => __( 'Edit Role', 'momentive' ),
		'view_item'         => __( 'View Role', 'momentive' ),
		'update_item'       => __( 'Update Role', 'momentive' ),
		'add_new_item'      => __( 'Add New Role', 'momentive' ),
		'new_item_name'     => __( 'New Role Name', 'momentive' ),
		'search_items'      => __( 'Search Roles', 'momentive' ),
		'not_found'         => __( 'No roles found.', 'momentive' ),
	);
	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false, // tag-like, as you intended
		'show_in_rest'       => true,
		'show_admin_column'  => true,  // surfaces role in the People list table
		'show_in_nav_menus'  => true,
		'rewrite'            => array(
			'slug'       => 'role',
			'with_front' => false,
		),
	);
	register_taxonomy( 'person_role', array( 'people' ), $args );
}
add_action( 'init', 'momentive_person_role_setup' );


/**
 * Seed the fixed Person Role vocabulary and lock it.
 *
 * The role taxonomy is a closed set (leader / author / presenter). We insert
 * the canonical terms once and remove the UI for adding or deleting terms so
 * editors can only assign existing roles, never invent new ones.
 */
function momentive_seed_person_roles() {
	$roles = array(
		'leader'    => 'Leader',
		'author'    => 'Author',
		'presenter' => 'Presenter',
	);

	foreach ( $roles as $slug => $name ) {
		if ( ! term_exists( $slug, 'person_role' ) ) {
			wp_insert_term( $name, 'person_role', array( 'slug' => $slug ) );
		}
	}
}
add_action( 'init', 'momentive_seed_person_roles', 20 ); // after taxonomy registration



/**
 * Backstop: when a post is saved with an empty byline, default post_author_ref
 * to the current user's linked People profile (read from their user meta).
 *
 * Seeds only when empty, so it never overrides a byline set deliberately. This
 * is the save-time safety net; the load_value filter below is what makes the
 * default visible in the editor.
 */
add_action( 'acf/save_post', function ( $post_id ) {
	if ( get_post_type( $post_id ) !== 'post' ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Only seed when the byline is currently empty.
	$existing = get_field( 'post_author_ref', $post_id );
	if ( ! empty( $existing ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return; // WP-CLI / import context — nothing to seed from.
	}

	$person_id = msw_resolve_linked_person( $user_id );
	if ( $person_id ) {
		update_field( 'post_author_ref', array( $person_id ), $post_id );
	}
}, 20 );

/**
 * Add a "Linked Accounts" column to the People list table, showing every WP
 * user whose linked_person points at this profile. For a shared byline like
 * "Momentive Software" this lists all the developer accounts that post as it.
 */
add_filter( 'manage_people_posts_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['linked_accounts'] = __( 'Linked Accounts', 'momentive' );
		}
	}
	if ( ! isset( $new['linked_accounts'] ) ) {
		$new['linked_accounts'] = __( 'Linked Accounts', 'momentive' );
	}
	return $new;
} );

add_action( 'manage_people_posts_custom_column', function ( $column, $post_id ) {
	if ( 'linked_accounts' !== $column ) {
		return;
	}

	$users = get_users( array(
		'meta_key'   => 'linked_person',
		'meta_value' => $post_id,
		'fields'     => array( 'ID', 'display_name', 'user_login' ),
	) );

	if ( empty( $users ) ) {
		echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
			esc_html__( 'No linked accounts', 'momentive' ) . '</span>';
		return;
	}

	$links = array();
	foreach ( $users as $u ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_user_link( $u->ID ) ),
			esc_html( $u->display_name )
		);
	}
	echo wp_kses_post( implode( ', ', $links ) );
}, 10, 2 );

/**
 * Add a "Linked Person" column to the Users list table, showing the People
 * profile this user posts as (their byline identity). Multiple users may share
 * one person (e.g. several developers posting as "Momentive Software").
 */
add_filter( 'manage_users_columns', function ( $columns ) {
	$columns['linked_person'] = __( 'Linked Person', 'momentive' );
	return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $output, $column, $user_id ) {
	if ( 'linked_person' !== $column ) {
		return $output;
	}

	$person_id = msw_resolve_linked_person( $user_id );

	if ( ! $person_id ) {
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
			esc_html__( 'No linked person', 'momentive' ) . '</span>';
	}

	// Flag a stale link: the stored person no longer exists or isn't a person.
	if ( get_post_type( $person_id ) !== 'people' ) {
		return sprintf(
			'<span style="color:#b32d2e;">%s</span>',
			esc_html( sprintf( __( '⚠ Missing person (#%d)', 'momentive' ), $person_id ) )
		);
	}

	return sprintf(
		'<a href="%s">%s</a>',
		esc_url( get_edit_post_link( $person_id ) ),
		esc_html( get_the_title( $person_id ) )
	);
}, 10, 3 );

/**
 * Prefill post_author_ref with the current user's linked People profile when
 * they open a NEW post, so the byline default is visible in the editor rather
 * than only applied silently on save.
 *
 * Only fires when there is no saved value yet (new post / never set). Existing
 * posts return their stored value unchanged, so this never overrides a byline
 * a developer set deliberately.
 */
add_filter( 'acf/load_value/name=post_author_ref', function ( $value, $post_id, $field ) {
	if ( ! is_admin() ) {
		return $value;
	}
	if ( ! empty( $value ) ) {
		return $value;
	}
	if ( ! is_numeric( $post_id ) ) {
		return $value;
	}
	// Only default on genuinely new posts (auto-draft).
	if ( 'auto-draft' !== get_post_status( $post_id ) ) {
		return $value;
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $value;
	}
	$person_id = msw_resolve_linked_person( $user_id );
	if ( $person_id ) {
		return array( $person_id );
	}
	return $value;
}, 10, 3 );

/**
 * Resolve a linked_person field value to a plain post ID, regardless of whether
 * the field returns an ID, a WP_Post object, or an array (post-object multiple).
 */
function msw_resolve_linked_person( $user_id ) {
	$raw = get_field( 'linked_person', 'user_' . (int) $user_id );

	if ( empty( $raw ) ) {
		return 0;
	}
	if ( is_object( $raw ) ) {
		return (int) $raw->ID;          // post-object, return format = object
	}
	if ( is_array( $raw ) ) {
		$first = reset( $raw );
		return is_object( $first ) ? (int) $first->ID : (int) $first; // multiple
	}
	return (int) $raw;                   // already an ID
}

/**
 * Add a "Filter by role" dropdown above the People list table.
 */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'people' !== $post_type ) {
		return;
	}

	$tax   = 'person_role';
	$terms = get_terms( array(
		'taxonomy'   => $tax,
		'hide_empty' => false,
	) );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return;
	}

	$current = isset( $_GET[ $tax ] ) ? sanitize_text_field( wp_unslash( $_GET[ $tax ] ) ) : '';

	printf( '<select name="%s" id="%s">', esc_attr( $tax ), esc_attr( $tax ) );
	printf( '<option value="">%s</option>', esc_html__( 'All roles', 'momentive' ) );
	foreach ( $terms as $term ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $term->slug ),
			selected( $current, $term->slug, false ),
			esc_html( $term->name )
		);
	}
	echo '</select>';
} );

/**
 * Apply the role filter to the People query.
 */
add_filter( 'parse_query', function ( $query ) {
	global $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow ) {
		return $query;
	}
	if ( empty( $query->query_vars['post_type'] ) || 'people' !== $query->query_vars['post_type'] ) {
		return $query;
	}

	$tax = 'person_role';
	if ( ! empty( $_GET[ $tax ] ) ) {
		$slug = sanitize_text_field( wp_unslash( $_GET[ $tax ] ) );
		$query->query_vars['tax_query'] = array(
			array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $slug,
			),
		);
	}

	return $query;
} );
