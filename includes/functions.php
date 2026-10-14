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
