<?php
/**
 * Tells search engines about the other languages of a page (hreflang).
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * <link rel="alternate" hreflang="…"> for every enabled language, plus x-default (the
 * default language) for visitors whose language isn't on the site. <html lang> follows
 * from the locale (see Request::filter_locale()).
 */
class Seo {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'print_alternates' ], 5 );
	}

	/**
	 * The address of what is being shown, in a language. Falls back to that language's
	 * home page for things that only exist once (search results, 404).
	 *
	 * @param string $code Language code.
	 */
	public static function current_url_in( string $code ): string {
		if ( is_front_page() || ( is_home() && ! get_option( 'page_for_posts' ) ) ) {
			return Links::home( $code );
		}
		if ( is_singular() ) {
			$url = Links::in_language( $code, fn () => get_permalink( get_queried_object_id() ) );
			return is_string( $url ) ? $url : Links::home( $code );
		}
		if ( is_home() ) {
			$url = Links::in_language( $code, fn () => get_permalink( (int) get_option( 'page_for_posts' ) ) );
			return is_string( $url ) ? $url : Links::home( $code );
		}
		if ( is_category() || is_tag() || is_tax() || is_post_type_archive() || is_author() || is_date() ) {
			$url = Links::raw( fn () => self::archive_url() );
			return '' !== $url ? Links::add_prefix( $url, $code ) : Links::home( $code );
		}
		return Links::home( $code );
	}

	/**
	 * Archive URL in the default language's form, without prefix.
	 */
	private static function archive_url(): string {
		$object = get_queried_object();
		if ( $object instanceof \WP_Term ) {
			$url = get_term_link( $object );
		} elseif ( $object instanceof \WP_Post_Type ) {
			$url = get_post_type_archive_link( $object->name );
		} elseif ( $object instanceof \WP_User ) {
			$url = get_author_posts_url( $object->ID );
		} else {
			$url = '';
		}
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Prints the alternate links.
	 */
	public static function print_alternates(): void {
		if ( ! Languages::is_multilingual() || is_404() || is_search() ) {
			return;
		}
		/**
		 * Filters whether to print hreflang links (turn off when an SEO plugin prints them).
		 *
		 * @param bool $print Whether to print.
		 */
		if ( ! apply_filters( 'blocklingo_print_hreflang', true ) ) {
			return;
		}

		$urls = [];
		foreach ( array_keys( Languages::enabled() ) as $code ) {
			$urls[ Languages::html_lang( $code ) ] = self::current_url_in( $code );
		}
		$urls['x-default'] = self::current_url_in( Languages::default_code() );

		foreach ( $urls as $hreflang => $url ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $hreflang ), esc_url( $url ) );
		}
	}
}
