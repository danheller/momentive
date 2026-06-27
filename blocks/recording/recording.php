<?php
/**
 * Recording Video block.
 *
 * A simple renderer for a recording's video embed (video_embed_code from the
 * shared Recording field group). Host-agnostic: works for any post type that
 * carries the Recording fields (webinars today, product overviews later).
 *
 * It does NOT decide whether the recording view is active — that's the template
 * branch's job (get_query_var('recording_view')). This block just outputs the
 * player wherever the recording pattern places it.
 *
 * The embed is third-party markup (Wistia / Vimeo / YouTube script or iframe),
 * so it's echoed raw — it can't be escaped without breaking the player. Safe
 * because only admins edit the field, same as the HubSpot embed block.
 */

$is_preview = $is_preview ?? false;
$post_id    = get_the_ID();

// Source: 'post' pulls the recording from the post's Recording field group
// (the default — used by webinars and any recording-host post). 'block' uses a
// one-off embed entered directly on this block.
$source = get_field( 'recording_source' ) ?: 'post';

if ( 'block' === $source ) {
	$embed = trim( (string) get_field( 'block_video_embed_code' ) );
} else {
	$embed = $post_id ? trim( (string) get_field( 'video_embed_code', $post_id ) ) : '';
}

// ── Empty state ─────────────────────────────────────────────────────────────
if ( '' === $embed ) {
	if ( $is_preview ) {
		$hint = ( 'block' === $source )
			? __( 'Recording Video — add a video embed code in this block\'s settings.', 'momentive' )
			: __( 'Recording Video — add a video embed code in the Recording panel.', 'momentive' );
		$attrs = get_block_wrapper_attributes( [ 'class' => 'recording is-empty' ] );
		printf(
			'<div %s style="padding:2rem;border:1px dashed #ccc;text-align:center;color:#666;">%s</div>',
			$attrs,
			esc_html( $hint )
		);
	}
	return; // front end renders nothing if no embed
}

// ── Render ──────────────────────────────────────────────────────────────────
$attrs = get_block_wrapper_attributes( [ 'class' => 'recording' ] );
?>
<div <?php echo $attrs; ?>>
	<div class="recording__player">
		<?php echo $embed; // raw third-party embed, intentionally unescaped ?>
	</div>
</div>
