/**
 * Language Switcher block: rendered on the server (it depends on the page being viewed),
 * previewed in the editor with the same output.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

function Edit( { attributes, setAttributes } ) {
	const { label, showCurrent } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'blocklingo' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Show', 'blocklingo' ) }
						value={ label }
						options={ [
							{
								label: __( 'Language name', 'blocklingo' ),
								value: 'name',
							},
							{
								label: __( 'Language code', 'blocklingo' ),
								value: 'code',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Include the current language',
							'blocklingo'
						) }
						checked={ showCurrent }
						onChange={ ( value ) =>
							setAttributes( { showCurrent: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					skipBlockSupportAttributes
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
