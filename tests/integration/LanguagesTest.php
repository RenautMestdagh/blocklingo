<?php
/**
 * Settings and language lookups.
 *
 * @package Blocklingo
 */

use Blocklingo\Languages;

/**
 * Tests for the Languages class.
 *
 * @covers \Blocklingo\Languages
 */
class LanguagesTest extends Blocklingo_TestCase {

	public function test_enabled_languages_start_with_the_default(): void {
		// Arrange.
		$this->set_languages( [ 'default' => 'fr' ] );

		// Act.
		$codes = array_keys( Languages::enabled() );

		// Assert.
		$this->assertSame( [ 'fr', 'nl', 'en' ], $codes );
	}

	public function test_without_settings_the_site_language_is_the_only_one(): void {
		// Arrange.
		delete_option( Languages::OPTION );
		Languages::flush();

		// Act.
		$enabled = Languages::enabled();

		// Assert.
		$this->assertCount( 1, $enabled );
		$this->assertFalse( Languages::is_multilingual() );
	}

	public function test_default_language_cannot_be_disabled(): void {
		// Arrange.
		$this->set_languages(
			[
				'languages' => [
					[
						'code'    => 'nl',
						'enabled' => false,
					],
					[
						'code'    => 'fr',
						'enabled' => true,
					],
				],
				'default'   => 'nl',
			]
		);

		// Act.
		$enabled = Languages::is_enabled( 'nl' );

		// Assert.
		$this->assertTrue( $enabled );
	}

	public function test_unknown_default_falls_back_to_the_first_language(): void {
		// Arrange.
		$this->set_languages( [ 'default' => 'de' ] );

		// Act.
		$default = Languages::default_code();

		// Assert.
		$this->assertSame( 'nl', $default );
	}

	public function test_disabled_language_is_not_found_by_slug(): void {
		// Arrange.
		$languages                  = Languages::settings()['languages'];
		$languages['en']['enabled'] = false;
		$this->set_languages( [ 'languages' => array_values( $languages ) ] );

		// Act.
		$code = Languages::code_by_slug( 'en' );

		// Assert.
		$this->assertNull( $code );
	}

	public function test_enabled_language_is_found_by_slug(): void {
		// Arrange: the default languages of the test case.

		// Act.
		$code = Languages::code_by_slug( 'fr' );

		// Assert.
		$this->assertSame( 'fr', $code );
	}

	public function test_default_language_has_no_prefix_by_default(): void {
		// Arrange: prefix_default is off in the test case.

		// Act.
		$default_prefix = Languages::prefix( 'nl' );
		$other_prefix   = Languages::prefix( 'fr' );

		// Assert.
		$this->assertSame( '', $default_prefix );
		$this->assertSame( 'fr', $other_prefix );
	}

	public function test_default_language_gets_a_prefix_when_asked(): void {
		// Arrange.
		$this->set_languages( [ 'prefix_default' => true ] );

		// Act.
		$prefix = Languages::prefix( 'nl' );

		// Assert.
		$this->assertSame( 'nl', $prefix );
	}

	/**
	 * Language codes are cleaned to a safe form.
	 *
	 * @dataProvider provide_codes
	 *
	 * @param string $raw      Entered code.
	 * @param string $expected Cleaned code.
	 */
	public function test_sanitize_code( string $raw, string $expected ): void {
		// Arrange: $raw from the data provider.

		// Act.
		$code = Languages::sanitize_code( $raw );

		// Assert.
		$this->assertSame( $expected, $code );
	}

	public function provide_codes(): array {
		return [
			'plain'          => [ 'fr', 'fr' ],
			'uppercase'      => [ 'FR', 'fr' ],
			'region'         => [ 'pt_BR', 'pt-br' ],
			'script'         => [ 'zh-Hant', 'zh-hant' ],
			'three letters'  => [ 'fil', 'fil' ],
			'too long'       => [ 'french', '' ],
			'path injection' => [ 'fr/../x', '' ],
			'empty'          => [ '', '' ],
		];
	}

	public function test_html_lang_uses_the_locale(): void {
		// Arrange: en has locale en_GB in the test case.

		// Act.
		$lang = Languages::html_lang( 'en' );

		// Assert.
		$this->assertSame( 'en-GB', $lang );
	}
}
