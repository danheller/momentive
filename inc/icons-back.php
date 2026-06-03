<?php

/**
 * Custom Post Type: Icons
 */

// Icon definitions - add your available icons here
function momentive_get_available_icons() {
	return [
		'dollar-sign' => 'Dollar Sign',
		'headphones' => 'Headphones',
		'iq' => 'IQ',
		'molecule' => 'Molecule',
		'people' => 'People',
		'rocket' => 'Rocket',
	];
}

// Register the custom block
function momentive_register_icon_block() {
	wp_register_script(
		'momentive-icon-block',
		get_stylesheet_directory_uri() . '/blocks/icon-block/block.js',
		array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
		wp_get_theme()->get( 'Version' )
	);
	
	wp_register_style(
		'momentive-icon-block-editor',
		get_stylesheet_directory_uri() . '/blocks/icon-block/editor.css',
		array('wp-edit-blocks'),
		wp_get_theme()->get( 'Version' )
	);
	
	wp_register_style(
		'momentive-icon-block-style',
		get_stylesheet_directory_uri() . '/blocks/icon-block/style.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
	
	register_block_type('momentive/icon-block', array(
		'editor_script' => 'momentive-icon-block',
		'editor_style'  => 'momentive-icon-block-editor',
		'style'         => 'momentive-icon-block-style',
		'render_callback' => 'momentive_render_icon_block',
		'attributes'    => array(
			'iconId'          => array('type' => 'string', 'default' => 'charts-horizontal'),
			'shape'           => array('type' => 'string', 'default' => 'circle'),
			'backgroundColor' => array('type' => 'string', 'default' => 'pink'),
			'strokeColor'     => array('type' => 'string', 'default' => 'default'),
			'fillColor'       => array('type' => 'string', 'default' => 'none'),
			'className'       => array('type' => 'string', 'default' => ''),
		)
	));
}
add_action('init', 'momentive_register_icon_block');

// Render callback for the block
function momentive_render_icon_block($attributes) {
	$icon_id = $attributes['iconId'] ?? 'charts-horizontal';
	$shape = $attributes['shape'] ?? 'circle';
	$bg_color = $attributes['backgroundColor'] ?? 'pink';
	$stroke_color = $attributes['strokeColor'] ?? 'default';
	$fill_color = $attributes['fillColor'] ?? 'none';
	$custom_class = $attributes['className'] ?? '';

	$classes = array('svg-icon', 'shape-' . $shape, 'bg-' . $bg_color, $icon_id . '-icon');

	// Add custom class if provided
	if (!empty($custom_class)) {
		$classes[] = $custom_class;
	}
	
	$style_vars = '';
	if ($stroke_color !== 'default') {
		$style_vars .= '--icon-stroke: var(--' . $stroke_color . ');';
	}
	if ($fill_color !== 'none') {
		$style_vars .= '--icon-fill: var(--' . $fill_color . ');';
	}
	
	$style_attr = $style_vars ? ' style="' . esc_attr($style_vars) . '"' : '';
	
	return sprintf(
		'<span class="%s"%s><svg aria-hidden="true"><use href="#icon-%s"></use></svg></span>',
		esc_attr(implode(' ', $classes)),
		$style_attr,
		esc_attr($icon_id)
	);
}

// Helper function to recursively extract icons from nested blocks
function momentive_extract_icons_from_blocks( $blocks ) {
	$icons = [];
	
	foreach ( $blocks as $block ) {
		// Check for iconId attribute
		if ( isset( $block['attrs']['iconId'] ) ) {
			$icons[] = $block['attrs']['iconId'];
		}
		
		// Check for has-{icon}-icon classes
		if ( isset( $block['attrs']['className'] ) ) {
			if ( preg_match_all( '/has-([a-z0-9\-]+)-icon/', $block['attrs']['className'], $matches ) ) {
				$icons = array_merge( $icons, $matches[1] );
			}
		}
		
		// Recursively check inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			$icons = array_merge( $icons, momentive_extract_icons_from_blocks( $block['innerBlocks'] ) );
		}
	}
	
	return $icons;
}

// Add SVG symbols to block editor
function momentive_add_svg_symbols_to_editor() {
	$screen = get_current_screen();
	if ($screen && $screen->is_block_editor()) {
		add_action('admin_footer', 'momentive_output_svg_symbols');
	}
}
add_action('admin_head', 'momentive_add_svg_symbols_to_editor');

// Pass available icons to the block editor
function momentive_icon_block_editor_assets() {
	$screen = get_current_screen();
	if ($screen && $screen->is_block_editor()) {
		wp_localize_script('momentive-icon-block', 'momentiveIcons', array(
			'available' => momentive_get_available_icons()
		));
	}
}
add_action('admin_enqueue_scripts', 'momentive_icon_block_editor_assets');

function momentive_enqueue_icon_overlay_script() {
	wp_enqueue_script(
		'momentive-icon-overlay',
		get_stylesheet_directory_uri() . '/blocks/icon-block/editor.js',
		array('wp-dom-ready', 'wp-data', 'wp-blocks'), // Dependencies
		'1.0.0',
		true
	);
}
add_action('enqueue_block_editor_assets', 'momentive_enqueue_icon_overlay_script'); // Editor

// for now, add all symbols to page footer
add_action( 'wp_footer', function() {
	momentive_output_svg_symbols( null ); // always output all icons
}, 20 );


/*
// later, only show symbols that are used
add_action( 'wp_footer', function() {
	global $post, $momentive_icons_used;
	if ( ! isset( $momentive_icons_used ) ) $momentive_icons_used = [];
	
	// Add icons from the page's own blocks
	if ( $post ) {
		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $block ) {
			$momentive_icons_used = array_merge(
				$momentive_icons_used,
				momentive_extract_icons_from_blocks( [ $block ] )
			);
		}
	}
	
	momentive_output_svg_symbols( ! empty( $momentive_icons_used ) ? array_unique( $momentive_icons_used ) : null );
}, 20 );
*/

// Shared function to output SVG symbols
function momentive_output_svg_symbols( $icons_used = null ) {
	// If no specific icons requested, output all (for editor/admin)
	$output_all = false;
	if( ! isset( $icons_used ) || ! is_array( $icons_used ) ) {
		$output_all = true;
	}
	?>
	<svg style="display: none;" aria-hidden="true">
	<?php 

	if ( $output_all || in_array( 'dollar-sign', $icons_used ) ) { ?>
		<symbol id="icon-dollar-sign" viewBox="0 0 85.764 85.59" xml:space="preserve" id="svg-Dollar_sign" xmlns="http://www.w3.org/2000/svg"><path fill="var(--icon-fill)" d="M37.531 85.46c-10.993-1.05-21.511-6.65-28.29-15.064C.918 60.068-1.963 46.802 1.334 34c1.776-6.896 5.722-13.762 10.862-18.901C18.395 8.899 26.623 4.782 35.467 3.452c2.78-.417 9.103-.364 11.997.102 6.503 1.046 13.229 3.914 18.508 7.892l2.117 1.595-4.362 4.394c-2.4 2.416-4.494 4.653-4.655 4.971-1.276 2.517 1.703 5.36 4.239 4.045.345-.18 2.569-2.245 4.942-4.591l4.314-4.265.785.926c.994 1.174 3.342 4.716 4.384 6.614 3.599 6.557 5.327 14.903 4.638 22.406-.5 5.452-1.405 8.902-3.64 13.886-5.58 12.434-17.087 21.35-30.476 23.616-3.019.51-7.804.697-10.727.417zm5.694-20.152c.662-.468 1.261-1.841 1.265-2.897 0-.34.377-.454 1.825-.558 2.266-.162 3.972-.952 5.627-2.605 1.547-1.545 2.227-2.869 2.613-5.081.361-2.076.005-4.958-.81-6.557-.868-1.7-3.428-3.408-7.137-4.762l-1.984-.724-.073-4.525c-.08-5.038-.115-4.954 1.692-4.097 1.207.573 1.902 1.612 2.222 3.32.442 2.358 1.802 3.61 3.563 3.28 2.128-.399 2.991-2.01 2.516-4.697-.55-3.109-2.712-6.166-5.29-7.48-1.144-.584-1.574-.705-3.77-1.064-.546-.09-.993-.306-.994-.48-.004-.87-.609-2.313-1.229-2.933-1.762-1.762-5.12-.167-5.12 2.432 0 .756-.032.773-1.677.905-3.468.278-6.313 2.264-7.805 5.448-.625 1.333-.703 1.814-.695 4.28.01 3.219.47 4.575 2.087 6.145 1.167 1.134 3.846 2.565 6.304 3.368l1.786.584V55.632l-.918-.15c-1.747-.283-2.845-1.534-3.183-3.624-.313-1.939-.955-2.759-2.56-3.267-.79-.25-2.283.351-3.006 1.21-.643.765-.695.993-.588 2.573.184 2.742 1.057 4.621 3.087 6.652 1.932 1.932 3.901 2.886 5.953 2.886h1.147l.136 1.211c.296 2.627 2.83 3.73 5.016 2.185zm1.266-13.053c0-1.819.104-3.307.232-3.307.61 0 2.598.874 3.134 1.378.45.422.603.861.603 1.724 0 2.131-1.168 3.512-2.97 3.512h-.999zm-7.903-12.824c-2.176-.74-2.54-1.302-2.443-3.76.016-.413.399-1.102.919-1.653.742-.787 1.073-.945 1.984-.945h1.093v3.44c0 1.891-.03 3.43-.066 3.42-.036-.01-.705-.236-1.487-.502zM81.92 22.447c-.442-.114-1.156-.576-1.588-1.026l-.783-.818-.133-4.944-.132-4.943-3.37 3.29-3.37 3.29-2.093-2.085-2.094-2.085 3.277-3.454 3.278-3.454-5.045-.133-5.044-.132-.906-.988c-1.273-1.39-1.158-2.943.322-4.314L64.942 0h9.318c9.158 0 9.33.01 10.048.574 1.383 1.088 1.459 1.626 1.455 10.332-.002 4.406-.078 8.466-.17 9.02-.186 1.13-1.313 2.432-2.261 2.613-.335.064-.97.023-1.412-.092z"/></symbol>
	<?php }

	if ( $output_all || in_array( 'headphones', $icons_used ) ) { ?>
		<symbol id="icon-headphones" viewBox="0 0 79.071 79.127" xml:space="preserve" id="svg-Headphones" xmlns="http://www.w3.org/2000/svg"><path fill="var(--icon-fill)" d="M31.73 73.295v-5.832h15.588l.078 1.918.077 1.918 3.704.078c2.038.043 4.478-.054 5.424-.215 2.851-.486 4.967-2.08 6.138-4.625.605-1.314.61-1.418.742-15.081.097-10.18.216-13.906.457-14.325.178-.312.693-.878 1.144-1.257.757-.637 1.013-.69 3.314-.69 1.818 0 2.493-.09 2.493-.33 0-.183-.293-1.538-.65-3.012-1.366-5.636-4.063-10.384-8.335-14.669-5.67-5.687-11.574-8.482-19.508-9.234-15.717-1.49-30.647 9.403-33.793 24.654-.231 1.122-.42 2.163-.42 2.315 0 .17.869.276 2.262.276 2.523 0 3.361.3 4.453 1.598l.693.824-.001 11.423c-.001 10.931-.024 11.46-.527 12.285-.982 1.61-1.849 1.914-5.481 1.915-2.603 0-3.511-.104-4.67-.537-1.8-.674-3.655-2.285-4.397-3.817-.561-1.16-.567-1.331-.463-13.16.116-13.228.125-13.31 1.96-18.733C5.156 17.69 11.268 10.224 19.91 5.117 25.673 1.711 32.221-.007 39.403 0 50.166.011 59.374 3.736 67.15 11.224c5.605 5.399 9.455 12.297 11.304 20.256.392 1.688.477 3.768.566 13.934.105 11.841.1 11.963-.476 13.303-1.065 2.475-4.065 4.513-6.644 4.513-.702 0-.746.066-.746 1.135 0 2.748-1.734 6.96-3.871 9.403-1.575 1.8-4.084 3.57-6.347 4.476l-1.82.728-13.692.078-13.692.077z"/></symbol>
	<?php }

	
	if ( $output_all || in_array( 'molecule', $icons_used ) ) {
	?>
		<symbol id="icon-molecule" viewBox="0 0 46.027 46.183" xml:space="preserve" xmlns="http://www.w3.org/2000/svg"><path fill="var(--icon-fill)" d="M16.366 45.904c-2.221-.83-3.691-2.236-4.479-4.28-.457-1.188-.393-3.857.117-4.843l.436-.842-2.238-2.979-2.239-2.978-1.49-.052c-1.925-.067-3.54-.855-4.887-2.385C.364 26.155-.006 25.058 0 22.838c.006-2.015.622-3.383 2.158-4.794 1.744-1.6 3.491-2.023 6.729-1.625.433.053.977-.494 2.401-2.417l1.842-2.485-.781-1.442C10.068 5.863 12.855.61 17.672.041c3.669-.433 7.409 2.66 7.54 6.237l.048 1.328 4.491 1.82 4.49 1.82.988-.714c2.722-1.972 6.358-1.672 8.831.73 1.38 1.34 1.958 2.763 1.962 4.829.005 2.795-1.301 4.98-3.728 6.234l-.762.394v5.479l.784.324c1.1.456 3.046 2.617 3.445 3.826.53 1.605.236 4.556-.581 5.838-1.317 2.064-2.893 3.062-5.12 3.24-2.281.184-3.346-.07-4.737-1.133l-1.195-.912-4.48.923c-2.465.507-4.536.922-4.604.922-.068 0-.19.268-.273.596-.28 1.11-1.727 2.742-3.088 3.483-1.615.88-3.886 1.135-5.317.6zm3.741-5.142c.749-.952.677-2.22-.174-3.071-2.018-2.018-5.037.834-3.266 3.085.875 1.113 2.559 1.106 3.44-.014zM40.76 36.22c.862-.862.96-1.537.386-2.648-.437-.845-.881-1.077-2.065-1.077-2.235 0-2.866 3.093-.855 4.193.899.491 1.728.338 2.534-.468zm-11.917-.42c1.792-.364 3.273-.692 3.291-.727.019-.035.168-.737.332-1.56.406-2.04 1.398-3.503 3.123-4.607l1.446-.925V22.716l-.992-.511c-2.054-1.058-3.453-3.123-3.693-5.447l-.144-1.39-4.433-1.768-4.432-1.767-1.237.834c-.972.656-1.626.871-3.057 1.005l-1.82.17-2.166 2.879-2.166 2.878.467 1.54c.576 1.903.402 3.5-.577 5.3l-.705 1.294.638.88c.35.485 1.172 1.601 1.825 2.481l1.188 1.6 2.12-.124c1.57-.092 2.373-.015 3.101.297 1.233.528 2.761 1.856 3.271 2.843.215.414.61.754.877.754s1.951-.299 3.743-.663zM8.34 24.703c1.412-1.411.421-3.848-1.564-3.848-.696 0-1.078.194-1.6.814-.748.889-.85 1.555-.376 2.444.744 1.398 2.448 1.682 3.54.59zm32.42-7.003c.862-.862.96-1.537.386-2.647-.437-.846-.881-1.078-2.065-1.078-1.93 0-2.804 2.498-1.345 3.85.962.892 2.052.847 3.024-.125zM19.816 8.527c1.613-1.357.69-3.813-1.435-3.813-2.354 0-3.114 3-1.058 4.178.844.484 1.619.37 2.493-.365z"/></symbol>
	<?php }

	if ( $output_all || in_array( 'people', $icons_used ) ) {
	?>
		<symbol id="icon-people" viewBox="0 0 105.418 94.295" xml:space="preserve" id="svg-People" xmlns="http://www.w3.org/2000/svg"><path fill="var(--icon-fill)" d="M21.798 93.994c-7.681-1.466-13.637-6.012-16.876-12.879-.558-1.183-1.726-4.951-2.946-9.501C.06 64.464-.04 63.956.062 61.824c.127-2.651.911-4.394 2.86-6.36 1.575-1.588 2.556-1.946 12.303-4.491 4.512-1.178 8.41-2.203 8.666-2.278.436-.128.463.606.463 12.817 0 14.24.077 15.173 1.63 19.577 1.43 4.052 3.702 7.788 6.342 10.429.69.689 1.156 1.313 1.037 1.386-.12.074-1.213.374-2.43.666-2.94.707-6.73.883-9.135.424zm26.827-.027c-4.835-.917-8.755-3.116-12.399-6.955a22.809 22.809 0 0 1-5.301-9.048c-.615-1.973-.618-2.068-.618-15.743V48.463l.687-1.456c.899-1.903 2.571-3.586 4.43-4.458l1.497-.701 15.151-.073c16.762-.08 16.705-.084 19.158 1.604 1.605 1.105 2.711 2.524 3.398 4.36.538 1.438.55 1.82.461 14.726-.088 12.816-.11 13.313-.681 15.189-1.907 6.263-6.316 11.62-11.873 14.426-3.812 1.925-9.582 2.708-13.91 1.887zm29.042.22c-1.473-.19-4.492-.837-5.252-1.127l-.755-.287 1.61-1.72c2.795-2.989 4.973-6.587 6.243-10.311 1.25-3.67 1.285-4.124 1.443-18.414l.15-13.65 8.732 2.303c4.802 1.267 9.202 2.503 9.778 2.746 3.043 1.286 5.145 3.982 5.675 7.278.252 1.561.198 1.959-.794 5.812-3.222 12.52-4.145 15.114-6.528 18.34-2.535 3.431-6.973 6.67-10.954 7.992-2.775.923-6.782 1.367-9.348 1.037zm-66.675-53.23C5.927 39.497 1.958 35.51.425 30.338c-.569-1.92-.566-5.765.005-7.673.971-3.247 3.125-6.582 5.32-8.238 6.203-4.68 14.23-4.078 19.8 1.487 5.935 5.928 5.875 15.002-.138 21.05-3.169 3.187-6.284 4.503-10.573 4.467-1.294-.01-2.938-.214-3.847-.476zm75.7.107c-2.625-.752-4.724-2.013-6.806-4.088-3.145-3.134-4.483-6.26-4.489-10.488-.01-8.284 6.793-15.215 14.917-15.196 6.755.017 12.86 4.848 14.609 11.562 1.834 7.044-2.363 15.04-9.297 17.708-1.485.572-2.355.717-4.73.787-1.947.058-3.338-.036-4.204-.285zm-36.307-7.273c-5.885-.916-10.698-4.535-13.213-9.935-1.164-2.498-1.54-4.612-1.379-7.743.156-3.033.812-5.193 2.388-7.867 1.46-2.477 3.06-4.135 5.402-5.594 6.116-3.812 13.21-3.5 19.303.847 5.255 3.75 7.835 10.983 6.2 17.376-1.309 5.118-4.472 9.04-9.208 11.419-2.6 1.306-6.634 1.942-9.493 1.497z"/></symbol>
	<?php }

	if ( $output_all || in_array( 'iq', $icons_used ) ) {
	?>
		<symbol id="icon-iq" fill="none" viewBox="0 0 42 42" id="svg-IQ" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#aa)"><path fill="var(--icon-fill)" d="M-.031 4.904v32.668h42V4.904Zm24.705 6.225c5.99 0 8.986 3.08 8.986 9.24 0 5.067-1.885 8.06-5.656 8.975l2.95 3.431-2.66 2.022-4.13-5.238c-5.474 0-8.346-3.063-8.617-9.19 0-6.16 3.043-9.24 9.127-9.24zm-15.686.127h3.37v18.176h-3.37Zm15.686 2.797c-3.754 0-5.631 2.08-5.631 6.24 0 4.228 1.877 6.344 5.63 6.344 3.662 0 5.491-2.116 5.491-6.344 0-4.16-1.83-6.24-5.49-6.24z"/></g><defs><clipPath id="aa"><path d="M0 14.238a9.333 9.333 0 0 1 9.333-9.334h23.334A9.333 9.333 0 0 1 42 14.238v14a9.333 9.333 0 0 1-9.333 9.333H9.333A9.333 9.333 0 0 1 0 28.238Z" fill="var(--icon-fill)"/></clipPath></defs></symbol>
	<?php }

	if ( $output_all || in_array( 'rocket', $icons_used ) ) {
	?>
		<symbol id="icon-rocket" viewBox="0 0 79.248 79.328" xml:space="preserve" id="svg-Rocket" xmlns="http://www.w3.org/2000/svg"><path fill="var(--icon-fill)" d="M47.518 79.068c-.824-.296-5.785-5.11-23.508-22.811C11.655 43.917 1.2 33.356.779 32.788c-1.277-1.718-.988-3.45.902-5.408 2.533-2.624 6.785-5.03 10.114-5.722 2.68-.557 8.93-1.025 11.287-.845l2.035.156 6.299-6.501c3.615-3.731 7.232-7.204 8.49-8.15 3.88-2.917 9.139-5.166 14.041-6.002 3.886-.662 13.983-.233 19.13.813 4.103.834 4.623 1.453 5.563 6.63.507 2.79.602 4.238.608 9.26.007 6.022-.143 7.509-1.21 12.039-.828 3.518-2.036 6.298-4.023 9.26-.586.873-4.306 4.862-8.267 8.864l-7.201 7.276-.031 3.307c-.088 9.492-1.514 14.144-5.896 19.241-1.85 2.152-3.263 2.723-5.102 2.062zm1.407-11.086c1.363-3.64 2.06-8.474 1.725-11.936-.114-1.165-.203-2.356-.2-2.647.024-2.054-.171-1.81 8.122-10.168 4.413-4.449 8.404-8.584 8.867-9.19 3.153-4.12 4.643-12.592 3.738-21.255-.472-4.527-.215-4.126-2.86-4.457-4.081-.511-10.055-.661-12.346-.311-4.153.634-8.377 2.317-11.657 4.644-.882.625-4.785 4.408-8.673 8.406-3.888 3.999-7.42 7.416-7.85 7.594-.592.245-1.507.26-3.772.06-4.605-.406-8.797.12-12.457 1.562-.75.296-1.408.582-1.463.637-.123.122 37.866 38.48 38.114 38.483.099 0 .42-.639.712-1.422zm3.387-35.452c-2.469-.693-4.88-3.006-5.488-5.264-.456-1.692-.205-4.34.554-5.847 1.972-3.915 6.497-5.417 10.47-3.474 2.497 1.222 4.102 3.613 4.29 6.392.364 5.403-4.705 9.631-9.826 8.193zM3.986 73.838c.015-4.175 1.666-10.47 3.78-14.416.654-1.222 1.921-2.602 3.006-3.272l.934-.577 5.918 5.913c6.767 6.762 6.438 5.9 3.117 8.196-4.692 3.243-11.178 5.544-15.636 5.545H3.98z"/></symbol>
	<?php }
	
	?>
	</svg>
	<?php
}
