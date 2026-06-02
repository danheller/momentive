<?php
/**
 * Table of Contents block — register block JSON, editor script, and frontend javascript and CSS.
 */

add_action( 'init', function () {
	wp_register_script(
		'momentive-toc-editor',
		get_template_directory_uri() . '/blocks/table-of-contents/editor.js',
		[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_script(
		'momentive-toc',
		get_template_directory_uri() . '/blocks/table-of-contents/toc.js',
		[],
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_register_style(
		'momentive-toc',
		get_template_directory_uri() . '/blocks/table-of-contents/toc.css',
		[],
		wp_get_theme()->get( 'Version' )
	);

	register_block_type(
		get_template_directory() . '/blocks/table-of-contents/block.json',
		[
			'render_callback' => 'momentive_toc_render',
			'editor_script'   => 'momentive-toc-editor',
			'script'          => 'momentive-toc',
			'style'           => 'momentive-toc',
		]
	);
} );

/**
 * Table of Contents block — render callback.
 *
 * Behavior:
 *  - Parses the current post's rendered content for h2 (and optionally h3) headings.
 *  - Falls back to the post title when no headings are found (Momentive "in the news" case).
 *  - Injects id attributes onto headings via the_content filter so anchor links work.
 *  - Expand/collapse toggle stored in sessionStorage so it persists within a visit.
 */

// ── Heading ID injection ──────────────────────────────────────────────────────
// Runs on the_content so heading IDs exist in the page before the TOC renders.
// Uses a static tracker to ensure IDs are unique across multiple calls.

add_filter( 'the_content', 'momentive_toc_inject_heading_ids', 5 );

function momentive_toc_inject_heading_ids( string $content ): string {
    $allowed_post_types = [ 'post', 'press-article', 'case-studies' ];
    if ( ! is_singular( $allowed_post_types ) ) return $content;

	static $h2_index = 0;
	static $h3_index = 0;

	$content = preg_replace_callback(
		'/<h2(?![^>]*\bid=)[^>]*>(.*?)<\/h2>/is',
		function ( $m ) use ( &$h2_index ) {
			$id = momentive_toc_anchor( wp_strip_all_tags( $m[1] ), $h2_index++ );
			return str_replace( '<h2', '<h2 id="' . esc_attr( $id ) . '"', $m[0] );
		},
		$content
	);

	$content = preg_replace_callback(
		'/<h3(?![^>]*\bid=)[^>]*>(.*?)<\/h3>/is',
		function ( $m ) use ( &$h3_index ) {
			$id = momentive_toc_anchor( wp_strip_all_tags( $m[1] ), $h3_index++ );
			return str_replace( '<h3', '<h3 id="' . esc_attr( $id ) . '"', $m[0] );
		},
		$content
	);

	return $content;
}

// ── Anchor generation ─────────────────────────────────────────────────────────

function momentive_toc_anchor( string $text, int $index = 0 ): string {
	// Decode HTML entities first so &nbsp; → space, &amp; → &, etc.
	$anchor = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$anchor = strtolower( $anchor );
	$anchor = preg_replace( '/[^\w\s-]/', '',  $anchor ); // strip non-word, non-space, non-dash
	$anchor = preg_replace( '/[\s_]+/',   '-', $anchor ); // spaces/underscores → dash
	$anchor = preg_replace( '/-{2,}/',   '-', $anchor ); // collapse multiple dashes
	$anchor = trim( $anchor, '-' );
	$anchor = substr( $anchor, 0, 64 );

	if ( $anchor === '' )                        $anchor = 'section';
	if ( preg_match( '/^\d/', $anchor ) )        $anchor = 'heading-' . $anchor;
	if ( $index > 0 )                            $anchor .= '-' . $index;

	return $anchor;
}

// ── Heading extraction ────────────────────────────────────────────────────────

function momentive_toc_extract_headings( string $content, int $max_level ): array {
	$headings = [];
	$h2_index = 0;
	$h3_index = 0;

	preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$level = (int) $match[1];
		if ( $level > $max_level ) continue;

		$text = wp_strip_all_tags( $match[2] );
		if ( $text === '' ) continue;

		$index  = $level === 2 ? $h2_index++ : $h3_index++;
		$anchor = momentive_toc_anchor( $text, $index );

		$headings[] = [
			'level'  => $level,
			'text'   => $text,
			'anchor' => $anchor,
		];
	}

	return $headings;
}

// ── Render callback ───────────────────────────────────────────────────────────

function momentive_toc_render( array $attributes ): string {
    $allowed_post_types = [ 'post', 'press-article', 'case-studies' ];
    if ( ! is_singular( $allowed_post_types ) ) return $content;

	$post        = get_queried_object();
	$title       = esc_html( $attributes['title']           ?? 'Contents' );
	$max_level   = (int) ( $attributes['maxLevel']          ?? 2 );
	$expanded    = ! empty( $attributes['defaultExpanded'] );
	$content     = $post->post_content;
	$headings    = momentive_toc_extract_headings( $content, $max_level );

	// ── Fallback: post title when no headings found ───────────────────────────
	// Matches Momentive's "in the news" behaviour: a single link to the top
	// of the page using the post title as the label.
	$fallback = false;
	if ( empty( $headings ) ) {
		$headings = [ [
			'level'  => 2,
			'text'   => get_the_title( $post ),
			'anchor' => '', // links to page top
		] ];
		$fallback = true;
	}

	// ── Build list items ──────────────────────────────────────────────────────

	$items_html = '';
	foreach ( $headings as $heading ) {
		$href  = $heading['anchor'] !== '' ? '#' . esc_attr( $heading['anchor'] ) : '#';
		$level = (int) $heading['level'];
		$text  = esc_html( $heading['text'] );

		$items_html .= sprintf(
			'<li class="toc-item toc-item--h%d"%s>
				<a class="toc-link" href="%s" data-anchor="%s">%s</a>
			</li>',
			$level,
			$fallback ? ' data-fallback="true"' : '',
			$href,
			esc_attr( $heading['anchor'] ),
			$text
		);
	}

	// ── Expanded state: sessionStorage key so JS can restore preference ───────
	$storage_key = 'toc-expanded-' . $post->ID;

	ob_start();
	?>
	<nav
		class="toc-block"
		id="toc-block"
		aria-label="<?php echo esc_attr( $title ); ?>"
		data-storage-key="<?php echo esc_attr( $storage_key ); ?>"
	>
		<div class="toc-header">
			<span class="toc-title"><?php echo $title; ?></span>
			<button
				class="toc-toggle"
				type="button"
				aria-expanded="<?php echo $expanded ? 'true' : 'false'; ?>"
				aria-controls="toc-list"
				aria-label="<?php echo $expanded
					? esc_attr__( 'Collapse contents', 'momentive' )
					: esc_attr__( 'Expand contents',   'momentive' ); ?>"
			>
				<svg class="toc-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
					<path d="M3 6L8 11L13 6" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</button>
		</div>
		<ol
			class="toc-list"
			id="toc-list"
			<?php echo $expanded ? '' : 'hidden'; ?>
		>
			<?php echo $items_html; ?>
		</ol>
	</nav>
	<?php
	return ob_get_clean();
}