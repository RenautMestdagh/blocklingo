<?php
/**
 * The site's languages and settings (Settings → Blocklingo).
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Read access to the configured languages. One language is the default: content written
 * in the editor is in that language, the others are translations of it.
 */
class Languages {

	const OPTION = 'blocklingo_settings';

	/**
	 * Settings, loaded once per request.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * All settings, with defaults filled in.
	 *
	 * @return array{languages: array<string, array>, default: string, prefix_default: bool, post_types: string[]}
	 */
	public static function settings(): array {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, [] );
			self::$settings = self::normalize( is_array( $saved ) ? $saved : [] );
		}
		return self::$settings;
	}

	/**
	 * Fills in defaults and removes what doesn't make sense, so the rest of the plugin can
	 * rely on the shape. Without settings, the site's own language is the only language.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function normalize( array $settings ): array {
		$languages = [];
		foreach ( (array) ( $settings['languages'] ?? [] ) as $language ) {
			$code = self::sanitize_code( (string) ( $language['code'] ?? '' ) );
			if ( '' === $code || isset( $languages[ $code ] ) ) {
				continue;
			}
			$languages[ $code ] = [
				'code'    => $code,
				'name'    => (string) ( $language['name'] ?? $code ),
				'locale'  => (string) ( $language['locale'] ?? '' ),
				'slug'    => sanitize_title( (string) ( $language['slug'] ?? $code ) ),
				'enabled' => ! empty( $language['enabled'] ),
			];
			if ( '' === $languages[ $code ]['slug'] ) {
				$languages[ $code ]['slug'] = $code;
			}
		}
		if ( ! $languages ) {
			$locale             = get_option( 'WPLANG' ) ? get_option( 'WPLANG' ) : 'en_US';
			$code               = self::code_from_locale( $locale );
			$languages[ $code ] = [
				'code'    => $code,
				'name'    => $code,
				'locale'  => $locale,
				'slug'    => $code,
				'enabled' => true,
			];
		}

		$default = (string) ( $settings['default'] ?? '' );
		if ( ! isset( $languages[ $default ] ) ) {
			$default = (string) array_key_first( $languages );
		}
		// The default language is always on: it is the one the content is written in.
		$languages[ $default ]['enabled'] = true;

		return [
			'languages'      => $languages,
			'default'        => $default,
			'prefix_default' => ! empty( $settings['prefix_default'] ),
			'post_types'     => array_values( array_filter( (array) ( $settings['post_types'] ?? [ 'page', 'post' ] ), 'is_string' ) ),
		];
	}

	/**
	 * Saves settings and resets the cache.
	 *
	 * @param array $settings Settings as submitted.
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION, self::normalize( $settings ) );
		self::$settings = null;
	}

	/**
	 * Clears the in-memory copy (after the option changed elsewhere).
	 */
	public static function flush(): void {
		self::$settings = null;
	}

	/**
	 * All languages, enabled or not, by code.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		return self::settings()['languages'];
	}

	/**
	 * The languages visitors can see, by code; the default language first.
	 *
	 * @return array<string, array>
	 */
	public static function enabled(): array {
		$enabled = array_filter( self::all(), fn ( $language ) => $language['enabled'] );
		$default = self::default_code();
		return [ $default => $enabled[ $default ] ] + $enabled;
	}

	/**
	 * Whether there is anything to translate at all.
	 */
	public static function is_multilingual(): bool {
		return count( self::enabled() ) > 1;
	}

	/**
	 * One language.
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	public static function get( string $code ): ?array {
		return self::all()[ $code ] ?? null;
	}

	/**
	 * Whether a language exists and is enabled.
	 *
	 * @param string $code Language code.
	 */
	public static function is_enabled( string $code ): bool {
		return isset( self::enabled()[ $code ] );
	}

	/**
	 * The language the content is written in.
	 */
	public static function default_code(): string {
		return self::settings()['default'];
	}

	/**
	 * Whether a language is the default.
	 *
	 * @param string $code Language code.
	 */
	public static function is_default( string $code ): bool {
		return self::default_code() === $code;
	}

	/**
	 * The enabled language whose URL prefix is this slug.
	 *
	 * @param string $slug First segment of a URL path.
	 * @return string|null Language code.
	 */
	public static function code_by_slug( string $slug ): ?string {
		foreach ( self::enabled() as $code => $language ) {
			if ( $language['slug'] === $slug ) {
				return $code;
			}
		}
		return null;
	}

	/**
	 * The URL prefix of a language, or '' when its URLs have none (the default language,
	 * unless "prefix the default language" is on).
	 *
	 * @param string $code Language code.
	 */
	public static function prefix( string $code ): string {
		if ( self::is_default( $code ) && ! self::settings()['prefix_default'] ) {
			return '';
		}
		return self::get( $code )['slug'] ?? '';
	}

	/**
	 * Whether the default language has a prefix too (example.com/en/ instead of example.com/).
	 */
	public static function prefix_default(): bool {
		return self::settings()['prefix_default'];
	}

	/**
	 * Post types whose posts get translated titles, slugs and excerpts.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filters the post types Blocklingo translates.
		 *
		 * @param string[] $post_types Post type names.
		 */
		return (array) apply_filters( 'blocklingo_post_types', self::settings()['post_types'] );
	}

	/**
	 * WordPress locale of a language (for its translations of WordPress itself).
	 *
	 * @param string $code Language code.
	 */
	public static function locale( string $code ): string {
		$locale = self::get( $code )['locale'] ?? '';
		return '' !== $locale ? $locale : 'en_US';
	}

	/**
	 * Language tag for HTML and hreflang: nl_BE → nl-BE.
	 *
	 * @param string $code Language code.
	 */
	public static function html_lang( string $code ): string {
		return str_replace( '_', '-', self::locale( $code ) );
	}

	/**
	 * A safe language code: lowercase letters, optionally a region (fr, pt-br, zh-hant).
	 *
	 * @param string $code Raw code.
	 */
	public static function sanitize_code( string $code ): string {
		$code = strtolower( str_replace( '_', '-', trim( $code ) ) );
		return preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code ) ? $code : '';
	}

	/**
	 * Short code for a WordPress locale: nl_BE → nl, pt_BR → pt-br (kept when the region
	 * matters for the language itself).
	 *
	 * @param string $locale WordPress locale.
	 */
	public static function code_from_locale( string $locale ): string {
		$parts = explode( '_', strtolower( $locale ) );
		$code  = self::sanitize_code( $parts[0] );
		return '' !== $code ? $code : 'en';
	}
}
