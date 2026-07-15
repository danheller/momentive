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
<!-- wp:acf/back-link {"name":"acf/back-link","data":{"url":"/webinars/","label":"All webinars"},"mode":"preview"} /-->

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

<!-- wp:acf/webinar-form-heading {"name":"acf/webinar-form-heading","mode":"preview"} /-->

<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:acf/webinar-presenters {"name":"acf/webinar-presenters","data":{"layout":"two-columns","_layout":"field_webinar_presenters_layout"},"mode":"preview"} /-->

<!-- wp:momentive/social-share /-->
