/**
 * Languages and options handed over by the server (see includes/class-editor.php).
 */
const data = window.blocklingoEditor ?? {};

export const settings = {
	defaultLanguage: data.defaultLanguage ?? '',
	languages: Array.isArray( data.languages ) ? data.languages : [],
	postTypes: Array.isArray( data.postTypes ) ? data.postTypes : [],
};

/**
 * Display name of a language.
 *
 * @param {string} code Language code.
 * @return {string} Name.
 */
export function languageName( code ) {
	return (
		settings.languages.find( ( language ) => language.code === code )
			?.name ?? code
	);
}
