<?php
/**
 * Title: Solution Feature (Image + Text)
 * Slug: momentive/solution-media-feature
 * Description: A single image-and-text feature row, meant to be inserted once per
 *              feature and stacked to build a "Features" section (see the alternating
 *              media blocks in the Member Management example — 6 of these, back to
 *              back). Deliberately NOT wrapped in a locked section or paired with an
 *              intro heading, so it stays a small, freely-duplicatable unit rather than
 *              a fixed-count block editors have to fight.
 * Categories: momentive-section
 * Post Types: solutions
 * Inserter: true
 *
 * To alternate image side, use the block's own "Media & Text" toolbar control
 * (media-text has a built-in "show media on right" toggle) rather than keeping two
 * separate left/right pattern variants in sync by hand.
 */
?>
<!-- wp:media-text {"mediaType":"image","linkDestination":"none","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|medium"}}}} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><figure class="wp-block-media-text__media"><img src="" alt="" /></figure><div class="wp-block-media-text__content">
<!-- wp:heading {"level":3,"fontSize":"xx-large","placeholder":"Feature headline"} -->
<h3 class="wp-block-heading has-xx-large-font-size"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences describing this feature and the benefit it delivers."} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->
