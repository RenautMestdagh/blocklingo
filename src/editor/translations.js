/**
 * Translations stored on blocks: pure functions, no WordPress state.
 *
 * A block's `blocklingo` attribute holds, per language and per translated attribute, the
 * original as it was when translated and the translation:
 *
 *     { fr: { content: [ 'Hallo', 'Bonjour' ] } }
 */

/** Attributes that hold addresses or identifiers, never visible text. */
const NOT_TEXT = [
	'url',
	'href',
	'src',
	'id',
	'ref',
	'linkTarget',
	'rel',
	'linkClass',
	'anchor',
];

/**
 * Names of the attributes of a block type that hold text to translate: content attributes
 * (role "content") that are strings or rich text.
 *
 * @param {Object} blockType Block type as registered in the editor.
 * @return {string[]} Attribute names.
 */
export function getTranslatableAttributes( blockType ) {
	const attributes = blockType?.attributes ?? {};
	return Object.keys( attributes ).filter( ( name ) => {
		const attribute = attributes[ name ];
		const role = attribute.role ?? attribute.__experimentalRole;
		const isText =
			attribute.type === 'string' ||
			attribute.type === 'rich-text' ||
			[ 'html', 'rich-text', 'text' ].includes( attribute.source );
		return role === 'content' && isText && ! NOT_TEXT.includes( name );
	} );
}

/**
 * An attribute value as a string: rich text values (RichTextData) become their HTML.
 *
 * @param {unknown} value Attribute value.
 * @return {string} String value.
 */
export function toText( value ) {
	if ( value === undefined || value === null ) {
		return '';
	}
	if ( typeof value?.toHTMLString === 'function' ) {
		return value.toHTMLString();
	}
	return String( value );
}

/**
 * The stored pair [ original, translation ] of one attribute, or null.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} language   Language code.
 * @param {string} name       Attribute name.
 * @return {?string[]} Pair.
 */
export function getPair( attributes, language, name ) {
	const pair = attributes?.blocklingo?.[ language ]?.[ name ];
	return Array.isArray( pair ) &&
		typeof pair[ 0 ] === 'string' &&
		typeof pair[ 1 ] === 'string'
		? pair
		: null;
}

/**
 * Status of one attribute in a language: 'missing', 'outdated' (the original changed after
 * it was translated) or 'translated'. Empty originals need no translation.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} language   Language code.
 * @param {string} name       Attribute name.
 * @return {string} Status.
 */
export function getAttributeStatus( attributes, language, name ) {
	const original = toText( attributes?.[ name ] );
	if ( original === '' ) {
		return 'translated';
	}
	const pair = getPair( attributes, language, name );
	if ( ! pair ) {
		return 'missing';
	}
	return pair[ 0 ] === original ? 'translated' : 'outdated';
}

/**
 * Status of a block in a language: the worst of its attributes, or null when it has
 * nothing to translate.
 *
 * @param {Object}   attributes Block attributes.
 * @param {string}   language   Language code.
 * @param {string[]} names      Translatable attribute names.
 * @return {?string} 'missing', 'outdated', 'translated' or null.
 */
export function getBlockStatus( attributes, language, names ) {
	const statuses = names
		.filter( ( name ) => toText( attributes?.[ name ] ) !== '' )
		.map( ( name ) => getAttributeStatus( attributes, language, name ) );
	if ( ! statuses.length ) {
		return null;
	}
	if ( statuses.includes( 'missing' ) ) {
		return 'missing';
	}
	return statuses.includes( 'outdated' ) ? 'outdated' : 'translated';
}

/**
 * The attributes as the editor shows them in a language: each translatable attribute is
 * its translation, or the original when there is none yet.
 *
 * @param {Object}   attributes Block attributes.
 * @param {string}   language   Language code.
 * @param {string[]} names      Translatable attribute names.
 * @return {Object} Attributes to show.
 */
export function getTranslatedAttributes( attributes, language, names ) {
	const shown = { ...attributes };
	names.forEach( ( name ) => {
		const pair = getPair( attributes, language, name );
		if ( pair ) {
			shown[ name ] = pair[ 1 ];
		}
	} );
	return shown;
}

/**
 * The blocklingo attribute after typing a translation. An empty translation removes it;
 * the stored original is always the current one (typing makes it up to date).
 *
 * @param {Object}  attributes  Block attributes.
 * @param {string}  language    Language code.
 * @param {string}  name        Attribute name.
 * @param {unknown} translation New translated value.
 * @return {Object} New blocklingo attribute value.
 */
export function setTranslation( attributes, language, name, translation ) {
	const all = { ...( attributes?.blocklingo ?? {} ) };
	const forLanguage = { ...( all[ language ] ?? {} ) };
	const text = toText( translation );

	if ( text === '' ) {
		delete forLanguage[ name ];
	} else {
		forLanguage[ name ] = [ toText( attributes?.[ name ] ), text ];
	}

	if ( Object.keys( forLanguage ).length ) {
		all[ language ] = forLanguage;
	} else {
		delete all[ language ];
	}
	return all;
}

/**
 * The blocklingo attribute with every outdated translation of a block in a language
 * accepted as up to date: the original changed but the translation still fits.
 *
 * @param {Object}   attributes Block attributes.
 * @param {string}   language   Language code.
 * @param {string[]} names      Translatable attribute names.
 * @return {Object} New blocklingo attribute value.
 */
export function acceptOutdated( attributes, language, names ) {
	let all = attributes?.blocklingo ?? {};
	names.forEach( ( name ) => {
		if ( getAttributeStatus( attributes, language, name ) === 'outdated' ) {
			all = setTranslation(
				{ ...attributes, blocklingo: all },
				language,
				name,
				getPair( attributes, language, name )[ 1 ]
			);
		}
	} );
	return all;
}

/**
 * Splits attribute updates made while editing a translation: translatable attributes go
 * into the translation, everything else (layout, colours, links) is shared by all
 * languages.
 *
 * @param {Object}   attributes Current block attributes.
 * @param {Object}   updates    Attributes being set.
 * @param {string}   language   Language code.
 * @param {string[]} names      Translatable attribute names.
 * @return {Object} Attributes to really set.
 */
export function splitUpdates( attributes, updates, language, names ) {
	const shared = {};
	let blocklingo = attributes?.blocklingo;
	let translated = false;
	Object.keys( updates ).forEach( ( key ) => {
		if ( names.includes( key ) ) {
			blocklingo = setTranslation(
				{ ...attributes, blocklingo },
				language,
				key,
				updates[ key ]
			);
			translated = true;
		} else {
			shared[ key ] = updates[ key ];
		}
	} );
	return translated ? { ...shared, blocklingo } : shared;
}
