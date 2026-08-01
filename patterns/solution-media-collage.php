<?php
/**
 * Title: Solution Benefits (Media Collage)
 * Slug: momentive/solution-media-collage
 * Description: "Benefits" section, media-collage layout (legacy field prefix
 *              benefits_-_*, 17 of 87 child solutions) — a centered heading
 *              and description over a main image with two smaller floating
 *              circle images layered on top. Matches the collage already
 *              built on the rebuilt Fundraising hub page and the shape
 *              migrate-solutions.php's momentive_sol_benefits_media_block()
 *              generates — this pattern exists so the same section can be
 *              dropped into a page by hand (e.g. while hand-building an
 *              example ahead of running the full migration script), using
 *              the exact same content-collage / circle-left / circle-right
 *              classes so both paths render identically.
 * Categories: momentive-section
 * Post Types: solutions
 * Inserter: true
 *
 * Guardrails: the outer collage wrapper is templateLock:contentOnly so the
 * heading/paragraph/three images stay editable but the group nesting and
 * circle-left/circle-right image classes (which position the floating
 * images via CSS) can't be accidentally restructured.
 */
?>
<!-- wp:group {"className":"content-collage wide is-style-bg-dots is-style-ellipse-top","layout":{"type":"constrained"},"templateLock":"contentOnly"} -->
<div class="wp-block-group content-collage wide is-style-bg-dots is-style-ellipse-top"><!-- wp:group {"className":"content-collage-inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group content-collage-inner"><!-- wp:heading {"placeholder":"Benefits section headline"} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"One or two sentences describing the benefit this section highlights."} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"content-collage-images","layout":{"type":"constrained"}} -->
<div class="wp-block-group content-collage-images"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"horizontal"} -->
<figure class="wp-block-image size-large horizontal"><img src="" alt="" /></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"circle circle-left"} -->
<figure class="wp-block-image size-large circle circle-left"><img src="" alt="" /></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"circle circle-right"} -->
<figure class="wp-block-image size-large circle circle-right"><img src="" alt="" /></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
