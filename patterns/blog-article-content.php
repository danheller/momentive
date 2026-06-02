<?php
/**
 * Title: Blog Article Content
 * Slug: momentive/blog-article-content
 * Description: Full layout for blog posts: hero, byline, body, and sidebar.
 * Post Types: press-article
 * Inserter: true
 */
?>
<!-- wp:group {"className":"entry-header","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group entry-header alignfull">

	<!-- wp:group {"className":"header-inner"} -->
	<div class="wp-block-group header-inner">

		<!-- wp:group {"className":"header-media"} -->
		<div class="wp-block-group header-media">
			<!-- wp:post-featured-image {"isLink":false} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"header-content"} -->
		<div class="wp-block-group header-content">

			<!-- wp:momentive/breadcrumbs {"lock":{"move":true,"remove":true}} /-->

			<!-- wp:post-title {"level":1,"lock":{"move":true,"remove":true}} /-->

			<!-- wp:post-terms {"term":"category","separator":"","className":"taxonomy-category lower-label","lock":{"move":true,"remove":true}} /-->

			<!-- wp:momentive/post-cta-button /-->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->

<!-- wp:columns {"className":"post-layout","isStackedOnMobile":false} -->
<div class="wp-block-columns post-layout is-not-stacked-on-mobile">

	<!-- wp:column {"className":"post-content"} -->
	<div class="wp-block-column post-content">

		<!-- wp:momentive/post-byline /-->

		<!-- wp:paragraph {"placeholder":"Write your blog post here\u2026"} -->
		<p></p>
		<!-- /wp:paragraph -->

	</div>
	<!-- /wp:column -->

	<!-- wp:column {"className":"post-sidebar"} -->
	<div class="wp-block-column post-sidebar">

		<!-- wp:group {"className":"sidebar-sticky"} -->
		<div class="wp-block-group sidebar-sticky">

			<!-- wp:momentive/table-of-contents /-->

			<!-- wp:momentive/social-share /-->

		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:column -->

</div>
<!-- /wp:columns -->