<?php
/**
 * Admin list table customizations.
 *
 * Replaces the ambiguous built-in "Date" column (which shows either publish
 * date or modified date depending on post status) with explicit "Published"
 * and "Last Modified" columns on every post type's list table. Both are
 * sortable and display date + time in the site's configured timezone/format.
 */

// ── Column headers ─────────────────────────────────────────────────────────────

add_filter( 'manage_posts_columns', 'momentive_replace_date_columns' );
add_filter( 'manage_pages_columns', 'momentive_replace_date_columns' );

function momentive_replace_date_columns( array $columns ): array {
	// Drop the built-in Date column; add our two explicit replacements.
	unset( $columns['date'] );
	$columns['momentive_published'] = __( 'Published', 'momentive' );
	$columns['momentive_modified']  = __( 'Last Modified', 'momentive' );
	return $columns;
}

// ── Column rendering ───────────────────────────────────────────────────────────

add_action( 'manage_posts_custom_column', 'momentive_render_date_columns', 10, 2 );
add_action( 'manage_pages_custom_column', 'momentive_render_date_columns', 10, 2 );

function momentive_render_date_columns( string $column, int $post_id ): void {
	if ( 'momentive_published' === $column ) {
		// Show '—' for posts that have never been published.
		$post = get_post( $post_id );
		if ( $post && '0000-00-00 00:00:00' === $post->post_date ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}
		momentive_render_admin_date( get_post_time( 'U', false, $post_id ) );

	} elseif ( 'momentive_modified' === $column ) {
		momentive_render_admin_date( get_post_modified_time( 'U', false, $post_id ) );
	}
}

/**
 * Render a date + time cell.
 *
 * @param int|false $timestamp Local-timezone Unix timestamp (as returned by
 *                             get_post_time/get_post_modified_time with $gmt=false).
 */
function momentive_render_admin_date( int|false $timestamp ): void {
	if ( ! $timestamp ) {
		echo '<span aria-hidden="true">—</span>';
		return;
	}

	// "F j, Y \a\t g:i a"  →  "May 15, 2026 at 1:29 am"
	// Single-quoted so \a and \t stay as literal backslash-letter pairs,
	// which date_i18n() then interprets as escaped (literal) characters.
	$format    = get_option( 'date_format' ) . ' \a\t ' . get_option( 'time_format' );
	$formatted = date_i18n( $format, $timestamp );
	$ago       = human_time_diff( $timestamp ) . ' ago';

	printf(
		'<abbr title="%s">%s</abbr>',
		esc_attr( $ago ),
		esc_html( $formatted )
	);
}

// ── Sortable columns ───────────────────────────────────────────────────────────

// `manage_posts_sortable_columns` is NOT a real WP filter. WP applies
// `manage_{screen_id}_sortable_columns` where screen_id is `edit-{post_type}`.
// Hook on `current_screen` so we know the exact screen ID at registration time.

add_action( 'current_screen', function ( WP_Screen $screen ): void {
	if ( 'edit' !== $screen->base ) return;
	add_filter( "manage_{$screen->id}_sortable_columns", 'momentive_sortable_date_columns' );
} );

function momentive_sortable_date_columns( array $columns ): array {
	$columns['momentive_published'] = 'date';
	$columns['momentive_modified']  = 'modified';
	return $columns;
}

// ── "Rebuilt?" column on the Pages list table ──────────────────────────────────
//
// A page counts as "rebuilt" when its post_content contains at least one real
// WordPress block comment beyond the trivial empty paragraph the editor inserts
// on first open. Empty shells created by create-empty-pages.php show as "—".
//
// This is intentionally pages-only: other CPTs have their own rebuild workflows
// and don't need the same indicator.

add_filter( 'manage_pages_columns', 'momentive_add_rebuilt_column' );

function momentive_add_rebuilt_column( array $columns ): array {
	// Insert after the Title column.
	$out = [];
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['momentive_rebuilt'] = 'Rebuilt?';
		}
	}
	return $out;
}

add_action( 'manage_pages_custom_column', 'momentive_render_rebuilt_column', 10, 2 );

function momentive_render_rebuilt_column( string $column, int $post_id ): void {
	if ( 'momentive_rebuilt' !== $column ) {
		return;
	}

	$content  = (string) get_post_field( 'post_content', $post_id );
	$stripped = preg_replace(
		'#<!--\s*wp:paragraph\s*-->\s*<p[^>]*>\s*</p>\s*<!--\s*/wp:paragraph\s*-->\s*#i',
		'',
		$content
	);
	$rebuilt  = '' !== trim( (string) $stripped ) && str_contains( (string) $stripped, '<!-- wp:' );

	if ( $rebuilt ) {
		echo '<span style="color:#00a32a;font-size:1.2em;line-height:1;" title="Has block content">✓</span>';
	} else {
		echo '<span style="color:#aaa;" title="Empty shell">—</span>';
	}
}

// ── "Rebuilt?" filter dropdown on the Pages list table ─────────────────────────
//
// Adds a "Rebuilt / Not rebuilt" select to the Pages filter bar. Filters via a
// SQL LIKE on post_content — "rebuilt" means has at least one block comment;
// "not rebuilt" means none. The trivial-empty-paragraph edge case (a page opened
// once but never really written) lands in "rebuilt" via SQL, but the column
// indicator already shows — for it visually, so the discrepancy is harmless.

add_action( 'restrict_manage_posts', 'momentive_rebuilt_filter_dropdown' );

function momentive_rebuilt_filter_dropdown( string $post_type ): void {
	if ( 'page' !== $post_type ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current = isset( $_GET['momentive_rebuilt_filter'] ) ? sanitize_key( $_GET['momentive_rebuilt_filter'] ) : '';
	?>
	<select name="momentive_rebuilt_filter">
		<option value=""><?php esc_html_e( 'All pages', 'momentive' ); ?></option>
		<option value="rebuilt" <?php selected( $current, 'rebuilt' ); ?>><?php esc_html_e( 'Rebuilt', 'momentive' ); ?></option>
		<option value="not_rebuilt" <?php selected( $current, 'not_rebuilt' ); ?>><?php esc_html_e( 'Not rebuilt', 'momentive' ); ?></option>
	</select>
	<?php
}

add_action( 'pre_get_posts', 'momentive_rebuilt_filter_query' );

function momentive_rebuilt_filter_query( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'edit-page' !== $screen->id ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$filter = isset( $_GET['momentive_rebuilt_filter'] ) ? sanitize_key( $_GET['momentive_rebuilt_filter'] ) : '';
	if ( '' === $filter || ! in_array( $filter, [ 'rebuilt', 'not_rebuilt' ], true ) ) {
		return;
	}

	// Attach a posts_where filter for this query only. Running it inside
	// pre_get_posts scopes it to this request naturally.
	add_filter( 'posts_where', static function ( string $where, WP_Query $q ) use ( $filter ): string {
		if ( ! $q->is_main_query() ) {
			return $where;
		}
		global $wpdb;
		if ( 'rebuilt' === $filter ) {
			// Has at least one block comment.
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s", '%<!-- wp:%' );
		} else {
			// No block comments at all — empty shell or classic-editor content.
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_content NOT LIKE %s", '%<!-- wp:%' );
		}
		return $where;
	}, 10, 2 );
}
