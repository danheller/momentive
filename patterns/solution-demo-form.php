<?php
/**
 * Title: Solution Demo Form
 * Slug: momentive/solution-demo-form
 * Description: The "Request a Demo" section already used at the bottom of
 *              patterns/solution-content.php — registered separately so it can be
 *              re-inserted on a page that started from a different pattern, or
 *              re-added after being deleted.
 * Categories: momentive-cta
 * Post Types: solutions
 * Inserter: true
 *
 * Guardrails: the outer section and its two-column layout are
 * templateLock:contentOnly — this keeps the HubSpot form block from being
 * accidentally deleted or the layout restructured, while copy, heading, and image
 * on the left stay fully editable.
 */
?>
<!-- wp:group {"className":"demo-form is-style-ellipse-bottom","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|medium","top":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"},"templateLock":"contentOnly"} -->
<div class="wp-block-group demo-form is-style-ellipse-bottom" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow">Request a Demo</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"placeholder":"Headline for the demo form section"} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences about this solution to accompany the demo request form."} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-rounder"} -->
<figure class="wp-block-image size-large is-style-rounder"><img src="" alt="" /></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column --><div class="wp-block-column"><!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview","lock":{"move":true,"remove":true}} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
