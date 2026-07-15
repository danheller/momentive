<?php
/**
 * Pattern: Stats — Centered Three Up
 *
 * Register via your theme's patterns/ directory (WordPress auto-registers
 * any .php file placed there), or call register_block_pattern() manually.
 *
 * Slug: momentive/stats-centered-three-up
 *
 * Usage: Drop into theme-root/patterns/stats-centered-three-up.php
 */

/**
 * @var array $args
 */
?>
<!-- wp:group {"style":{"color":{"background":"#1B2559"},"border":{"radius":"16px"},"spacing":{"padding":{"top":"clamp(3rem, 6vw, 5rem)","bottom":"clamp(3rem, 6vw, 5rem)","left":"clamp(1.5rem, 4vw, 4rem)","right":"clamp(1.5rem, 4vw, 4rem)"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="border-radius:16px;background-color:#1B2559;padding-top:clamp(3rem, 6vw, 5rem);padding-bottom:clamp(3rem, 6vw, 5rem);padding-left:clamp(1.5rem, 4vw, 4rem);padding-right:clamp(1.5rem, 4vw, 4rem)">

	<!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"fontWeight":"700"},"spacing":{"margin":{"bottom":"clamp(2rem, 4vw, 3.5rem)"}}},"textColor":"white"} -->
	<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color" style="font-weight:700;margin-bottom:clamp(2rem, 4vw, 3.5rem)">Power Mission Success with MIP and GiveSmart</h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"left":"clamp(2rem, 4vw, 3rem)"}}}} -->
	<div class="wp-block-columns is-not-stacked-on-mobile">

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:momentive/impact-stat {"statPrefix":"$","statNumber":35.5,"statSuffix":"M","statLabel":"generated in total revenue","accentColor":"#E8611A"} /-->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:momentive/impact-stat {"statPrefix":"$","statNumber":144,"statSuffix":"K","statLabel":"raised on average per organization","accentColor":"#7B61FF"} /-->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:momentive/impact-stat {"statPrefix":"","statNumber":1000,"statSuffix":"s","statLabel":"of organizations grew with these powerful platforms","accentColor":"#3B82F6"} /-->
		</div>
		<!-- /wp:column -->

	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->
