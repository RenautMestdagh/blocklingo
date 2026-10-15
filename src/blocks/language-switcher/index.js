/**
 * Language Switcher block: rendered on the server (it depends on the page being viewed),
 * previewed in the editor with the same output.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

function Edit( { attributes, setAttributes } ) {
	const { label, showCurrent, display, orientation } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'blocklingo' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Display', 'blocklingo' ) }
						help={
							display === 'dropdown'
								? __(
										'Shows the current language; the others open below it. Fits a header.',
										'blocklingo'
									)
								: undefined
						}
						value={ display }
						options={ [
							{
								label: __( 'List', 'blocklingo' ),
								value: 'list',
							},
							{
								label: __( 'Dropdown', 'blocklingo' ),
								value: 'dropdown',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					/>
					{ display === 'list' && (
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Orientation', 'blocklingo' ) }
							value={ orientation }
							options={ [
								{
									label: __( 'Horizontal', 'blocklingo' ),
									value: 'horizontal',
								},
								{
									label: __( 'Vertical', 'blocklingo' ),
									value: 'vertical',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { orientation: value } )
							}
						/>
					) }
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
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						skipBlockSupportAttributes
					/>
				</Disabled>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
