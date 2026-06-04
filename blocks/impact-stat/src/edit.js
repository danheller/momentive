import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	RangeControl,
	ColorPalette,
} from '@wordpress/components';

/**
 * Predefined accent colors drawn from the Momentive brand palette.
 * Editors can also enter a custom hex via the color picker.
 */
const ACCENT_COLORS = [
	{ name: __( 'Orange', 'momentive' ),  color: '#E8611A' },
	{ name: __( 'Purple', 'momentive' ),  color: '#7B61FF' },
	{ name: __( 'Teal', 'momentive' ),    color: '#00C4B4' },
	{ name: __( 'Blue', 'momentive' ),    color: '#3B82F6' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		statPrefix,
		statNumber,
		statSuffix,
		statLabel,
		accentColor,
		animationDuration,
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
					<RangeControl
						label={ __( 'Duration (ms)', 'momentive' ) }
						help={ __( 'How long the count-up animation takes', 'momentive' ) }
						value={ animationDuration }
						onChange={ ( val ) => setAttributes( { animationDuration: val } ) }
						min={ 500 }
						max={ 4000 }
						step={ 100 }
					/>
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
