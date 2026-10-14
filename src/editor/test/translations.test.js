/**
 * Tests for the translation helpers.
 */
import { describe, expect, it } from 'vitest';

import {
	acceptOutdated,
	getBlockStatus,
	getPair,
	getTranslatableAttributes,
	getTranslatedAttributes,
	setTranslation,
	splitUpdates,
	toText,
} from '../translations';

describe( 'getTranslatableAttributes', () => {
	it( 'picks content attributes holding text', () => {
		// Arrange.
		const blockType = {
			attributes: {
				content: {
					type: 'rich-text',
					source: 'rich-text',
					role: 'content',
				},
				alt: { type: 'string', source: 'attribute', role: 'content' },
				caption: { type: 'string', __experimentalRole: 'content' },
				url: { type: 'string', role: 'content' },
				align: { type: 'string' },
				width: { type: 'number', role: 'content' },
			},
		};

		// Act.
		const names = getTranslatableAttributes( blockType );

		// Assert.
		expect( names ).toEqual( [ 'content', 'alt', 'caption' ] );
	} );

	it( 'returns nothing for an unknown block type', () => {
		// Arrange.
		const blockType = undefined;

		// Act.
		const names = getTranslatableAttributes( blockType );

		// Assert.
		expect( names ).toEqual( [] );
	} );
} );

describe( 'toText', () => {
	it( 'turns rich text into its HTML', () => {
		// Arrange.
		const richText = {
			toHTMLString: () => 'Hallo <strong>wereld</strong>',
		};

		// Act.
		const text = toText( richText );

		// Assert.
		expect( text ).toBe( 'Hallo <strong>wereld</strong>' );
	} );

	it( 'turns missing values into an empty string', () => {
		// Arrange.
		const values = [ undefined, null ];

		// Act.
		const texts = values.map( toText );

		// Assert.
		expect( texts ).toEqual( [ '', '' ] );
	} );
} );

describe( 'getPair', () => {
	it( 'ignores malformed stored values', () => {
		// Arrange.
		const attributes = {
			blocklingo: { fr: { content: [ 'Hallo' ], alt: 'x' } },
		};

		// Act.
		const pairs = [
			getPair( attributes, 'fr', 'content' ),
			getPair( attributes, 'fr', 'alt' ),
		];

		// Assert.
		expect( pairs ).toEqual( [ null, null ] );
	} );
} );

describe( 'getBlockStatus', () => {
	it( 'is missing without a translation', () => {
		// Arrange.
		const attributes = { content: 'Hallo' };

		// Act.
		const status = getBlockStatus( attributes, 'fr', [ 'content' ] );

		// Assert.
		expect( status ).toBe( 'missing' );
	} );

	it( 'is translated when the stored original is the current one', () => {
		// Arrange.
		const attributes = {
			content: 'Hallo',
			blocklingo: { fr: { content: [ 'Hallo', 'Bonjour' ] } },
		};

		// Act.
		const status = getBlockStatus( attributes, 'fr', [ 'content' ] );

		// Assert.
		expect( status ).toBe( 'translated' );
	} );

	it( 'is outdated when the original changed after translating', () => {
		// Arrange.
		const attributes = {
			content: 'Hallo allemaal',
			blocklingo: { fr: { content: [ 'Hallo', 'Bonjour' ] } },
		};

		// Act.
		const status = getBlockStatus( attributes, 'fr', [ 'content' ] );

		// Assert.
		expect( status ).toBe( 'outdated' );
	} );

	it( 'is null when the block has no text', () => {
		// Arrange.
		const attributes = { content: '' };

		// Act.
		const status = getBlockStatus( attributes, 'fr', [ 'content' ] );

		// Assert.
		expect( status ).toBeNull();
	} );

	it( 'reports the worst attribute', () => {
		// Arrange.
		const attributes = {
			alt: 'Foto',
			caption: 'Bijschrift',
			blocklingo: { fr: { alt: [ 'Foto', 'Photo' ] } },
		};

		// Act.
		const status = getBlockStatus( attributes, 'fr', [ 'alt', 'caption' ] );

		// Assert.
		expect( status ).toBe( 'missing' );
	} );
} );

describe( 'getTranslatedAttributes', () => {
	it( 'shows translations and falls back to originals', () => {
		// Arrange.
		const attributes = {
			alt: 'Foto',
			caption: 'Bijschrift',
			align: 'wide',
			blocklingo: { fr: { alt: [ 'Foto', 'Photo' ] } },
		};

		// Act.
		const shown = getTranslatedAttributes( attributes, 'fr', [
			'alt',
			'caption',
		] );

		// Assert.
		expect( shown ).toMatchObject( {
			alt: 'Photo',
			caption: 'Bijschrift',
			align: 'wide',
		} );
	} );
} );

describe( 'setTranslation', () => {
	it( 'stores the current original with the translation', () => {
		// Arrange.
		const attributes = { content: 'Hallo' };

		// Act.
		const blocklingo = setTranslation(
			attributes,
			'fr',
			'content',
			'Bonjour'
		);

		// Assert.
		expect( blocklingo ).toEqual( {
			fr: { content: [ 'Hallo', 'Bonjour' ] },
		} );
	} );

	it( 'keeps other languages and attributes', () => {
		// Arrange.
		const attributes = {
			content: 'Hallo',
			blocklingo: { en: { content: [ 'Hallo', 'Hello' ] } },
		};

		// Act.
		const blocklingo = setTranslation(
			attributes,
			'fr',
			'content',
			'Bonjour'
		);

		// Assert.
		expect( blocklingo ).toEqual( {
			en: { content: [ 'Hallo', 'Hello' ] },
			fr: { content: [ 'Hallo', 'Bonjour' ] },
		} );
	} );

	it( 'removes a translation that is emptied', () => {
		// Arrange.
		const attributes = {
			content: 'Hallo',
			blocklingo: { fr: { content: [ 'Hallo', 'Bonjour' ] } },
		};

		// Act.
		const blocklingo = setTranslation( attributes, 'fr', 'content', '' );

		// Assert.
		expect( blocklingo ).toEqual( {} );
	} );

	it( 'does not change the attributes it was given', () => {
		// Arrange.
		const attributes = { content: 'Hallo', blocklingo: { fr: {} } };

		// Act.
		setTranslation( attributes, 'fr', 'content', 'Bonjour' );

		// Assert.
		expect( attributes ).toEqual( {
			content: 'Hallo',
			blocklingo: { fr: {} },
		} );
	} );
} );

describe( 'acceptOutdated', () => {
	it( 'marks outdated translations as up to date', () => {
		// Arrange.
		const attributes = {
			content: 'Hallo allemaal',
			blocklingo: { fr: { content: [ 'Hallo', 'Bonjour à tous' ] } },
		};

		// Act.
		const blocklingo = acceptOutdated( attributes, 'fr', [ 'content' ] );

		// Assert.
		expect( blocklingo ).toEqual( {
			fr: { content: [ 'Hallo allemaal', 'Bonjour à tous' ] },
		} );
	} );
} );

describe( 'splitUpdates', () => {
	it( 'sends text to the translation and the rest to the shared attributes', () => {
		// Arrange.
		const attributes = { content: 'Hallo', align: 'left' };
		const updates = { content: 'Bonjour', align: 'center' };

		// Act.
		const result = splitUpdates( attributes, updates, 'fr', [ 'content' ] );

		// Assert.
		expect( result ).toEqual( {
			align: 'center',
			blocklingo: { fr: { content: [ 'Hallo', 'Bonjour' ] } },
		} );
	} );

	it( 'leaves the blocklingo attribute alone when no text changes', () => {
		// Arrange.
		const attributes = { content: 'Hallo' };
		const updates = { align: 'center' };

		// Act.
		const result = splitUpdates( attributes, updates, 'fr', [ 'content' ] );

		// Assert.
		expect( result ).toEqual( { align: 'center' } );
	} );
} );
