<?php
/**
 * Title: Video Content
 * Slug: momentive/video-content
 * Description: Base layout for video posts. Two-column: description/checklist on the left,
 *              featured image and HubSpot gate on the right. Matches the whitepaper/infographic
 *              pattern. For ungated videos, replace the hubspot-form block with the embed directly.
 * Post Types: video
 * Inserter: true
 */
?>

<!-- wp:columns {"className":"post-layout"} -->
<div class="wp-block-columns post-layout"><!-- wp:column {"className":"post-content no-padding"} -->
<div class="wp-block-column post-content no-padding">
<!-- wp:acf/back-link {"name":"acf/back-link","data":{"url":"/videos/","label":"All videos"},"mode":"preview"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:paragraph -->
<p>Add your video description</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:post-featured-image /-->

<!-- wp:paragraph -->
<p>Watch the video</p>
<!-- /wp:paragraph -->

<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:momentive/social-share /-->
