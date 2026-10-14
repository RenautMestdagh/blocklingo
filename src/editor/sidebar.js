/**
 * Sidebar "Translate": the language being edited, how far the translation is, and the
 * post's title, address and excerpt in that language.
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { __, _n, sprintf } from '@wordpress/i18n';

import { translatableAttributesOf } from './block-edit';
import { languageName, settings } from './settings';
import { STORE_NAME } from './store';
import { getBlockStatus } from './translations';

/**
 * Translation progress of the blocks in the editor. Plain numbers, so the sidebar only
 * re-renders when they change.
 *
 * @param {string} language Language code.
 * @return {{total: number, todo: number, outdated: number, next: ?string}} Progress.
 */
function useProgress( language ) {
	return useSelect(
		( select ) => {
			const {
				getClientIdsWithDescendants,
				getBlockName,
				getBlockAttributes,
			} = select( blockEditorStore );
			const progress = { total: 0, todo: 0, outdated: 0, next: null };
			getClientIdsWithDescendants().forEach( ( clientId ) => {
				const names = translatableAttributesOf(
					getBlockName( clientId )
				);
				const status = names.length
					? getBlockStatus(
							getBlockAttributes( clientId ),
							language,
							names
						)
					: null;
				if ( ! status ) {
					return;
				}
				progress.total++;
				if ( status !== 'translated' ) {
					progress.todo++;
					progress.next = progress.next ?? clientId;
				}
				if ( status === 'outdated' ) {
					progress.outdated++;
				}
			} );
			return progress;
		},
		[ language ]
	);
}

function LanguageSwitch() {
	const language = useSelect(
		( select ) => select( STORE_NAME ).getLanguage(),
		[]
	);
	const { setLanguage } = useDispatch( STORE_NAME );
	return (
		<div
			className="blocklingo-language-switch"
			role="group"
			aria-label={ __( 'Edit in', 'blocklingo' ) }
		>
			{ settings.languages.map( ( { code, name } ) => (
				<Button
					key={ code }
					__next40pxDefaultSize
					variant={ code === language ? 'primary' : 'secondary' }
					isPressed={ code === language }
					label={ name }
					showTooltip
					onClick={ () => setLanguage( code ) }
				>
					{ code.toUpperCase() }
				</Button>
			) ) }
		</div>
	);
}

function Progress( { language } ) {
	const { total, todo, outdated, next } = useProgress( language );
	const { selectBlock } = useDispatch( blockEditorStore );

	if ( ! total ) {
		return <p>{ __( 'Nothing to translate yet.', 'blocklingo' ) }</p>;
	}
	return (
		<>
			<p>
				{ sprintf(
					/* translators: 1: translated blocks, 2: all blocks with text. */
					__( '%1$d of %2$d blocks translated.', 'blocklingo' ),
					total - todo,
					total
				) }
				{ outdated > 0 &&
					' ' +
						sprintf(
							/* translators: %d: number of blocks. */
							_n(
								'%d is outdated: its original changed.',
								'%d are outdated: their originals changed.',
								outdated,
								'blocklingo'
							),
							outdated
						) }
			</p>
			{ next && (
				<Button
					variant="secondary"
					onClick={ () => selectBlock( next ) }
				>
					{ __( 'Go to the next block to translate', 'blocklingo' ) }
				</Button>
			) }
		</>
	);
}

function PostFields( { language } ) {
	const { postType, postId } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postType: editor.getCurrentPostType(),
			postId: editor.getCurrentPostId(),
		};
	}, [] );
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);
	if ( ! settings.postTypes.includes( postType ) || ! meta ) {
		return null;
	}

	const translations = meta.blocklingo ?? {};
	const current = translations[ language ] ?? {};
	const update = ( field, value ) =>
		setMeta( {
			...meta,
			blocklingo: {
				...translations,
				[ language ]: { ...current, [ field ]: value },
			},
		} );

	return (
		<PanelBody
			title={ sprintf(
				/* translators: %s: language name. */
				__( 'Title and address in %s', 'blocklingo' ),
				languageName( language )
			) }
		>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Title', 'blocklingo' ) }
				value={ current.title ?? '' }
				onChange={ ( value ) => update( 'title', value ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Slug', 'blocklingo' ) }
				help={ __(
					'The last part of the address in this language. Empty: the original slug.',
					'blocklingo'
				) }
				value={ current.slug ?? '' }
				onChange={ ( value ) => update( 'slug', value ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Excerpt', 'blocklingo' ) }
				help={ __(
					'Used as the description for search engines and link previews.',
					'blocklingo'
				) }
				value={ current.excerpt ?? '' }
				onChange={ ( value ) => update( 'excerpt', value ) }
			/>
		</PanelBody>
	);
}

export function Sidebar() {
	const language = useSelect(
		( select ) => select( STORE_NAME ).getLanguage(),
		[]
	);
	const translating = language !== settings.defaultLanguage;

	return (
		<>
			<PanelBody>
				<LanguageSwitch />
				{ translating ? (
					<>
						<Notice status="info" isDismissible={ false }>
							{ sprintf(
								/* translators: %s: language name. */
								__(
									'Text you type now is the %s translation. Adding, moving or styling blocks changes every language.',
									'blocklingo'
								),
								languageName( language )
							) }
						</Notice>
						<Progress language={ language } />
					</>
				) : (
					<p>
						{ sprintf(
							/* translators: %s: language name. */
							__(
								'You are editing the original, in %s. Choose another language to translate.',
								'blocklingo'
							),
							languageName( language )
						) }
					</p>
				) }
			</PanelBody>
			{ translating && <PostFields language={ language } /> }
		</>
	);
}
