<?php
/**
 * Title: Whitepaper Content
 * Slug: momentive/whitepaper-content
 * Description: Base layout for whitepaper posts. Sections with templateLock:contentOnly preserve
 *              structure while allowing text, image, and link edits.
 * Post Types: whitepaper
 * Inserter: true
 */
?>

<!-- wp:columns {"className":"post-layout"} -->
<div class="wp-block-columns post-layout"><!-- wp:column {"className":"post-content no-padding"} -->
<div class="wp-block-column post-content no-padding">
<!-- wp:acf/back-link {"name":"acf/back-link","data":{"url":"/whitepapers/","label":"All whitepapers"},"mode":"preview"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:paragraph -->
<p>Add your whitepaper description</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:post-featured-image /-->

<!-- wp:paragraph -->
<p>Add your form heading</p>
<!-- /wp:paragraph -->


<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:momentive/social-share /-->
