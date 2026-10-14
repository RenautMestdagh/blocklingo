<?php
/**
 * Contact Form 7 forms in every language, without Contact Form 7 installed: its form
 * object and post type are stood in for.
 *
 * @package Blocklingo
 */

use Blocklingo\Contact_Form_7;
use Blocklingo\Request;
use Blocklingo\Strings;

require_once dirname( __DIR__ ) . '/fixtures/class-blocklingo-test-contact-form.php';

/**
 * Tests for the Contact_Form_7 class.
 *
 * @covers \Blocklingo\Contact_Form_7
 * @covers \Blocklingo\Request
 */
class ContactForm7Test extends Blocklingo_TestCase {

	/**
	 * A stored form.
	 *
	 * @var int
	 */
	private $form;

	public function set_up(): void {
		parent::set_up();
		delete_option( Strings::OPTION );
		Strings::flush();
		register_post_type( 'wpcf7_contact_form' );
		$this->form = self::factory()->post->create(
			[
				'post_type'  => 'wpcf7_contact_form',
				'post_title' => 'Contact',
			]
		);
		update_post_meta( $this->form, '_form', '<label>Naam [text* your-name]</label>' );
		update_post_meta( $this->form, '_messages', [ 'mail_sent_ok' => 'Bedankt, je bericht is verzonden.' ] );
		update_post_meta(
			$this->form,
			'_mail_2',
			[
				'active'  => true,
				'subject' => 'Je bericht',
				'body'    => "Dag [your-name],\nbedankt.",
			]
		);
	}

	public function tear_down(): void {
		unset( $_POST['_blocklingo_lang'] );
		unregister_post_type( 'wpcf7_contact_form' );
		parent::tear_down();
	}

	/**
	 * Stores a translation of a form text.
	 *
	 * @param string $original    Original.
	 * @param string $translation French translation.
	 */
	private function translate_text( string $original, string $translation ): void {
		$all = Strings::all();
		$all['fr'][ Contact_Form_7::group( $this->form ) ][ Strings::key( $original ) ] = [ $original, $translation ];
		update_option( Strings::OPTION, Strings::sanitize( $all ) );
		Strings::flush();
	}

	private function properties(): array {
		return [
			'form'     => '<label>Naam [text* your-name]</label>',
			'messages' => [ 'mail_sent_ok' => 'Bedankt, je bericht is verzonden.' ],
			'mail_2'   => [
				'active'  => true,
				'subject' => 'Je bericht',
				'body'    => "Dag [your-name],\nbedankt.",
			],
		];
	}

	public function test_form_texts_appear_on_the_strings_screen(): void {
		// Arrange: the form from set_up.

		// Act.
		$strings = Strings::registered()[ Contact_Form_7::group( $this->form ) ];

		// Assert.
		$originals = array_column( $strings, 'original' );
		$this->assertContains( '<label>Naam [text* your-name]</label>', $originals );
		$this->assertContains( 'Bedankt, je bericht is verzonden.', $originals );
		$this->assertContains( "Dag [your-name],\nbedankt.", $originals );
	}

	public function test_form_and_messages_are_translated_on_a_french_page(): void {
		// Arrange.
		$this->translate_text( '<label>Naam [text* your-name]</label>', '<label>Nom [text* your-name]</label>' );
		$this->translate_text( 'Bedankt, je bericht is verzonden.', 'Merci, votre message a été envoyé.' );
		$this->visit( '/fr/' );

		// Act.
		$properties = Contact_Form_7::translate_properties( $this->properties(), new Blocklingo_Test_Contact_Form( $this->form ) );

		// Assert.
		$this->assertSame( '<label>Nom [text* your-name]</label>', $properties['form'] );
		$this->assertSame( 'Merci, votre message a été envoyé.', $properties['messages']['mail_sent_ok'] );
		$this->assertSame( 'Je bericht', $properties['mail_2']['subject'] );
	}

	public function test_default_language_form_is_unchanged(): void {
		// Arrange.
		$this->translate_text( '<label>Naam [text* your-name]</label>', '<label>Nom [text* your-name]</label>' );
		$this->visit( '/' );

		// Act.
		$properties = Contact_Form_7::translate_properties( $this->properties(), new Blocklingo_Test_Contact_Form( $this->form ) );

		// Assert.
		$this->assertSame( $this->properties(), $properties );
	}

	public function test_form_carries_the_page_language(): void {
		// Arrange.
		$this->visit( '/fr/' );

		// Act.
		$fields = Contact_Form_7::add_language_field( [ '_wpcf7' => '12' ] );

		// Assert.
		$this->assertSame( 'fr', $fields['_blocklingo_lang'] );
	}

	public function test_sending_a_form_answers_in_the_page_language(): void {
		// Arrange: Contact Form 7's REST submission, from a French page.
		$_SERVER['REQUEST_URI']    = '/wp-json/contact-form-7/v1/contact-forms/' . $this->form . '/feedback';
		$_POST['_blocklingo_lang'] = 'fr';
		Request::detect();

		// Act.
		Contact_Form_7::detect_submission_language();

		// Assert.
		$this->assertSame( 'fr', Request::lang() );
	}

	public function test_submission_language_must_be_an_enabled_language(): void {
		// Arrange.
		$_SERVER['REQUEST_URI']    = '/wp-json/contact-form-7/v1/contact-forms/' . $this->form . '/feedback';
		$_POST['_blocklingo_lang'] = '../de';
		Request::detect();

		// Act.
		Contact_Form_7::detect_submission_language();

		// Assert.
		$this->assertSame( 'nl', Request::lang() );
	}

	public function test_language_field_is_ignored_on_other_requests(): void {
		// Arrange.
		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/posts';
		$_POST['_blocklingo_lang'] = 'fr';
		Request::detect();

		// Act.
		Contact_Form_7::detect_submission_language();

		// Assert.
		$this->assertSame( 'nl', Request::lang() );
	}
}
