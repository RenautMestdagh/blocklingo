<?php
/**
 * Requests in a language end up at the right post.
 *
 * @package Blocklingo
 */

use Blocklingo\Request;
use Blocklingo\Router;

/**
 * Tests for the Router and Request classes.
 *
 * @covers \Blocklingo\Router
 * @covers \Blocklingo\Request
 */
class RouterTest extends Blocklingo_TestCase {

	/**
	 * Page "tarieven" with French slug "tarifs" and title "Tarifs".
	 *
	 * @var int
	 */
	private $page;

	public function set_up(): void {
		parent::set_up();
		$this->page = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'tarieven',
				'post_title' => 'Tarieven',
			]
		);
		$this->translate(
			$this->page,
			[
				'fr' => [
					'slug'  => 'tarifs',
					'title' => 'Tarifs',
				],
			]
		);
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	public function test_prefix_sets_the_language(): void {
		// Arrange: the page from set_up.

		// Act.
		$this->visit( '/fr/tarifs/' );

		// Assert.
		$this->assertSame( 'fr', Request::lang() );
		$this->assertTrue( Request::is_prefixed() );
	}

	public function test_translated_slug_finds_the_page(): void {
		// Arrange: the page from set_up, French slug "tarifs".

		// Act.
		$this->visit( '/fr/tarifs/' );

		// Assert.
		$this->assertTrue( is_page( $this->page ) );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_original_slug_resolves_in_a_language_without_translation(): void {
		// Arrange: the page has no English slug.

		// Act.
		$this->visit( '/en/tarieven/' );

		// Assert.
		$this->assertTrue( is_page( $this->page ) );
	}

	public function test_translated_slug_does_not_work_in_another_language(): void {
		// Arrange: "tarifs" is the French slug only.

		// Act.
		$this->visit( '/en/tarifs/' );

		// Assert.
		$this->assertTrue( is_404() );
	}

	public function test_unprefixed_url_is_the_default_language(): void {
		// Arrange: the page from set_up.

		// Act.
		$this->visit( '/tarieven/' );

		// Assert.
		$this->assertSame( 'nl', Request::lang() );
		$this->assertTrue( is_page( $this->page ) );
	}

	public function test_language_home_is_the_front_page(): void {
		// Arrange: the site's home page.

		// Act.
		$this->visit( '/fr/' );

		// Assert.
		$this->assertTrue( is_front_page() );
		$this->assertSame( 'fr', Request::lang() );
	}

	public function test_request_uri_is_restored_after_parsing(): void {
		// Arrange: the page from set_up.

		// Act.
		$this->visit( '/fr/tarifs/' );

		// Assert.
		$this->assertSame( '/fr/tarifs/', wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	public function test_translated_title_is_shown(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$title = get_the_title( $this->page );

		// Assert.
		$this->assertSame( 'Tarifs', $title );
	}

	public function test_canonical_redirect_never_points_to_the_requested_url(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$target = Router::filter_canonical_redirect( $this->home() . '/fr/tarifs/', $this->home() . '/fr/tarifs/' );

		// Assert.
		$this->assertFalse( $target );
	}

	public function test_canonical_redirect_from_original_slug_to_translated_slug(): void {
		// Arrange.
		$this->visit( '/fr/tarieven/' );

		// Act.
		$target = Router::filter_canonical_redirect( $this->home() . '/fr/tarifs/', $this->home() . '/fr/tarieven/' );

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $target );
	}

	public function test_canonical_redirect_adds_the_language_wordpress_left_out(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$target = Router::filter_canonical_redirect( $this->home() . '/contact/', $this->home() . '/fr/contact' );

		// Assert.
		$this->assertSame( $this->home() . '/fr/contact/', $target );
	}

	public function test_canonical_trailing_slash_redirect_still_happens(): void {
		// Arrange.
		$this->visit( '/fr/tarifs' );

		// Act.
		$target = Router::filter_canonical_redirect( $this->home() . '/fr/tarifs/', $this->home() . '/fr/tarifs' );

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $target );
	}

	public function test_original_slug_in_a_translated_language_points_to_the_translated_address(): void {
		// Arrange.
		$this->visit( '/fr/tarieven/' );

		// Act.
		$target = Router::translated_address();

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $target );
	}

	public function test_translated_address_is_not_redirected(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$target = Router::translated_address();

		// Assert.
		$this->assertSame( '', $target );
	}

	public function test_translated_address_keeps_the_query_string(): void {
		// Arrange.
		$this->visit( '/fr/tarieven/?utm_source=x' );

		// Act.
		$target = Router::translated_address();

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/?utm_source=x', $target );
	}

	public function test_plain_permalinks_read_the_query_parameter(): void {
		// Arrange.
		$this->set_permalink_structure( '' );
		$_GET['lang'] = 'en';

		// Act.
		$this->visit( '/?page_id=' . $this->page . '&lang=en' );

		// Assert.
		$this->assertSame( 'en', Request::lang() );
	}
}
