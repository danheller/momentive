<?php
/**
 * Title: Solution Content
 * Slug: momentive/solution-content
 * Description: Base layout for child solution posts: two-column hero, selling points accordion, and demo form.
 * Post Types: solutions
 * Inserter: true
 */
?>
<!-- wp:group {"className":"breadcrumb-bar","layout":{"type":"constrained"}} -->
<div class="wp-block-group breadcrumb-bar"><!-- wp:momentive/breadcrumbs /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-bg-dots hero-background","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-bg-dots hero-background"><!-- wp:group {"className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium","bottom":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group hero" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"left"}}} -->
<h1 class="wp-block-heading has-text-align-left">Solution Name</h1>
<!-- /wp:heading -->

<!-- wp:heading {"className":"is-style-has-swoop tucked","style":{"typography":{"textAlign":"left"}},"fontSize":"xxx-large",,"placeholder":"One or two sentences describing this solution and its core value proposition."} -->
<h2 class="wp-block-heading has-text-align-left is-style-has-swoop tucked has-xxx-large-font-size"><strong>Key Benefit</strong> in a Short Phrase</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"narrow balance","style":{"typography":{"textAlign":"left"}},"placeholder":"One or two sentences describing this solution and its core value proposition."} -->
<p class="has-text-align-left narrow balance"></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex"}} -->
<div class="wp-block-buttons"><!-- wp:button {"style":{"typography":{"textAlign":"center"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-align-center wp-element-button" href="#form">Talk to an expert</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="" alt="" /></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"selling-points","layout":{"type":"constrained"}} -->
<div class="wp-block-group selling-points"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"is-style-eyebrow","placeholder":"What You Get"} -->
<p class="is-style-eyebrow">What You Get</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"h2","fontSize":"xxx-large","placeholder":"Short headline about this solution's key benefits"} -->
<p class="h2 has-xxx-large-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"Two or three sentences expanding on the headline. What does this solution help customers do?"} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:momentive/accordion {"style":"icon","items":[{"_key":"item1","question":"Benefit One","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""},{"_key":"item2","question":"Benefit Two","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""},{"_key":"item3","question":"Benefit Three","answer":"Description of this benefit.","iconSlug":"bx-check-circle","category":""}]} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"demo-form is-style-ellipse-bottom","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|medium","top":"var:preset|spacing|medium"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group demo-form is-style-ellipse-bottom" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"className":"is-style-eyebrow","placeholder":"Request a Demo"} -->
<p class="is-style-eyebrow">Request a Demo</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Headline for the demo form section</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"One or two sentences about this solution to accompany the demo request form.}-->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-rounder"} -->
<figure class="wp-block-image size-large is-style-rounder"><img src="" alt="" /></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"hubspot_embed_code":"","_hubspot_embed_code":"field_6a2873ba3bf87"},"mode":"preview"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->