<?php
/**
 * Stand-in for Contact Form 7's form object, so tests don't need the plugin.
 *
 * @package Blocklingo
 */

/**
 * The parts of WPCF7_ContactForm Blocklingo uses.
 */
class Blocklingo_Test_Contact_Form {

	/**
	 * Form ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Sets the ID.
	 *
	 * @param int $id Form ID.
	 */
	public function __construct( int $id ) {
		$this->id = $id;
	}

	/**
	 * Form ID.
	 */
	public function id(): int {
		return $this->id;
	}
}
