import { useBlockProps } from '@wordpress/block-editor';

export default function Save( { attributes } ) {
	const {
		statPrefix,
		statNumber,
		statSuffix,
		statLabel,
		accentColor,
		animationDuration,
	} = attributes;

	// Integer check: format with thousands separator; decimals stay as-is.
	const isInteger = Number.isInteger( statNumber );
	const formattedFinal = isInteger
		? statNumber.toLocaleString( 'en-US' )
		: statNumber.toString();

	const blockProps = useBlockProps.save( {
		className: 'impact-stat',
		style: { '--accent-color': accentColor },
		// Data attributes consumed by view.js
		'data-stat-number': statNumber,
		'data-stat-prefix': statPrefix,
		'data-stat-suffix': statSuffix,
		'data-stat-integer': isInteger ? 'true' : 'false',
		'data-animation-duration': animationDuration,
	} );

	return (
		<div { ...blockProps }>
			<div className="impact-stat__border" />
			<div className="impact-stat__content">
				<p className="impact-stat__value" aria-label={ `${ statPrefix }${ statNumber }${ statSuffix }` }>
					{ /* Prefix is rendered as static text outside the animated span */ }
					{ statPrefix && (
						<span className="impact-stat__prefix" aria-hidden="true">{ statPrefix }</span>
					) }
					<span
						className="impact-stat__number"
						aria-hidden="true"
						data-final={ formattedFinal }
					>
						{ /* Starts at 0; view.js animates to the final value on intersection */ }
						0
					</span>
					{ statSuffix && (
						<span className="impact-stat__suffix" aria-hidden="true">{ statSuffix }</span>
					) }
				</p>
				{ statLabel && (
					<p className="impact-stat__label">{ statLabel }</p>
				) }
			</div>
		</div>
	);
}
