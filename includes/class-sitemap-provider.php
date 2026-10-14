<?php
/**
 * Sitemap provider for the addresses in the other languages.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * One sitemap per language other than the default (its "object subtype"): the language's
 * home page, then the published posts of every translated post type, in that language.
 */
class Sitemap_Provider extends \WP_Sitemaps_Provider {

	const NAME = 'blocklingo';

	/**
	 * Sets the provider's name and object type.
	 */
	public function __construct() {
		$this->name        = self::NAME;
		$this->object_type = 'language';
	}

	/**
	 * The languages, as subtypes.
	 *
	 * @return array<string, object>
	 */
	public function get_object_subtypes() {
		$subtypes = [];
		foreach ( array_keys( Languages::enabled() ) as $code ) {
			if ( ! Languages::is_default( $code ) ) {
				$subtypes[ $code ] = (object) [ 'name' => $code ];
			}
		}
		return $subtypes;
	}

	/**
	 * Post types listed, as WordPress's own posts sitemap lists them.
	 *
	 * @return string[]
	 */
	private function post_types(): array {
		$listed = array_keys( wp_sitemaps_get_server()->registry->get_provider( 'posts' ) ? wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_object_subtypes() : [] );
		return array_values( array_intersect( Languages::post_types(), $listed ) );
	}

	/**
	 * Query for one page of posts.
	 *
	 * @param int $page_num Page, from 1.
	 * @return array
	 */
	private function query_args( int $page_num ): array {
		return [
			'post_type'              => $this->post_types(),
			'post_status'            => 'publish',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'posts_per_page'         => wp_sitemaps_get_max_urls( $this->object_type ),
			'paged'                  => $page_num,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'has_password'           => false,
			'ignore_sticky_posts'    => true,
		];
	}

	/**
	 * Addresses of one page of the sitemap for a language.
	 *
	 * @param int    $page_num       Page, from 1.
	 * @param string $object_subtype Language code.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$code = (string) $object_subtype;
		if ( ! Languages::is_enabled( $code ) || Languages::is_default( $code ) || ! $this->post_types() ) {
			return [];
		}
		$urls = [];
		if ( 1 === (int) $page_num ) {
			$urls[] = [ 'loc' => Links::home( $code ) ];
		}
		$front = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$query = new \WP_Query( $this->query_args( (int) $page_num ) );
		foreach ( $query->posts as $post ) {
			if ( $post->ID === $front ) {
				continue;
			}
			$urls[] = [
				'loc'     => Links::in_language( $code, fn () => get_permalink( $post ) ),
				'lastmod' => wp_date( DATE_W3C, strtotime( $post->post_modified_gmt ) ),
			];
		}
		return $urls;
	}

	/**
	 * Number of pages of the sitemap for a language.
	 *
	 * @param string $object_subtype Language code.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! $this->post_types() ) {
			return 0;
		}
		$args                  = $this->query_args( 1 );
		$args['fields']        = 'ids';
		$args['no_found_rows'] = false;
		$query                 = new \WP_Query( $args );
		return max( 1, (int) $query->max_num_pages );
	}
}
