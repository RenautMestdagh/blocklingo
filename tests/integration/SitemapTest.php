<?php
/**
 * The other languages' addresses in the sitemap.
 *
 * @package Blocklingo
 */

use Blocklingo\Languages;
use Blocklingo\Sitemap;
use Blocklingo\Sitemap_Provider;

/**
 * Tests for the Sitemap and Sitemap_Provider classes.
 *
 * @covers \Blocklingo\Sitemap
 * @covers \Blocklingo\Sitemap_Provider
 */
class SitemapTest extends Blocklingo_TestCase {

	/**
	 * Page "tarieven" with French slug "tarifs".
	 *
	 * @var int
	 */
	private $page;

	public function set_up(): void {
		parent::set_up();
		require_once BLOCKLINGO_DIR . '/includes/class-sitemap-provider.php';
		$this->page = self::factory()->post->create(
			[
				'post_type' => 'page',
				'post_name' => 'tarieven',
			]
		);
		$this->translate( $this->page, [ 'fr' => [ 'slug' => 'tarifs' ] ] );
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	public function test_one_sitemap_per_other_language(): void {
		// Arrange.
		$provider = new Sitemap_Provider();

		// Act.
		$subtypes = array_keys( $provider->get_object_subtypes() );

		// Assert.
		$this->assertSame( [ 'fr', 'en' ], $subtypes );
	}

	public function test_language_sitemap_lists_its_home_and_translated_addresses(): void {
		// Arrange.
		$provider = new Sitemap_Provider();

		// Act.
		$locations = array_column( $provider->get_url_list( 1, 'fr' ), 'loc' );

		// Assert.
		$this->assertContains( $this->home() . '/fr/', $locations );
		$this->assertContains( $this->home() . '/fr/tarifs/', $locations );
	}

	public function test_front_page_is_listed_once_as_the_language_home(): void {
		// Arrange.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page );
		$provider = new Sitemap_Provider();

		// Act.
		$locations = array_column( $provider->get_url_list( 1, 'fr' ), 'loc' );

		// Assert.
		$this->assertSame( [ $this->home() . '/fr/' ], array_values( array_unique( $locations ) ) );
	}

	public function test_default_language_has_no_extra_sitemap(): void {
		// Arrange.
		$provider = new Sitemap_Provider();

		// Act.
		$urls = $provider->get_url_list( 1, Languages::default_code() );

		// Assert.
		$this->assertSame( [], $urls );
	}

	public function test_drafts_are_not_listed(): void {
		// Arrange.
		$draft = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_name'   => 'concept',
			]
		);
		$this->translate( $draft, [ 'fr' => [ 'slug' => 'brouillon' ] ] );
		$provider = new Sitemap_Provider();

		// Act.
		$locations = implode( ' ', array_column( $provider->get_url_list( 1, 'fr' ), 'loc' ) );

		// Assert.
		$this->assertStringNotContainsString( 'brouillon', $locations );
	}

	public function test_provider_is_hooked_into_the_sitemaps(): void {
		// Arrange: the plugin as loaded.

		// Act.
		$priority = has_action( 'wp_sitemaps_init', [ Sitemap::class, 'register' ] );

		// Assert.
		$this->assertNotFalse( $priority );
	}

	public function test_provider_is_added_to_the_sitemaps(): void {
		// Arrange.
		$sitemaps = new WP_Sitemaps();
		Sitemap::register( $sitemaps );

		// Act.
		$provider = $sitemaps->registry->get_provider( Sitemap_Provider::NAME );

		// Assert.
		$this->assertInstanceOf( Sitemap_Provider::class, $provider );
	}
}
