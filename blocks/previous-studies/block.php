<?php
/**
 * Block: momentive/previous-studies
 *
 * "Explore Previous Studies" card grid for the research-study shape of the
 * `guide` CPT (see inc/guides.php and notes/guide-reference-sheet.md — 3/25
 * legacy posts use this section).
 *
 * Deliberately NOT a post_object/relationship field, and deliberately NOT a
 * variant of momentive/solution-resources. Checked against the legacy export:
 * the download link on every card points straight at a hosted PDF, never at
 * an internal permalink — even on the one card whose title matches an actual
 * migrated guide post. And at least one card ("Benchmark Report: Small-Staff
 * Associations") references an asset that matches no post in either the
 * guides or whitepapers export at all. A relationship field would sit empty
 * for that card and still need a freeform fallback, so the legacy shape
 * (small hand-entered repeater: year + image + title + description + link)
 * is what's actually being modeled here, not a "browse the real thing" grid
 * like solution-resources.
 *
 * Data: block-level ACF fields `heading`, `label`, and repeater `items`
 * (subfields: year, image, title, description, download_link). `download_link`
 * is an ACF Link field (return_format "array": url/title/target) rather than
 * separate URL + link-text fields — its own "title" doubles as the button
 * label, falling back to "Download Now" when left blank, and its "target"
 * drives whether the button opens in a new tab.
 * `label` is a single field for the whole block, not per-card — the legacy
 * "2024 Small Staff Report" pill is `{item year} {label}` composed at
 * render time, matching how the legacy previous_resource_type field applies
 * uniformly across every card in a section. See acf-json for field keys.
 */

if ( ! function_exists( 'momentive_register_previous_studies_block' ) ) {

	add_action( 'init', 'momentive_register_previous_studies_block' );

	function momentive_register_previous_studies_block(): void {
		register_block_type( __DIR__ );

		// Front-end styles — registered here, enqueued conditionally below.
		wp_register_style(
			'momentive-previous-studies',
			get_template_directory_uri() . '/blocks/previous-studies/previous-studies.css',
			[],
			wp_get_theme()->get( 'Version' )
		);
	}

	// Conditional enqueue: only when the block is present (singular) — matches
	// the project's enqueue_block_assets + momentive_content_has_block pattern.
	add_action( 'enqueue_block_assets', function (): void {
		if ( is_admin() ) {
			return;
		}
		if ( momentive_content_has_block( 'momentive/previous-studies' ) ) {
			wp_enqueue_style( 'momentive-previous-studies' );
		}
	} );
}

/**
 * Render callback (ACF renderTemplate target).
 *
 * @param array  $block      Block settings and attributes.
 * @param string $content    Block inner content (unused).
 * @param bool   $is_preview True during AJAX editor preview.
 * @param int    $post_id    The post ID this block is rendering on.
 */

$heading = get_field( 'heading' );
$heading = ( null === $heading || false === $heading || '' === $heading ) ? 'Explore Previous Studies' : $heading;

$label = trim( (string) get_field( 'label' ) );

$rows = get_field( 'items' );

// Drop rows with nothing usable (no image, no title, no link) so a stray
// empty repeater row doesn't render a blank card.
$cards = [];
if ( is_array( $rows ) ) {
	foreach ( $rows as $row ) {
		$title = trim( (string) ( $row['title'] ?? '' ) );
		$link  = is_array( $row['download_link'] ?? null ) ? trim( (string) ( $row['download_link']['url'] ?? '' ) ) : '';
		$image = $row['image'] ?? 0;
		if ( '' === $title && '' === $link && empty( $image ) ) {
			continue;
		}
		$cards[] = $row;
	}
}

// No cards: render a placeholder in the editor, nothing on the front end.
if ( empty( $cards ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<div class="previous-studies is-placeholder"><p>Add one or more previous studies to display.</p></div>';
	}
	return;
}

$anchor = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';
?>
<div class="previous-studies"<?php echo $anchor; ?>>

	<?php if ( $heading ) : ?>
	<h2 class="previous-studies__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<ul class="previous-studies__grid" data-count="<?php echo count( $cards ); ?>" role="list">
		<?php foreach ( $cards as $row ) :
			$year        = trim( (string) ( $row['year'] ?? '' ) );
			$title       = trim( (string) ( $row['title'] ?? '' ) );
			$description = trim( (string) ( $row['description'] ?? '' ) );

			// ACF Link field (return_format "array"): url/title/target.
			$link_field    = is_array( $row['download_link'] ?? null ) ? $row['download_link'] : [];
			$download_url  = trim( (string) ( $link_field['url'] ?? '' ) );
			$link_text     = trim( (string) ( $link_field['title'] ?? '' ) ) ?: 'Download Now';
			$link_new_tab  = '_blank' === ( $link_field['target'] ?? '' );

			// Pill text composes the card's own year with the block-level
			// label (e.g. "2024" + "Small Staff Report"), matching the
			// legacy previous_resource_type behavior — one label applied
			// across every card in the section, not stored per row.
			$pill = trim( $year . ' ' . $label );

			$image_id = $row['image'] ?? 0;
			$image_id = is_array( $image_id ) ? ( $image_id['ID'] ?? 0 ) : (int) $image_id;
			?>
			<li class="previous-studies__card">

				<?php if ( $image_id || $pill ) : ?>
				<div class="previous-studies__media">
					<?php if ( $pill ) : ?>
					<span class="previous-studies__pill"><?php echo esc_html( $pill ); ?></span>
					<?php endif; ?>
					<?php if ( $image_id ) : ?>
						<?php echo wp_get_attachment_image( $image_id, 'large', false, [
							'class'   => 'previous-studies__image',
							'alt'     => esc_attr( $title ),
							'loading' => 'lazy',
						] ); ?>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="previous-studies__body">
					<?php if ( $title ) : ?>
					<h3 class="previous-studies__title"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>

					<?php if ( $description ) : ?>
					<p class="previous-studies__description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>

					<?php if ( $download_url ) : ?>
					<div class="wp-block-buttons"><div class="wp-block-button has-arrow"><a class="previous-studies__button" href="<?php echo esc_url( $download_url ); ?>"<?php echo $link_new_tab ? ' target="_blank" rel="noreferrer noopener"' : ''; ?>>
						<?php echo esc_html( $link_text ); ?>
					</a></div></div>
					<?php endif; ?>
				</div>

			</li>
		<?php endforeach; ?>
	</ul>
</div>
