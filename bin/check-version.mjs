/**
 * Checks that the version is the same everywhere it is written, and, for a release tag
 * (vX.Y.Z as the first argument), that it is that version.
 *
 *     node bin/check-version.mjs          # all the same?
 *     node bin/check-version.mjs v0.2.0   # all 0.2.0?
 */
/* eslint-disable no-console -- a command-line tool: it reports on the console. */
import { readFileSync } from 'node:fs';

const read = ( file ) =>
	readFileSync( new URL( `../${ file }`, import.meta.url ), 'utf8' );
const find = ( text, pattern, label ) => {
	const match = text.match( pattern );
	if ( ! match ) {
		throw new Error( `No version found in ${ label }` );
	}
	return match[ 1 ];
};

const main = read( 'blocklingo.php' );
const versions = {
	'blocklingo.php header': find(
		main,
		/^\s*\*\s*Version:\s*(\S+)/m,
		'blocklingo.php header'
	),
	BLOCKLINGO_VERSION: find(
		main,
		/define\(\s*'BLOCKLINGO_VERSION',\s*'([^']+)'/,
		'BLOCKLINGO_VERSION'
	),
	'readme.txt Stable tag': find(
		read( 'readme.txt' ),
		/^Stable tag:\s*(\S+)/m,
		'readme.txt'
	),
	'package.json': JSON.parse( read( 'package.json' ) ).version,
	'block.json (language switcher)': JSON.parse(
		read( 'src/blocks/language-switcher/block.json' )
	).version,
};

const tag = process.argv[ 2 ];
const expected = tag ? tag.replace( /^v/, '' ) : Object.values( versions )[ 0 ];
const wrong = Object.entries( versions ).filter(
	( [ , version ] ) => version !== expected
);

if ( wrong.length ) {
	wrong.forEach( ( [ where, version ] ) =>
		console.error( `${ where }: ${ version }, expected ${ expected }` )
	);
	process.exit( 1 );
}
console.log( `Version ${ expected } everywhere.` );
