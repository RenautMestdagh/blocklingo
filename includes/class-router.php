<?php
/**
 * From a translated URL to the right post, and redirects to each page's one real address.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress parses /fr/tarifs/ as the page "tarifs", which doesn't exist: the page is
 * called "tarieven" and "tarifs" is its French slug. This finds it.
 */
class Router {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'request', [ __CLASS__, 'resolve_translated_slugs' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_to_language' ], 1 );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_to_translated_address' ], 2 );
		add_filter( 'redirect_canonical', [ __CLASS__, 'filter_canonical_redirect' ], 20, 2 );
	}

	/**
	 * Replaces a translated slug in the parsed query by the post it belongs to.
	 *
	 * @param array $query_vars Parsed query variables.
	 * @return array
	 */
	public static function resolve_translated_slugs( $query_vars ) {
		$code = Request::lang();
		if ( ! Request::is_frontend() || Languages::is_default( $code ) || ! Languages::is_multilingual() ) {
			return $query_vars;
		}

		$post_types = Languages::post_types();
		if ( ! empty( $query_vars['pagename'] ) ) {
			$path = (string) $query_vars['pagename'];
		} elseif ( ! empty( $query_vars['name'] ) ) {
			$path = (string) $query_vars['name'];
		} else {
			foreach ( $post_types as $post_type ) {
				$object = get_post_type_object( $post_type );
				if ( $object && $object->query_var && ! empty( $query_vars[ $object->query_var ] ) ) {
					$path       = (string) $query_vars[ $object->query_var ];
					$post_types = [ $post_type ];
					break;
				}
			}
		}
		if ( empty( $path ) ) {
			return $query_vars;
		}

		$post = Posts::find_by_path( $path, $code, $post_types );
		if ( ! $post ) {
			return $query_vars;
		}

		unset( $query_vars['pagename'], $query_vars['name'], $query_vars['attachment'], $query_vars['error'] );
		if ( 'page' === $post->post_type ) {
			$query_vars['pagename'] = get_page_uri( $post );
		} elseif ( 'post' === $post->post_type ) {
			$query_vars['name'] = $post->post_name;
		} else {
			$object                  = get_post_type_object( $post->post_type );
			$query_vars['post_type'] = $post->post_type;
			$query_vars['name']      = $post->post_name;
			if ( $object && $object->query_var ) {
				$query_vars[ $object->query_var ] = get_page_uri( $post );
			}
		}
		return $query_vars;
	}

	/**
	 * With "prefix the default language" on, an address without a language (an old link,
	 * or the bare domain) moves to the default language's address: / → /nl/,
	 * /contact/ → /nl/contact/.
	 */
	public static function redirect_to_language(): void {
		$url = self::language_address();
		if ( '' !== $url ) {
			wp_safe_redirect( $url, 301, 'Blocklingo' );
			exit;
		}
	}

	/**
	 * The default language's address of this request when it came without a prefix, or ''
	 * when no redirect is needed.
	 */
	public static function language_address(): string {
		if ( ! Languages::is_multilingual() || ! Languages::prefix_default() || Request::is_prefixed() ) {
			return '';
		}
		if ( ! self::is_plain_page_view() || is_404() ) {
			return '';
		}
		$url = Links::in_language( Languages::default_code(), fn () => self::current_url() );
		return ( $url && self::requested_url() !== $url ) ? wp_sanitize_redirect( $url ) : '';
	}

	/**
	 * A post asked for by another address than its own in this language (/fr/tarieven/
	 * when its French address is /fr/tarifs/) moves to its own: one address per page and
	 * language, so search engines don't see the same page twice.
	 */
	public static function redirect_to_translated_address(): void {
		$url = self::translated_address();
		if ( '' !== $url ) {
			wp_safe_redirect( $url, 301, 'Blocklingo' );
			exit;
		}
	}

	/**
	 * The address to redirect a post view to, or '' when it is already at its own.
	 */
	public static function translated_address(): string {
		if ( ! Languages::is_multilingual() || ! is_singular() || is_front_page() || ! self::is_plain_page_view() ) {
			return '';
		}
		// Paginated posts (<!--nextpage-->) and comment pages keep their extra path.
		if ( get_query_var( 'page' ) > 1 || get_query_var( 'cpage' ) ) {
			return '';
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! Posts::is_translatable( $post->post_type ) ) {
			return '';
		}
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || ! get_option( 'permalink_structure' ) ) {
			return '';
		}
		$requested = self::requested_url();
		$own_path  = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$path      = (string) wp_parse_url( $requested, PHP_URL_PATH );
		if ( untrailingslashit( rawurldecode( $path ) ) === untrailingslashit( rawurldecode( $own_path ) ) ) {
			return '';
		}
		return self::with_query( $permalink, self::query_string() );
	}

	/**
	 * WordPress's canonical redirect (e.g. a wrong slug to the right one) in the language
	 * of the request; never a redirect to the address that was asked for.
	 *
	 * @param string|false $redirect_url  Where WordPress wants to go.
	 * @param string       $requested_url What was asked for.
	 * @return string|false
	 */
	public static function filter_canonical_redirect( $redirect_url, $requested_url ) {
		if ( ! $redirect_url || ! Languages::is_multilingual() ) {
			return $redirect_url;
		}
		$code = Request::lang();
		if ( Request::is_prefixed() || Languages::prefix_default() ) {
			$redirect_url = Links::add_prefix( Links::strip_prefix( $redirect_url ), $code );
		}
		return $redirect_url === $requested_url ? false : $redirect_url;
	}

	/**
	 * Whether this is an ordinary GET page view (not a feed, sitemap, robots.txt, preview…).
	 */
	private static function is_plain_page_view(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';
		if ( 'get' !== $method && 'head' !== $method ) {
			return false;
		}
		if ( is_feed() || is_robots() || is_favicon() || is_trackback() || is_preview() || is_customize_preview() || get_query_var( 'sitemap' ) || get_query_var( 'sitemap-stylesheet' ) ) {
			return false;
		}
		return Request::is_frontend();
	}

	/**
	 * The address of what is being shown, in the language of Links::lang().
	 */
	private static function current_url(): string {
		$query = self::query_string();
		if ( is_front_page() || ( is_home() && ! get_option( 'page_for_posts' ) ) ) {
			return self::with_query( Links::home( Links::lang() ), $query );
		}
		if ( is_singular() ) {
			$url = get_permalink( get_queried_object_id() );
			return is_string( $url ) ? self::with_query( $url, $query ) : '';
		}
		return Links::add_prefix( Links::strip_prefix( self::requested_url() ), Links::lang() );
	}

	/**
	 * Query string of the request, without the language parameter.
	 */
	private static function query_string(): string {
		$query = (string) wp_parse_url( self::requested_url(), PHP_URL_QUERY );
		parse_str( $query, $args );
		unset( $args['lang'] );
		return $args ? http_build_query( $args ) : '';
	}

	/**
	 * Appends a query string to a URL.
	 *
	 * @param string $url   URL.
	 * @param string $query Query string.
	 */
	private static function with_query( string $url, string $query ): string {
		return '' === $query ? $url : $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
	}

	/**
	 * The full URL of this request.
	 */
	private static function requested_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared and passed to wp_safe_redirect only.
		$url = set_url_scheme( 'http://' . wp_parse_url( Links::base(), PHP_URL_HOST ) . ( wp_parse_url( Links::base(), PHP_URL_PORT ) ? ':' . wp_parse_url( Links::base(), PHP_URL_PORT ) : '' ) . $uri );
		return esc_url_raw( $url );
	}
}
