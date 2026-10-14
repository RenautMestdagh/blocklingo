<?php
/**
 * Which language this request is in, from the URL: example.com/fr/… is French.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the language as soon as the plugin loads (WordPress loads its own translations
 * right after), and hides the prefix from WordPress while it parses the URL, so every
 * rewrite rule keeps working: /fr/tarifs/ is parsed as /tarifs/.
 */
class Request {

	/**
	 * Language of this request.
	 *
	 * @var string
	 */
	private static $lang = '';

	/**
	 * Whether the URL started with a language prefix.
	 *
	 * @var bool
	 */
	private static $prefixed = false;

	/**
	 * REQUEST_URI as the visitor sent it, while WordPress parses the stripped one.
	 *
	 * @var string|null
	 */
	private static $original_uri = null;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		self::detect();
		add_filter( 'locale', [ __CLASS__, 'filter_locale' ], 1 );
		add_filter( 'do_parse_request', [ __CLASS__, 'strip_prefix' ], 1 );
		add_action( 'parse_request', [ __CLASS__, 'restore_uri' ], -1000 );
	}

	/**
	 * The language of the content being shown.
	 */
	public static function lang(): string {
		return '' !== self::$lang ? self::$lang : Languages::default_code();
	}

	/**
	 * Switches this request to another enabled language, for requests whose address has no
	 * language in it (an integration's REST call made from a page in that language).
	 * Unknown or disabled languages are ignored.
	 *
	 * @param string $code Language code.
	 */
	public static function use_language( string $code ): void {
		if ( Languages::is_enabled( $code ) ) {
			self::$lang = $code;
		}
	}

	/**
	 * Whether the URL had a language prefix.
	 */
	public static function is_prefixed(): bool {
		return self::$prefixed;
	}

	/**
	 * Whether this is a page view for visitors (not the admin, REST API, cron or WP-CLI).
	 */
	public static function is_frontend(): bool {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		return ! self::is_rest_path( self::path() );
	}

	/**
	 * Reads the language from the first segment of the path.
	 */
	public static function detect(): void {
		self::$lang     = Languages::default_code();
		self::$prefixed = false;
		if ( ! Languages::is_multilingual() || ! self::is_frontend() ) {
			return;
		}

		$segments = explode( '/', trim( self::path(), '/' ), 2 );
		$code     = Languages::code_by_slug( $segments[0] );
		if ( null !== $code ) {
			self::$lang     = $code;
			self::$prefixed = true;
			return;
		}

		// Plain permalinks (?page_id=2) have no path to put a prefix in: ?lang=fr instead.
		$query = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		if ( '' !== $query && Languages::is_enabled( $query ) && ! get_option( 'permalink_structure' ) ) {
			self::$lang = $query;
		}
	}

	/**
	 * Path of this request below the site's home, with leading and trailing slash: '/fr/tarifs/'.
	 */
	public static function path(): string {
		$uri  = self::$original_uri ?? ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never output.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH );
		$home = untrailingslashit( $home );
		if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Whether a path is a REST API request.
	 *
	 * @param string $path Path below home.
	 */
	private static function is_rest_path( string $path ): bool {
		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		return 0 === strpos( ltrim( $path, '/' ), $prefix . '/' ) || ltrim( $path, '/' ) === $prefix;
	}

	/**
	 * WordPress's own texts in the visitor's language.
	 *
	 * @param string $locale Site locale.
	 */
	public static function filter_locale( $locale ) {
		if ( Languages::is_multilingual() && self::is_frontend() ) {
			return Languages::locale( self::lang() );
		}
		return $locale;
	}

	/**
	 * Lets WordPress parse the URL without the language prefix.
	 *
	 * @param bool $parse Whether to parse the request.
	 * @return bool
	 */
	public static function strip_prefix( $parse ) {
		if ( ! self::$prefixed || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $parse;
		}
		$uri                = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- restored unchanged after parsing.
		self::$original_uri = $uri;

		$slug    = Languages::get( self::$lang )['slug'];
		$home    = untrailingslashit( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) );
		$pattern = '#^' . preg_quote( $home . '/' . $slug, '#' ) . '(/|\?|$)#';
		$uri     = preg_replace( $pattern, $home . '/$1', $uri, 1 );
		$uri     = preg_replace( '#^//+#', '/', $uri );

		$_SERVER['REQUEST_URI'] = wp_slash( $uri );
		return $parse;
	}

	/**
	 * Puts the visitor's URL back once WordPress has parsed it, so canonical redirects
	 * and plugins see the real address.
	 */
	public static function restore_uri(): void {
		if ( null !== self::$original_uri ) {
			$_SERVER['REQUEST_URI'] = wp_slash( self::$original_uri );
			self::$original_uri     = null;
		}
	}
}
