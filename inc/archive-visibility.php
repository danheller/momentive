<?php
/**
 * Archive visibility controls.
 *
 * Two ACF true/false fields (created in the ACF UI, versioned via acf-json/)
 * let editors hide posts from archive listings:
 *
 *   hide_from_archives        — excludes from CPT archives, the blog index,
 *                               and the Resource Center.
 *   hide_from_resource_center — excludes from the Resource Center only; the
 *                               post still appears on its CPT archive. ACF
 *                               conditional logic shows this field only when
 *                               hide_from_archives is off.
 *
 * ACF field group to create in the UI:
 *   Title:    Archive Visibility
 *   Location: post_type == post
 *          OR post_type == case-study
 *          OR post_type == webinar
 *          OR post_type == whitepaper
 *          OR post_type == infographic
 *          OR post_type == guide
 *          OR post_type == video
 *          OR post_type == event
 *          OR post_type == press-article
 *          OR post_type == toolkit
 *          OR post_type == interactive-tool
 *   Fields:
 *     1. hide_from_archives (true/false)
 *        Label: "Hide from all archives"
 *        Instructions: "Excludes this post from CPT archives, the blog index,
 *        and the Resource Center. Use for client-only or campaign-exclusive content."
 *     2. hide_from_resource_center (true/false)
 *        Label: "Hide from resource center only"
 *        Instructions: "Excludes from the Resource Center grid but the post
 *        still appears on the CPT archive."
 *        Conditional logic: show when hide_from_archives != 1
 *
 * The Resource Center REST route exclusion lives in inc/resources.php —
 * search for "hide_from_archives" to find it.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Exclude hide_from_archives posts from CPT archives and the blog index.
 *
 * Uses pre_get_posts so it applies to the main archive query. The Resource
 * Center (AJAX/REST) exclusion is handled separately in inc/resources.php and
 * covers both hide_from_archives and hide_from_resource_center.
 */
add_action( 'pre_get_posts', 'momentive_archive_visibility_filter' );

function momentive_archive_visibility_filter( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Blog archive (post CPT uses is_home(), not is_post_type_archive()).
	$is_blog = $query->is_home();

	// CPT archives for any registered resource type.
	$resource_types    = function_exists( 'momentive_get_resource_post_types' )
		? momentive_get_resource_post_types()
		: [];
	$queried_type      = $query->get( 'post_type' );
	$is_resource_archive = $query->is_post_type_archive()
		&& in_array( $queried_type, $resource_types, true );

	if ( ! $is_blog && ! $is_resource_archive ) {
		return;
	}

	$existing   = $query->get( 'meta_query' ) ?: [];
	$existing[] = [
		'relation' => 'OR',
		[ 'key' => 'hide_from_archives', 'compare' => 'NOT EXISTS' ],
		[ 'key' => 'hide_from_archives', 'value' => '1', 'compare' => '!=' ],
	];
	$query->set( 'meta_query', $existing );
}
