<?php
/**
 * Title: Recording Content
 * Slug: momentive/recording-content
 * Description: Layout for the recording view (/recordings/{slug}) — title, the
 *              recording video, and related resources. No registration form.
 * Inserter: false
 */
?>
<!-- wp:group {"className":"hero-background is-style-bg-dark","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero-background is-style-bg-dark"><!-- wp:group {"className":"hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group hero"><!-- wp:post-title {"textAlign":"center","level":1,"className":"balance","fontSize":"display-large"} /-->

<!-- wp:acf/recording {"name":"acf/recording","data":{"recording_source":"post","_recording_source":"field_6a3b72040796e"},"mode":"preview"} /-->

<!-- wp:momentive/related-posts /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
