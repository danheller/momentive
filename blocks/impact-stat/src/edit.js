import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	RangeControl,
	ToggleControl,
	ColorPalette,
} from '@wordpress/components';

/**
 * Predefined accent colors drawn from the Momentive brand palette.
 * Editors can also enter a custom hex via the color picker.
 *
 * "Purple" and "Rose" reference the theme.json palette (`purple`/`rose`,
 * added alongside this change) via CSS custom properties rather than
 * hardcoded hex, so a future palette adjustment updates every impact-stat
 * instance automatically instead of requiring a hex hunt across posts.
 *
 * "Sky" (formerly a hardcoded #61C6D2) was close enough to the existing
 * "Brand Blue" theme.json color (rgb(110, 193, 228)) that having both as
 * separate swatches just invited picking the wrong near-duplicate — it now
 * points at that same preset instead of adding a second, barely-distinct
 * blue to the design system.
 *
 * "Orange", "Teal", and "Blue" are left as their original hardcoded hex —
 * they aren't near-duplicates of anything already in theme.json and haven't
 * come up as needing consolidation, so there's no theme.json entry to point
 * them at yet.
 */
const ACCENT_COLORS = [
	{ name: __( 'Orange', 'momentive' ),      color: '#E8611A' },
	{ name: __( 'Purple', 'momentive' ),      color: 'var(--wp--preset--color--purple)' },
	{ name: __( 'Teal', 'momentive' ),        color: '#00C4B4' },
	{ name: __( 'Blue', 'momentive' ),        color: '#3B82F6' },
	{ name: __( 'Rose', 'momentive' ),        color: 'var(--wp--preset--color--rose)' },
	{ name: __( 'Brand Blue', 'momentive' ),  color: 'var(--wp--preset--color--brand)' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		statPrefix,
		statNumber,
		statSuffix,
		statLabel,
		accentColor,
		animationDuration,
		animate,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'impact-stat',
		style: { '--accent-color': accentColor },
	} );

	// Format number with thousands separator for the editor preview.
	const formattedNumber = Number.isInteger( statNumber )
		? statNumber.toLocaleString( 'en-US' )
		: statNumber;

	return (
		<>
			{ /* ── Sidebar controls ── */ }
			<InspectorControls>
				<PanelBody title={ __( 'Stat Value', 'momentive' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Prefix', 'momentive' ) }
						help={ __( 'Text before the number, e.g. "$" or "1 in "', 'momentive' ) }
						value={ statPrefix }
						onChange={ ( val ) => setAttributes( { statPrefix: val } ) }
					/>
					<TextControl
						label={ __( 'Number', 'momentive' ) }
						help={ __( 'Numeric value that animates. Use decimals for e.g. 35.5', 'momentive' ) }
						type="number"
						value={ statNumber }
						onChange={ ( val ) => setAttributes( { statNumber: parseFloat( val ) || 0 } ) }
					/>
					<TextControl
						label={ __( 'Suffix', 'momentive' ) }
						help={ __( 'Text after the number, e.g. "M+", "K", "s"', 'momentive' ) }
						value={ statSuffix }
						onChange={ ( val ) => setAttributes( { statSuffix: val } ) }
					/>
					<TextControl
						label={ __( 'Label', 'momentive' ) }
						help={ __( 'Descriptor line below the stat', 'momentive' ) }
						value={ statLabel }
						onChange={ ( val ) => setAttributes( { statLabel: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Appearance', 'momentive' ) } initialOpen={ true }>
					<p className="components-base-control__label">
						{ __( 'Accent Color', 'momentive' ) }
					</p>
					<ColorPalette
						colors={ ACCENT_COLORS }
						value={ accentColor }
						onChange={ ( val ) => setAttributes( { accentColor: val || '#E8611A' } ) }
						clearable={ false }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Animation', 'momentive' ) } initialOpen={ false }>
					<ToggleControl
						label={ __( 'Count up', 'momentive' ) }
						help={ __( 'Turn off for ordinals like "2nd" or "#1" — counting from 0 doesn\'t read well there.', 'momentive' ) }
						checked={ animate }
						onChange={ ( val ) => setAttributes( { animate: val } ) }
					/>
					{ animate && (
						<RangeControl
							label={ __( 'Duration (ms)', 'momentive' ) }
							help={ __( 'How long the count-up animation takes', 'momentive' ) }
							value={ animationDuration }
							onChange={ ( val ) => setAttributes( { animationDuration: val } ) }
							min={ 500 }
							max={ 4000 }
							step={ 100 }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			{ /* ── Editor preview ── */ }
			<div { ...blockProps }>
				<div className="impact-stat__border" />
				<div className="impact-stat__content">
					<p className="impact-stat__value">
						{ statPrefix }
						<span className="impact-stat__number">
							{ formattedNumber }
						</span>
						{ statSuffix }
					</p>
					{ statLabel && (
						<p className="impact-stat__label">{ statLabel }</p>
					) }
				</div>
			</div>
		</>
	);
}
