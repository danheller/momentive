<?php
/**
 * Title: Guide Content
 * Slug: momentive/guide-content
 * Description: Base layout for the "guides" shape of the Guides & Research CPT
 *              (description + optional checklist on the left, HubSpot form or
 *              download button on the right) — the common case, ~half of the
 *              migrated corpus. Deliberately NOT a research-study default:
 *              that shape (custom hero, insight/stat sections, previous-studies
 *              grid, webinar CTA band) is bespoke per post and hand-assembled
 *              from individual blocks, the same way case-study posts with
 *              unusual sidebars deviate from case-study-content.php. Applied
 *              via the CPT template hook in inc/guides.php.
 * Post Types: guide
 * Inserter: true
 */
?>

<!-- wp:group {"className":"hero-background","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero-background"><!-- wp:columns {"className":"post-layout"} -->
<div class="wp-block-columns post-layout"><!-- wp:column {"className":"post-content no-padding"} -->
<div class="wp-block-column post-content no-padding"><!-- wp:acf/back-link {"name":"acf/back-link","data":{"label":"All research & guides","_label":"field_6a44a408f79e0","url":"/guides/","_url":"field_6a44a420f79e1"},"mode":"preview"} /-->

<!-- wp:query-title {"type":"post-type","showPrefix":false,"className":"top-label"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:paragraph -->
<p>Add your guide description</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><strong>Open this guide to learn more about:</strong></p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Add a checklist item</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:post-featured-image /-->

<!-- wp:paragraph -->
<p>Add your form heading</p>
<!-- /wp:paragraph -->

<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87","two_step":"0","_two_step":"field_6a35626f3a11b"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:momentive/social-share /-->
