<?php
/**
 * Title: Webinar Content
 * Slug: momentive/webinar-content
 * Description: Base layout for webinar posts. Sections with templateLock:contentOnly preserve
 *              structure while allowing text, image, and link edits.
 * Post Types: webinar
 * Inserter: true
 */
?>

<!-- wp:columns {"className":"post-layout"} -->
<div class="wp-block-columns post-layout"><!-- wp:column {"className":"post-content no-padding"} -->
<div class="wp-block-column post-content no-padding">
<!-- wp:paragraph {"className":"back-link"} -->
<p class="back-link"><a href="/webinars/">All webinars</a></p>
<!-- /wp:paragraph -->

<!-- wp:acf/webinar-status {"name":"acf/webinar-status","mode":"preview"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:acf/webinar-schedule {"name":"acf/webinar-schedule","mode":"preview"} /-->

<!-- wp:paragraph -->
<p>Add your webinar description</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:post-featured-image /-->

<!-- wp:paragraph -->
<p><strong>Save your spot</strong></p>
<!-- /wp:paragraph -->

<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:momentive/social-share /-->
