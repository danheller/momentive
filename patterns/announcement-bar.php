<?php
/**
 * Template Part: Announcement Bar
 *
 * Displays a dismissible announcement bar above the site header.
 * Dismissal is stored in a sitewide cookie (not page-scoped) so
 * closing the bar on any page hides it everywhere until the cookie expires.
 *
 * To include in your theme, add the following to header.php (or your
 * FSE theme's parts/header.html via a PHP block / custom block):
 *
 *   <?php get_template_part( 'template-parts/announcement-bar' ); ?>
 *
 * Customise the content via the constants / filter at the top of this file.
 */

// ── Configuration ────────────────────────────────────────────────────────────

/**
 * Filter: momentive_announcement_bar_args
 *
 * Return an associative array to override any of the defaults below.
 *
 * @param array $args Default bar configuration.
 * @return array
 */
$bar = apply_filters( 'momentive_announcement_bar_args', array(
	// Main announcement text (plain text or simple inline HTML – no block tags).
	'text'         => 'Explore AI Resources for Mission-Driven Organizations.',

	// URL the "Learn More" link points to.
	'link_url'     => 'ai-resource-hub/',

	// Label for the CTA link.
	'link_label'   => 'Learn More',

	// Cookie name.  Change if you ever need to force the bar to reappear.
	'cookie_name'  => 'momentive_announcement_dismissed',

	// Cookie lifetime in days.
	'cookie_days'  => 30,

	// Optional extra CSS classes on the outer <div>.
	'extra_classes' => '',
) );

// ── Early-out: already dismissed ─────────────────────────────────────────────

// If the cookie is already set we output nothing (server-side guard).
// The JS below also handles the dismiss action so the bar never flickers.
if ( ! empty( $_COOKIE[ $bar['cookie_name'] ] ) ) {
	return;
}

// ── Sanitise ─────────────────────────────────────────────────────────────────

$cookie_name  = sanitize_key( $bar['cookie_name'] );
$cookie_days  = absint( $bar['cookie_days'] );
$link_url     = esc_url( $bar['link_url'] );
$link_label   = esc_html( $bar['link_label'] );
$extra_classes = esc_attr( $bar['extra_classes'] );

?>
<div
	id="momentive-announcement-bar"
	class="announcement-bar<?php echo $extra_classes ? ' ' . $extra_classes : ''; ?>"
	role="region"
	aria-label="<?php esc_attr_e( 'Site announcement', 'momentive' ); ?>"
>
	<div class="wrapper">
		<span class="icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><path fill="#3B88C3" d="M14 19c-3.314 0-6 2.687-6 6s2.686 6 6 6 6-2.687 6-6-2.687-6-6-6zm0 10c-2.209 0-4-1.791-4-4s1.791-4 4-4 4 1.791 4 4-1.791 4-4 4z"/><path fill="#55ACEE" d="M1.783 14.023v.02C.782 14.263 0 15.939 0 18s.782 3.737 1.783 3.956v.021l28.701 7.972V6.064L1.783 14.023z"/><ellipse fill="#269" cx="31" cy="18" rx="5" ry="12"/></svg>
		</span>

		<p class="announcement">
			<?php echo wp_kses( $bar['text'], array( 'strong' => array(), 'em' => array(), 'span' => array( 'class' => array() ) ) ); ?>
			<a href="<?php echo $link_url; ?>" class="link">
				<?php echo $link_label; ?>
			</a>
		</p>

	</div>

	<button
		class="close"
		id="momentive-announcement-close"
		aria-label="<?php esc_attr_e( 'Dismiss announcement', 'momentive' ); ?>"
		type="button"
	>
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" focusable="false">
			<line x1="18" y1="6" x2="6" y2="18"/>
			<line x1="6" y1="6" x2="18" y2="18"/>
		</svg>
	</button>
</div>

<script>
( function () {
	'use strict';

	var COOKIE_NAME = <?php echo json_encode( $cookie_name ); ?>;
	var COOKIE_DAYS = <?php echo (int) $cookie_days; ?>;

	/**
	 * Set a cookie that is valid across the entire domain (path=/),
	 * which means closing the bar on one page dismisses it everywhere.
	 */
	function setDismissedCookie() {
		var expires = new Date();
		expires.setDate( expires.getDate() + COOKIE_DAYS );
		document.cookie =
			encodeURIComponent( COOKIE_NAME ) + '=1' +
			'; expires=' + expires.toUTCString() +
			'; path=/'  +                          // ← sitewide, not page-scoped
			'; SameSite=Lax';
	}

	function dismissBar() {
		var bar = document.getElementById( 'momentive-announcement-bar' );
		if ( ! bar ) { return; }

		// Animate out, then remove from DOM so it doesn't affect layout.
		bar.classList.add( 'is-dismissed' );
		bar.addEventListener( 'transitionend', function () {
			bar.remove();
			// Unset the CSS custom property so the sticky header sits flush.
			document.documentElement.style.removeProperty( '--announcement-bar-height' );
		}, { once: true } );

		setDismissedCookie();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		// Wire up the close button.
		var btn = document.getElementById( 'momentive-announcement-close' );
		if ( btn ) {
			btn.addEventListener( 'click', dismissBar );
		}

		// Keep a CSS custom property in sync with the bar's rendered height so
		// the sticky header (and any scroll-margin-top values) can offset correctly.
		var bar = document.getElementById( 'momentive-announcement-bar' );
		if ( ! bar ) { return; }

		function updateHeight() {
			document.documentElement.style.setProperty(
				'--announcement-bar-height',
				bar.offsetHeight + 'px'
			);
		}

		updateHeight();

		// Re-measure on resize (e.g. text reflows on narrow viewports).
		var ro = window.ResizeObserver
			? new ResizeObserver( updateHeight )
			: null;

		if ( ro ) {
			ro.observe( bar );
		} else {
			window.addEventListener( 'resize', updateHeight );
		}
	} );
} () );
</script>
