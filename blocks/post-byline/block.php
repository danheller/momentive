<?php
/**
 * Post CTA Button block — register block JSON and editor script.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-post-byline-editor',
		get_template_directory_uri() . '/blocks/post-byline/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	register_block_type(
		get_template_directory() . '/blocks/post-byline/block.json',
		[
			'render_callback' => 'momentive_post_byline_render',
			'editor_script'   => 'momentive-post-byline-editor',
		]
	);
} );

add_action( 'enqueue_block_assets', function () {
	if ( ! momentive_content_has_block( 'momentive/post-byline' ) ) {
		return;
	}

	wp_enqueue_style(
		'momentive-byline',
		get_template_directory_uri() . '/assets/css/byline.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );


/**
 * Post Byline block — render callback.
 *
 * Reads author data from the 'authors' CPT via ACF post object field
 * 'post_author_ref'. Falls back to the WordPress post author if the
 * field is empty or ACF isn't active.
 */

function momentive_post_byline_render( $attributes, $content, $block ): string {

	$post_id = $block->context['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$show_modified = ! empty( $attributes['showModified'] );
	$show_reading  = ! empty( $attributes['showReadingTime'] );

	// ── Author ────────────────────────────────────────────────────────────────

	$author_name   = '';
	$author_avatar = '';

	$author_post = get_post_meta( $post_id, 'post_author_ref', true );
	
	// Fallback to the "Momentive Software" author CPT entry if no author is assigned.

	if ( ! $author_post ) {
		$default_author = get_page_by_title(
			'Momentive Software',
			OBJECT,
			'authors'
		);
		if ( $default_author ) {
			$author_post = $default_author->ID;
		}
	}
	
	// Build author data from the authors CPT	

	if ( $author_post ) {
		$author_name = get_the_title( $author_post );
		$thumb_id = get_post_thumbnail_id( $author_post );
		if ( ! $thumb_id ) {
			$thumb_id = 10559; // default image ID
		}
		if ( $thumb_id ) {
			$author_avatar = wp_get_attachment_image(
				$thumb_id,
				[ 112, 112 ], // retina size; displayed at 56×56
				false,
				[
					'class' => 'byline__avatar',
					'alt'   => esc_attr( $author_name ),
				]
			);
		}
	}

	// ── Modified date ─────────────────────────────────────────────────────────
	// Only shown when the modified date is more than 24 hours after publish,
	// to avoid showing "Last updated" on posts edited minutes after publishing.

	$modified_html = '';
	if ( $show_modified ) {
		$published = get_post_field( 'post_date_gmt', $post_id );
		$modified  = get_post_field( 'post_modified_gmt', $post_id );

		if ( strtotime( $modified ) - strtotime( $published ) > DAY_IN_SECONDS ) {
			$modified_html = sprintf(
				'<span class="byline__updated">Last updated: <time datetime="%s">%s</time></span>',
				esc_attr( get_the_modified_date( 'c', $post_id ) ),
				esc_html( get_the_modified_date( 'F j, Y', $post_id ) )
			);
		}
	}

	// ── Reading time ──────────────────────────────────────────────────────────

	$reading_html = '';
	if ( $show_reading ) {
		$mins = momentive_reading_time( $post_id );
		$reading_html = sprintf(
			'<div class="byline__reading-time is-style-pill">%d min read</div>',
			$mins
		);
	}

	// ── Nothing to show ───────────────────────────────────────────────────────

	if ( ! $author_name && ! $modified_html && ! $reading_html ) return '';

	// ── Render ────────────────────────────────────────────────────────────────

	$has_meta = $modified_html || $reading_html;

	ob_start();
	?>
	<div class="byline">

		<?php if ( $author_avatar ) : ?>
		<div class="byline__photo"><?php echo $author_avatar; ?></div>
		<?php endif; ?>

		<div class="byline__text">

			<?php if ( $author_name ) : ?>
			<span class="byline__name"><?php echo esc_html( $author_name ); ?></span>
			<?php endif; ?>

			<?php if ( $has_meta ) : ?>
			<div class="byline__meta">
				<?php echo $modified_html; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php echo $reading_html; ?>
	</div>
	<?php
	return ob_get_clean();
}

// ── Reading time helper ───────────────────────────────────────────────────────
// Exposed as a standalone function so it can be reused on archive cards
// or anywhere else without needing the full byline block.

if ( ! function_exists( 'momentive_reading_time' ) ) {
	function momentive_reading_time( int $post_id = 0 ): int {
		$post_id = $post_id ?: get_the_ID();
		$content = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
		$words   = str_word_count( $content );
		return max( 1, (int) ceil( $words / 220 ) );
	}
}