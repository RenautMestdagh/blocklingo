<?php
/**
 * Base class for Blocklingo tests: languages set up and "visiting" a URL.
 *
 * @package Blocklingo
 */

use Blocklingo\Languages;
use Blocklingo\Links;
use Blocklingo\Posts;
use Blocklingo\Request;

/**
 * Three languages (nl default, fr, en), pretty permalinks.
 */
abstract class Blocklingo_TestCase extends WP_UnitTestCase {

	/**
	 * Sets up languages and permalinks for every test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		// Category and tag addresses need the taxonomies registered under that structure.
		create_initial_taxonomies();
		flush_rewrite_rules( false );
		$this->set_languages();
		Request::detect();
		// The test suite resets post types between tests, and with them their meta fields.
		Posts::register_meta();
	}

	/**
	 * Leaves the request as the next test expects it.
	 */
	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = '/';
		unset( $_GET['lang'] );
		Languages::flush();
		Links::reset();
		Request::detect();
		parent::tear_down();
	}

	/**
	 * Saves the language settings.
	 *
	 * @param array $overrides Settings to change.
	 */
	protected function set_languages( array $overrides = [] ): void {
		Languages::save(
			array_merge(
				[
					'languages'      => [
						[
							'code'    => 'nl',
							'name'    => 'Nederlands',
							'locale'  => 'nl_NL',
							'slug'    => 'nl',
							'enabled' => true,
						],
						[
							'code'    => 'fr',
							'name'    => 'Français',
							'locale'  => 'fr_FR',
							'slug'    => 'fr',
							'enabled' => true,
						],
						[
							'code'    => 'en',
							'name'    => 'English',
							'locale'  => 'en_GB',
							'slug'    => 'en',
							'enabled' => true,
						],
					],
					'default'        => 'nl',
					'prefix_default' => false,
					'post_types'     => [ 'page', 'post' ],
				],
				$overrides
			)
		);
	}

	/**
	 * Requests a URL path the way a visitor would: language detection, then WordPress's
	 * query parsing.
	 *
	 * @param string $path Path below home, e.g. '/fr/tarifs/'.
	 */
	protected function visit( string $path ): void {
		$_SERVER['REQUEST_URI'] = $path;
		Request::detect();
		$this->go_to( untrailingslashit( get_option( 'home' ) ) . $path );
	}

	/**
	 * Gives a post translations.
	 *
	 * @param int   $post_id      Post ID.
	 * @param array $translations Language code → [ title, slug, excerpt ].
	 */
	protected function translate( int $post_id, array $translations ): void {
		update_post_meta( $post_id, 'blocklingo', $translations );
	}
}
