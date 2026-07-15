import { useState } from '@wordpress/element';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Top-level blocks permitted as direct children of a megamenu panel.
 * Columns handles the standard two-column layout; Group covers any
 * single-column or wrapper variation. Lift this constraint in block.json
 * and here if the designs diverge.
 */
const ALLOWED_BLOCKS = [ 'core/columns', 'core/group' ];

/**
 * Orientation of the editor placeholder chevron — points right when
 * collapsed, down when expanded — purely decorative.
 */
function Chevron( { expanded } ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			style={ {
				transform:  expanded ? 'rotate(90deg)' : 'rotate(0deg)',
				transition: 'transform 120ms ease',
				flexShrink: 0,
			} }
		>
			<path
				d="M6 3L11 8L6 13"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { panelLabel, menuSlug } = attributes;
	const [ expanded, setExpanded ] = useState( false );

	const blockProps = useBlockProps( {
		className: 'megamenu-panel-editor',
	} );

	const label = panelLabel || __( '(unlabeled panel)', 'momentive' );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Panel Settings', 'momentive' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Panel label', 'momentive' ) }
						help={ __( 'Identifies this panel in the editor. Not output on the frontend.', 'momentive' ) }
						value={ panelLabel }
						onChange={ ( val ) => setAttributes( { panelLabel: val } ) }
					/>
					<TextControl
						label={ __( 'Menu slug', 'momentive' ) }
						help={ __( 'Must match the data-menu attribute on the nav trigger, e.g. "products" or "solutions".', 'momentive' ) }
						value={ menuSlug }
						onChange={ ( val ) => setAttributes( { menuSlug: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ /* ── Collapsed header bar ── */ }
				<div className="megamenu-panel-editor__header">
					<Chevron expanded={ expanded } />
					<span className="megamenu-panel-editor__label">
						{ __( 'Mega Menu Panel:', 'momentive' ) }{ ' ' }
						<strong>{ label }</strong>
						{ menuSlug && (
							<code className="megamenu-panel-editor__slug">
								{ menuSlug }
							</code>
						) }
					</span>
					<Button
						variant="tertiary"
						size="small"
						onClick={ () => setExpanded( ( v ) => ! v ) }
						aria-expanded={ expanded }
					>
						{ expanded
							? __( 'Collapse', 'momentive' )
							: __( 'Edit panel', 'momentive' ) }
					</Button>
				</div>

				{ /* ── Inner blocks — only rendered when expanded ── */ }
				{ expanded && (
					<div className="megamenu-panel-editor__canvas">
						<InnerBlocks
							allowedBlocks={ ALLOWED_BLOCKS }
							template={ [ [ 'core/columns' ] ] }
							templateLock={ false }
						/>
					</div>
				) }
			</div>
		</>
	);
}
