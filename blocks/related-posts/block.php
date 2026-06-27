<?php
add_action( 'init', function() {
	register_block_type( get_template_directory() . '/blocks/related-posts', [
		'render_callback' => function() {
			if ( ! is_singular( [ 'post', 'press-article', 'webinar' ] ) ) return '';
			ob_start();
			get_template_part( 'patterns/related-posts' );
			return ob_get_clean();
		},
	] );
} );
