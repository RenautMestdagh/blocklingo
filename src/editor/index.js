/**
 * Blocklingo in the block editor and the Site Editor.
 */
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import { withTranslation, withTranslationStatus } from './block-edit';
import { settings } from './settings';
import { Sidebar } from './sidebar';
import './store';
import './editor.scss';

/**
 * The attribute exists on every block type, so the editor keeps it when saving.
 *
 * @param {Object} blockSettings Block type settings.
 * @return {Object} Settings with the attribute.
 */
function addAttribute( blockSettings ) {
	return {
		...blockSettings,
		attributes: {
			...blockSettings.attributes,
			blocklingo: { type: 'object' },
		},
	};
}

addFilter( 'blocks.registerBlockType', 'blocklingo/attribute', addAttribute );

if ( settings.languages.length > 1 ) {
	addFilter( 'editor.BlockEdit', 'blocklingo/translation', withTranslation );
	addFilter(
		'editor.BlockListBlock',
		'blocklingo/translation-status',
		withTranslationStatus
	);

	registerPlugin( 'blocklingo', {
		icon: 'translation',
		render: () => (
			<>
				<PluginSidebarMoreMenuItem target="blocklingo">
					{ __( 'Translate', 'blocklingo' ) }
				</PluginSidebarMoreMenuItem>
				<PluginSidebar
					name="blocklingo"
					title={ __( 'Translate', 'blocklingo' ) }
				>
					<Sidebar />
				</PluginSidebar>
			</>
		),
	} );
}
