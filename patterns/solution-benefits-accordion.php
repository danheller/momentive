<?php
/**
 * Title: Solution Benefits Accordion
 * Slug: momentive/solution-benefits-accordion
 * Description: Two-column "Benefits" section — intro copy on the left, an icon-style
 *              accordion on the right. This is the section already used in
 *              patterns/solution-content.php; registered separately here so it can
 *              also be inserted on its own into a page that started from a different
 *              pattern, or re-added after being deleted.
 * Categories: momentive-section
 * Post Types: solutions
 * Inserter: true
 *
 * Guardrails: the two-column layout is templateLock:contentOnly (structure locked,
 * content editable). The accordion's items are edited through the accordion block's
 * own inspector controls, not the template lock, so nothing here restricts adding,
 * removing, or reordering accordion items.
 */
?>
<!-- wp:group {"layout":{"type":"constrained"},"templateLock":"contentOnly"} -->
<div class="wp-block-group"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow">Benefits</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"h2","fontSize":"xxx-large","placeholder":"Short headline about this solution's key benefits"} -->
<p class="h2 has-xxx-large-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"Two or three sentences expanding on the headline. What does this solution help customers do?"} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:momentive/accordion {"style":"icon","openFirst":true,"items":[{"_key":"item1","question":"Benefit One","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""},{"_key":"item2","question":"Benefit Two","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""},{"_key":"item3","question":"Benefit Three","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""}]} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
