<?php
/**
 * Wistia Popover Video block — render template.
 *
 * Fields:
 *   media_id         (text)       Wistia media ID, e.g. "sbkdhn0omw"
 *   poster_image     (image/array) Optional poster — returned as array with
 *                                  url, alt, width, height keys.
 *   show_play_button (true/false)  Overlay a CSS-drawn play button on the
 *                                  poster. Default true. Turn off when the
 *                                  poster image already has a play button
 *                                  baked in.
 *
 * How it works:
 *   The Wistia web component (<wistia-player>) renders a popover player.
 *   The trigger element (poster image) is passed via a named slot — the
 *   slot name must be exactly "wistia-{media-id}-popover-link". Clicking
 *   the trigger opens the video in a Wistia-managed popover lightbox.
 *   player.js is enqueued separately in block.php so it isn't inlined here.
 */

$is_preview = $is_preview ?? false;
$media_id   = trim( (string) get_field( 'media_id' ) );
$poster     = get_field( 'poster_image' );                        // array or null
$show_play  = (bool) get_field( 'show_play_button' );

// ── Editor placeholder ───────────────────────────────────────────────────────
if ( '' === $media_id ) {
	if ( $is_preview ) {
		$attrs = get_block_wrapper_attributes( [ 'class' => 'wistia-popover is-empty' ] );
		printf(
			'<div %s style="padding:2rem;border:1px dashed #ccc;text-align:center;color:#666;">%s</div>',
			$attrs,
			esc_html__( 'Wistia Popover — enter a Wistia Media ID in the block settings.', 'momentive' )
		);
	}
	return; // render nothing on the front end if no media ID
}

// ── Slot name — must match exactly ──────────────────────────────────────────
$slot = 'wistia-' . esc_attr( $media_id ) . '-popover-link';

// ── Trigger / poster markup ──────────────────────────────────────────────────
if ( $poster && ! empty( $poster['url'] ) ) {
	$figure_class = 'wistia-popover__poster' . ( $show_play ? ' has-play-button' : '' );
	$play_span    = $show_play ? '<span class="wistia-popover__play" aria-hidden="true"></span>' : '';
	$trigger = sprintf(
		'<figure class="%s"><img src="%s" alt="%s" loading="lazy"%s>%s</figure>',
		esc_attr( $figure_class ),
		esc_url( $poster['url'] ),
		esc_attr( $poster['alt'] ?? '' ),
		( ! empty( $poster['width'] ) && ! empty( $poster['height'] ) )
			? ' width="' . (int) $poster['width'] . '" height="' . (int) $poster['height'] . '"'
			: '',
		$play_span
	);
} else {
	// No poster set — plain text link (editor can always add a poster later).
	$trigger = '<a href="#" class="wistia-popover__text-link">' . esc_html__( 'Watch video', 'momentive' ) . '</a>';
}

// ── Output ───────────────────────────────────────────────────────────────────
$attrs = get_block_wrapper_attributes( [ 'class' => 'wistia-popover' ] );
?>
<div <?php echo $attrs; ?>>
	<wistia-player
		media-id="<?php echo esc_attr( $media_id ); ?>"
		popover-content="link"
		wistia-popover="true"
		aspect="1.7777777777777777"
	>
		<div slot="<?php echo $slot; ?>">
			<?php echo $trigger; ?>
		</div>
	</wistia-player>
</div>
