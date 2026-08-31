<?php
/**
 * Announcement Bar Settings — ACF Options sub-page.
 *
 * Registers "Announcement Bar" under Settings in the WP admin, then
 * merges saved field values into the bar via momentive_announcement_bar_args.
 *
 * Fields (all stored as options):
 *   announcement_bar_enabled    — true/false toggle
 *   announcement_bar_text       — main copy (inline HTML: strong, em, span)
 *   announcement_bar_link_url   — CTA href
 *   announcement_bar_link_label — CTA text
 *   announcement_bar_cookie_name  — change to force bar to reappear for past-dismissers
 *   announcement_bar_cookie_days  — cookie lifetime in days
 */

// ── Options sub-page ─────────────────────────────────────────────────────────

add_action( 'init', function () : void {
	if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;

	acf_add_options_sub_page( [
		'page_title'  => 'Announcement Bar',
		'menu_title'  => 'Announcement Bar',
		'menu_slug'   => 'momentive-announcement-bar',
		'parent_slug' => 'options-general.php',
		'capability'  => 'manage_options',
	] );
} );

// ── Merge ACF option values into bar args ────────────────────────────────────

add_filter( 'momentive_announcement_bar_args', function ( array $args ) : array {
	if ( ! function_exists( 'get_field' ) ) return $args;

	$text        = get_field( 'announcement_bar_text',        'option' );
	$link_url    = get_field( 'announcement_bar_link_url',    'option' );
	$link_label  = get_field( 'announcement_bar_link_label',  'option' );
	$cookie_name = get_field( 'announcement_bar_cookie_name', 'option' );
	$cookie_days = get_field( 'announcement_bar_cookie_days', 'option' );

	if ( $text )        $args['text']        = $text;
	if ( $link_url )    $args['link_url']    = $link_url;
	if ( $link_label )  $args['link_label']  = $link_label;
	if ( $cookie_name ) $args['cookie_name'] = sanitize_key( $cookie_name );
	if ( $cookie_days ) $args['cookie_days'] = absint( $cookie_days );

	return $args;
}, 5 ); // Priority 5 — runs before the example filter in functions.php (priority 10),
        // so code-level overrides there still win if needed.

// ── Enabled check (called from momentive_render_announcement_bar) ─────────────

/**
 * Returns false when the admin has explicitly toggled the bar off.
 * Returns true in all other cases (field not yet saved = default on).
 */
function momentive_announcement_bar_is_enabled() : bool {
	if ( ! function_exists( 'get_field' ) ) return true;
	// true_false field: returns 1 (on), 0 (off), or false (never saved → default on).
	$value = get_field( 'announcement_bar_enabled', 'option' );
	return ( $value !== 0 && $value !== '0' );
}
