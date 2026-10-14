/**
 * Sidebar "Translate": the language being edited, how far the translation is, and the
 * post's title, address and excerpt in that language.
 */
import apiFetch from '@wordpress/api-fetch';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import { __, _n, sprintf } from '@wordpress/i18n';

import { textPathsOf } from './block-edit';
import { languageName, settings } from './settings';
import { STORE_NAME } from './store';
import { collectMissing, getBlockStatus, setTranslation } from './translations';

/** Texts per request to the site (it sends them on to DeepL). */
const BATCH = 50;

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
				const attributes = getBlockAttributes( clientId );
				const names = textPathsOf(
					getBlockName( clientId ),
					attributes
				);
				const status = names.length
					? getBlockStatus( attributes, language, names )
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

/**
 * "Translate empty blocks with DeepL": sends the untranslated texts through the site to
 * DeepL and puts the results into the blocks as their translations.
 *
 * @param {Object} props          Props.
 * @param {string} props.language Language code.
 */
function MachineTranslate( { language } ) {
	const registry = useRegistry();
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ done, setDone ] = useState( null );

	const run = async () => {
		const editor = registry.select( blockEditorStore );
		const { updateBlockAttributes } = registry.dispatch( blockEditorStore );
		const blocks = editor
			.getClientIdsWithDescendants()
			.map( ( clientId ) => {
				const attributes = editor.getBlockAttributes( clientId );
				return {
					clientId,
					attributes,
					names: textPathsOf(
						editor.getBlockName( clientId ),
						attributes
					),
				};
			} );
		const jobs = collectMissing( blocks, language );

		setBusy( true );
		setError( null );
		setDone( null );
		try {
			for ( let start = 0; start < jobs.length; start += BATCH ) {
				const batch = jobs.slice( start, start + BATCH );
				const { translations } = await apiFetch( {
					path: '/blocklingo/v1/translate',
					method: 'POST',
					data: {
						texts: batch.map( ( job ) => job.text ),
						target: language,
					},
				} );
				batch.forEach( ( job, index ) => {
					// Read again: an earlier job may have changed the same block.
					const attributes = editor.getBlockAttributes(
						job.clientId
					);
					updateBlockAttributes( job.clientId, {
						blocklingo: setTranslation(
							attributes,
							language,
							job.name,
							translations[ index ]
						),
					} );
				} );
			}
			setDone( jobs.length );
		} catch ( requestError ) {
			setError(
				requestError?.message ??
					__( 'Machine translation failed.', 'blocklingo' )
			);
		}
		setBusy( false );
	};

	return (
		<>
			<Button
				variant="secondary"
				isBusy={ busy }
				disabled={ busy }
				onClick={ run }
			>
				{ __( 'Translate empty blocks with DeepL', 'blocklingo' ) }
			</Button>
			{ done !== null && (
				<p>
					{ sprintf(
						/* translators: %d: number of texts. */
						_n(
							'%d text translated. Check it before you save.',
							'%d texts translated. Check them before you save.',
							done,
							'blocklingo'
						),
						done
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
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
						{ settings.machineTranslation && (
							<MachineTranslate language={ language } />
						) }
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
