import { useBlockProps } from '@wordpress/block-editor';

/**
 * Deprecations for momentive/impact-stat.
 *
 * v1 (current save.js) adds a `data-animate` attribute to the block
 * wrapper for the "Count up" toggle. Every impact-stat block published
 * before that change has HTML frozen in post_content without that
 * attribute, so the current save() no longer byte-matches it — without
 * this entry, WordPress treats every existing instance as invalid content
 * and shows "Attempt Recovery" instead of the normal block UI (which is
 * also why the new toggle appeared to be missing: the recovery screen
 * doesn't render InspectorControls at all).
 *
 * This save() is an exact copy of save.js as it existed before the
 * `data-animate` attribute was added. Keep it that way — it's a historical
 * snapshot, not a place to fix bugs or add features.
 */
const v1 = {
	attributes: {
		statPrefix: {
			type: 'string',
			default: '',
		},
		statNumber: {
			type: 'number',
			default: 0,
		},
		statSuffix: {
			type: 'string',
			default: '',
		},
		statLabel: {
			type: 'string',
			default: '',
		},
		accentColor: {
			type: 'string',
			default: '#E8611A',
		},
		animationDuration: {
			type: 'number',
			default: 1800,
		},
	},

	save( { attributes } ) {
		const {
			statPrefix,
			statNumber,
			statSuffix,
			statLabel,
			accentColor,
			animationDuration,
		} = attributes;

		const isInteger = Number.isInteger( statNumber );
		const formattedFinal = isInteger
			? statNumber.toLocaleString( 'en-US' )
			: statNumber.toString();

		const blockProps = useBlockProps.save( {
			className: 'impact-stat',
			style: { '--accent-color': accentColor },
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
						{ statPrefix && (
							<span className="impact-stat__prefix" aria-hidden="true">{ statPrefix }</span>
						) }
						<span
							className="impact-stat__number"
							aria-hidden="true"
							data-final={ formattedFinal }
						>
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
	},
};

export default [ v1 ];
