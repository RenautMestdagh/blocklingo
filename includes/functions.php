<?php
/**
 * Public functions for themes and other plugins. Check with function_exists() before use,
 * so a theme keeps working when Blocklingo is off.
 *
 * @package Blocklingo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Code of the language being shown, e.g. 'fr'.
 */
function blocklingo_current_language(): string {
	return Blocklingo\Links::lang();
}

/**
 * Code of the default language: the one content is written in.
 */
function blocklingo_default_language(): string {
	return Blocklingo\Languages::default_code();
}

/**
 * The enabled languages, by code: [ 'fr' => [ 'code', 'name', 'locale', 'slug' ], … ].
 *
 * @return array<string, array>
 */
function blocklingo_languages(): array {
	return Blocklingo\Languages::enabled();
}

/**
 * The address of the page being shown, in another language.
 *
 * @param string $code Language code.
 */
function blocklingo_current_url( string $code ): string {
	return Blocklingo\Seo::current_url_in( $code );
}

/**
 * A URL on this site in a language (translated slugs included when it is a post or page).
 *
 * @param string      $url  Absolute URL or path starting with /.
 * @param string|null $code Language code; the current language when left out.
 */
function blocklingo_localize_url( string $url, ?string $code = null ): string {
	return Blocklingo\Links::localize_url( $url, $code ?? Blocklingo\Links::lang() );
}

/**
 * Registers a text of a theme or plugin for translation: it appears on Settings →
 * Blocklingo → Strings. Call it on every request where the text is used (e.g. on `init`);
 * nothing is written to the database.
 *
 * @param string $original  Text in the default language.
 * @param string $group     Group shown on the Strings screen, e.g. the theme name. Not translated.
 * @param string $label     Short description for translators; the text itself when empty.
 * @param bool   $multiline Whether the text spans several lines.
 */
function blocklingo_register_string( string $original, string $group = 'default', string $label = '', bool $multiline = false ): void {
	Blocklingo\Strings::register( $original, $group, $label, $multiline );
}

/**
 * A registered text in the language being shown (or in $code): its translation, or the
 * original when there is none or when the original changed since it was translated.
 *
 * @param string      $original Text in the default language.
 * @param string      $group    Group it was registered in.
 * @param string|null $code     Language code; the current language when left out.
 */
function blocklingo_translate( string $original, string $group = 'default', ?string $code = null ): string {
	return Blocklingo\Strings::translate( $original, $group, $code );
}
