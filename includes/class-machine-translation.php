<?php
/**
 * Optional machine translation with DeepL, with the site owner's own API key.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Nothing is sent anywhere until a DeepL API key is saved under Settings → Blocklingo.
 * Then the editor can ask the site (REST: POST blocklingo/v1/translate) to translate texts;
 * the site sends them to DeepL with the key. The key never reaches the browser.
 */
class Machine_Translation {

	const OPTION     = 'blocklingo_deepl_key';
	const MAX_TEXTS  = 50;
	const MAX_CHARS  = 50000;
	const REST_ROUTE = '/translate';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_route' ] );
	}

	/**
	 * The saved key, or ''.
	 */
	public static function key(): string {
		return trim( (string) get_option( self::OPTION, '' ) );
	}

	/**
	 * Whether machine translation is set up.
	 */
	public static function is_enabled(): bool {
		return '' !== self::key();
	}

	/**
	 * DeepL's API host: free keys end in ":fx".
	 *
	 * @param string $key API key.
	 */
	public static function endpoint( string $key ): string {
		$host = ':fx' === substr( $key, -3 ) ? 'api-free.deepl.com' : 'api.deepl.com';
		return 'https://' . $host . '/v2/translate';
	}

	/**
	 * DeepL's code for a language as source: the language only (EN, PT, ZH).
	 *
	 * @param string $code Language code.
	 */
	public static function source_code( string $code ): string {
		return strtoupper( explode( '-', Languages::code_from_locale( Languages::locale( $code ) ) )[0] );
	}

	/**
	 * DeepL's code for a language as target: with the variant where DeepL needs one
	 * (EN-GB or EN-US, PT-PT or PT-BR, ZH-HANS or ZH-HANT).
	 *
	 * @param string $code Language code.
	 */
	public static function target_code( string $code ): string {
		$locale   = Languages::locale( $code );
		$base     = self::source_code( $code );
		$variants = [
			'EN' => 'en_US' === $locale ? 'EN-US' : 'EN-GB',
			'PT' => 'pt_BR' === $locale ? 'PT-BR' : 'PT-PT',
			'ZH' => in_array( $locale, [ 'zh_TW', 'zh_HK' ], true ) ? 'ZH-HANT' : 'ZH-HANS',
		];
		return $variants[ $base ] ?? $base;
	}

	/**
	 * The REST route for the editor.
	 */
	public static function register_route(): void {
		register_rest_route(
			'blocklingo/v1',
			self::REST_ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle' ],
				'permission_callback' => fn () => current_user_can( 'edit_posts' ),
				'args'                => [
					'texts'  => [
						'type'     => 'array',
						'items'    => [ 'type' => 'string' ],
						'required' => true,
						'minItems' => 1,
						'maxItems' => self::MAX_TEXTS,
					],
					'target' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Translates texts from the default language.
	 *
	 * @param \WP_REST_Request $request Request with texts and target.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ) {
		$texts  = array_map( 'strval', (array) $request->get_param( 'texts' ) );
		$target = (string) $request->get_param( 'target' );
		if ( ! Languages::is_enabled( $target ) || Languages::is_default( $target ) ) {
			return new \WP_Error( 'blocklingo_target', __( 'Choose a language other than the default.', 'blocklingo' ), [ 'status' => 400 ] );
		}
		if ( array_sum( array_map( 'strlen', $texts ) ) > self::MAX_CHARS ) {
			return new \WP_Error( 'blocklingo_too_long', __( 'Too much text at once.', 'blocklingo' ), [ 'status' => 400 ] );
		}
		$translations = self::translate( $texts, $target );
		if ( is_wp_error( $translations ) ) {
			return $translations;
		}
		return rest_ensure_response( [ 'translations' => $translations ] );
	}

	/**
	 * Sends texts to DeepL. Markup is kept (tag handling), and every translation is cleaned
	 * like post content before it is returned.
	 *
	 * @param string[] $texts  Texts in the default language.
	 * @param string   $target Language code.
	 * @return string[]|\WP_Error Translations in the same order.
	 */
	public static function translate( array $texts, string $target ) {
		$key = self::key();
		if ( '' === $key ) {
			return new \WP_Error( 'blocklingo_no_key', __( 'Machine translation is not set up: add a DeepL API key under Settings → Blocklingo.', 'blocklingo' ), [ 'status' => 400 ] );
		}
		$response = wp_remote_post(
			self::endpoint( $key ),
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'DeepL-Auth-Key ' . $key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'text'         => array_values( $texts ),
						'source_lang'  => self::source_code( Languages::default_code() ),
						'target_lang'  => self::target_code( $target ),
						'tag_handling' => 'html',
					]
				),
			]
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'blocklingo_deepl_unreachable', __( 'DeepL could not be reached. Try again later.', 'blocklingo' ), [ 'status' => 502 ] );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! isset( $body['translations'] ) || ! is_array( $body['translations'] ) || count( $body['translations'] ) !== count( $texts ) ) {
			$message = 403 === $status
				? __( 'DeepL refused the API key.', 'blocklingo' )
				: ( 456 === $status ? __( 'The DeepL quota is used up for now.', 'blocklingo' ) : __( 'DeepL returned an error.', 'blocklingo' ) );
			return new \WP_Error( 'blocklingo_deepl_error', $message, [ 'status' => 502 ] );
		}
		return array_map(
			fn ( $translation ) => wp_kses_post( (string) ( $translation['text'] ?? '' ) ),
			$body['translations']
		);
	}
}
