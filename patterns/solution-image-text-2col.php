<?php
/**
 * Title: Solution Image + Text (2 Columns)
 * Slug: momentive/solution-image-text-2col
 * Description: "Image + Text, 2 columns" section (legacy field prefix
 *              image__text_2_cols_-_*, 18 of 87 child solutions) — left
 *              column is a heading + paragraph, right column is a single
 *              image with its own smaller heading/description overlaid via
 *              media-text. Distinct from the always-on Features section
 *              (which alternates full-width momentive/solution-media-feature
 *              rows) — this is a single fixed two-column layout, matching
 *              migrate-solutions.php's momentive_sol_image_text_2cols_block().
 * Categories: momentive-section
 * Post Types: solutions
 * Inserter: true
 *
 * Guardrails: the outer alignfull wrapper and column split are
 * templateLock:contentOnly — editors can't add a third column or remove
 * either side, but every heading, paragraph, and image stays editable.
 */
?>
<!-- wp:group {"className":"alignfull no-margin","templateLock":"contentOnly"} -->
<div class="wp-block-group alignfull no-margin"><!-- wp:columns {"className":"content-width","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} -->
<div class="wp-block-columns content-width" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)"><!-- wp:column {"verticalAlignment":"center","width":"50%","className":"no-padding"} -->
<div class="wp-block-column is-vertically-aligned-center no-padding" style="flex-basis:50%"><!-- wp:heading {"placeholder":"Section headline"} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences of supporting copy."} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:media-text {"linkDestination":"none","mediaType":"image","className":"is-style-stacked"} -->
<div class="wp-block-media-text is-stacked-on-mobile is-style-stacked"><figure class="wp-block-media-text__media"><img src="" alt="" /></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3,"placeholder":"Image caption headline"} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One short sentence."} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
