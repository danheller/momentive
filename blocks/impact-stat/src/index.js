import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import Save from './save';
import metadata from '../block.json';

registerBlockType( metadata.name, {
	title: __( 'Impact Stat', 'momentive' ),
	edit: Edit,
	save: Save,
} );
