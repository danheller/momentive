<?php
/**
 * Webinar Schedule block.
 *
 * Renders the webinar's date / time / timezone. Disappears once the webinar is
 * on-demand (the event has passed), since a past date isn't useful on the
 * recording view. In the editor we show a muted placeholder instead of nothing,
 * so the block doesn't look broken when placed on an on-demand webinar.
 *
 * Reads from the Webinar Settings field group:
 *   webinar_date (Ymd), webinar_end_date (Ymd),
 *   webinar_time_start / webinar_time_end (g:i a), webinar_timezone (text)
 */

$is_preview = $is_preview ?? false;
// ACF passes $post_id into the render template; fall back to get_the_ID()
// as a safety net for FSE template contexts where the loop may not be set.
$post_id = $post_id ?? get_the_ID();

$status = function_exists( 'momentive_webinar_status' )
	? momentive_webinar_status( $post_id )
	: 'upcoming';

// ── Front end: render nothing once on-demand ────────────────────────────────
if ( 'on-demand' === $status && ! $is_preview ) {
	return;
}

// ── Build the display string ────────────────────────────────────────────────
$date_raw     = get_field( 'webinar_date', $post_id );      // 'Ymd' or ''
$end_date_raw = get_field( 'webinar_end_date', $post_id );  // 'Ymd' or ''
$time_start   = get_field( 'webinar_time_start', $post_id ); // 'g:i a' or ''
$time_end     = get_field( 'webinar_time_end', $post_id );   // 'g:i a' or ''
$timezone     = get_field( 'webinar_timezone', $post_id );

$date_display = '';
if ( $date_raw ) {
	$start_dt = DateTime::createFromFormat( 'Ymd', $date_raw );
	$end_dt   = $end_date_raw ? DateTime::createFromFormat( 'Ymd', $end_date_raw ) : false;

	if ( $start_dt ) {
		if ( $end_dt && $end_dt->format( 'Ymd' ) !== $start_dt->format( 'Ymd' ) ) {
			// Multi-day range.
			if ( $start_dt->format( 'Y-m' ) === $end_dt->format( 'Y-m' ) ) {
				// Same month: "June 21–23, 2026"
				$date_display = $start_dt->format( 'F j' ) . '–' . $end_dt->format( 'j, Y' );
			} elseif ( $start_dt->format( 'Y' ) === $end_dt->format( 'Y' ) ) {
				// Same year, different month: "June 30 – July 2, 2026"
				$date_display = $start_dt->format( 'F j' ) . ' – ' . $end_dt->format( 'F j, Y' );
			} else {
				// Spanning years: "Dec 31, 2026 – Jan 2, 2027"
				$date_display = $start_dt->format( 'F j, Y' ) . ' – ' . $end_dt->format( 'F j, Y' );
			}
		} else {
			// Single day.
			$date_display = $start_dt->format( 'F j, Y' );
		}
	}
}

$time_display = '';
if ( $time_start ) {
	$time_display = $time_end ? "{$time_start} – {$time_end}" : $time_start;
	if ( $timezone ) {
		$time_display .= " {$timezone}";
	}
}

// ── Editor placeholder when on-demand (front end already returned above) ─────
if ( 'on-demand' === $status && $is_preview ) {
	$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'webinar-schedule is-editor-hidden' ] );
	printf(
		'<div %s style="opacity:.6;font-style:italic;">%s</div>',
		$wrapper_attrs,
		esc_html__( 'Webinar Schedule — hidden on the front end because this webinar is on-demand.', 'momentive' )
	);
	return;
}

// ── Nothing to show (no date set) ───────────────────────────────────────────
if ( ! $date_display && ! $time_display ) {
	if ( $is_preview ) {
		$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'webinar-schedule is-empty' ] );
		printf(
			'<div %s style="opacity:.6;font-style:italic;">%s</div>',
			$wrapper_attrs,
			esc_html__( 'Webinar Schedule — add a date and time in the Webinar Settings panel.', 'momentive' )
		);
	}
	return;
}

// ── Normal render ───────────────────────────────────────────────────────────
$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'webinar-schedule' ] );
?>
<div <?php echo $wrapper_attrs; ?>>
	<?php if ( $date_display ) : ?>
		<span class="webinar-schedule__date">
			<?php echo momentive_render_icon( 'calendar-days-full', 'class="webinar-schedule__icon"' ); ?>
			<?php echo esc_html( $date_display ); ?>
		</span>
	<?php endif; ?>
	<?php if ( $time_display ) : ?>
		<span class="webinar-schedule__time">
			<?php echo momentive_render_icon( 'clock-full', 'class="webinar-schedule__icon"' ); ?>
			<?php echo esc_html( $time_display ); ?>
		</span>
	<?php endif; ?>
</div>
