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

function isText( name, attribute ) {
	const role = attribute.role ?? attribute.__experimentalRole;
	const text =
		attribute.type === 'string' ||
		attribute.type === 'rich-text' ||
		[ 'html', 'rich-text', 'text' ].includes( attribute.source );
	return role === 'content' && text && ! NOT_TEXT.includes( name );
}

/**
 * Whether a list attribute (source "query", like table rows or tab labels) has text in
 * its items, at any depth.
 *
 * @param {Object} attribute Attribute definition.
 * @return {boolean} Has text.
 */
function hasTextItems( attribute ) {
	return Object.entries( attribute.query ?? {} ).some( ( [ name, item ] ) =>
		item.source === 'query' ? hasTextItems( item ) : isText( name, item )
	);
}

/**
 * Names of the attributes of a block type that hold text to translate: content attributes
 * (role "content") that are strings or rich text, and lists with such text in their items.
 *
 * @param {Object} blockType Block type as registered in the editor.
 * @return {string[]} Attribute names.
 */
export function getTranslatableAttributes( blockType ) {
	const attributes = blockType?.attributes ?? {};
	return Object.keys( attributes ).filter( ( name ) =>
		attributes[ name ].source === 'query'
			? hasTextItems( attributes[ name ] )
			: isText( name, attributes[ name ] )
	);
}

function itemPaths( query, items, prefix ) {
	if ( ! Array.isArray( items ) ) {
		return [];
	}
	return items.flatMap( ( item, index ) =>
		Object.entries( query ?? {} ).flatMap( ( [ name, attribute ] ) => {
			const path = `${ prefix }.${ index }.${ name }`;
			if ( attribute.source === 'query' ) {
				return itemPaths( attribute.query, item?.[ name ], path );
			}
			return isText( name, attribute ) ? [ path ] : [];
		} )
	);
}

/**
 * Where a block holds text to translate: attribute names, and for lists the path of each
 * text in them, like `body.0.cells.1.content` for the second cell of a table's first row.
 * Each path is translated on its own.
 *
 * @param {Object} blockType  Block type as registered in the editor.
 * @param {Object} attributes Block attributes.
 * @return {string[]} Paths.
 */
export function getTextPaths( blockType, attributes ) {
	const definitions = blockType?.attributes ?? {};
	return getTranslatableAttributes( blockType ).flatMap( ( name ) =>
		definitions[ name ].source === 'query'
			? itemPaths( definitions[ name ].query, attributes?.[ name ], name )
			: [ name ]
	);
}

/**
 * The value at a path in the attributes.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} path       Attribute name or path.
 * @return {unknown} Value.
 */
export function getValue( attributes, path ) {
	return path
		.split( '.' )
		.reduce( ( value, key ) => value?.[ key ], attributes );
}

/**
 * A copy of the attributes with the value at a path replaced; what it passes through is
 * copied, the rest is shared.
 *
 * @param {Object}  attributes Block attributes.
 * @param {string}  path       Attribute name or path.
 * @param {unknown} value      New value.
 * @return {Object} New attributes.
 */
export function setValue( attributes, path, value ) {
	const [ key, ...rest ] = path.split( '.' );
	const copy = Array.isArray( attributes )
		? [ ...attributes ]
		: { ...attributes };
	copy[ key ] = rest.length
		? setValue( attributes?.[ key ] ?? {}, rest.join( '.' ), value )
		: value;
	return copy;
}

/**
 * Whether two list values have the same items, nested lists included: only then do the
 * paths of one point at the same texts in the other.
 *
 * @param {unknown} a Value.
 * @param {unknown} b Value.
 * @return {boolean} Same shape.
 */
function sameShape( a, b ) {
	if ( Array.isArray( a ) || Array.isArray( b ) ) {
		return (
			Array.isArray( a ) &&
			Array.isArray( b ) &&
			a.length === b.length &&
			a.every( ( item, index ) => sameShape( item, b[ index ] ) )
		);
	}
	if ( a && b && a.constructor === Object && b.constructor === Object ) {
		return Object.keys( a ).every( ( key ) =>
			sameShape( a[ key ], b[ key ] )
		);
	}
	return true;
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
 * Whether a text has words: numbers, prices and codes alone (a table's "€ 7,50") read the
 * same in every language.
 *
 * @param {string} text Text, possibly with markup.
 * @return {boolean} Has letters.
 */
function hasWords( text ) {
	return /\p{L}/u.test( text.replace( /<[^>]*>|&[#\w]+;/g, '' ) );
}

/**
 * Status of one attribute (or path) in a language: 'missing', 'outdated' (the original
 * changed after it was translated) or 'translated'. Texts without words need none.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} language   Language code.
 * @param {string} name       Attribute name or path.
 * @return {string} Status.
 */
export function getAttributeStatus( attributes, language, name ) {
	const original = toText( getValue( attributes, name ) );
	const pair = getPair( attributes, language, name );
	if ( ! pair ) {
		return hasWords( original ) ? 'missing' : 'translated';
	}
	return pair[ 0 ] === original ? 'translated' : 'outdated';
}

/**
 * Status of a block in a language: the worst of its attributes, or null when it has
 * nothing to translate.
 *
 * @param {Object}   attributes Block attributes.
 * @param {string}   language   Language code.
 * @param {string[]} names      Text paths (getTextPaths).
 * @return {?string} 'missing', 'outdated', 'translated' or null.
 */
export function getBlockStatus( attributes, language, names ) {
	const statuses = names
		.filter( ( name ) => toText( getValue( attributes, name ) ) !== '' )
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
 * The attributes as the editor shows them in a language: each text is its translation, or
 * the original when there is none yet.
 *
 * @param {Object}   attributes Block attributes.
 * @param {string}   language   Language code.
 * @param {string[]} names      Text paths (getTextPaths).
 * @return {Object} Attributes to show.
 */
export function getTranslatedAttributes( attributes, language, names ) {
	return names.reduce( ( shown, name ) => {
		const pair = getPair( attributes, language, name );
		return pair ? setValue( shown, name, pair[ 1 ] ) : shown;
	}, attributes );
}

/**
 * The blocklingo attribute after typing a translation. An empty translation removes it;
 * the stored original is always the current one (typing makes it up to date).
 *
 * @param {Object}  attributes  Block attributes.
 * @param {string}  language    Language code.
 * @param {string}  name        Attribute name or path.
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
		forLanguage[ name ] = [ toText( getValue( attributes, name ) ), text ];
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
 * @param {string[]} names      Text paths (getTextPaths).
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
 * Splits attribute updates made while editing a translation: text goes into the
 * translation, everything else (layout, colours, links) is shared by all languages.
 *
 * A list (table rows) comes back whole: each text in it that differs from what was shown
 * becomes a translation, the rest of the list (a cell's alignment) is shared, with the
 * original texts kept. Adding or removing items changes every language, so it is left to
 * the original.
 *
 * @param {Object}   attributes Current block attributes.
 * @param {Object}   updates    Attributes being set.
 * @param {string}   language   Language code.
 * @param {string[]} names      Text paths (getTextPaths).
 * @return {Object} Attributes to really set.
 */
export function splitUpdates( attributes, updates, language, names ) {
	const shared = {};
	let blocklingo = attributes?.blocklingo;
	let translated = false;
	const translate = ( name, value ) => {
		blocklingo = setTranslation(
			{ ...attributes, blocklingo },
			language,
			name,
			value
		);
		translated = true;
	};

	Object.keys( updates ).forEach( ( key ) => {
		const paths = names.filter( ( name ) => name.startsWith( key + '.' ) );
		if ( names.includes( key ) ) {
			translate( key, updates[ key ] );
		} else if ( ! paths.length ) {
			shared[ key ] = updates[ key ];
		} else if ( sameShape( attributes[ key ], updates[ key ] ) ) {
			const shown = getTranslatedAttributes(
				attributes,
				language,
				paths
			);
			let value = { [ key ]: updates[ key ] };
			paths.forEach( ( path ) => {
				const typed = toText( getValue( value, path ) );
				if ( typed !== toText( getValue( shown, path ) ) ) {
					translate( path, typed );
				}
				value = setValue( value, path, getValue( attributes, path ) );
			} );
			shared[ key ] = value[ key ];
		}
	} );
	return translated ? { ...shared, blocklingo } : shared;
}

/**
 * The texts without a translation in a language, one job per text, for machine
 * translation. Outdated translations are left alone: someone wrote them.
 *
 * @param {Array<{clientId: string, attributes: Object, names: string[]}>} blocks   Blocks.
 * @param {string}                                                         language Language code.
 * @return {Array<{clientId: string, name: string, text: string}>} Jobs.
 */
export function collectMissing( blocks, language ) {
	const jobs = [];
	blocks.forEach( ( { clientId, attributes, names } ) => {
		names.forEach( ( name ) => {
			if (
				getAttributeStatus( attributes, language, name ) === 'missing'
			) {
				jobs.push( {
					clientId,
					name,
					text: toText( getValue( attributes, name ) ),
				} );
			}
		} );
	} );
	return jobs;
}
