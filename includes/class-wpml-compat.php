<?php
/**
 * Answers the hooks themes and plugins use to work with WPML, so code written for WPML
 * works with Blocklingo too. Only when WPML itself is not active.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * WPML's public hooks (https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/),
 * mapped to Blocklingo: languages, the current language, string registration and
 * translation, URLs. Objects are never duplicated in Blocklingo, so "the translation of
 * post 12" is post 12.
 */
class Wpml_Compat {

	/**
	 * Hooks, once all plugins are loaded (to see whether WPML is there).
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'register' ], 20 );
	}

	/**
	 * Adds the hooks unless WPML is active or a site turned this off.
	 */
	public static function register(): void {
		/**
		 * Filters whether Blocklingo answers WPML's hooks.
		 *
		 * @param bool $enabled Whether to answer them; false when WPML is active.
		 */
		if ( ! apply_filters( 'blocklingo_wpml_compat', ! defined( 'ICL_SITEPRESS_VERSION' ) ) ) {
			return;
		}
		add_filter( 'wpml_current_language', [ __CLASS__, 'current_language' ] );
		add_filter( 'wpml_default_language', [ __CLASS__, 'default_language' ] );
		add_filter( 'wpml_active_languages', [ __CLASS__, 'active_languages' ] );
		add_filter( 'wpml_translate_single_string', [ __CLASS__, 'translate_single_string' ], 10, 4 );
		add_action( 'wpml_register_single_string', [ __CLASS__, 'register_single_string' ], 10, 3 );
		add_filter( 'wpml_object_id', [ __CLASS__, 'object_id' ] );
		add_filter( 'wpml_permalink', [ __CLASS__, 'permalink' ], 10, 2 );
		add_filter( 'wpml_home_url', [ __CLASS__, 'home_url' ] );
	}

	/**
	 * The language being shown.
	 */
	public static function current_language(): string {
		return Links::lang();
	}

	/**
	 * The default language.
	 */
	public static function default_language(): string {
		return Languages::default_code();
	}

	/**
	 * The enabled languages, in WPML's format, with the current page's URL in each.
	 *
	 * @return array<string, array>
	 */
	public static function active_languages(): array {
		$current   = Links::lang();
		$languages = [];
		foreach ( Languages::enabled() as $code => $language ) {
			$languages[ $code ] = [
				'id'               => $code,
				'code'             => $code,
				'language_code'    => $code,
				'native_name'      => $language['name'],
				'translated_name'  => $language['name'],
				'default_locale'   => Languages::locale( $code ),
				'tag'              => Languages::html_lang( $code ),
				'active'           => $code === $current ? 1 : 0,
				'url'              => Request::is_frontend() ? Seo::current_url_in( $code ) : Links::home( $code ),
				'missing'          => 0,
				'country_flag_url' => '',
			];
		}
		return $languages;
	}

	/**
	 * A registered string in a language.
	 *
	 * @param string      $original Original string.
	 * @param string      $context  WPML context ("domain"): used as the group.
	 * @param string      $name     WPML string name (unused: strings are keyed by their text).
	 * @param string|null $code     Language; the current one when empty.
	 * @return string
	 */
	public static function translate_single_string( $original, $context = '', $name = '', $code = null ) {
		unset( $name );
		$group = '' !== (string) $context ? (string) $context : 'default';
		return Strings::translate( (string) $original, $group, $code ? (string) $code : null );
	}

	/**
	 * Registers a string for translation.
	 *
	 * @param string $context WPML context: used as the group.
	 * @param string $name    WPML string name: used as the label.
	 * @param string $value   Original string.
	 */
	public static function register_single_string( $context, $name, $value ): void {
		Strings::register( (string) $value, '' !== (string) $context ? (string) $context : 'default', (string) $name );
	}

	/**
	 * The ID of an object's translation: the object itself, as Blocklingo translates in place.
	 *
	 * @param int $id Object ID.
	 * @return int
	 */
	public static function object_id( $id ) {
		return $id;
	}

	/**
	 * A URL in a language.
	 *
	 * @param string      $url  URL on this site.
	 * @param string|null $code Language code; the current one when empty.
	 * @return string
	 */
	public static function permalink( $url, $code = null ) {
		$code = $code && Languages::is_enabled( (string) $code ) ? (string) $code : Links::lang();
		return Links::localize_url( Links::strip_prefix( (string) $url ), $code );
	}

	/**
	 * The home page in the current language.
	 */
	public static function home_url(): string {
		return Links::home( Links::lang() );
	}
}
