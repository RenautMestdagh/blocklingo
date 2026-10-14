<?php
/**
 * Texts outside blocks: site title, tagline, theme and plugin strings, WPML's hooks.
 *
 * @package Blocklingo
 */

use Blocklingo\Strings;
use Blocklingo\Strings_Page;
use Blocklingo\Wpml_Compat;

/**
 * Tests for the Strings, Strings_Page and Wpml_Compat classes.
 *
 * @covers \Blocklingo\Strings
 * @covers \Blocklingo\Strings_Page
 * @covers \Blocklingo\Wpml_Compat
 */
class StringsTest extends Blocklingo_TestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( Strings::OPTION );
		Strings::flush();
		Wpml_Compat::register();
	}

	/**
	 * Stores a translation directly.
	 *
	 * @param string $code        Language.
	 * @param string $group       Group.
	 * @param string $original    Original.
	 * @param string $translation Translation.
	 */
	private function store( string $code, string $group, string $original, string $translation ): void {
		$all = Strings::all();
		$all[ $code ][ $group ][ Strings::key( $original ) ] = [ $original, $translation ];
		update_option( Strings::OPTION, Strings::sanitize( $all ) );
		Strings::flush();
	}

	public function test_translation_in_the_requested_language(): void {
		// Arrange.
		$this->store( 'fr', 'Thema', 'Maak een afspraak', 'Prendre rendez-vous' );

		// Act.
		$french  = Strings::translate( 'Maak een afspraak', 'Thema', 'fr' );
		$english = Strings::translate( 'Maak een afspraak', 'Thema', 'en' );
		$default = Strings::translate( 'Maak een afspraak', 'Thema', 'nl' );

		// Assert.
		$this->assertSame( 'Prendre rendez-vous', $french );
		$this->assertSame( 'Maak een afspraak', $english );
		$this->assertSame( 'Maak een afspraak', $default );
	}

	public function test_translation_follows_the_page_language(): void {
		// Arrange.
		$this->store( 'fr', 'Thema', 'Maak een afspraak', 'Prendre rendez-vous' );
		$this->visit( '/fr/' );

		// Act.
		$text = blocklingo_translate( 'Maak een afspraak', 'Thema' );

		// Assert.
		$this->assertSame( 'Prendre rendez-vous', $text );
	}

	public function test_same_text_in_another_group_is_separate(): void {
		// Arrange.
		$this->store( 'fr', 'Thema', 'Contact', 'Contactez-moi' );

		// Act.
		$text = Strings::translate( 'Contact', 'Plugin', 'fr' );

		// Assert.
		$this->assertSame( 'Contact', $text );
	}

	public function test_site_title_is_translated_on_the_site(): void {
		// Arrange.
		update_option( 'blogname', 'Logopedie Margot' );
		$this->store( 'fr', Strings::SITE_GROUP, 'Logopedie Margot', 'Logopédie Margot' );
		$this->visit( '/fr/' );

		// Act.
		$title = get_bloginfo( 'name' );

		// Assert.
		$this->assertSame( 'Logopédie Margot', $title );
	}

	public function test_site_title_is_never_translated_in_the_admin(): void {
		// Arrange: saving Settings → General must keep the original.
		update_option( 'blogname', 'Logopedie Margot' );
		$this->store( 'fr', Strings::SITE_GROUP, 'Logopedie Margot', 'Logopédie Margot' );
		$this->visit( '/fr/' );
		set_current_screen( 'options-general' );

		// Act.
		$title = get_option( 'blogname' );

		// Assert.
		$this->assertSame( 'Logopedie Margot', $title );
	}

	public function test_registered_strings_include_the_site_title_and_registered_texts(): void {
		// Arrange.
		update_option( 'blogname', 'Mijn site' );
		blocklingo_register_string( 'Maak een afspraak', 'Thema', 'Knop in de header' );

		// Act.
		$strings = Strings::registered();

		// Assert.
		$this->assertSame( 'Mijn site', $strings[ Strings::SITE_GROUP ][ Strings::key( 'Mijn site' ) ]['original'] );
		$this->assertSame( 'Knop in de header', $strings['Thema'][ Strings::key( 'Maak een afspraak' ) ]['label'] );
	}

	public function test_sanitize_drops_bad_entries(): void {
		// Arrange.
		$input = [
			'nl' => [ 'g' => [ Strings::key( 'a' ) => [ 'a', 'origineel' ] ] ],
			'de' => [ 'g' => [ Strings::key( 'a' ) => [ 'a', 'unbekannt' ] ] ],
			'fr' => [
				'g' => [
					Strings::key( 'a' ) => [ 'a', 'b<script>alert(1)</script>' ],
					'wrong-key'         => [ 'c', 'd' ],
					Strings::key( 'e' ) => [ 'e', '   ' ],
					Strings::key( 'f' ) => [ 'f' ],
				],
			],
		];

		// Act.
		$clean = Strings::sanitize( $input );

		// Assert.
		$this->assertSame(
			[
				'nl' => [ 'g' => [ Strings::key( 'a' ) => [ 'a', 'origineel' ] ] ],
				'fr' => [ 'g' => [ Strings::key( 'a' ) => [ 'a', 'balert(1)' ] ] ],
			],
			$clean
		);
	}

	public function test_default_language_can_have_translations(): void {
		// Arrange: a theme text written in French, on a site whose default is Dutch.
		$this->store( 'nl', 'Thema', 'Prendre rendez-vous', 'Maak een afspraak' );
		$this->visit( '/' );

		// Act.
		$text = blocklingo_translate( 'Prendre rendez-vous', 'Thema' );

		// Assert.
		$this->assertSame( 'Maak een afspraak', $text );
	}

	public function test_saving_the_screen_keeps_texts_not_on_it(): void {
		// Arrange: a translation of a text from a theme that is switched off.
		$this->store( 'fr', 'Oud thema', 'Welkom', 'Bienvenue' );
		$submitted = [ 'fr' => [ 'Thema' => [ Strings::key( 'Contact' ) => [ 'Contact', 'Contactez-moi' ] ] ] ];

		// Act.
		$saved = Strings_Page::sanitize( $submitted );

		// Assert.
		$this->assertSame( [ 'Welkom', 'Bienvenue' ], $saved['fr']['Oud thema'][ Strings::key( 'Welkom' ) ] );
		$this->assertSame( [ 'Contact', 'Contactez-moi' ], $saved['fr']['Thema'][ Strings::key( 'Contact' ) ] );
	}

	public function test_emptying_a_field_removes_its_translation(): void {
		// Arrange.
		$this->store( 'fr', 'Thema', 'Contact', 'Contactez-moi' );
		$submitted = [ 'fr' => [ 'Thema' => [ Strings::key( 'Contact' ) => [ 'Contact', '' ] ] ] ];

		// Act.
		$saved = Strings_Page::sanitize( $submitted );

		// Assert.
		$this->assertArrayNotHasKey( 'Thema', $saved['fr'] ?? [] );
	}

	public function test_wpml_string_translation_hook(): void {
		// Arrange: a theme written for WPML.
		$this->store( 'fr', 'mytheme', 'Lees meer', 'Lire la suite' );
		$this->visit( '/fr/' );

		// Act.
		$text = apply_filters( 'wpml_translate_single_string', 'Lees meer', 'mytheme', 'read-more' );

		// Assert.
		$this->assertSame( 'Lire la suite', $text );
	}

	public function test_wpml_string_registration_hook(): void {
		// Arrange.
		do_action( 'wpml_register_single_string', 'mytheme', 'Footer text', 'Alle rechten voorbehouden' );

		// Act.
		$strings = Strings::registered();

		// Assert.
		$this->assertSame( 'Footer text', $strings['mytheme'][ Strings::key( 'Alle rechten voorbehouden' ) ]['label'] );
	}

	public function test_wpml_language_hooks(): void {
		// Arrange.
		$this->visit( '/fr/' );

		// Act.
		$current   = apply_filters( 'wpml_current_language', null );
		$default   = apply_filters( 'wpml_default_language', null );
		$languages = apply_filters( 'wpml_active_languages', null, [] );

		// Assert.
		$this->assertSame( 'fr', $current );
		$this->assertSame( 'nl', $default );
		$this->assertSame( [ 'nl', 'fr', 'en' ], array_keys( $languages ) );
		$this->assertSame( 1, $languages['fr']['active'] );
	}
}
