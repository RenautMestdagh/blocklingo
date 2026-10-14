<?php
/**
 * Nothing assumes a particular default language: here French is the default while the
 * WordPress site language stays English.
 *
 * @package Blocklingo
 */

use Blocklingo\Languages;
use Blocklingo\Links;
use Blocklingo\Request;
use Blocklingo\Router;
use Blocklingo\Seo;

/**
 * The plugin with French as the default language.
 *
 * @covers \Blocklingo\Languages
 * @covers \Blocklingo\Request
 * @covers \Blocklingo\Links
 * @covers \Blocklingo\Router
 */
class DefaultLanguageTest extends Blocklingo_TestCase {

	/**
	 * Page "tarifs" (French, the default) with Dutch slug "tarieven".
	 *
	 * @var int
	 */
	private $page;

	public function set_up(): void {
		parent::set_up();
		$this->set_languages( [ 'default' => 'fr' ] );
		Request::detect();
		$this->page = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'tarifs',
				'post_title' => 'Tarifs',
			]
		);
		$this->translate(
			$this->page,
			[
				'nl' => [
					'slug'  => 'tarieven',
					'title' => 'Tarieven',
				],
			]
		);
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	public function test_unprefixed_address_is_the_default_language(): void {
		// Arrange: French is the default.

		// Act.
		$this->visit( '/tarifs/' );

		// Assert.
		$this->assertSame( 'fr', Request::lang() );
		$this->assertSame( 'fr_FR', get_locale() );
		$this->assertTrue( is_page( $this->page ) );
	}

	public function test_former_default_language_is_now_a_translation(): void {
		// Arrange: Dutch is no longer the default.

		// Act.
		$this->visit( '/nl/tarieven/' );

		// Assert.
		$this->assertSame( 'nl', Request::lang() );
		$this->assertTrue( is_page( $this->page ) );
		$this->assertSame( 'Tarieven', get_the_title( $this->page ) );
	}

	public function test_translations_stored_for_the_default_language_are_ignored(): void {
		// Arrange: a French "translation" can't exist for French content.
		$this->translate( $this->page, [ 'fr' => [ 'slug' => 'prix' ] ] );

		// Act.
		$url = get_permalink( $this->page );

		// Assert.
		$this->assertSame( $this->home() . '/tarifs/', $url );
	}

	public function test_x_default_points_to_the_default_language(): void {
		// Arrange.
		$this->visit( '/nl/tarieven/' );

		// Act.
		$url = Seo::current_url_in( Languages::default_code() );

		// Assert.
		$this->assertSame( $this->home() . '/tarifs/', $url );
	}

	public function test_block_translation_in_the_former_default_language(): void {
		// Arrange.
		$this->visit( '/nl/' );
		$content = '<!-- wp:paragraph ' . wp_json_encode( [ 'blocklingo' => [ 'nl' => [ 'content' => [ 'Bonjour', 'Hallo' ] ] ] ] ) . ' --><p>Bonjour</p><!-- /wp:paragraph -->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Hallo</p>', $html );
	}

	public function test_prefixed_default_language_redirects_bare_addresses_to_it(): void {
		// Arrange.
		$this->set_languages(
			[
				'default'        => 'fr',
				'prefix_default' => true,
			]
		);
		$this->visit( '/tarifs/' );

		// Act.
		$target = Router::language_address();

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $target );
	}

	public function test_prefixed_default_language_home(): void {
		// Arrange.
		$this->set_languages(
			[
				'default'        => 'fr',
				'prefix_default' => true,
			]
		);

		// Act.
		$home = Links::home( 'fr' );

		// Assert.
		$this->assertSame( $this->home() . '/fr/', $home );
	}
}
