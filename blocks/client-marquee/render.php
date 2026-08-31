<?php
/**
 * Front-end render for momentive/client-marquee.
 *
 * Called via momentive_render_client_marquee() in block.php.
 * Variables available: $attributes (array), $content (string), $block (WP_Block).
 *
 * Marquee mode uses a pure CSS animation (no Splide / JS dependency).
 * The logo set is output twice so the -50% translateX animation loops seamlessly.
 * Duration is computed from logo count to maintain a consistent scroll speed
 * regardless of how many logos are shown.
 *
 * Images use fetchpriority="low" + decoding="async" throughout:
 *   - fetchpriority="low" tells the browser these aren't LCP candidates so
 *     they yield to hero images and critical content, without blocking load.
 *   - decoding="async" keeps image decoding off the main thread.
 *   - No loading="lazy" on marquee images — they're all in the DOM for the loop
 *     and lazy-loading can cause pop-in when a CSS transform brings a slide
 *     into view (the browser sees layout position, not transform position).
 */

$mode         = $attributes['mode']             ?? 'marquee';
$logo_variant = $attributes['logoVariant']      ?? 'mono';
$grid_size    = $attributes['gridSize']         ?? 'medium';
$count        = max( 1, (int) ( $attributes['count'] ?? 20 ) );
$cat_id       = (int) ( $attributes['filterByCategory'] ?? 0 );
$tag_id       = (int) ( $attributes['filterByTag']      ?? 0 );
$class_name   = $attributes['className']        ?? '';
$anchor       = $attributes['anchor']           ?? '';
$show_name    = ! empty( $attributes['showName'] );
$faded_logos  = ! empty( $attributes['fadedLogos'] );
$two_row      = ! empty( $attributes['twoRow'] );
$show_mask    = $attributes['showMask'] ?? true;

// ── Query ─────────────────────────────────────────────────────────────────────

$query_args = [
	'post_type'      => 'client',
	'post_status'    => 'publish',
	'posts_per_page' => $count,
	'orderby'        => 'menu_order title',
	'order'          => 'ASC',
	'no_found_rows'  => true,
];

$tax_query = [];
if ( $cat_id ) {
	$tax_query[] = [
		'taxonomy' => 'category',
		'field'    => 'term_id',
		'terms'    => $cat_id,
	];
}
if ( $tag_id ) {
	$tax_query[] = [
		'taxonomy' => 'post_tag',
		'field'    => 'term_id',
		'terms'    => $tag_id,
	];
}
if ( $tax_query ) {
	$tax_query['relation']   = 'AND';
	$query_args['tax_query'] = $tax_query;
}

$clients = new WP_Query( $query_args );

if ( ! $clients->have_posts() ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		echo '<div class="is-placeholder" style="padding:2rem;text-align:center;border:1px dashed #ccc;color:#999;">No client logos found. Add posts to the Clients post type and assign categories or tags as needed.</div>';
	}
	return;
}

// ── Build logo list ───────────────────────────────────────────────────────────

$items = [];
while ( $clients->have_posts() ) {
	$clients->the_post();
	$id       = get_the_ID();
	$name     = get_the_title();
	$logo_url = '';
	$logo_alt = $name;

	if ( 'mono' === $logo_variant ) {
		$mono = get_field( 'logo_mono', $id );
		if ( ! empty( $mono['url'] ) ) {
			$logo_url = esc_url( $mono['url'] );
			$logo_alt = ! empty( $mono['alt'] ) ? $mono['alt'] : $name;
		}
	}

	if ( ! $logo_url ) {
		$thumb_id = get_post_thumbnail_id( $id );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $src ) {
				$logo_url = esc_url( $src[0] );
				$alt_meta = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
				$logo_alt = $alt_meta ?: $name;
			}
		}
	}

	if ( ! $logo_url ) continue;

	$items[] = [
		'url'        => $logo_url,
		'alt'        => esc_attr( $logo_alt ),
		'name'       => esc_attr( $name ),
		'client_url' => get_field( 'client_url', $id ) ? esc_url( get_field( 'client_url', $id ) ) : '',
	];
}
wp_reset_postdata();

if ( ! $items ) return;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Render one logo as a <figure> with performance attributes.
 * Defined as a closure so this file can be included multiple times
 * (once per block instance) without triggering a redeclaration error.
 * $aria_hidden — true for the duplicate set (screen readers skip it).
 */
$figure = function ( array $item, bool $aria_hidden = false, bool $show_name = false ): string {
	$img_attrs = sprintf(
		'src="%s" alt="%s" fetchpriority="low" decoding="async"',
		$item['url'],
		$aria_hidden ? '' : $item['alt'] // duplicates need no alt (aria-hidden on parent)
	);

	$inner = $item['client_url']
		? sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer"><img %s /></a>', $item['client_url'], $img_attrs )
		: sprintf( '<img %s />', $img_attrs );

	$data_name = ( $show_name && ! $aria_hidden && ! empty( $item['name'] ) )
		? ' data-name="' . $item['name'] . '"'
		: '';

	return '<figure' . $data_name . '>' . $inner . '</figure>';
};

// ── Output ────────────────────────────────────────────────────────────────────

$wrapper_classes = trim( 'wp-block-momentive-client-marquee'
	. ( $faded_logos         ? ' has-faded-logos'  : '' )
	. ( $show_name           ? ' has-name-tooltip' : '' )
	. ( ! $show_mask         ? ' no-mask'          : '' )
	. ' ' . $class_name );
$wrapper_attrs   = 'class="' . esc_attr( $wrapper_classes ) . '"';
if ( $anchor ) {
	$wrapper_attrs .= ' id="' . esc_attr( $anchor ) . '"';
}

// Marquee: duration scales with logo count so scroll speed stays consistent
// (~3.5s per logo at 11rem/logo ≈ 50px/s). The -50% animation covers one full
// set (half the doubled track width).
// In two-row mode each row has ~half the logos, so duration is halved too.
if ( 'marquee' === $mode ) {
	$logos_per_row = $two_row ? (int) ceil( count( $items ) / 2 ) : count( $items );
	$duration      = max( 10, (int) round( $logos_per_row * 3.5 ) );
	$wrapper_attrs .= ' style="--marquee-duration:' . $duration . 's"';
}

echo '<div ' . $wrapper_attrs . '>';

// ── Marquee ───────────────────────────────────────────────────────────────────

if ( 'marquee' === $mode ) {

	/**
	 * Output one marquee row.
	 * $row_items  — the logos for this row.
	 * $reverse    — true → second row, scrolls right via CSS class.
	 */
	$render_row = function ( array $row_items, bool $reverse ) use ( $figure, $show_name ): void {
		$track_class = 'client-marquee__track' . ( $reverse ? ' client-marquee__track--reverse' : '' );
		echo '<div class="client-marquee__viewport">';
		echo '<div class="' . esc_attr( $track_class ) . '">';

		// Set 1 — real, announced to screen readers.
		echo '<div class="client-marquee__set">';
		foreach ( $row_items as $item ) {
			echo $figure( $item, false, $show_name );
		}
		echo '</div>';

		// Set 2 — duplicate for seamless loop; hidden from screen readers.
		echo '<div class="client-marquee__set" aria-hidden="true">';
		foreach ( $row_items as $item ) {
			echo $figure( $item, true, false );
		}
		echo '</div>';

		echo '</div>'; // .client-marquee__track
		echo '</div>'; // .client-marquee__viewport
	};

	if ( $two_row ) {
		$mid   = (int) ceil( count( $items ) / 2 );
		$row1  = array_slice( $items, 0, $mid );
		$row2  = array_slice( $items, $mid );
		$render_row( $row1, false );
		$render_row( $row2, true );
	} else {
		$render_row( $items, false );
	}

// ── Grid ──────────────────────────────────────────────────────────────────────

} else {
	$grid_classes = 'client-logos grid';
	if ( 'medium' === $grid_size ) $grid_classes .= ' medium';
	if ( 'small'  === $grid_size ) $grid_classes .= ' small';

	echo '<div class="' . esc_attr( $grid_classes ) . '">';
	foreach ( $items as $item ) {
		// Grid logos are likely below the fold — lazy load is appropriate here.
		$img_attrs = sprintf(
			'src="%s" alt="%s" loading="lazy" decoding="async"',
			$item['url'],
			$item['alt']
		);
		$inner = $item['client_url']
			? sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer"><img %s /></a>', $item['client_url'], $img_attrs )
			: sprintf( '<img %s />', $img_attrs );
		$data_name = ( $show_name && ! empty( $item['name'] ) ) ? ' data-name="' . $item['name'] . '"' : '';
		echo '<figure' . $data_name . '>' . $inner . '</figure>';
	}
	echo '</div>';
}

echo '</div>';
