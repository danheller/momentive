<?php
/**
 * Title: Solution Features Overview (Icon Grid)
 * Slug: momentive/solution-features-icon-grid
 * Description: "Features Overview" section, icon-grid layout — a centered intro
 *              followed by a 3-across grid of icon + heading + description cards.
 *              This and momentive/solution-features-checklist are two layout variants
 *              of the SAME conceptual section (see that pattern's description) — pick
 *              one based on whether each feature needs its own short description, not
 *              both.
 *
 *              The rebuilt Association Event Management example links each card's
 *              heading to a related solution page — that's a manual addition, not
 *              something to bake into the placeholder here, since which page to link
 *              to is different every time.
 * Categories: momentive-section
 * Post Types: solutions
 * Inserter: true
 */
?>
<!-- wp:group {"className":"to-edge is-style-motion-blur top","backgroundColor":"neutral","layout":{"type":"constrained"}} -->
<div class="wp-block-group to-edge is-style-motion-blur top has-neutral-background-color has-background"><!-- wp:group {"className":"narrow","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|small"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group narrow" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--small)"><!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center is-style-eyebrow">Features Overview</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"center"}},"placeholder":"[Solution] tools"} -->
<h2 class="wp-block-heading has-text-align-center balance"></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:columns {"className":"is-style-boxed"} -->
<div class="wp-block-columns is-style-boxed"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:momentive/icon-block {"iconId":"bx-check-circle"} /-->

<!-- wp:heading {"level":3,"placeholder":"Feature name"} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"One short sentence describing this feature."} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:momentive/icon-block {"iconId":"bx-check-circle"} /-->

<!-- wp:heading {"level":3,"placeholder":"Feature name"} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"One short sentence describing this feature."} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:momentive/icon-block {"iconId":"bx-check-circle"} /-->

<!-- wp:heading {"level":3,"placeholder":"Feature name"} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"One short sentence describing this feature."} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
