<?php
/**
 * Deduplication: merge cloned People posts into canonical profiles.
 *
 * The presenter migration created duplicate People profiles for persons who
 * already had a profile in the DB. Titles like "Rob Miller, MPA, CAE" were
 * treated as separate people. This script:
 *
 *   1. Loads all People posts from the DB.
 *   2. Groups them by normalised base name (title stripped of comma-separated
 *      credential/job suffixes).
 *   3. For each duplicate group, elects a canonical post:
 *        non-migrated > has thumbnail > lowest ID
 *   4. Detects credential-like suffixes (short, no-space tokens — e.g. "CAE,
 *      PMP") and writes them to the ACF `certifications` field on the canonical
 *      post if the field is currently empty.
 *   5. Cleans the canonical post title (strips the credential suffix if
 *      present).
 *   6. Merges `person_role` taxonomy terms from all duplicates into canonical.
 *   7. Rewrites all Webinar `presenters` ACF field references from duplicate
 *      IDs → canonical ID.
 *   8. Trashes the duplicate posts.
 *
 * Known duplicate groups as of 2026-07-01 (verified from WXR analysis):
 *   - Allyson Olaniel        (canonical 12455 + 1 migrated clone)
 *   - Liam O'Malley          (canonical 12543 + 1 non-migrated clone)
 *   - Rich Vallaster         (canonical 12583 + 1 migrated clone)
 *   - Rob Miller             (canonical 12585 + 2 migrated clones)
 *   - Sean Connelly          (canonical 12590 + 1 migrated clone)
 *   - Tirrah Switzer         (canonical 10548 + 7 migrated clones)
 *
 * Usage — dry run (safe default, no writes):
 *   wp eval-file migrations/dedup-people.php --user=<admin>
 *
 * Usage — live:
 *   wp eval-file migrations/dedup-people.php live --user=<admin>
 *
 * Flags (positional, same pattern as other migration scripts):
 *   live / go      → write changes (default: dry run)
 *   only=<name>    → restrict to one group, matched against the normalised
 *                    base name with hyphens (e.g. only=rob-miller,
 *                    only=tirrah-switzer)
 *
 * NOTE: The `certifications` field key is resolved by ACF field name.
 * Ensure the field is registered under the name "certifications" in
 * inc/acf-groups.php before running live.
 */

/* ---- Flag parsing --------------------------------------------------------- */

$_flags = isset( $args ) && is_array( $args ) ? $args : [];
$dry    = true;
$only   = '';

foreach ( $_flags as $tok ) {
	$tok = ltrim( (string) $tok, '-' );
	if ( in_array( $tok, [ 'live', 'go' ], true ) )               { $dry  = false; }
	if ( in_array( $tok, [ 'dry', 'dry-run', 'dry_run' ], true ) ) { $dry  = true; }
	if ( str_starts_with( $tok, 'only=' ) )                        { $only = substr( $tok, 5 ); }
}

WP_CLI::log( '=====================================================' );
WP_CLI::log( '  People deduplication' );
WP_CLI::log( '  MODE: ' . ( $dry ? 'DRY RUN (no writes)' : '*** LIVE — WRITING DB ***' ) );
if ( '' !== $only ) { WP_CLI::log( '  only: "' . $only . '"' ); }
WP_CLI::log( '=====================================================' );

/* ---- Helpers -------------------------------------------------------------- */

/**
 * Normalise a person name to a stable grouping key.
 * Strips comma-separated suffixes, lowercases, removes non-alphanumeric
 * (handles apostrophes, accents, punctuation).
 *
 * "Rob Miller, MPA, CAE"           → "rob miller"
 * "Liam O'Malley, CAE, PMP"        → "liam omalley"
 * "Tirrah Switzer"                 → "tirrah switzer"
 */
function msw_dp_normalise( string $title ): string {
	$base = strtok( $title, ',' ); // everything before the first comma
	$base = strtolower( trim( $base ) );
	return preg_replace( '/[^a-z0-9 ]/', '', $base );
}

/**
 * Convert a normalised name to a hyphenated slug suitable for `only=` matching.
 * "rob miller" → "rob-miller"
 */
function msw_dp_slug( string $norm ): string {
	return str_replace( ' ', '-', $norm );
}

/**
 * Determine whether the comma-suffix of a title looks like actual credentials
 * (e.g. "CAE, PMP") rather than a job title ("Director, Product Management").
 *
 * Heuristic: every token (split on ", ") must be ≤ 6 characters and contain
 * no spaces. This reliably separates short initialisms from prose job titles.
 *
 * Returns the credentials string (trimmed), or '' if it doesn't look like creds.
 */
function msw_dp_extract_creds( string $title ): string {
	$comma_pos = strpos( $title, ',' );
	if ( false === $comma_pos ) {
		return '';
	}
	$suffix = trim( substr( $title, $comma_pos + 1 ) );
	$tokens = array_map( 'trim', explode( ',', $suffix ) );
	foreach ( $tokens as $token ) {
		if ( '' === $token || strlen( $token ) > 6 || str_contains( $token, ' ' ) ) {
			return ''; // at least one token looks like a job title word
		}
	}
	return $suffix;
}

/* ---- Load all People posts ------------------------------------------------ */

$all_people = get_posts( [
	'post_type'      => 'people',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'no_found_rows'  => true,
] );

WP_CLI::log( sprintf( 'Loaded %d People posts.', count( $all_people ) ) );

/* ---- Group by normalised name --------------------------------------------- */

$groups = []; // normalised name → [ WP_Post, ... ]

foreach ( $all_people as $post ) {
	$norm            = msw_dp_normalise( $post->post_title );
	$groups[ $norm ][] = $post;
}

$dup_groups = array_filter( $groups, fn( $g ) => count( $g ) > 1 );

WP_CLI::log( sprintf( 'Duplicate groups found: %d', count( $dup_groups ) ) . "\n" );

if ( empty( $dup_groups ) ) {
	WP_CLI::success( 'No duplicates — nothing to do.' );
	return;
}

/* ---- Summary counters ----------------------------------------------------- */

$summary = [
	'groups_processed'    => 0,
	'groups_skipped'      => 0,
	'duplicates_trashed'  => 0,
	'titles_cleaned'      => 0,
	'certs_written'       => 0,
	'roles_merged'        => 0,
	'webinars_updated'    => 0,
	'presenter_refs_fixed'=> 0,
];

/* ---- Pre-load all webinar posts and their presenter fields ----------------- */
// We do this once to avoid N+1 queries inside the loop.

$webinar_posts = get_posts( [
	'post_type'      => 'webinar',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'no_found_rows'  => true,
	'fields'         => 'ids',
] );

WP_CLI::log( sprintf( 'Loaded %d webinar posts.', count( $webinar_posts ) ) );

// Build map: webinar_id → array of current presenter post IDs
$webinar_presenters = []; // webinar_id → int[]
foreach ( $webinar_posts as $wid ) {
	$wid  = (int) $wid;
	$raw  = get_field( 'presenters', $wid );
	$ids  = [];
	foreach ( (array) $raw as $p ) {
		$ids[] = is_object( $p ) ? (int) $p->ID : (int) $p;
	}
	$webinar_presenters[ $wid ] = array_filter( $ids ); // drop zeros
}

/* ---- Process each duplicate group ----------------------------------------- */

foreach ( $dup_groups as $norm => $group ) {
	$slug = msw_dp_slug( $norm );

	// Filter by `only=` if given.
	if ( '' !== $only && $slug !== $only && $norm !== str_replace( '-', ' ', $only ) ) {
		$summary['groups_skipped']++;
		continue;
	}

	WP_CLI::log( "=== [{$norm}] ===" );
	$summary['groups_processed']++;

	/* ---- Elect canonical post ---------------------------------------------- */
	// Preference: non-migrated > has thumbnail > lowest ID.

	usort( $group, function ( $a, $b ) {
		$a_migrated = (bool) get_post_meta( $a->ID, '_momentive_migration_run', true );
		$b_migrated = (bool) get_post_meta( $b->ID, '_momentive_migration_run', true );
		if ( $a_migrated !== $b_migrated ) {
			return $a_migrated ? 1 : -1; // non-migrated first
		}
		$a_thumb = (bool) get_post_thumbnail_id( $a->ID );
		$b_thumb = (bool) get_post_thumbnail_id( $b->ID );
		if ( $a_thumb !== $b_thumb ) {
			return $a_thumb ? -1 : 1; // has thumbnail first
		}
		return $a->ID <=> $b->ID; // lowest ID first
	} );

	$canonical   = $group[0];
	$duplicates  = array_slice( $group, 1 );
	$dup_ids     = array_map( fn( $p ) => $p->ID, $duplicates );

	WP_CLI::log( sprintf(
		'  Canonical: ID=%d "%s"  (migrated=%s, thumb=%s)',
		$canonical->ID,
		$canonical->post_title,
		get_post_meta( $canonical->ID, '_momentive_migration_run', true ) ? 'yes' : 'no',
		get_post_thumbnail_id( $canonical->ID ) ? 'yes' : 'no'
	) );
	foreach ( $duplicates as $dup ) {
		WP_CLI::log( sprintf(
			'  Duplicate: ID=%d "%s"  (migrated=%s, thumb=%s)',
			$dup->ID,
			$dup->post_title,
			get_post_meta( $dup->ID, '_momentive_migration_run', true ) ? 'yes' : 'no',
			get_post_thumbnail_id( $dup->ID ) ? 'yes' : 'no'
		) );
	}

	/* ---- Credential extraction ------------------------------------------- */
	// Check all titles in the group for a credential-like suffix.
	// Take the first one found; short-circuit if canonical already has creds.

	$all_titles = array_map( fn( $p ) => $p->post_title, $group );
	$found_creds = '';
	foreach ( $all_titles as $t ) {
		$c = msw_dp_extract_creds( $t );
		if ( '' !== $c ) {
			$found_creds = $c;
			break;
		}
	}

	if ( '' !== $found_creds ) {
		WP_CLI::log( "  Credentials detected: \"{$found_creds}\"" );

		$existing_certs = get_field( 'certifications', $canonical->ID );
		if ( empty( $existing_certs ) ) {
			if ( $dry ) {
				WP_CLI::log( "  [dry] would write certifications: \"{$found_creds}\"" );
			} else {
				update_field( 'certifications', $found_creds, $canonical->ID );
				WP_CLI::log( "  wrote certifications: \"{$found_creds}\"" );
			}
			$summary['certs_written']++;
		} else {
			WP_CLI::log( "  certifications already set (\"{$existing_certs}\") — skipped" );
		}
	}

	/* ---- Clean canonical title --------------------------------------------- */
	// Strip credentials suffix from the canonical title if present.

	$clean_title = trim( strtok( $canonical->post_title, ',' ) );
	if ( $clean_title !== $canonical->post_title ) {
		WP_CLI::log( "  Cleaning title: \"{$canonical->post_title}\" → \"{$clean_title}\"" );
		if ( $dry ) {
			WP_CLI::log( "  [dry] would update post title" );
		} else {
			wp_update_post( [
				'ID'         => $canonical->ID,
				'post_title' => $clean_title,
			] );
			WP_CLI::log( "  title updated" );
		}
		$summary['titles_cleaned']++;
	}

	/* ---- Merge person_role taxonomy terms ---------------------------------- */
	// Collect all role terms from duplicates that the canonical doesn't have.

	$canonical_terms = wp_get_post_terms( $canonical->ID, 'person_role', [ 'fields' => 'slugs' ] );
	if ( is_wp_error( $canonical_terms ) ) { $canonical_terms = []; }

	$terms_to_add = [];
	foreach ( $duplicates as $dup ) {
		$dup_terms = wp_get_post_terms( $dup->ID, 'person_role', [ 'fields' => 'slugs' ] );
		if ( is_wp_error( $dup_terms ) ) { continue; }
		foreach ( $dup_terms as $slug_term ) {
			if ( ! in_array( $slug_term, $canonical_terms, true ) &&
			     ! in_array( $slug_term, $terms_to_add, true ) ) {
				$terms_to_add[] = $slug_term;
			}
		}
	}

	if ( ! empty( $terms_to_add ) ) {
		WP_CLI::log( '  Adding roles to canonical: ' . implode( ', ', $terms_to_add ) );
		if ( $dry ) {
			WP_CLI::log( '  [dry] would add role terms' );
		} else {
			wp_set_post_terms( $canonical->ID, array_merge( $canonical_terms, $terms_to_add ), 'person_role' );
			WP_CLI::log( '  role terms merged' );
		}
		$summary['roles_merged']++;
	}

	/* ---- Rewrite webinar presenter references ------------------------------ */

	$dup_id_set = array_flip( $dup_ids ); // for O(1) lookup

	foreach ( $webinar_presenters as $wid => $current_ids ) {
		$overlap = array_intersect_key( array_flip( $current_ids ), $dup_id_set );
		if ( empty( $overlap ) ) {
			continue;
		}

		// Build the new presenter list: replace each dup ID with canonical ID,
		// then deduplicate (canonical may already be in the list).
		$new_ids = [];
		$canonical_added = in_array( $canonical->ID, $current_ids, true );

		foreach ( $current_ids as $pid ) {
			if ( isset( $dup_id_set[ $pid ] ) ) {
				// Replace dup with canonical (only add canonical once).
				if ( ! $canonical_added ) {
					$new_ids[]       = $canonical->ID;
					$canonical_added = true;
				}
			} else {
				$new_ids[] = $pid;
			}
		}
		$new_ids = array_values( array_unique( $new_ids ) );

		$wpost  = get_post( $wid );
		$wtitle = $wpost ? $wpost->post_title : "webinar #{$wid}";
		WP_CLI::log( sprintf(
			'  Webinar [%d] "%s": %s → %s',
			$wid,
			$wtitle,
			implode( ', ', $current_ids ),
			implode( ', ', $new_ids )
		) );

		if ( $dry ) {
			WP_CLI::log( "  [dry] would update presenters field" );
		} else {
			update_field( 'presenters', $new_ids, $wid );
			// Update our local map so subsequent groups see the updated state.
			$webinar_presenters[ $wid ] = $new_ids;
			WP_CLI::log( "  presenters updated" );
		}

		$summary['webinars_updated']++;
		$summary['presenter_refs_fixed'] += count( $overlap );
	}

	/* ---- Trash duplicates -------------------------------------------------- */

	foreach ( $duplicates as $dup ) {
		WP_CLI::log( sprintf( '  Trash: ID=%d "%s"', $dup->ID, $dup->post_title ) );
		if ( $dry ) {
			WP_CLI::log( '  [dry] would trash post' );
		} else {
			wp_trash_post( $dup->ID );
			WP_CLI::log( '  trashed' );
		}
		$summary['duplicates_trashed']++;
	}

	WP_CLI::log( '' );
}

/* ---- Summary -------------------------------------------------------------- */

WP_CLI::log( '== Summary ==' );
foreach ( $summary as $k => $v ) {
	WP_CLI::log( sprintf( '  %-28s %d', $k, $v ) );
}

WP_CLI::success( $dry
	? 'Dry run complete. Pass `live` to apply changes.'
	: 'Deduplication complete. Check trashed posts in WP admin before permanent deletion.'
);
