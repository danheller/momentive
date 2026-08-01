<?php
/**
 * AI-assisted Solution relevance tagging for resources.
 *
 * Problem this solves: the legacy site's "Featured Resources" admin screen
 * required a human to hand-pick posts per top-level solution CATEGORY
 * (~11 of them). That's too coarse for child solution pages — e.g. a
 * Ticketing page inherited whatever was picked for Fundraising broadly, so
 * "featured" content was often only tangentially related to the actual page
 * it appeared on. Re-doing that by hand at the CHILD solution level (~87 of
 * them) would be a worse editorial burden, not a better one — exactly the
 * kind of classification task worth delegating to an LLM instead.
 *
 * How it works:
 *   1. On publish/update of any resource post type (momentive_get_resource_
 *      post_types(), from inc/resources.php), a WP-Cron event is scheduled a
 *      short time later (so the editor's save isn't blocked on an API call).
 *   2. The cron callback sends the post's title/excerpt/content, plus the
 *      full list of child Solutions (name + short description), to Claude,
 *      and asks for the subset that's genuinely relevant.
 *   3. The result is written to the ordinary `relevant_solutions` ACF field
 *      (post_object, multiple — see acf-json/group_6a95a10cf001.json), so
 *      it's a normal, visible, editable field, not a hidden black box.
 *   4. momentive_query_resources_for_solution() (inc/resources.php) reads
 *      this field first, falling back to the pre-existing category-level
 *      mechanism only to top up remaining slots.
 *
 * Re-tagging is gated so it doesn't re-spend an API call on every save:
 * skipped when the post's content hasn't changed since the last successful
 * tag (tracked via a content hash in postmeta), and skipped entirely once an
 * editor has manually touched the field (tracked via
 * _momentive_relevance_manual_override) — an explicit AI re-tag is always
 * available via the "Re-tag Solution relevance (AI)" bulk action, which
 * clears that override flag.
 *
 * Requires one of:
 *   - a MOMENTIVE_ANTHROPIC_API_KEY constant defined in wp-config.php, or
 *   - a `momentive_anthropic_api_key` filter returning the key from
 *     wherever it's actually managed (env var, secrets plugin, etc.)
 * If neither is present, tagging silently no-ops (logged once per attempt)
 * rather than breaking saves.
 */

/* -----------------------------------------------------------------------
 * Scheduling
 * ---------------------------------------------------------------------*/

add_action( 'save_post', function ( int $post_id, WP_Post $post, bool $update ): void {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( 'publish' !== $post->post_status ) {
		return;
	}
	if ( ! in_array( $post->post_type, momentive_get_resource_post_types(), true ) ) {
		return;
	}

	momentive_maybe_schedule_relevance_tagging( $post_id );
}, 20, 3 );

/**
 * Queue (or skip) an AI relevance-tagging pass for a resource post.
 *
 * @param int  $post_id Resource post ID.
 * @param bool $force   Bypass the unchanged-content check and any manual
 *                       override flag — used by the bulk "Re-tag" action.
 */
function momentive_maybe_schedule_relevance_tagging( int $post_id, bool $force = false ): void {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	if ( $force ) {
		delete_post_meta( $post_id, '_momentive_relevance_manual_override' );
	} elseif ( get_post_meta( $post_id, '_momentive_relevance_manual_override', true ) ) {
		return; // an editor deliberately set this — don't auto-overwrite it
	} else {
		$hash          = momentive_resource_content_hash( $post );
		$existing_hash = get_post_meta( $post_id, '_momentive_relevance_hash', true );
		if ( '' !== $existing_hash && $existing_hash === $hash ) {
			return; // content unchanged since the last successful tag
		}
	}

	if ( wp_next_scheduled( 'momentive_tag_resource_relevance', [ $post_id ] ) ) {
		return; // already queued
	}

	// Deferred rather than run inline: keeps a slow/failed API call from
	// ever blocking or erroring an editor's save.
	wp_schedule_single_event( time() + 30, 'momentive_tag_resource_relevance', [ $post_id ] );
}

function momentive_resource_content_hash( WP_Post $post ): string {
	return md5( $post->post_title . '|' . $post->post_excerpt . '|' . $post->post_content );
}

add_action( 'momentive_tag_resource_relevance', 'momentive_tag_resource_relevance_now' );

function momentive_tag_resource_relevance_now( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return;
	}

	$candidates = momentive_get_taggable_child_solutions();
	if ( empty( $candidates ) ) {
		return;
	}

	$matched_ids = momentive_ask_llm_for_relevant_solutions( $post, $candidates );
	if ( null === $matched_ids ) {
		return; // API call failed or was unconfigured — leave hash alone so the next save retries
	}

	// Guard our own write so it isn't mistaken for a manual override (see
	// the acf/update_value listener below).
	$GLOBALS['momentive_relevance_autotagging'] = true;
	update_field( 'relevant_solutions', $matched_ids, $post_id );
	$GLOBALS['momentive_relevance_autotagging'] = false;

	update_post_meta( $post_id, '_momentive_relevance_hash', momentive_resource_content_hash( $post ) );
	update_post_meta( $post_id, '_momentive_relevance_tagged_at', time() );
}

/**
 * Once an editor changes `relevant_solutions` by hand (via the post edit
 * screen), stop auto-retagging that post until someone explicitly asks for
 * a re-tag (the bulk action below). Guarded against our own cron-driven
 * update_field() call via the $GLOBALS flag set in
 * momentive_tag_resource_relevance_now().
 */
add_filter( 'acf/update_value/name=relevant_solutions', function ( $value, $post_id, $field ) {
	if ( empty( $GLOBALS['momentive_relevance_autotagging'] ) ) {
		update_post_meta( $post_id, '_momentive_relevance_manual_override', 1 );
	}
	return $value;
}, 10, 3 );

/* -----------------------------------------------------------------------
 * Candidate list: every child Solution (top-level hub pages are excluded —
 * they're not the granularity this is trying to solve for).
 * ---------------------------------------------------------------------*/

/**
 * @return array<int,array{id:int,name:string,description:string}>
 */
function momentive_get_taggable_child_solutions(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$post_ids = get_posts( [
		'post_type'      => 'solutions',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	] );

	$cache = [];
	foreach ( $post_ids as $post_id ) {
		if ( ! wp_get_post_parent_id( $post_id ) ) {
			continue; // top-level hub page, not a taggable target
		}

		$description = get_the_excerpt( $post_id );
		if ( ! $description ) {
			$description = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 );
		}

		$cache[] = [
			'id'          => $post_id,
			'name'        => get_the_title( $post_id ),
			'description' => $description,
		];
	}

	return $cache;
}

/* -----------------------------------------------------------------------
 * The actual LLM call
 * ---------------------------------------------------------------------*/

/**
 * @param WP_Post                                            $post
 * @param array<int,array{id:int,name:string,description:string}> $candidates
 * @return int[]|null Matched solution IDs (possibly empty), or null on failure.
 */
function momentive_ask_llm_for_relevant_solutions( WP_Post $post, array $candidates ): ?array {
	$api_key = momentive_anthropic_api_key();
	if ( ! $api_key ) {
		error_log( 'Momentive relevance tagging: no Anthropic API key configured — define MOMENTIVE_ANTHROPIC_API_KEY in wp-config.php (see inc/resource-relevance.php).' );
		return null;
	}

	$body_text = mb_substr( wp_strip_all_tags( $post->post_content ), 0, 4000 );

	$solutions_list = implode( "\n", array_map(
		function ( array $c ): string {
			return sprintf( '- id:%d name:"%s" — %s', $c['id'], $c['name'], $c['description'] );
		},
		$candidates
	) );

	$prompt = "You are tagging a piece of marketing content with the specific solution pages it is genuinely relevant to.\n\n"
		. "Content title: {$post->post_title}\n"
		. "Content excerpt: {$post->post_excerpt}\n"
		. "Content body (may be truncated):\n{$body_text}\n\n"
		. "Candidate solution pages (id, name, description):\n{$solutions_list}\n\n"
		. "Return ONLY a JSON array of the matching solution ids (integers), most relevant first. "
		. "Only include a solution if the content is substantively about that specific topic — not just tangentially related. "
		. "Return an empty array [] if nothing is a clear match. Output nothing but the JSON array — no prose, no markdown fences.";

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 30,
		'headers' => [
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		],
		'body'    => wp_json_encode( [
			'model'      => momentive_relevance_model(),
			'max_tokens' => 512,
			'messages'   => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		error_log( 'Momentive relevance tagging: request failed for post ' . $post->ID . ' — ' . $response->get_error_message() );
		return null;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		error_log( 'Momentive relevance tagging: API returned ' . $code . ' for post ' . $post->ID . ' — ' . wp_remote_retrieve_body( $response ) );
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$text = trim( (string) ( $data['content'][0]['text'] ?? '' ) );
	$text = trim( preg_replace( '/^```(?:json)?|```$/m', '', $text ) );

	$ids = json_decode( $text, true );
	if ( ! is_array( $ids ) ) {
		error_log( 'Momentive relevance tagging: could not parse model response for post ' . $post->ID . ': ' . $text );
		return null;
	}

	$valid_ids = wp_list_pluck( $candidates, 'id' );

	// array_intersect keeps $ids' order (the model's relevance ranking),
	// dropping anything that isn't an actual candidate ID (hallucination guard).
	return array_values( array_intersect( array_map( 'intval', $ids ), $valid_ids ) );
}

/**
 * Model used for relevance tagging. Overridable without a code change via a
 * MOMENTIVE_RELEVANCE_MODEL constant — a fast/cheap model is plenty for this
 * classification task.
 */
function momentive_relevance_model(): string {
	return defined( 'MOMENTIVE_RELEVANCE_MODEL' ) ? MOMENTIVE_RELEVANCE_MODEL : 'claude-haiku-4-5-20251001';
}

/**
 * Resolve the Anthropic API key. Prefers a wp-config.php constant; falls
 * back to a filter for sites that manage secrets another way (env var,
 * a secrets-manager plugin, etc.).
 */
function momentive_anthropic_api_key(): string {
	if ( defined( 'MOMENTIVE_ANTHROPIC_API_KEY' ) && MOMENTIVE_ANTHROPIC_API_KEY ) {
		return (string) MOMENTIVE_ANTHROPIC_API_KEY;
	}
	return (string) apply_filters( 'momentive_anthropic_api_key', '' );
}

/* -----------------------------------------------------------------------
 * Manual "Re-tag" escape hatch — bulk action on each resource post type's
 * list table. Clears the manual-override flag and forces a fresh AI pass,
 * e.g. after adding new child solutions that older content should now
 * consider.
 * ---------------------------------------------------------------------*/

add_action( 'admin_init', function (): void {
	foreach ( momentive_get_resource_post_types() as $post_type ) {
		add_filter( "bulk_actions-edit-{$post_type}", function ( array $actions ): array {
			$actions['momentive_retag_relevance'] = __( 'Re-tag Solution relevance (AI)', 'momentive' );
			return $actions;
		} );

		add_filter( "handle_bulk_actions-edit-{$post_type}", function ( string $redirect_to, string $action, array $post_ids ) {
			if ( 'momentive_retag_relevance' !== $action ) {
				return $redirect_to;
			}
			foreach ( $post_ids as $post_id ) {
				momentive_maybe_schedule_relevance_tagging( (int) $post_id, true );
			}
			return add_query_arg( 'momentive_retagged', count( $post_ids ), $redirect_to );
		}, 10, 3 );
	}
} );

add_action( 'admin_notices', function (): void {
	if ( empty( $_GET['momentive_retagged'] ) ) {
		return;
	}
	$count = (int) $_GET['momentive_retagged'];
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf(
			/* translators: %d: number of posts queued */
			_n( 'Queued %d post for AI Solution-relevance re-tagging — check back in a minute.', 'Queued %d posts for AI Solution-relevance re-tagging — check back in a minute.', $count, 'momentive' ),
			$count
		) )
	);
} );
