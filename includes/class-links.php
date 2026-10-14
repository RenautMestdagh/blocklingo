<?php
/**
 * URLs in the right language: permalinks, the home link, and links inside content.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Every permalink WordPress builds gets the language prefix and translated slugs of the
 * language being shown. To build a URL in another language (switcher, hreflang), wrap the
 * call in Links::in_language().
 */
class Links {

	/**
	 * Languages forced by in_language(), innermost last.
	 *
	 * @var string[]
	 */
	private static $stack = [];

	/**
	 * Resolved internal links of this request: original URL → post ID (0 = not a post).
	 *
	 * @var array<string, int>
	 */
	private static $resolved = [];

	/**
	 * Depth of raw() calls; filters are off while above 0.
	 *
	 * @var int
	 */
	private static $raw = 0;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'page_link', [ __CLASS__, 'filter_page_link' ], 20, 3 );
		add_filter( 'post_link', [ __CLASS__, 'filter_post_link' ], 20, 2 );
		add_filter( 'post_type_link', [ __CLASS__, 'filter_post_link' ], 20, 2 );
		add_filter( 'home_url', [ __CLASS__, 'filter_home_url' ], 20, 2 );
		// Archives, which classic themes link to from PHP (block themes from blocks).
		foreach ( [ 'term_link', 'post_type_archive_link', 'year_link', 'month_link', 'day_link', 'author_link', 'get_pagenum_link', 'search_link' ] as $filter ) {
			add_filter( $filter, [ __CLASS__, 'filter_archive_link' ], 20 );
		}
		add_filter( 'render_block', [ __CLASS__, 'localize_content_links' ], 20 );
		add_filter( 'the_content', [ __CLASS__, 'localize_content_links' ], 20 );
		// Lookups are only valid for one request (long-running PHP, WP-CLI, tests).
		add_action( 'parse_request', [ __CLASS__, 'reset' ] );
		add_action( 'save_post', [ __CLASS__, 'reset' ] );
	}

	/**
	 * Forgets resolved links.
	 */
	public static function reset(): void {
		self::$resolved = [];
	}

	/**
	 * Language URLs are built for: forced by in_language(), else the request's language.
	 */
	public static function lang(): string {
		$forced = end( self::$stack );
		return false !== $forced ? $forced : Request::lang();
	}

	/**
	 * Runs a callback with all of this class's URL filters off: WordPress's own URLs, in the
	 * default language's slugs and without prefixes (for url_to_postid() and the like).
	 *
	 * @param callable $callback Callback.
	 * @return mixed What the callback returns.
	 */
	public static function raw( callable $callback ) {
		++self::$raw;
		try {
			return $callback();
		} finally {
			--self::$raw;
		}
	}

	/**
	 * Runs a callback with URLs built for another language.
	 *
	 * @param string   $code     Language code.
	 * @param callable $callback Callback.
	 * @return mixed What the callback returns.
	 */
	public static function in_language( string $code, callable $callback ) {
		self::$stack[] = $code;
		try {
			return $callback();
		} finally {
			array_pop( self::$stack );
		}
	}

	/**
	 * Whether permalinks get a language. Everywhere once there are two languages, so "View"
	 * links in the admin and editor point to the real address too (in the default language).
	 */
	private static function active(): bool {
		return 0 === self::$raw && Languages::is_multilingual();
	}

	/**
	 * The site's home without any language: what get_option( 'home' ) holds.
	 */
	public static function base(): string {
		return untrailingslashit( (string) get_option( 'home' ) );
	}

	/**
	 * Home page of a language: example.com/fr/.
	 *
	 * @param string $code Language code.
	 */
	public static function home( string $code ): string {
		$prefix = Languages::prefix( $code );
		if ( '' === $prefix ) {
			return self::base() . '/';
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'lang', $code, self::base() . '/' );
		}
		return self::base() . '/' . user_trailingslashit( $prefix );
	}

	/**
	 * Adds a language to a URL path below home: 'contact/' → '/fr/contact/'.
	 *
	 * @param string $url  Full URL on this site.
	 * @param string $code Language code.
	 */
	public static function add_prefix( string $url, string $code ): string {
		$base = self::base();
		if ( 0 !== strpos( $url, $base ) ) {
			return $url;
		}
		$prefix = Languages::prefix( $code );
		if ( '' === $prefix ) {
			return $url;
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'lang', $code, $url );
		}
		$rest = substr( $url, strlen( $base ) );
		return $base . '/' . $prefix . ( '' === $rest ? '/' : '/' . ltrim( $rest, '/' ) );
	}

	/**
	 * Page permalinks: prefix and translated slugs of the page and its parents.
	 *
	 * @param string $link    Permalink.
	 * @param int    $post_id Page ID.
	 * @param bool   $sample  Whether this is a sample permalink (editor).
	 * @return string
	 */
	public static function filter_page_link( $link, $post_id, $sample = false ) {
		if ( $sample || ! self::active() ) {
			return $link;
		}
		$code = self::lang();
		if ( (int) get_option( 'page_on_front' ) === (int) $post_id && 'page' === get_option( 'show_on_front' ) ) {
			return self::home( $code );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! get_option( 'permalink_structure' ) || ! Posts::is_translatable( $post->post_type ) ) {
			return self::add_prefix( self::strip_prefix( $link ), $code );
		}
		return self::add_prefix( self::base() . '/' . user_trailingslashit( Posts::path( $post, $code ), 'page' ), $code );
	}

	/**
	 * Post and custom post type permalinks: prefix and translated slug.
	 *
	 * @param string   $link Permalink.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function filter_post_link( $link, $post ) {
		if ( ! self::active() || ! $post instanceof \WP_Post ) {
			return $link;
		}
		$code = self::lang();
		$link = self::strip_prefix( $link );
		if ( get_option( 'permalink_structure' ) && Posts::is_translatable( $post->post_type ) && $post->post_name ) {
			$slug = Posts::slug( $post, $code );
			if ( $slug !== $post->post_name ) {
				$link = preg_replace( '#/' . preg_quote( $post->post_name, '#' ) . '(/?)($|\?|\#)#', '/' . $slug . '$1$2', $link, 1 );
			}
		}
		return self::add_prefix( $link, $code );
	}

	/**
	 * Archive links (categories, tags, dates, authors, page 2 of a list, search) in the
	 * language being shown. Their slugs are not translated.
	 *
	 * @param string $link Link.
	 * @return string
	 */
	public static function filter_archive_link( $link ) {
		if ( ! self::active() || ! is_string( $link ) ) {
			return $link;
		}
		return self::add_prefix( self::strip_prefix( $link ), self::lang() );
	}

	/**
	 * The home link (site title, logo, "Home" in menus) points to the language's home page.
	 * Only for home_url() and home_url( '/' ): other paths are built by WordPress for its
	 * own purposes (feeds, REST, sitemaps) and stay as they are.
	 *
	 * @param string $url  Home URL.
	 * @param string $path Requested path.
	 * @return string
	 */
	public static function filter_home_url( $url, $path = '' ) {
		if ( ( '' !== $path && '/' !== $path ) || ! self::active() || ( ! Request::is_frontend() && empty( self::$stack ) ) ) {
			return $url;
		}
		$code = self::lang();
		return '' === Languages::prefix( $code ) ? $url : self::home( $code );
	}

	/**
	 * Removes a language prefix from a URL on this site (to rebuild it in another language).
	 *
	 * @param string $url URL.
	 */
	public static function strip_prefix( string $url ): string {
		$base = self::base();
		if ( 0 !== strpos( $url, $base . '/' ) ) {
			return $url;
		}
		$rest     = substr( $url, strlen( $base ) + 1 );
		$segments = explode( '/', $rest, 2 );
		$first    = (string) preg_replace( '/[?#].*$/', '', $segments[0] );
		if ( null !== Languages::code_by_slug( $first ) ) {
			return $base . '/' . ( $segments[1] ?? '' );
		}
		return $url;
	}

	/**
	 * Links to this site inside content point to the same language: on a French page,
	 * a link to /contact/ becomes /fr/contact/ (with the translated slug).
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public static function localize_content_links( $html ) {
		if ( ! Request::is_frontend() || ! self::active() || false === strpos( (string) $html, 'href' ) ) {
			return $html;
		}
		$code = self::lang();
		if ( '' === Languages::prefix( $code ) && Languages::is_default( $code ) ) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'a' ) ) {
			$href = $tags->get_attribute( 'href' );
			if ( is_string( $href ) ) {
				$localized = self::localize_url( $href, $code );
				if ( $localized !== $href ) {
					$tags->set_attribute( 'href', $localized );
				}
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * One URL in a language, if it points to this site's content.
	 *
	 * @param string $href URL as written (absolute or starting with /).
	 * @param string $code Language code.
	 */
	public static function localize_url( string $href, string $code ): string {
		$base     = self::base();
		$relative = 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' );
		$absolute = $relative ? $base . $href : $href;
		if ( 0 !== strpos( $absolute, $base . '/' ) && $absolute !== $base ) {
			return $href;
		}

		$path = (string) wp_parse_url( $absolute, PHP_URL_PATH );
		$home = (string) wp_parse_url( $base, PHP_URL_PATH );
		$rest = ltrim( substr( $path, strlen( $home ) ), '/' );
		// Files, the admin, the API and feeds are not pages.
		if ( preg_match( '#^(wp-content|wp-admin|wp-includes|wp-json|wp-login\.php|feed|xmlrpc\.php)(/|$)#', $rest ) || preg_match( '#\.[a-z0-9]{2,5}$#i', $rest ) ) {
			return $href;
		}
		// Already in a language.
		$first = explode( '/', $rest )[0];
		if ( '' !== $first && null !== Languages::code_by_slug( $first ) ) {
			return $href;
		}

		$suffix = '';
		if ( preg_match( '/[?#].*$/', $absolute, $match ) ) {
			$suffix   = $match[0];
			$absolute = substr( $absolute, 0, -strlen( $suffix ) );
		}

		if ( ! array_key_exists( $absolute, self::$resolved ) ) {
			self::$resolved[ $absolute ] = (int) self::raw( fn () => url_to_postid( $absolute ) );
		}
		$post_id = self::$resolved[ $absolute ];

		if ( $post_id ) {
			$url = self::in_language( $code, fn () => get_permalink( $post_id ) );
		} elseif ( '' === $rest ) {
			$url = self::home( $code );
		} else {
			$url = self::add_prefix( $absolute, $code );
		}
		if ( ! is_string( $url ) || '' === $url ) {
			return $href;
		}
		if ( $relative ) {
			$url = substr( $url, strlen( $base ) );
			$url = '' === $url ? '/' : $url;
		}
		return $url . $suffix;
	}
}
