<?php
/**
 * Animated rings background — render_block filter
 *
 * When a core/group block carries the is-style-bg-rings block style,
 * this filter:
 *   1. Adds the has-animated-rings class to the outer div (so SCSS can
 *      suppress the static :after background-image for this instance).
 *   2. Injects an inline animated SVG as the first child of the group,
 *      replacing the CSS background with orbiting shapes.
 *
 * The static rings (concentric circles) are included in the inline SVG
 * so everything lives in one place. Each decorative shape is wrapped in a
 * <g> with an orbit-cw / orbit-ccw class and a --orbit-dur CSS custom
 * property; the animation keyframes and transform-origin live in
 * momentive.scss. prefers-reduced-motion is handled there too.
 */

add_filter( 'render_block', 'momentive_maybe_inject_animated_rings', 10, 2 );

/**
 * @param string $block_content Rendered block HTML.
 * @param array  $block         Block data array.
 * @return string
 */
function momentive_maybe_inject_animated_rings( string $block_content, array $block ): string {
	if ( 'core/group' !== $block['blockName'] ) {
		return $block_content;
	}

	$classes = $block['attrs']['className'] ?? '';
	if ( ! str_contains( $classes, 'is-style-bg-rings' ) ) {
		return $block_content;
	}

	// 1. Add has-animated-rings alongside the existing class so SCSS can
	//    suppress :after (the static CSS background) for this instance.
	$block_content = preg_replace(
		'/(class="[^"]*\bis-style-bg-rings\b)/',
		'$1 has-animated-rings',
		$block_content,
		1
	);

	// 2. Inject the SVG wrapper right after the first opening tag's ">".
	$first_gt = strpos( $block_content, '>' );
	if ( false === $first_gt ) {
		return $block_content;
	}

	$svg = momentive_animated_rings_svg();

	return substr( $block_content, 0, $first_gt + 1 )
		. $svg
		. substr( $block_content, $first_gt + 1 );
}

/**
 * Returns the inline animated SVG markup.
 *
 * The SVG viewBox is 1920 × 1294, centred at (960, 524) — matching the
 * original rings-with-shapes-full.svg. Shapes orbit that centre point.
 *
 * Orbit directions and durations are chosen for a slow, varied feel:
 *   - Cyan square    (outer, ~683 r) : CW  82 s
 *   - Purple circle  (mid,   ~449 r) : CCW 54 s
 *   - Red rect       (mid,   ~475 r) : CCW 68 s
 *   - Orange arrow   (inner, ~359 r) : CW  38 s
 *   - Teal circle    (outer, ~610 r) : CW  76 s
 *   - Blue circle    (mid,   ~523 r) : CCW 58 s
 *
 * @return string
 */
function momentive_animated_rings_svg(): string {
	// 6 orbits, one per impact-stat palette color, 3 copies each at 120°
	// spacing (animation-delay: 0, -dur/3, -2*dur/3). With shapes every
	// 120° the gap between any two is never more than 1/3 of the orbit,
	// so the background stays populated even as shapes exit the visible area.
	//
	// Orbit summary (approx. radius from centre 960,524):
	//   A  inner  ~359r  52s CW   Orange  #E8611A  arrow
	//   B  mid    ~449r  74s CCW  Purple  #7B61FF  filled circle
	//   C  mid    ~475r  92s CCW  Rose    #D73F5D  filled rotated-rect
	//   D  mid    ~523r  80s CW   Blue    #3B82F6  stroked circle
	//   E  outer  ~610r 102s CCW  Teal    #00C4B4  stroked circle
	//   F  outer  ~683r 112s CW   Brand   #6EC1E4  stroked square

	// Shape paths (original SVG coordinates).
	$arrow  = 'M1321.67 568.339C1321.15 566.022 1319.23 565.303 1317.38 566.742L1309.64 572.727C1307.17 574.64 1307.52 577.071 1310.44 578.165L1319.83 581.661C1322.75 582.754 1324.55 581.114 1323.85 578.021L1321.69 568.339H1321.67Z';
	$circ_f = 'M521.5 631C526.747 631 531 626.747 531 621.5C531 616.253 526.747 612 521.5 612C516.253 612 512 616.253 512 621.5C512 626.747 516.253 631 521.5 631Z';
	$rect_f = 'M497.802 390.815L504.489 389.024C506.595 388.459 508.763 389.711 509.328 391.818L511.168 398.687C511.733 400.794 510.481 402.962 508.375 403.527L501.703 405.314C499.596 405.879 497.428 404.627 496.863 402.52L495.023 395.651C494.458 393.544 495.71 391.376 497.817 390.811L497.802 390.815Z';
	$circ_s = 'M1465 397C1469.97 397 1474 392.971 1474 388C1474 383.029 1469.97 379 1465 379C1460.03 379 1456 383.029 1456 388C1456 392.971 1460.03 397 1465 397Z';
	$circ_t = 'M350.02 528C354.991 528 359.02 523.971 359.02 519C359.02 514.029 354.991 510 350.02 510C345.05 510 341.02 514.029 341.02 519C341.02 523.971 345.05 528 350.02 528Z';
	$sq_s   = 'M1633.84 592.429L1635.03 586.309C1635.49 583.925 1637.79 582.374 1640.17 582.837L1646.09 583.988C1648.48 584.451 1650.03 586.75 1649.56 589.135L1648.38 595.255C1647.91 597.639 1645.61 599.19 1643.23 598.726L1637.31 597.576C1634.92 597.112 1633.37 594.813 1633.84 592.429Z';

	// Build the three orbit copies for each group as a PHP helper.
	// $dir is 'orbit-cw' or 'orbit-ccw'; $dur in seconds; $inner is the
	// SVG element string (path/circle) to place inside each <g>.
	$trio = static function ( string $dir, int $dur, string $inner ): string {
		$d1 = (int) round( $dur / 3 );
		$d2 = (int) round( $dur * 2 / 3 );
		return <<<TRIO
  <g class="{$dir}" style="--orbit-dur:{$dur}s">{$inner}</g>
  <g class="{$dir}" style="--orbit-dur:{$dur}s;animation-delay:-{$d1}s">{$inner}</g>
  <g class="{$dir}" style="--orbit-dur:{$dur}s;animation-delay:-{$d2}s">{$inner}</g>
TRIO;
	};

	// Pre-render each orbit's three copies.
	$orbit_a = $trio( 'orbit-cw',  52,  "\n    <path d=\"{$arrow}\" fill=\"#E8611A\"/>" );
	$orbit_b = $trio( 'orbit-ccw', 74,  "\n    <path d=\"{$circ_f}\" fill=\"#7B61FF\"/>" );
	$orbit_c = $trio( 'orbit-ccw', 92,  "\n    <path d=\"{$rect_f}\" fill=\"#D73F5D\"/>" );
	$orbit_d = $trio( 'orbit-cw',  80,  "\n    <path d=\"{$circ_s}\" stroke=\"#3B82F6\" stroke-width=\"2.87\" stroke-miterlimit=\"10\"/>" );
	$orbit_e = $trio( 'orbit-ccw', 102, "\n    <path d=\"{$circ_t}\" stroke=\"#00C4B4\" stroke-width=\"2.87\" stroke-miterlimit=\"10\"/>" );
	$orbit_f = $trio( 'orbit-cw',  112, "\n    <path d=\"{$sq_s}\" stroke=\"#6EC1E4\" stroke-width=\"2.87\" stroke-miterlimit=\"10\"/>" );

	return <<<HTML
<div class="bg-rings-wrap" aria-hidden="true">
<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1294" viewBox="0 0 1920 1294" fill="none">
  <defs>
    <linearGradient id="bra_g0" x1="960.001" y1="235.535" x2="960.001" y2="812.462" gradientUnits="userSpaceOnUse">
      <stop offset="0.659821" stop-color="#64C1EE"/>
      <stop offset="1" stop-color="#64C1EE" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="bra_g1" x1="960" y1="164" x2="960" y2="885" gradientUnits="userSpaceOnUse">
      <stop offset="0.639692" stop-color="#64C1EE"/>
      <stop offset="1" stop-color="#64C1EE" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="bra_g2" x1="959.999" y1="96.2852" x2="959.999" y2="951.718" gradientUnits="userSpaceOnUse">
      <stop offset="0.764677" stop-color="#64C1EE"/>
      <stop offset="1" stop-color="#64C1EE" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="bra_g3" x1="960" y1="601.5" x2="960" y2="1048" gradientUnits="userSpaceOnUse">
      <stop stop-color="#64C1EE"/>
      <stop offset="1" stop-color="#64C1EE" stop-opacity="0"/>
    </linearGradient>
  </defs>

  <!-- Static concentric rings -->
  <g opacity="0.3">
    <path d="M960.001 812.462C1119.32 812.462 1248.46 683.313 1248.46 523.999C1248.46 364.685 1119.32 235.535 960.001 235.535C800.687 235.535 671.538 364.685 671.538 523.999C671.538 683.313 800.687 812.462 960.001 812.462Z" stroke="url(#bra_g0)" stroke-opacity="0.2" stroke-width="1.88221" stroke-miterlimit="10"/>
    <path d="M960 885C1159.37 885 1321 723.599 1321 524.5C1321 325.401 1159.37 164 960 164C760.625 164 599 325.401 599 524.5C599 723.599 760.625 885 960 885Z" stroke="url(#bra_g1)" stroke-opacity="0.1" stroke-width="1.88221" stroke-miterlimit="10"/>
    <path d="M959.999 951.718C1196.22 951.718 1387.72 760.223 1387.72 524.001C1387.72 287.78 1196.22 96.2852 959.999 96.2852C723.778 96.2852 532.283 287.78 532.283 524.001C532.283 760.223 723.778 951.718 959.999 951.718Z" stroke="url(#bra_g2)" stroke-opacity="0.07" stroke-width="1.88221" stroke-miterlimit="10"/>
    <path d="M960 1048C1249.4 1048 1484 813.397 1484 524C1484 234.603 1249.4 0 960 0C670.603 0 436 234.603 436 524C436 813.397 670.603 1048 960 1048Z" stroke="url(#bra_g3)" stroke-opacity="0.1" stroke-width="2.29244" stroke-miterlimit="10"/>
  </g>

  <!-- Orbit A — inner ~359r, 52s CW, Orange arrows -->
{$orbit_a}
  <!-- Orbit B — mid ~449r, 74s CCW, Purple filled circles -->
{$orbit_b}
  <!-- Orbit C — mid ~475r, 92s CCW, Rose filled rects -->
{$orbit_c}
  <!-- Orbit D — mid ~523r, 80s CW, Blue stroked circles -->
{$orbit_d}
  <!-- Orbit E — outer ~610r, 102s CCW, Teal stroked circles -->
{$orbit_e}
  <!-- Orbit F — outer ~683r, 112s CW, Brand Blue stroked squares -->
{$orbit_f}
</svg>
</div>
HTML;
}
