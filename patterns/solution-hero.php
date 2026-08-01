<?php
/**
 * Title: Solution Hero (Gradient)
 * Slug: momentive/solution-hero
 * Description: Two-column hero for solution child pages — light gradient background,
 *              swoop-style headline, single CTA. This is the hero shape actually in use
 *              on rebuilt child pages (see the Member Management / Association Event
 *              Management examples), which has moved on from the is-style-bg-dots hero
 *              baked into solution-content.php's starter template. Once this is the
 *              agreed hero, update solution-content.php to match so new posts start here.
 * Categories: momentive-hero
 * Post Types: solutions
 * Inserter: true
 *
 * Guardrails: the outer wrapper is lock-protected against being moved or removed
 * (it should always be the first section after the breadcrumb bar). The two-column
 * layout itself is templateLock:contentOnly — editors can't add a third column or
 * delete the image column, but every heading, paragraph, button, and image inside
 * is fully editable.
 */
?>
<!-- wp:group {"className":"hero-background","style":{"color":{"gradient":"linear-gradient(0deg,rgb(255,255,255) 0%,rgb(239,249,253) 100%)"}},"layout":{"type":"constrained"},"lock":{"move":true,"remove":true}} -->
<div class="wp-block-group hero-background has-background" style="background:linear-gradient(0deg,rgb(255,255,255) 0%,rgb(239,249,253) 100%)"><!-- wp:group {"className":"hero","align":"full","layout":{"type":"constrained","wideSize":"","contentSize":""},"templateLock":"contentOnly"} -->
<div class="wp-block-group alignfull hero"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"left"}},"placeholder":"Solution Name"} -->
<h1 class="wp-block-heading has-text-align-left">Solution Name</h1>
<!-- /wp:heading -->

<!-- wp:heading {"className":"is-style-has-swoop","fontSize":"display","placeholder":"One short, punchy line — wrap the key phrase in **bold**"} -->
<h2 class="wp-block-heading is-style-has-swoop has-display-font-size"><strong>Key phrase</strong> goes here for the swoop underline</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"left"}},"placeholder":"One or two sentences describing this solution and its core value proposition."} -->
<p class="has-text-align-left"></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"flex","justifyContent":"left"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#form">Get Started</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="" alt="" /></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
