<?php
/**
 * Contact Form 7: forms, messages and the auto-reply in every language.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Each form's template (labels, placeholders, button), its messages and the auto-reply
 * (Mail (2)) appear on the Strings screen, one group per form. On the site, and when a
 * form is sent (which goes through the REST API, without a language in its address), the
 * form is shown and answered in the language of the page it was on.
 */
class Contact_Form_7 {

	/**
	 * Hidden field with the page's language.
	 */
	const FIELD = '_blocklingo_lang';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		self::detect_submission_language();
		add_filter( 'blocklingo_strings', [ __CLASS__, 'register_strings' ] );
		add_filter( 'wpcf7_contact_form_properties', [ __CLASS__, 'translate_properties' ], 10, 2 );
		add_filter( 'wpcf7_form_hidden_fields', [ __CLASS__, 'add_language_field' ] );
	}

	/**
	 * Group of a form on the Strings screen.
	 *
	 * @param int $form_id Form (post) ID.
	 */
	public static function group( int $form_id ): string {
		return 'Contact Form 7 #' . $form_id;
	}

	/**
	 * When a form is sent, the language of the page it was on (from the hidden field), so
	 * its messages come back in that language. Only for Contact Form 7's REST route.
	 */
	public static function detect_submission_language(): void {
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		$route = isset( $_GET['rest_route'] ) ? wp_unslash( $_GET['rest_route'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		if ( false === strpos( $uri . ' ' . $route, '/contact-form-7/v1/contact-forms/' ) || ! isset( $_POST[ self::FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 verifies its own submissions; this only picks a language.
			return;
		}
		$code = sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Request::use_language( $code );
	}

	/**
	 * The page's language as a hidden field in every form.
	 *
	 * @param array $fields Hidden fields.
	 * @return array
	 */
	public static function add_language_field( $fields ) {
		if ( Languages::is_multilingual() ) {
			$fields[ self::FIELD ] = Links::lang();
		}
		return $fields;
	}

	/**
	 * The translatable texts of a form, from its stored properties.
	 *
	 * @param array $properties Form properties (form, messages, mail_2).
	 * @return array<string, array{0: string, 1: bool}> Label => [ text, multiline ].
	 */
	public static function texts( array $properties ): array {
		$texts = [];
		if ( ! empty( $properties['form'] ) && is_string( $properties['form'] ) ) {
			$texts[ __( 'Form', 'blocklingo' ) ] = [ $properties['form'], true ];
		}
		foreach ( (array) ( $properties['messages'] ?? [] ) as $name => $message ) {
			if ( is_string( $message ) && '' !== $message ) {
				/* translators: %s: Contact Form 7 message name, e.g. mail_sent_ok. */
				$texts[ sprintf( __( 'Message: %s', 'blocklingo' ), $name ) ] = [ $message, false ];
			}
		}
		$mail = $properties['mail_2'] ?? [];
		if ( is_array( $mail ) && ! empty( $mail['active'] ) ) {
			$fields = [
				'subject' => false,
				'body'    => true,
			];
			foreach ( $fields as $field => $multiline ) {
				if ( ! empty( $mail[ $field ] ) && is_string( $mail[ $field ] ) ) {
					/* translators: %s: e-mail field, subject or body. */
					$texts[ sprintf( __( 'Auto-reply: %s', 'blocklingo' ), $field ) ] = [ $mail[ $field ], $multiline ];
				}
			}
		}
		return $texts;
	}

	/**
	 * Adds every form's texts to the Strings screen.
	 *
	 * @param array $strings Registered strings.
	 * @return array
	 */
	public static function register_strings( $strings ) {
		if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
			return $strings;
		}
		$forms = get_posts(
			[
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			]
		);
		foreach ( $forms as $form ) {
			$properties = [
				'form'     => get_post_meta( $form->ID, '_form', true ),
				'messages' => get_post_meta( $form->ID, '_messages', true ),
				'mail_2'   => get_post_meta( $form->ID, '_mail_2', true ),
			];
			// The group is the form's ID, so renaming a form keeps its translations.
			$group = self::group( $form->ID );
			foreach ( self::texts( $properties ) as $label => [ $text, $multiline ] ) {
				$strings[ $group ][ Strings::key( $text ) ] = [
					'original'  => $text,
					'label'     => $form->post_title . ' – ' . $label,
					'multiline' => $multiline,
				];
			}
		}
		return $strings;
	}

	/**
	 * A form in the language being shown.
	 *
	 * @param array  $properties   Form properties.
	 * @param object $contact_form The form (WPCF7_ContactForm).
	 * @return array
	 */
	public static function translate_properties( $properties, $contact_form ) {
		$code = Links::lang();
		if ( ! is_array( $properties ) || ! Languages::is_multilingual() || Languages::is_default( $code ) || ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return $properties;
		}
		$group = self::group( (int) $contact_form->id() );

		if ( isset( $properties['form'] ) && is_string( $properties['form'] ) ) {
			$properties['form'] = Strings::translate( $properties['form'], $group, $code );
		}
		foreach ( (array) ( $properties['messages'] ?? [] ) as $name => $message ) {
			if ( is_string( $message ) ) {
				$properties['messages'][ $name ] = Strings::translate( $message, $group, $code );
			}
		}
		foreach ( [ 'subject', 'body' ] as $field ) {
			if ( isset( $properties['mail_2'][ $field ] ) && is_string( $properties['mail_2'][ $field ] ) ) {
				$properties['mail_2'][ $field ] = Strings::translate( $properties['mail_2'][ $field ], $group, $code );
			}
		}
		return $properties;
	}
}
