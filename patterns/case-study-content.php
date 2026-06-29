<?php
/**
 * Title: Case Study Content
 * Slug: momentive/case-study-content
 * Description: Full layout for case studies: hero (logo, title, featured image,
 *              download button), two-column body with testimonial/stats/prose,
 *              and a sticky sidebar (linked products, key features, CTA).
 * Post Types: case-study
 * Inserter: true
 */
?>
<!-- wp:group {"className":"breadcrumb-bar","layout":{"type":"constrained"}} -->
<div class="wp-block-group breadcrumb-bar"><!-- wp:momentive/breadcrumbs /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"hero-background","gradient":"vertical","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero-background has-vertical-gradient-background has-background"><!-- wp:group {"className":"hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"small-logo"} -->
<figure class="wp-block-image size-large small-logo"><img src="" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:post-title {"textAlign":"center","level":1,"fontSize":"display-large"} /-->

<!-- wp:post-featured-image {"className":"rounder"} /-->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"download"} -->
<div class="wp-block-button download"><a class="wp-block-button__link wp-element-button" href="#">Download full case study</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"isStackedOnMobile":false,"className":"post-layout"} -->
<div class="wp-block-columns is-not-stacked-on-mobile post-layout"><!-- wp:column {"className":"post-content"} -->
<div class="wp-block-column post-content"><!-- wp:paragraph {"placeholder":"Add the case study testimonial, stats, and body here\u2026"} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:momentive/social-share /--></div>
<!-- /wp:column -->

<!-- wp:column {"className":"post-sidebar"} -->
<div class="wp-block-column post-sidebar"><!-- wp:group {"className":"sidebar-sticky","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group sidebar-sticky"><!-- wp:group {"className":"solutions-block","layout":{"type":"constrained"}} -->
<div class="wp-block-group solutions-block"><!-- wp:momentive/linked-products /-->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:heading {"fontSize":"large"} -->
<h2 class="wp-block-heading has-large-font-size">Key Features</h2>
<!-- /wp:heading -->

<!-- wp:momentive/icon-list {"showHeading":false} /-->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center">Ready to get started?</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/solutions/">Explore our solutions</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
