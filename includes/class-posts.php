<?php
/**
 * Translated title, slug and excerpt of posts and pages.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Stores per post one meta value `blocklingo`: { "fr": { "title": "", "slug": "", "excerpt": "" } }.
 * Slugs are also kept in `_blocklingo_slug` ("fr:tarifs", one row per language), so a URL
 * can be looked up with a single query.
 */
class Posts {

	const META       = 'blocklingo';
	const SLUG_INDEX = '_blocklingo_slug';
	const FIELDS     = [ 'title', 'slug', 'excerpt' ];

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_meta' ], 20 );
		add_action( 'added_post_meta', [ __CLASS__, 'sync_slug_index' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'sync_slug_index' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'sync_slug_index' ], 10, 4 );

		add_filter( 'the_title', [ __CLASS__, 'filter_title' ], 10, 2 );
		add_filter( 'single_post_title', [ __CLASS__, 'filter_single_title' ], 10, 2 );
		add_filter( 'get_the_excerpt', [ __CLASS__, 'filter_excerpt' ], 10, 2 );
	}

	/**
	 * The meta field, editable through the REST API (and so in the block editor).
	 */
	public static function register_meta(): void {
		$translation = [
			'type'                 => 'object',
			'properties'           => [
				'title'   => [ 'type' => 'string' ],
				'slug'    => [ 'type' => 'string' ],
				'excerpt' => [ 'type' => 'string' ],
			],
			'additionalProperties' => false,
		];
		foreach ( Languages::post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META,
				[
					'type'              => 'object',
					'single'            => true,
					'default'           => [],
					'sanitize_callback' => [ __CLASS__, 'sanitize' ],
					'auth_callback'     => fn ( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
					'show_in_rest'      => [
						'schema' => [
							'type'                 => 'object',
							'additionalProperties' => $translation,
						],
					],
				]
			);
		}
	}

	/**
	 * Keeps only known languages and fields, cleaned.
	 *
	 * @param mixed $value Submitted value.
	 * @return array
	 */
	public static function sanitize( $value ): array {
		$clean = [];
		foreach ( (array) $value as $code => $fields ) {
			if ( ! Languages::get( (string) $code ) || Languages::is_default( (string) $code ) || ! is_array( $fields ) ) {
				continue;
			}
			$entry = [
				'title'   => sanitize_text_field( (string) ( $fields['title'] ?? '' ) ),
				'slug'    => sanitize_title( (string) ( $fields['slug'] ?? '' ) ),
				'excerpt' => sanitize_textarea_field( (string) ( $fields['excerpt'] ?? '' ) ),
			];
			if ( array_filter( $entry ) ) {
				$clean[ $code ] = $entry;
			}
		}
		return $clean;
	}

	/**
	 * Rebuilds the slug lookup rows whenever the translations of a post change.
	 *
	 * @param int|int[] $meta_id  Meta ID(s).
	 * @param int       $post_id  Post ID.
	 * @param string    $meta_key Meta key.
	 */
	public static function sync_slug_index( $meta_id, $post_id, $meta_key ): void {
		if ( self::META !== $meta_key ) {
			return;
		}
		delete_post_meta( $post_id, self::SLUG_INDEX );
		$translations = get_post_meta( $post_id, self::META, true );
		foreach ( is_array( $translations ) ? $translations : [] as $code => $fields ) {
			if ( ! empty( $fields['slug'] ) ) {
				add_post_meta( $post_id, self::SLUG_INDEX, $code . ':' . $fields['slug'] );
			}
		}
	}

	/**
	 * Whether a post type is translated.
	 *
	 * @param string $post_type Post type name.
	 */
	public static function is_translatable( string $post_type ): bool {
		return in_array( $post_type, Languages::post_types(), true );
	}

	/**
	 * One translated field, or '' when there is none (callers fall back to the original).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $code    Language code.
	 * @param string $field   title, slug or excerpt.
	 */
	public static function field( int $post_id, string $code, string $field ): string {
		if ( Languages::is_default( $code ) ) {
			return '';
		}
		$translations = get_post_meta( $post_id, self::META, true );
		return is_array( $translations ) ? (string) ( $translations[ $code ][ $field ] ?? '' ) : '';
	}

	/**
	 * The slug of a post in a language: its translated slug, or the original.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $code Language code.
	 */
	public static function slug( \WP_Post $post, string $code ): string {
		$slug = self::is_translatable( $post->post_type ) ? self::field( $post->ID, $code, 'slug' ) : '';
		return '' !== $slug ? $slug : $post->post_name;
	}

	/**
	 * Path of a post in a language, with its translated ancestors: 'over-ons/team'.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $code Language code.
	 */
	public static function path( \WP_Post $post, string $code ): string {
		$segments = [ self::slug( $post, $code ) ];
		foreach ( get_post_ancestors( $post ) as $ancestor_id ) {
			$ancestor = get_post( $ancestor_id );
			if ( $ancestor ) {
				array_unshift( $segments, self::slug( $ancestor, $code ) );
			}
		}
		return implode( '/', $segments );
	}

	/**
	 * Finds the post that has this translated path in a language.
	 *
	 * @param string   $path       Path below the language prefix, e.g. 'a-propos/equipe'.
	 * @param string   $code       Language code.
	 * @param string[] $post_types Post types to look in.
	 * @return \WP_Post|null
	 */
	public static function find_by_path( string $path, string $code, array $post_types ): ?\WP_Post {
		$path     = trim( $path, '/' );
		$segments = explode( '/', $path );
		$slug     = sanitize_title( (string) end( $segments ) );
		if ( '' === $slug || Languages::is_default( $code ) ) {
			return null;
		}

		$ids = get_posts(
			[
				'post_type'              => array_values( array_intersect( $post_types, Languages::post_types() ) ),
				'post_status'            => 'any',
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- indexed lookup row, one per translation.
					[
						'key'   => self::SLUG_INDEX,
						'value' => $code . ':' . $slug,
					],
				],
			]
		);
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && ( ! is_post_type_hierarchical( $post->post_type ) || self::path( $post, $code ) === $path ) ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Translated title on the site.
	 *
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function filter_title( $title, $post_id = 0 ) {
		if ( ! $post_id || ! Request::is_frontend() ) {
			return $title;
		}
		$translated = self::field( (int) $post_id, Links::lang(), 'title' );
		return '' !== $translated ? esc_html( $translated ) : $title;
	}

	/**
	 * Translated title in the browser tab (wp_get_document_title).
	 *
	 * @param string   $title Title.
	 * @param \WP_Post $post  Post.
	 * @return string
	 */
	public static function filter_single_title( $title, $post = null ) {
		if ( ! $post instanceof \WP_Post || ! Request::is_frontend() ) {
			return $title;
		}
		$translated = self::field( $post->ID, Links::lang(), 'title' );
		return '' !== $translated ? $translated : $title;
	}

	/**
	 * Translated excerpt (used for descriptions by many themes and SEO plugins).
	 *
	 * @param string   $excerpt Excerpt.
	 * @param \WP_Post $post    Post.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		if ( ! $post instanceof \WP_Post || ! Request::is_frontend() ) {
			return $excerpt;
		}
		$translated = self::field( $post->ID, Links::lang(), 'excerpt' );
		return '' !== $translated ? $translated : $excerpt;
	}
}
