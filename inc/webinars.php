<?php

/**
 * Custom Post Type: Webinars
 *
 * Design notes
 * ─────────────────────────────────────────────────────────────────────────────
 * The old site stored almost every piece of content in ACF postmeta fields
 * and used Elementor widgets to render them. In the rebuild those
 * responsibilities are split differently:
 *
 *   • Description copy, "you'll learn" checklist, presenter bios, quote box,
 *     CAE credits note, related resources, and CTA box are all blocks /
 *     block patterns in the post body — not ACF fields. Editors own them.
 *
 *   • ACF fields are reserved for structured data the theme PHP needs to read
 *     programmatically: dates/times, form embed codes, the recording page
 *     URL, hero image override, and the solution-category link.
 *
 * Auto-transition from Upcoming → On-Demand
 * ─────────────────────────────────────────────────────────────────────────────
 * Rather than a manual "switch this field when the event ends" step, the
 * webinar_status() helper derives the current state at runtime from the
 * stored Unix timestamp. A wp_head hook injects it as a body data-attribute
 * so CSS / JS can react without extra PHP. Action Scheduler fires once per
 * hour to purge the object cache and clear any page-cache layer after the
 * event ends, ensuring visitors never see a stale "Upcoming" page.
 *
 * The webinar_type ACF field still exists (values: upcoming | on-demand) and
 * is the *stored* canonical value. The auto-transition hook writes to it when
 * the event end time passes so the value in the DB stays correct for REST API
 * consumers and Query Loop filters. Editors can also flip it manually for
 * edge cases (cancelled event, rescheduled date, etc.).
 *
 * Two registration form fields
 * ─────────────────────────────────────────────────────────────────────────────
 * form_upcoming   – embed code shown before the event ends
 * form_ondemand   – embed code shown after (different HS form)
 *
 * The correct one is selected automatically by webinar_status(). Editors no
 * longer need to swap embed codes; they just paste both at setup time.
 *
 * Form heading is also auto-derived, but can be overridden via a field if
 * the default copy ("Save your spot" / "Watch this webinar") doesn't fit.
 *
 * Recording page
 * ─────────────────────────────────────────────────────────────────────────────
 * The old site used a separate "assets" CPT for recording pages. Here the
 * webinar post owns its recording, exposed through the shared /recordings/{slug}
 * layer (recordings.php). The recording fields (video_embed_code, recording_url)
 * live in their own "Recording" field group so the same fields can later attach
 * to other recording hosts (product overviews) without duplication.
 *
 *   video_embed_code (textarea) – the recording's video player embed. The
 *     recording view renders it once the webinar is on-demand.
 *   recording_url (text) – optional off-pattern override; blank derives
 *     /recordings/{slug}.
 *
 * Resolution, the rewrite namespace, legacy /assets/* redirects, and the
 * cross-host slug-collision guard are all in recordings.php.
 */


// ─────────────────────────────────────────────────────────────────────────────
// Helper: derive current status from the stored ACF date + time fields.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns 'upcoming' or 'on-demand' for a given webinar post.
 *
 * Logic:
 *   1. Use webinar_end_date + webinar_time_end if the end date is set.
 *   2. Otherwise use webinar_date + webinar_time_end (single-day event).
 *   3. If neither produces a valid timestamp, fall back to the stored
 *      webinar_type field value so manual overrides still work.
 *
 * The timezone field is advisory only (displayed to users); all stored dates
 * are assumed to be in the site's configured timezone (Settings → General).
 *
 * @param int $post_id
 * @return string 'upcoming'|'on-demand'
 */
function momentive_webinar_status( int $post_id ): string {
	// Date fields return 'Ymd' strings (e.g. "20260623"); strtotime() parses
	// these to midnight of that day in the site's timezone.
	// Prefer an explicit end-date; fall back to start-date.
	$end_raw  = get_field( 'webinar_end_date', $post_id ); // 'Ymd' or ''
	$date_raw = $end_raw ?: get_field( 'webinar_date', $post_id );
	$base_ts  = $date_raw ? strtotime( $date_raw ) : 0;

	if ( $base_ts ) {
		// Combine the base date with the end time. The time picker returns
		// 12-hour format (e.g. "1:00 pm"), so parse it accordingly.
		$time_end = get_field( 'webinar_time_end', $post_id ); // e.g. "1:00 pm"
		$parsed   = $time_end ? DateTime::createFromFormat( 'g:i a', $time_end ) : false;
		if ( $parsed ) {
			$h = (int) $parsed->format( 'G' ); // 0–23
			$m = (int) $parsed->format( 'i' );
			// $base_ts already points to midnight of the relevant day.
			// Replace its time component with the end hour/minute.
			$midnight = mktime( 0, 0, 0,
				(int) date( 'n', $base_ts ),
				(int) date( 'j', $base_ts ),
				(int) date( 'Y', $base_ts )
			);
			$event_end_ts = $midnight + ( $h * 3600 ) + ( $m * 60 );
		} else {
			// No end time: treat the whole end-date day as the boundary.
			$event_end_ts = mktime( 23, 59, 59,
				(int) date( 'n', $base_ts ),
				(int) date( 'j', $base_ts ),
				(int) date( 'Y', $base_ts )
			);
		}

		return ( time() >= $event_end_ts ) ? 'on-demand' : 'upcoming';
	}

	// No date data available — trust the stored field.
	return (string) get_field( 'webinar_type', $post_id ) ?: 'on-demand';
}

/**
 * Whether a webinar is a multi-session series.
 *
 * "Series" is a descriptive *kind*, orthogonal to the upcoming/on-demand
 * *status*. A series follows the same lifecycle as any webinar (it flips to
 * on-demand when its end date — or start date, if no end date — passes); the
 * plan is to split a finished series into individual on-demand posts manually.
 * This flag only affects display (status label, schedule), never the lifecycle.
 *
 * @param int $post_id
 * @return bool
 */
function momentive_webinar_is_series( int $post_id ): bool {
	return (bool) get_field( 'is_series', $post_id );
}

// ─────────────────────────────────────────────────────────────────────────────
// Post type registration
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', 'momentive_webinars_setup' );

// Front-end styles for the single-webinar view (headings, layout tweaks, etc.).
// Registered always, enqueued only on singular webinar posts.
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'momentive-gate',
		get_template_directory_uri() . '/assets/css/gate.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	wp_register_style(
		'momentive-webinar',
		get_template_directory_uri() . '/assets/css/webinar.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	if ( is_singular( 'webinar' ) ) {
		wp_enqueue_style( 'momentive-gate' );	
	}
	if ( is_singular( 'webinar' ) || is_archive('webinar') ) {
		wp_enqueue_style( 'momentive-webinar' );
	}
} );

function momentive_webinars_setup(): void {

	$labels = [
		'name'               => _x( 'Webinars', 'Post type general name', 'momentive' ),
		'singular_name'      => _x( 'Webinar', 'Post type singular name', 'momentive' ),
		'menu_name'          => _x( 'Webinars', 'Admin Menu text', 'momentive' ),
		'name_admin_bar'     => _x( 'Webinar', 'Add New on Toolbar', 'momentive' ),
		'add_new'            => __( 'Add New', 'momentive' ),
		'add_new_item'       => __( 'Add New Webinar', 'momentive' ),
		'new_item'           => __( 'New Webinar', 'momentive' ),
		'edit_item'          => __( 'Edit Webinar', 'momentive' ),
		'view_item'          => __( 'View Webinar', 'momentive' ),
		'all_items'          => __( 'All Webinars', 'momentive' ),
		'search_items'       => __( 'Search Webinars', 'momentive' ),
		'not_found'          => __( 'No webinars found.', 'momentive' ),
		'not_found_in_trash' => __( 'No webinars found in Trash.', 'momentive' ),
	];

	$args = [
		'labels'             => $labels,
		'public'             => true,
		'hierarchical'       => false,
		'menu_icon'          => 'dashicons-video-alt2',
		'menu_position'      => 7,
		'show_in_rest'       => true,
		'supports'           => [
			'title',
			'editor',   // Block content: description, checklist, presenters, etc.
			'excerpt',  // Used in archive/query loop cards.
			'thumbnail',
			'revisions',
		],
		'rewrite'            => [
			'slug'       => 'webinars',
			'with_front' => false,
		],
		'has_archive'        => 'webinars',
		'show_in_nav_menus'  => true,
		'publicly_queryable' => true,
		'capability_type'    => 'post',
		// Shared solution-scoped categories (children of "Solutions"), same
		// as products and testimonials.
		'taxonomies'         => [ 'category', 'webinar_type_tax' ],
		'template'           => [],   // Populated below once the pattern exists.
		'template_lock'      => false,
	];

	register_post_type( 'webinar', $args );


	// ── Webinar Type taxonomy ──────────────────────────────────────────────
	//
	// A simple two-term taxonomy used to filter Query Loops on archive pages
	// (e.g. "show only Upcoming" or "show only On-Demand").
	// The auto-transition hook keeps the assigned term in sync with
	// momentive_webinar_status() so editors don't have to manage it.

	register_taxonomy( 'webinar_type_tax', [ 'webinar' ], [
		'labels'             => [
			'name'          => _x( 'Webinar Type', 'taxonomy general name', 'momentive' ),
			'singular_name' => _x( 'Webinar Type', 'taxonomy singular name', 'momentive' ),
			'all_items'     => __( 'All Types', 'momentive' ),
		],
		'hierarchical'       => false,
		'show_ui'            => true,
		'show_admin_column'  => false,  // Redundant with the custom Status column; series shown there via is_series.
		'show_in_rest'       => true,
		'publicly_queryable' => false,   // No front-end taxonomy archive.
		'public'             => false,
		'rewrite'            => false,
	] );

	// Seed the two terms.
	foreach ( [
		'Upcoming'  => 'upcoming',
		'On-Demand' => 'on-demand',
	] as $name => $slug ) {
		if ( ! term_exists( $name, 'webinar_type_tax' ) ) {
			wp_insert_term( $name, 'webinar_type_tax', [ 'slug' => $slug ] );
		}
	}
}


// ─────────────────────────────────────────────────────────────────────────────
// Body class + data attribute — drives CSS without extra PHP in templates.
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'body_class', function( array $classes ): array {
	if ( is_singular( 'webinar' ) ) {
		$status    = momentive_webinar_status( get_the_ID() );
		$classes[] = 'single-webinar';
		$classes[] = 'webinar-' . $status; // 'webinar-upcoming' | 'webinar-on-demand'
	}
	return $classes;
} );

// Inject a data-webinar-status attribute on <body> for JS hooks.
add_action( 'wp_head', function() {
	if ( ! is_singular( 'webinar' ) ) return;
	$status = momentive_webinar_status( get_the_ID() );
	// Output a tiny inline script so the attribute is set before any JS runs.
	echo '<script>document.documentElement.dataset.webinarStatus=' . wp_json_encode( $status ) . ';</script>' . "\n";
} );


// ─────────────────────────────────────────────────────────────────────────────
// Auto-transition: write webinar_type field + sync taxonomy term
// ─────────────────────────────────────────────────────────────────────────────
//
// Runs every time a singular webinar is viewed (cheap: one ACF read).
// Uses a transient keyed to the post ID to avoid re-writing on every page load
// once the transition has already happened.

add_action( 'wp', function() {
	if ( ! is_singular( 'webinar' ) ) return;

	$post_id       = get_the_ID();
	$transient_key = 'momentive_webinar_transitioned_' . $post_id;

	// Already transitioned this post — skip.
	if ( get_transient( $transient_key ) ) return;

	$stored_type = get_field( 'webinar_type', $post_id );
	$live_status = momentive_webinar_status( $post_id );

	// Nothing to do if the stored type already matches.
	if ( $stored_type === $live_status ) {
		// Mark so we don't re-check on every request until something changes.
		set_transient( $transient_key, 1, HOUR_IN_SECONDS );
		return;
	}

	// Write the updated value back so REST API / Query Loops see the correct state.
	update_field( 'webinar_type', $live_status, $post_id );

	// Sync taxonomy term.
	$term = get_term_by( 'slug', $live_status, 'webinar_type_tax' );
	if ( $term ) {
		wp_set_object_terms( $post_id, [ $term->term_id ], 'webinar_type_tax' );
	}

	// Purge page-cache plugins that expose a helper function.
	if ( function_exists( 'rocket_clean_post' ) ) {
		rocket_clean_post( $post_id );
	}
	if ( function_exists( 'wp_cache_delete_group' ) ) {
		wp_cache_delete_group( 'posts' );
	}

	set_transient( $transient_key, 1, HOUR_IN_SECONDS );
} );


// ─────────────────────────────────────────────────────────────────────────────
// Action Scheduler: hourly sweep to transition any posts whose events have
// ended since the last time the singular page was visited.
// ─────────────────────────────────────────────────────────────────────────────
//
// This is a safety net for posts that don't receive traffic right at the
// transition moment (e.g. an upcoming webinar that nobody visits after it ends).
// It also clears the page cache for WP Engine's full-page caching layer.

add_action( 'init', function() {
	if ( ! function_exists( 'as_schedule_recurring_action' ) ) return;

	if ( false === as_next_scheduled_action( 'momentive_webinar_transition_sweep' ) ) {
		as_schedule_recurring_action(
			time(),
			HOUR_IN_SECONDS,
			'momentive_webinar_transition_sweep',
			[],
			'momentive'
		);
	}
} );

add_action( 'momentive_webinar_transition_sweep', function() {
	// Find all 'upcoming' webinars whose event end time is in the past.
	$posts = get_posts( [
		'post_type'      => 'webinar',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'tax_query'      => [
			[
				'taxonomy' => 'webinar_type_tax',
				'field'    => 'slug',
				'terms'    => 'upcoming',
			],
		],
	] );

	foreach ( $posts as $post_id ) {
		if ( momentive_webinar_status( $post_id ) === 'on-demand' ) {
			update_field( 'webinar_type', 'on-demand', $post_id );

			$term = get_term_by( 'slug', 'on-demand', 'webinar_type_tax' );
			if ( $term ) {
				wp_set_object_terms( $post_id, [ $term->term_id ], 'webinar_type_tax' );
			}

			// Invalidate transient so next page visit doesn't short-circuit.
			delete_transient( 'momentive_webinar_transitioned_' . $post_id );

			// WP Engine page cache purge (their mu-plugin exposes this function).
			if ( function_exists( 'wpengine_cache_flush_page' ) ) {
				wpengine_cache_flush_page( get_permalink( $post_id ) );
			}
		}
	}
} );


// ─────────────────────────────────────────────────────────────────────────────
// Form, schedule, and video rendering
// ─────────────────────────────────────────────────────────────────────────────
//
// These are all handled server-side by blocks in the webinar pattern, not by
// injected JS:
//
//   • Registration form — the acf/hubspot-form block with form_source = 'post'.
//     momentive_resolve_webinar_form() picks form_upcoming / form_ondemand based
//     on the computed status.
//   • Schedule (date/time) — the momentive/webinar-schedule block, which renders
//     nothing once momentive_webinar_status() returns 'on-demand'.
//   • Recording video — a block that outputs video_embed_code when on-demand.
//
// No wp_localize_script / webinar.js form injection is needed.


// ─────────────────────────────────────────────────────────────────────────────
// Recording URLs
// ─────────────────────────────────────────────────────────────────────────────
//
// Recording resolution, the /recordings/{slug} namespace, the legacy /assets/*
// redirects, and the slug-collision guard all live in the shared, host-agnostic
// recordings.php. Webinars register as a recording host there by default (see
// momentive_recording_host_types()). Nothing webinar-specific is needed here.
//
// The webinar's recording becomes available — and /recordings/{slug} starts
// resolving to the recording view rather than redirecting to registration —
// once momentive_webinar_status() returns 'on-demand' and a video embed is set.
// See momentive_recording_is_available().





// ─────────────────────────────────────────────────────────────────────────────
// Admin: Type badge column in posts list
// ─────────────────────────────────────────────────────────────────────────────

add_filter( 'manage_webinar_posts_columns', function( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( $key === 'title' ) {
			$new['webinar_status'] = __( 'Status', 'momentive' );
			$new['webinar_date_col'] = __( 'Date / Time', 'momentive' );
		}
	}
	return $new;
} );

add_action( 'manage_webinar_posts_custom_column', function( string $column, int $post_id ): void {
	if ( $column === 'webinar_status' ) {
		$status    = momentive_webinar_status( $post_id );
		$is_series = momentive_webinar_is_series( $post_id );
		$color     = $status === 'upcoming' ? '#00a32a' : '#787c82';
		$label     = $status === 'upcoming' ? 'Upcoming' : 'On-Demand';
		printf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:%s;color:#fff;">%s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
		if ( $is_series ) {
			echo ' <span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;background:#6b4f8a;color:#fff;">Series</span>';
		}
	}

	if ( $column === 'webinar_date_col' ) {
		$raw     = get_field( 'webinar_date', $post_id );
		$end_raw = get_field( 'webinar_end_date', $post_id );
		$dt      = $raw     ? DateTime::createFromFormat( 'Ymd', $raw )     : false;
		$end_dt  = $end_raw ? DateTime::createFromFormat( 'Ymd', $end_raw ) : false;

		if ( $dt ) {
			if ( $end_dt && $end_dt->format( 'Ymd' ) !== $dt->format( 'Ymd' ) ) {
				// Multi-day: collapse same-month ranges ("Jul 21–23, 2026").
				if ( $dt->format( 'Y-m' ) === $end_dt->format( 'Y-m' ) ) {
					echo esc_html( $dt->format( 'M j' ) . '–' . $end_dt->format( 'j, Y' ) );
				} elseif ( $dt->format( 'Y' ) === $end_dt->format( 'Y' ) ) {
					echo esc_html( $dt->format( 'M j' ) . ' – ' . $end_dt->format( 'M j, Y' ) );
				} else {
					echo esc_html( $dt->format( 'M j, Y' ) . ' – ' . $end_dt->format( 'M j, Y' ) );
				}
			} else {
				echo esc_html( $dt->format( 'M j, Y' ) );
			}

			$time = get_field( 'webinar_time_start', $post_id );
			$tz   = get_field( 'webinar_timezone', $post_id );
			if ( $time ) {
				echo '<br><span style="color:#666;font-size:11px;">' . esc_html( $time . ( $tz ? ' ' . $tz : '' ) ) . '</span>';
			}
		} else {
			echo '<span style="color:#999">—</span>';
		}
	}
}, 10, 2 );

add_filter( 'manage_edit-webinar_sortable_columns', function( array $columns ): array {
	$columns['webinar_date_col'] = 'webinar_date_col';
	return $columns;
} );

add_action( 'pre_get_posts', function( \WP_Query $query ) {
	if ( ! is_admin() ) return;
	if ( $query->get( 'orderby' ) !== 'webinar_date_col' ) return;
	$query->set( 'meta_key', 'webinar_date' );
	$query->set( 'orderby', 'meta_value_num' );
} );


// ─────────────────────────────────────────────────────────────────────────────
// Block pattern template (populated once momentive/webinar-content exists)
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function() {
	$cpt = get_post_type_object( 'webinar' );
	if ( ! $cpt ) return;

	$registry = WP_Block_Patterns_Registry::get_instance();
	$pattern  = $registry->get_registered( 'momentive/webinar-content' );

	if ( $pattern && ! empty( $pattern['content'] ) ) {
		$cpt->template = momentive_blocks_to_cpt_template(
			parse_blocks( $pattern['content'] )
		);
	}
	$cpt->template_lock = false;
}, 30 );


// ─────────────────────────────────────────────────────────────────────────────
// REST API extras
// ─────────────────────────────────────────────────────────────────────────────

// Expose webinar_status as a read-only REST field so the block editor
// and any front-end JS can read the computed state.
add_action( 'rest_api_init', function() {
	register_rest_field( 'webinar', 'webinar_status', [
		'get_callback' => fn( $post ) => momentive_webinar_status( $post['id'] ),
		'schema'       => [
			'description' => 'Computed webinar status: upcoming or on-demand.',
			'type'        => 'string',
			'enum'        => [ 'upcoming', 'on-demand' ],
			'context'     => [ 'view', 'embed' ],
			'readonly'    => true,
		],
	] );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Resolve which HubSpot embed code a webinar should show based on its type.
// Returns the upcoming form by default, the on-demand form once the event
// has passed (webinar_type flipped to 'on-demand' by the scheduler).
// ─────────────────────────────────────────────────────────────────────────────

function momentive_resolve_webinar_form( $post_id ) {
    if ( 'webinar' !== get_post_type( $post_id ) ) {
        return '';
    }
    $status = momentive_webinar_status( $post_id ); // computed, always live
    $field  = ( 'on-demand' === $status ) ? 'form_ondemand' : 'form_upcoming';
    return (string) get_field( $field, $post_id );
}

// ─────────────────────────────────────────────────────────────────────────────
// Order webinar archives: upcoming (by soonest date) first, then on-demand.
// ─────────────────────────────────────────────────────────────────────────────
//
// "Upcoming-first, then past" is a two-level sort:
//   1. Group by whether the event is still upcoming (status), upcoming above.
//   2. Within each group, order by the occurrence date.
//
// Because momentive_webinar_status() is computed at runtime (not a stored
// column we can ORDER BY), we can't express group-1 purely in SQL. Instead we
// sort everything by webinar_date in SQL, then do a stable status partition in
// PHP via the_posts. For two-digit webinar counts this is trivially cheap.

add_action( 'pre_get_posts', function ( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'webinar' ) ) { // post type, not 'webinars'
		return;
	}

	// Sort by the ACF occurrence date. Ymd strings sort correctly as CHAR.
	// Posts missing webinar_date fall to the bottom (handled in the partition).
	$query->set( 'meta_query', array(
		'relation' => 'OR',
		'has_date' => array( 'key' => 'webinar_date', 'compare' => 'EXISTS' ),
		'no_date'  => array( 'key' => 'webinar_date', 'compare' => 'NOT EXISTS' ),
	) );
	$query->set( 'meta_key', 'webinar_date' );
	$query->set( 'meta_type', 'CHAR' );
	$query->set( 'orderby', array(
		'has_date'   => 'DESC', // dated posts above undated
		'meta_value' => 'ASC',  // soonest first within each group
		'date'       => 'DESC',
	) );
} );

// Partition the ordered list: upcoming (incl. series) first, on-demand after.
// Each subgroup keeps the SQL date ordering (stable). Runs only on the
// webinar archive main query.
add_filter( 'the_posts', function ( array $posts, WP_Query $query ): array {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $posts;
	}
	if ( ! $query->is_post_type_archive( 'webinar' ) ) {
		return $posts;
	}

	$upcoming  = array();
	$ondemand  = array();
	foreach ( $posts as $post ) {
		if ( 'on-demand' === momentive_webinar_status( $post->ID ) ) {
			$ondemand[] = $post;
		} else {
			$upcoming[] = $post;
		}
	}
	return array_merge( $upcoming, $ondemand );
}, 10, 2 );