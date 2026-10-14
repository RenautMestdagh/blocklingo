<?php
/**
 * Optional machine translation with DeepL. DeepL itself is never called: requests are
 * answered by a stand-in.
 *
 * @package Blocklingo
 */

use Blocklingo\Machine_Translation;
use Blocklingo\Settings_Page;

/**
 * Tests for the Machine_Translation class and the key setting.
 *
 * @covers \Blocklingo\Machine_Translation
 * @covers \Blocklingo\Settings_Page
 */
class MachineTranslationTest extends Blocklingo_TestCase {

	/**
	 * The last request sent to "DeepL".
	 *
	 * @var array|null
	 */
	private $sent = null;

	public function set_up(): void {
		parent::set_up();
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		delete_option( Machine_Translation::OPTION );
		parent::tear_down();
	}

	/**
	 * Answers HTTP requests instead of DeepL.
	 *
	 * @param int   $status HTTP status to answer with.
	 * @param array $body   JSON body to answer with.
	 */
	private function fake_deepl( int $status, array $body ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $status, $body ) {
				$this->sent = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'headers'  => [],
					'body'     => wp_json_encode( $body ),
					'response' => [
						'code'    => $status,
						'message' => '',
					],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * Calls the REST route as a user with a role.
	 *
	 * @param string $role   Role.
	 * @param array  $params Body.
	 */
	private function call( string $role, array $params ): WP_REST_Response {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$request = new WP_REST_Request( 'POST', '/blocklingo/v1/translate' );
		$request->set_body_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	public function test_free_keys_use_the_free_api(): void {
		// Arrange.
		$free = 'abc-123:fx';
		$pro  = 'abc-123';

		// Act.
		$free_endpoint = Machine_Translation::endpoint( $free );
		$pro_endpoint  = Machine_Translation::endpoint( $pro );

		// Assert.
		$this->assertSame( 'https://api-free.deepl.com/v2/translate', $free_endpoint );
		$this->assertSame( 'https://api.deepl.com/v2/translate', $pro_endpoint );
	}

	public function test_target_codes_include_the_variant_deepl_needs(): void {
		// Arrange: en has locale en_GB in the test case.

		// Act.
		$english = Machine_Translation::target_code( 'en' );
		$french  = Machine_Translation::target_code( 'fr' );
		$source  = Machine_Translation::source_code( 'nl' );

		// Assert.
		$this->assertSame( 'EN-GB', $english );
		$this->assertSame( 'FR', $french );
		$this->assertSame( 'NL', $source );
	}

	public function test_texts_are_sent_with_the_key_and_translations_returned(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'secret-key:fx' );
		$this->fake_deepl( 200, [ 'translations' => [ [ 'text' => 'Bonjour <strong>monde</strong>' ] ] ] );

		// Act.
		$response = $this->call(
			'editor',
			[
				'texts'  => [ 'Hallo <strong>wereld</strong>' ],
				'target' => 'fr',
			]
		);

		// Assert.
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'Bonjour <strong>monde</strong>' ], $response->get_data()['translations'] );
		$this->assertSame( 'DeepL-Auth-Key secret-key:fx', $this->sent['args']['headers']['Authorization'] );
		$this->assertSame(
			[
				'text'         => [ 'Hallo <strong>wereld</strong>' ],
				'source_lang'  => 'NL',
				'target_lang'  => 'FR',
				'tag_handling' => 'html',
			],
			json_decode( $this->sent['args']['body'], true )
		);
	}

	public function test_translations_are_cleaned_like_post_content(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'secret-key:fx' );
		$this->fake_deepl( 200, [ 'translations' => [ [ 'text' => 'Bonjour<script>alert(1)</script>' ] ] ] );

		// Act.
		$response = $this->call(
			'editor',
			[
				'texts'  => [ 'Hallo' ],
				'target' => 'fr',
			]
		);

		// Assert.
		$this->assertSame( [ 'Bonjouralert(1)' ], $response->get_data()['translations'] );
	}

	public function test_users_who_cannot_edit_are_refused(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'secret-key:fx' );
		$this->fake_deepl( 200, [ 'translations' => [ [ 'text' => 'x' ] ] ] );

		// Act.
		$response = $this->call(
			'subscriber',
			[
				'texts'  => [ 'Hallo' ],
				'target' => 'fr',
			]
		);

		// Assert.
		$this->assertSame( 403, $response->get_status() );
		$this->assertNull( $this->sent );
	}

	public function test_nothing_is_sent_without_a_key(): void {
		// Arrange.
		$this->fake_deepl( 200, [ 'translations' => [ [ 'text' => 'x' ] ] ] );

		// Act.
		$response = $this->call(
			'editor',
			[
				'texts'  => [ 'Hallo' ],
				'target' => 'fr',
			]
		);

		// Assert.
		$this->assertSame( 400, $response->get_status() );
		$this->assertNull( $this->sent );
	}

	/**
	 * Requests that are refused before anything is sent.
	 *
	 * @dataProvider provide_invalid_requests
	 *
	 * @param array $params Body.
	 */
	public function test_invalid_requests_are_refused( array $params ): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'secret-key:fx' );
		$this->fake_deepl( 200, [ 'translations' => [] ] );

		// Act.
		$response = $this->call( 'editor', $params );

		// Assert.
		$this->assertSame( 400, $response->get_status() );
		$this->assertNull( $this->sent );
	}

	public function provide_invalid_requests(): array {
		return [
			'default language as target' => [
				[
					'texts'  => [ 'Hallo' ],
					'target' => 'nl',
				],
			],
			'unknown language'           => [
				[
					'texts'  => [ 'Hallo' ],
					'target' => 'xx',
				],
			],
			'too many texts'             => [
				[
					'texts'  => array_fill( 0, Machine_Translation::MAX_TEXTS + 1, 'a' ),
					'target' => 'fr',
				],
			],
			'too much text'              => [
				[
					'texts'  => [ str_repeat( 'a', Machine_Translation::MAX_CHARS + 1 ) ],
					'target' => 'fr',
				],
			],
			'no texts'                   => [
				[
					'texts'  => [],
					'target' => 'fr',
				],
			],
		];
	}

	public function test_refused_key_is_reported_without_details(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'wrong-key:fx' );
		$this->fake_deepl( 403, [ 'message' => 'Authorization failed for wrong-key:fx' ] );

		// Act.
		$response = $this->call(
			'editor',
			[
				'texts'  => [ 'Hallo' ],
				'target' => 'fr',
			]
		);

		// Assert.
		$this->assertSame( 502, $response->get_status() );
		$this->assertStringNotContainsString( 'wrong-key', wp_json_encode( $response->get_data() ) );
	}

	public function test_empty_key_field_keeps_the_saved_key(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'saved-key:fx' );

		// Act.
		$key = Settings_Page::sanitize_deepl_key( [ 'key' => '' ] );

		// Assert.
		$this->assertSame( 'saved-key:fx', $key );
	}

	public function test_remove_deletes_the_saved_key(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'saved-key:fx' );

		// Act.
		$key = Settings_Page::sanitize_deepl_key(
			[
				'key'    => '',
				'remove' => '1',
			]
		);

		// Assert.
		$this->assertSame( '', $key );
	}

	public function test_key_never_reaches_the_editor(): void {
		// Arrange.
		update_option( Machine_Translation::OPTION, 'secret-key:fx' );

		// Act.
		$data = wp_json_encode( Blocklingo\Editor::data() );

		// Assert.
		$this->assertStringNotContainsString( 'secret-key', $data );
		$this->assertStringContainsString( '"machineTranslation":true', $data );
	}
}
