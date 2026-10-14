<?php
/**
 * Texts outside blocks: the site title and tagline, and texts themes and plugins register.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Translations of texts by group and original. A text is found by its original (an md5 of
 * it), like gettext: when a theme changes a text, the new text is simply untranslated and
 * the old translation stays stored, unused, in case the change is undone.
 *
 * Option `blocklingo_strings`: { "fr": { "<group>": { "<md5 of original>": [ original, translation ] } } }
 *
 * Themes and plugins register their texts with blocklingo_register_string() or the
 * `blocklingo_strings` filter, so they appear on Settings → Blocklingo → Strings, and
 * translate them with blocklingo_translate().
 */
class Strings {

	const OPTION = 'blocklingo_strings';

	/**
	 * Group of the site title and tagline. Groups are identifiers, never translated: the same
	 * group must match in the admin and on the site, whatever language each is shown in.
	 */
	const SITE_GROUP = 'WordPress';

	/**
	 * Registered texts: group => [ md5 => [ 'original' => string, 'label' => string, 'multiline' => bool ] ].
	 *
	 * @var array
	 */
	private static $registered = [];

	/**
	 * Stored translations, loaded once per request.
	 *
	 * @var array|null
	 */
	private static $translations = null;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'option_blogname', [ __CLASS__, 'filter_site_title' ] );
		add_filter( 'option_blogdescription', [ __CLASS__, 'filter_tagline' ] );
		add_action( 'update_option_' . self::OPTION, [ __CLASS__, 'flush' ] );
	}

	/**
	 * Key of an original within its group.
	 *
	 * @param string $original Original text.
	 */
	public static function key( string $original ): string {
		return md5( $original );
	}

	/**
	 * Registers a text for translation (shown on the Strings screen).
	 *
	 * @param string $original  Text in the default language.
	 * @param string $group     Group, e.g. the theme or plugin name.
	 * @param string $label     Short description for translators.
	 * @param bool   $multiline Whether the text spans lines.
	 */
	public static function register( string $original, string $group = 'default', string $label = '', bool $multiline = false ): void {
		if ( '' === trim( $original ) ) {
			return;
		}
		self::$registered[ $group ][ self::key( $original ) ] = [
			'original'  => $original,
			'label'     => '' !== $label ? $label : $original,
			'multiline' => $multiline || false !== strpos( $original, "\n" ),
		];
	}

	/**
	 * All registered texts, including the built-in ones and those added through the
	 * `blocklingo_strings` filter.
	 *
	 * @return array group => [ md5 => [ original, label, multiline ] ]
	 */
	public static function registered(): array {
		$site = self::SITE_GROUP;
		$raw  = self::raw_option( 'blogname' );
		if ( '' !== $raw ) {
			self::register( $raw, $site, __( 'Site title', 'blocklingo' ) );
		}
		$raw = self::raw_option( 'blogdescription' );
		if ( '' !== $raw ) {
			self::register( $raw, $site, __( 'Tagline', 'blocklingo' ) );
		}

		/**
		 * Filters the texts that can be translated on the Strings screen.
		 *
		 * @param array $strings group => [ md5 => [ 'original', 'label', 'multiline' ] ].
		 */
		$strings = (array) apply_filters( 'blocklingo_strings', self::$registered );
		ksort( $strings );
		return $strings;
	}

	/**
	 * An option without this class's own filters on it (the original site title).
	 *
	 * @param string $name Option name.
	 */
	private static function raw_option( string $name ): string {
		$filter = 'blogname' === $name ? 'filter_site_title' : 'filter_tagline';
		remove_filter( 'option_' . $name, [ __CLASS__, $filter ] );
		$value = (string) get_option( $name, '' );
		add_filter( 'option_' . $name, [ __CLASS__, $filter ] );
		return $value;
	}

	/**
	 * Stored translations.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null === self::$translations ) {
			$stored             = get_option( self::OPTION, [] );
			self::$translations = is_array( $stored ) ? $stored : [];
		}
		return self::$translations;
	}

	/**
	 * Forgets the loaded translations (after saving).
	 */
	public static function flush(): void {
		self::$translations = null;
	}

	/**
	 * The stored pair [ original, translation ] of a text in a language, or null.
	 *
	 * @param string $original Original text.
	 * @param string $group    Group.
	 * @param string $code     Language code.
	 * @return array{0: string, 1: string}|null
	 */
	public static function pair( string $original, string $group, string $code ): ?array {
		$pair = self::all()[ $code ][ $group ][ self::key( $original ) ] ?? null;
		return is_array( $pair ) && isset( $pair[0], $pair[1] ) && is_string( $pair[0] ) && is_string( $pair[1] ) ? [ $pair[0], $pair[1] ] : null;
	}

	/**
	 * A text in a language: its translation when there is one that is up to date, else the
	 * original. Without a language: the language being shown.
	 *
	 * @param string      $original Text in the default language.
	 * @param string      $group    Group it was registered in.
	 * @param string|null $code     Language code.
	 */
	public static function translate( string $original, string $group = 'default', ?string $code = null ): string {
		$code = $code ?? Links::lang();
		// The default language can have translations too: a theme's texts are in the
		// language they were written in, which isn't always the site's default (for example
		// after switching the default language).
		if ( '' === $original || ! Languages::is_enabled( $code ) ) {
			return $original;
		}
		$pair = self::pair( $original, $group, $code );
		return ( $pair && $pair[0] === $original && '' !== $pair[1] ) ? $pair[1] : $original;
	}

	/**
	 * Cleans submitted translations: only configured languages, texts
	 * keyed by the md5 of their original, the same markup rules as posts.
	 *
	 * @param mixed $input Submitted value: { lang: { group: { md5: [ original, translation ] } } }.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$clean = [];
		foreach ( (array) $input as $code => $groups ) {
			$code = (string) $code;
			if ( ! Languages::get( $code ) || ! is_array( $groups ) ) {
				continue;
			}
			foreach ( $groups as $group => $texts ) {
				$group = sanitize_text_field( (string) $group );
				foreach ( (array) $texts as $key => $pair ) {
					if ( ! is_array( $pair ) || ! isset( $pair[0], $pair[1] ) || ! is_scalar( $pair[0] ) || ! is_scalar( $pair[1] ) ) {
						continue;
					}
					$original    = (string) $pair[0];
					$translation = wp_kses_post( (string) $pair[1] );
					if ( '' === trim( $translation ) || self::key( $original ) !== (string) $key ) {
						continue;
					}
					$clean[ $code ][ $group ][ (string) $key ] = [ $original, $translation ];
				}
			}
		}
		return $clean;
	}

	/**
	 * The site title in the visitor's language.
	 *
	 * @param string $value Site title.
	 * @return string
	 */
	public static function filter_site_title( $value ) {
		return self::filter_option( (string) $value );
	}

	/**
	 * The tagline in the visitor's language.
	 *
	 * @param string $value Tagline.
	 * @return string
	 */
	public static function filter_tagline( $value ) {
		return self::filter_option( (string) $value );
	}

	/**
	 * A site option in the visitor's language (only on the site, never in the admin).
	 *
	 * @param string $value Option value.
	 */
	private static function filter_option( string $value ): string {
		if ( ! Languages::is_multilingual() || ! Request::is_frontend() ) {
			return $value;
		}
		return self::translate( $value, self::SITE_GROUP );
	}
}
