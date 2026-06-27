<?php
/**
 * Title: Product Content
 * Slug: momentive/product-content
 * Description: Base layout for product posts. Sections with templateLock:contentOnly preserve
 *              structure while allowing text, image, and link edits. The testimonials Query Loop
 *              and related-products grid are left unlocked — configure their tax queries per product.
 * Post Types: product
 * Inserter: true
 */
?>

<!-- =========================================================
     HERO
     contentOnly: replace eyebrow, logo image, bullet points,
     hero screenshot, and HubSpot embed code (both instances).
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Hero"},
  "templateLock":"contentOnly",
  "className":"hero-background is-style-bg-dots",
  "style":{"spacing":{"padding":{"bottom":"0"}}},
  "gradient":"vertical",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group hero-background is-style-bg-dots has-vertical-gradient-background has-background" style="padding-bottom:0">

<!-- wp:group {"className":"breadcrumb-bar","layout":{"type":"constrained"}} -->
<div class="wp-block-group breadcrumb-bar"><!-- wp:momentive/breadcrumbs /--></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"0"}}},"layout":{"type":"constrained","wideSize":"","contentSize":""}} -->
<div class="wp-block-group alignfull hero" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:0"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center">

<!-- wp:heading {"level":1,"className":"is-style-eyebrow","placeholder":"[Product Name] Powered By Momentive"} -->
<h1 class="wp-block-heading is-style-eyebrow">[Product Name] Powered By Momentive</h1>
<!-- /wp:heading -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:10055/wp-content/uploads/product-logo-placeholder.svg" alt="Product logo" /></figure>
<!-- /wp:image -->

<!-- wp:list {"className":"is-style-blue-checks is-style-circle-checks"} -->
<ul class="wp-block-list is-style-blue-checks is-style-circle-checks"><!-- wp:list-item -->
<li>Key selling point</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Key selling point</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Key selling point</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"field_6a2873ba3bf87":"","field_6a35626f3a11b":"1"},"mode":"preview"} /-->

</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center">
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="" alt="Product screenshot" /></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     TRUST BAR
     Locked (insert) — shared boilerplate, not per-product.
     To update logos site-wide, edit the pattern source.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Trust bar — replace logos and tagline as needed for this product"},
  "templateLock":"contentOnly",
  "align":"full",
  "className":"trust",
  "style":{"spacing":{"padding":{"top":"0","bottom":"var:preset|spacing|medium"}}},
  "layout":{"type":"constrained","wideSize":"","contentSize":""}
} -->
<div class="wp-block-group alignfull trust" style="padding-top:0;padding-bottom:var(--wp--preset--spacing--medium)">

<!-- wp:paragraph {"className":"is-style-uppercase","style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center is-style-uppercase">Trusted by over 37,000 nonprofits and associations</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"autoslider logos","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group autoslider logos"><!-- wp:image {"id":704,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/northampton-primary-academy-trust.webp" alt="Northampton Primary Academy Trust logo" class="wp-image-704"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":751,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/samaritans-purse.webp" alt="Samaritan's Purse International Relief" class="wp-image-751"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":750,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/calcpa-logo.webp" alt="CalCPA" class="wp-image-750"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":764,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/habitat-for-humanity.webp" alt="Habitat for Humanity" class="wp-image-764"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":772,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/ibm.webp" alt="IBM" class="wp-image-772"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":770,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/mensa-logo.webp" alt="Mensa" class="wp-image-770"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":774,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/american-college-of-cardiology.webp" alt="American College of Cardiology" class="wp-image-774"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":776,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/girl-scouts.webp" alt="Girl Scouts" class="wp-image-776"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":7732,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/asae.webp" alt="American Society of Association Executives" class="wp-image-7732"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":7734,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/american-lung-association.webp" alt="American Lung Association" class="wp-image-7734"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":704,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:10055/wp-content/uploads/northampton-primary-academy-trust.webp" alt="Northampton Primary Academy Trust logo" class="wp-image-704"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     FEATURE ROWS
     contentOnly on each media-text block: replace the eyebrow
     link, heading, body copy, "Explore" link, and image.
     Duplicate the first block (⌘⇧D) to add more feature rows,
     then toggle image left/right via the block toolbar.
     ========================================================= -->
<!-- wp:media-text {
  "metadata":{"name":"Feature row 1 — image left"},
  "templateLock":"contentOnly",
  "mediaType":"image",
  "className":"no-shadow",
  "style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}
} -->
<div class="wp-block-media-text is-stacked-on-mobile no-shadow" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small)"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content">

<!-- wp:paragraph {"className":"is-style-eyebrow","placeholder":"Feature category — links to product site"} -->
<p class="is-style-eyebrow"><a href="">Feature category</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"xx-large","placeholder":"Benefit-led headline for this feature"} -->
<h3 class="wp-block-heading has-xx-large-font-size">Product feature headline</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences describing this feature and its value."} -->
<p class="has-medium-font-size">Feature description — what it does and why it matters to the customer.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward","fontSize":"medium"} -->
<p class="read-more has-arrow upward has-medium-font-size"><a href="">Explore</a></p>
<!-- /wp:paragraph -->

</div></div>
<!-- /wp:media-text -->

<!-- wp:media-text {
  "metadata":{"name":"Feature row 2 — image right (duplicate row 1 to add more)"},
  "templateLock":"contentOnly",
  "mediaPosition":"right",
  "mediaType":"image",
  "className":"no-shadow",
  "style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}
} -->
<div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile no-shadow" style="padding-top:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small)"><div class="wp-block-media-text__content">

<!-- wp:paragraph {"className":"is-style-eyebrow","placeholder":"Feature category — links to product site"} -->
<p class="is-style-eyebrow"><a href="">Feature category</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"xx-large","placeholder":"Benefit-led headline for this feature"} -->
<h3 class="wp-block-heading has-xx-large-font-size">Product feature headline</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences describing this feature and its value."} -->
<p class="has-medium-font-size">Feature description — what it does and why it matters to the customer.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward","fontSize":"medium"} -->
<p class="read-more has-arrow upward has-medium-font-size"><a href="">Explore</a></p>
<!-- /wp:paragraph -->

</div><figure class="wp-block-media-text__media"></figure></div>
<!-- /wp:media-text -->


<!-- =========================================================
     FEATURE BUTTONS ("Also included")
     contentOnly: edit button labels and links freely.
     Duplicate any button with ⌘⇧D; delete extras as needed.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Also included — feature button pills"},
  "templateLock":"contentOnly",
  "className":"feature-buttons",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group feature-buttons">

<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center">Also included</h2>
<!-- /wp:heading -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-superlight"} -->
<div class="wp-block-button is-style-superlight"><a class="wp-block-button__link wp-element-button" href="">Feature or add-on name</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-superlight"} -->
<div class="wp-block-button is-style-superlight"><a class="wp-block-button__link wp-element-button" href="">Feature or add-on name</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-superlight"} -->
<div class="wp-block-button is-style-superlight"><a class="wp-block-button__link wp-element-button" href="">Feature or add-on name</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     PRODUCT GRADIENT CTA BLOCK
     contentOnly: replace logo image, tagline, and button link.
     The dashboard screenshot on the right is also replaceable.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Product gradient CTA"},
  "templateLock":"contentOnly",
  "className":"featured-item space-around product-gradient",
  "style":{"spacing":{"padding":{"right":"0","left":"0"},"margin":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group featured-item space-around product-gradient" style="margin-top:var(--wp--preset--spacing--large);margin-bottom:var(--wp--preset--spacing--large);padding-right:0;padding-left:0">

<!-- wp:columns {"className":"is-style-columns-reverse","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small","top":"var:preset|spacing|small","bottom":"var:preset|spacing|small"}}}} -->
<div class="wp-block-columns is-style-columns-reverse" style="padding-top:var(--wp--preset--spacing--small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)"><!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small","top":"0","bottom":"0"}}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-top:0;padding-right:var(--wp--preset--spacing--small);padding-bottom:0;padding-left:var(--wp--preset--spacing--small)">

<!-- wp:image {"lightbox":{"enabled":false},"width":"200px","sizeSlug":"large","linkDestination":"custom","className":"product"} -->
<figure class="wp-block-image size-large is-resized product"><a href=""><img src="http://localhost:10055/wp-content/uploads/product-logo-placeholder.svg" alt="Product logo" style="width:200px"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"fontSize":"large","placeholder":"One sentence CTA — e.g. See how [Product] simplifies [key job]."} -->
<p class="has-large-font-size"><strong>See how [product] makes life better.</strong></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#form">Speak with an Expert</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small"}}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-right:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)">

<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="" alt="Product dashboard screenshot" /></figure>
<!-- /wp:image -->

</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     TESTIMONIALS QUERY LOOP
     NOT locked — the taxQuery term IDs must be updated to match
     this product's testimonial_type and post_tag terms.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"⚠ Testimonials — configure taxQuery terms for this product"},
  "className":"alignfull",
  "gradient":"white-light-white",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group alignfull has-white-light-white-gradient-background has-background">

<!-- wp:group {"metadata":{"name":"Testimonials slider"},"className":"testimonials-wrapper alignfull"} -->
<div class="wp-block-group testimonials-wrapper alignfull"><!-- wp:group {"align":"full","className":"testimonials-slider single-slide is-style-outline has-pagination has-side-arrows"} -->
<div class="wp-block-group alignfull testimonials-slider single-slide is-style-outline has-pagination has-side-arrows">

<!-- wp:query {"queryId":21,"query":{"perPage":10,"pages":0,"offset":0,"postType":"testimonials","order":"desc","orderBy":"date","inherit":false,"taxQuery":{"include":{}}}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:momentive/testimonial {"showCaseStudyButton":false} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->

</div>
<!-- /wp:group --></div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     RELATED PRODUCTS GRID
     NOT locked — update the four product logos and links to
     reflect the products most relevant to this one. Remove or
     add columns as needed.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Related products grid — update logos and links"},
  "className":"no-margin to-edge",
  "backgroundColor":"neutral",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group no-margin to-edge has-neutral-background-color has-background">

<!-- wp:columns {"style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}}} -->
<div class="wp-block-columns" style="padding-top:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large)"><!-- wp:column {"width":"35%","className":"no-padding"} -->
<div class="wp-block-column no-padding" style="flex-basis:35%">

<!-- wp:paragraph {"className":"is-style-eyebrow","style":{"typography":{"textAlign":"left"}}} -->
<p class="has-text-align-left is-style-eyebrow">Our Products</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"className":"balance","style":{"typography":{"textAlign":"left"}}} -->
<h2 class="wp-block-heading has-text-align-left balance">We offer so much more too.</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"balance","style":{"typography":{"textAlign":"left"}},"fontSize":"medium"} -->
<p class="has-text-align-left balance has-medium-font-size">Whether you are looking for advanced analytics, all-in-one simplicity, enterprise-level customization, or globalization needs, we have the right solution for you.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:column -->

<!-- wp:column {"width":"65%","className":"no-padding","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column no-padding" style="flex-basis:65%">

<!-- wp:columns {"className":"is-style-boxed small-gap","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|x-small"}}}} -->
<div class="wp-block-columns is-style-boxed small-gap"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"lightbox":{"enabled":false},"width":"auto","height":"48px","sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large is-resized"><a href=""><img src="" alt="Related product" style="width:auto;height:48px"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Brief description of this related product and how it complements this one.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward"} -->
<p class="read-more has-arrow upward"><a href="">Discover [Product]</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"lightbox":{"enabled":false},"width":"auto","height":"48px","sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large is-resized"><a href=""><img src="" alt="Related product" style="width:auto;height:48px"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Brief description of this related product and how it complements this one.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward"} -->
<p class="read-more has-arrow upward"><a href="">Discover [Product]</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"className":"is-style-boxed small-gap","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|x-small"}}}} -->
<div class="wp-block-columns is-style-boxed small-gap"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"lightbox":{"enabled":false},"width":"auto","height":"48px","sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large is-resized"><a href=""><img src="" alt="Related product" style="width:auto;height:48px"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Brief description of this related product and how it complements this one.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward"} -->
<p class="read-more has-arrow upward"><a href="">Discover [Product]</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"lightbox":{"enabled":false},"width":"auto","height":"48px","sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large is-resized"><a href=""><img src="" alt="Related product" style="width:auto;height:48px"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Brief description of this related product and how it complements this one.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more has-arrow upward"} -->
<p class="read-more has-arrow upward"><a href="">Discover [Product]</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     DARK CTA BLOCK
     contentOnly: replace eyebrow, heading (+ its link), body
     copy, "Learn more" link, and the right-column image.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Dark feature CTA"},
  "templateLock":"contentOnly",
  "className":"featured-item space-around is-style-bg-dark",
  "style":{"spacing":{"padding":{"right":"0","left":"0"},"margin":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large"}}},
  "gradient":"dark-navy",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group featured-item space-around is-style-bg-dark has-dark-navy-gradient-background has-background" style="margin-top:var(--wp--preset--spacing--large);margin-bottom:var(--wp--preset--spacing--large);padding-right:0;padding-left:0">

<!-- wp:columns {"className":"is-style-outline is-style-columns-reverse","style":{"spacing":{"padding":{"top":"var:preset|spacing|small","bottom":"var:preset|spacing|small","left":"var:preset|spacing|small","right":"var:preset|spacing|small"}}}} -->
<div class="wp-block-columns is-style-outline is-style-columns-reverse" style="padding-top:var(--wp--preset--spacing--small);padding-right:var(--wp--preset--spacing--small);padding-bottom:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)"><!-- wp:column {"verticalAlignment":"center","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small"}}}} -->
<div class="wp-block-column is-vertically-aligned-center" style="padding-right:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)">

<!-- wp:paragraph {"className":"is-style-eyebrow","placeholder":"e.g. Free Trial, Bootcamp, Getting Started"} -->
<p class="is-style-eyebrow">Eyebrow</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3,"fontSize":"xx-large","placeholder":"Link this heading to the destination page"} -->
<h3 class="wp-block-heading has-xx-large-font-size"><a href="">See what [Product] can do</a></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Two or three sentences about this trial, resource, or feature highlight."} -->
<p>Add a blurb here, then link the "Learn more" text below to the destination page.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"read-more chevron"} -->
<p class="read-more chevron"><a href="">Learn more</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center","className":"no-padding","style":{"spacing":{"padding":{"right":"var:preset|spacing|small","left":"var:preset|spacing|small"}}}} -->
<div class="wp-block-column is-vertically-aligned-center no-padding" style="padding-right:var(--wp--preset--spacing--small);padding-left:var(--wp--preset--spacing--small)">

<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="" alt="" /></figure>
<!-- /wp:image -->

</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     DEMO FORM
     contentOnly: replace eyebrow, heading, body copy, and
     the HubSpot embed code in the right column.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"Demo request form — update HubSpot embed code"},
  "templateLock":"contentOnly",
  "className":"demo-form is-style-ellipse-bottom fade-to-white",
  "style":{"spacing":{"padding":{"bottom":"var:preset|spacing|medium","top":"var:preset|spacing|medium"}}},
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group demo-form is-style-ellipse-bottom fade-to-white" style="padding-top:var(--wp--preset--spacing--medium);padding-bottom:var(--wp--preset--spacing--medium)">

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column">

<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow">Request a Demo</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"placeholder":"Outcome-focused headline — what will they be able to do?"} -->
<h2 class="wp-block-heading">Demo section headline</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"One or two sentences about this product to accompany the demo request form."} -->
<p class="has-medium-font-size">Demo call-to-action copy goes here.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:acf/hubspot-form {"name":"acf/hubspot-form","data":{"field_6a2873ba3bf87":"","field_6a35626f3a11b":"0"},"mode":"preview"} /-->
</div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

</div>
<!-- /wp:group -->


<!-- =========================================================
     FAQ
     contentOnly: edit the intro text and accordion items.
     ========================================================= -->
<!-- wp:group {
  "metadata":{"name":"FAQ"},
  "templateLock":"contentOnly",
  "className":"faq-wrapper alignfull",
  "style":{"spacing":{"padding":{"bottom":"var:preset|spacing|large"}}},
  "gradient":"white-to-superlight",
  "layout":{"type":"constrained"}
} -->
<div class="wp-block-group faq-wrapper alignfull has-white-to-superlight-gradient-background has-background" style="padding-bottom:var(--wp--preset--spacing--large)">

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
<p class="is-style-eyebrow">Help &amp; Support</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">FAQ</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium","placeholder":"Intro sentence, e.g. 'Everything you need to know about getting started with [Product].'"} -->
<p class="has-medium-font-size">Everything you need to know about getting started with [Product].</p>
<!-- /wp:paragraph -->

<!-- wp:momentive/accordion {"items":[{"_key":"faq1","question":"Question","answer":"Answer","iconSlug":"","category":""},{"_key":"faq2","question":"Question","answer":"Answer","iconSlug":"","category":""},{"_key":"faq3","question":"Question","answer":"Answer","iconSlug":"","category":""}],"queryPostsPerPage":15} /-->

</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->
