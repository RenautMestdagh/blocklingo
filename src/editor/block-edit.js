/**
 * Translating in place: while another language is selected, a block is shown with its
 * translations, and typing writes into the translation. The block's own attributes keep
 * the default language, so saving works exactly as without the plugin.
 */
import { BlockControls } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { RichTextData } from '@wordpress/rich-text';

import { STORE_NAME } from './store';
import {
	acceptOutdated,
	getBlockStatus,
	getTextPaths,
	getTranslatedAttributes,
	getValue,
	setValue,
	splitUpdates,
} from './translations';

/**
 * Where a block holds text to translate (see getTextPaths).
 *
 * @param {string} name       Block name.
 * @param {Object} attributes Block attributes.
 * @return {string[]} Paths.
 */
export function textPathsOf( name, attributes ) {
	return getTextPaths( getBlockType( name ), attributes );
}

/**
 * The shown value in the type the block expects: rich text stays rich text.
 *
 * @param {unknown} original Original value.
 * @param {string}  shown    Value to show.
 * @return {unknown} Value for the block.
 */
function asOriginalType( original, shown ) {
	if (
		original instanceof RichTextData &&
		typeof shown === 'string' &&
		typeof RichTextData.fromHTMLString === 'function'
	) {
		return RichTextData.fromHTMLString( shown );
	}
	return shown;
}

/**
 * The language being edited, when it isn't the default.
 */
function useTranslationLanguage() {
	return useSelect( ( select ) => {
		const editor = select( STORE_NAME );
		return editor.isTranslating() ? editor.getLanguage() : null;
	}, [] );
}

export const withTranslation = createHigherOrderComponent(
	( BlockEdit ) =>
		function BlocklingoBlockEdit( props ) {
			const language = useTranslationLanguage();
			const { name, attributes, setAttributes } = props;
			const names = useMemo(
				() => textPathsOf( name, attributes ),
				[ name, attributes ]
			);
			const active = !! language && names.length > 0;

			const shown = useMemo( () => {
				if ( ! active ) {
					return attributes;
				}
				const translated = getTranslatedAttributes(
					attributes,
					language,
					names
				);
				return names.reduce(
					( typed, path ) =>
						setValue(
							typed,
							path,
							asOriginalType(
								getValue( attributes, path ),
								getValue( translated, path )
							)
						),
					translated
				);
			}, [ active, attributes, language, names ] );

			const setShown = useCallback(
				( updates ) =>
					setAttributes(
						active
							? splitUpdates(
									attributes,
									// Blocks like the table pass a function of their attributes.
									typeof updates === 'function'
										? updates( shown )
										: updates,
									language,
									names
								)
							: updates
					),
				[ active, attributes, language, names, setAttributes, shown ]
			);

			if ( ! active ) {
				return <BlockEdit { ...props } />;
			}

			const outdated =
				getBlockStatus( attributes, language, names ) === 'outdated';

			return (
				<>
					{ outdated && props.isSelected && (
						<BlockControls group="other">
							<ToolbarGroup>
								<ToolbarButton
									icon="yes"
									label={ __(
										'Translation still fits: mark as up to date',
										'blocklingo'
									) }
									onClick={ () =>
										setAttributes( {
											blocklingo: acceptOutdated(
												attributes,
												language,
												names
											),
										} )
									}
								/>
							</ToolbarGroup>
						</BlockControls>
					) }
					<BlockEdit
						{ ...props }
						attributes={ shown }
						setAttributes={ setShown }
					/>
				</>
			);
		},
	'withBlocklingoTranslation'
);

/**
 * Marks blocks in the canvas while translating: missing, outdated or translated.
 */
export const withTranslationStatus = createHigherOrderComponent(
	( BlockListBlock ) =>
		function BlocklingoBlockListBlock( props ) {
			const language = useTranslationLanguage();
			const names = textPathsOf( props.name, props.attributes );
			if ( ! language || ! names.length ) {
				return <BlockListBlock { ...props } />;
			}
			const status = getBlockStatus( props.attributes, language, names );
			if ( ! status ) {
				return <BlockListBlock { ...props } />;
			}
			const className = [ props.className, `blocklingo-is-${ status }` ]
				.filter( Boolean )
				.join( ' ' );
			return <BlockListBlock { ...props } className={ className } />;
		},
	'withBlocklingoTranslationStatus'
);
