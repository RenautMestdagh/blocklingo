<?php
/**
 * URLs in a language: permalinks with translated slugs, home, links in content.
 *
 * @package Blocklingo
 */

use Blocklingo\Links;

/**
 * Tests for the Links class.
 *
 * @covers \Blocklingo\Links
 */
class LinksTest extends Blocklingo_TestCase {

	/**
	 * Page "tarieven" with French slug "tarifs".
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
		$this->translate( $this->page, [ 'fr' => [ 'slug' => 'tarifs' ] ] );
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	public function test_default_language_permalink_is_unchanged(): void {
		// Arrange: the page from set_up.

		// Act.
		$url = get_permalink( $this->page );

		// Assert.
		$this->assertSame( $this->home() . '/tarieven/', $url );
	}

	public function test_permalink_in_another_language_uses_prefix_and_translated_slug(): void {
		// Arrange: the page from set_up, with French slug "tarifs".

		// Act.
		$url = Links::in_language( 'fr', fn () => get_permalink( $this->page ) );

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $url );
	}

	public function test_untranslated_slug_falls_back_to_the_original(): void {
		// Arrange: the page has no English slug.

		// Act.
		$url = Links::in_language( 'en', fn () => get_permalink( $this->page ) );

		// Assert.
		$this->assertSame( $this->home() . '/en/tarieven/', $url );
	}

	public function test_child_page_uses_translated_slugs_of_its_parents(): void {
		// Arrange.
		$child = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_name'   => 'terugbetaling',
				'post_parent' => $this->page,
			]
		);
		$this->translate( $child, [ 'fr' => [ 'slug' => 'remboursement' ] ] );

		// Act.
		$url = Links::in_language( 'fr', fn () => get_permalink( $child ) );

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/remboursement/', $url );
	}

	public function test_post_permalink_uses_translated_slug(): void {
		// Arrange.
		$post = self::factory()->post->create( [ 'post_name' => 'nieuws' ] );
		$this->translate( $post, [ 'en' => [ 'slug' => 'news' ] ] );

		// Act.
		$url = Links::in_language( 'en', fn () => get_permalink( $post ) );

		// Assert.
		$this->assertSame( $this->home() . '/en/news/', $url );
	}

	public function test_front_page_is_the_language_home(): void {
		// Arrange.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page );

		// Act.
		$french  = Links::in_language( 'fr', fn () => get_permalink( $this->page ) );
		$default = get_permalink( $this->page );

		// Assert.
		$this->assertSame( $this->home() . '/fr/', $french );
		$this->assertSame( $this->home() . '/', $default );
	}

	public function test_prefixed_default_language(): void {
		// Arrange.
		$this->set_languages( [ 'prefix_default' => true ] );

		// Act.
		$url  = get_permalink( $this->page );
		$home = Links::home( 'nl' );

		// Assert.
		$this->assertSame( $this->home() . '/nl/tarieven/', $url );
		$this->assertSame( $this->home() . '/nl/', $home );
	}

	public function test_plain_permalinks_use_a_query_parameter(): void {
		// Arrange.
		$this->set_permalink_structure( '' );

		// Act.
		$url = Links::in_language( 'fr', fn () => get_permalink( $this->page ) );

		// Assert.
		$this->assertStringContainsString( 'lang=fr', $url );
	}

	/**
	 * Links to this site point to the same language; other links stay as they are.
	 *
	 * @dataProvider provide_content_links
	 *
	 * @param string $href     Link as written.
	 * @param string $expected Link on a French page.
	 */
	public function test_localize_url( string $href, string $expected ): void {
		// Arrange.
		$href     = str_replace( '{home}', $this->home(), $href );
		$expected = str_replace( '{home}', $this->home(), $expected );

		// Act.
		$url = Links::localize_url( $href, 'fr' );

		// Assert.
		$this->assertSame( $expected, $url );
	}

	public function provide_content_links(): array {
		return [
			'relative page'     => [ '/tarieven/', '/fr/tarifs/' ],
			'absolute page'     => [ '{home}/tarieven/', '{home}/fr/tarifs/' ],
			'fragment kept'     => [ '/tarieven/#prijzen', '/fr/tarifs/#prijzen' ],
			'query kept'        => [ '/tarieven/?a=1', '/fr/tarifs/?a=1' ],
			'home'              => [ '/', '/fr/' ],
			'unknown path'      => [ '/elders/', '/fr/elders/' ],
			'already prefixed'  => [ '/fr/tarifs/', '/fr/tarifs/' ],
			'uploaded file'     => [ '/wp-content/uploads/a.pdf', '/wp-content/uploads/a.pdf' ],
			'file extension'    => [ '/sitemap.xml', '/sitemap.xml' ],
			'admin'             => [ '/wp-admin/', '/wp-admin/' ],
			'other site'        => [ 'https://example.com/tarieven/', 'https://example.com/tarieven/' ],
			'protocol relative' => [ '//example.com/x/', '//example.com/x/' ],
			'anchor only'       => [ '#top', '#top' ],
			'mailto'            => [ 'mailto:a@b.c', 'mailto:a@b.c' ],
		];
	}

	public function test_category_link_follows_the_page_language(): void {
		// Arrange.
		$category = self::factory()->category->create( [ 'slug' => 'nieuws' ] );
		$this->visit( '/fr/' );

		// Act.
		$url = get_category_link( $category );

		// Assert.
		$this->assertSame( $this->home() . '/fr/category/nieuws/', $url );
	}

	public function test_date_archive_link_follows_the_page_language(): void {
		// Arrange.
		$this->visit( '/fr/' );

		// Act.
		$url = get_month_link( 2026, 10 );

		// Assert.
		$this->assertSame( $this->home() . '/fr/2026/10/', $url );
	}

	public function test_archive_links_in_the_default_language_are_unchanged(): void {
		// Arrange.
		$category = self::factory()->category->create( [ 'slug' => 'nieuws' ] );
		$this->visit( '/' );

		// Act.
		$url = get_category_link( $category );

		// Assert.
		$this->assertSame( $this->home() . '/category/nieuws/', $url );
	}

	public function test_category_archive_in_another_language_resolves(): void {
		// Arrange.
		$category = self::factory()->category->create( [ 'slug' => 'nieuws' ] );
		self::factory()->post->create( [ 'post_category' => [ $category ] ] );

		// Act.
		$this->visit( '/fr/category/nieuws/' );

		// Assert.
		$this->assertTrue( is_category( $category ) );
	}

	public function test_links_in_content_follow_the_page_language(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$html = '<p><a href="/tarieven/">Tarifs</a> <a href="https://example.com/">x</a></p>';

		// Act.
		$localized = Links::localize_content_links( $html );

		// Assert.
		$this->assertSame( '<p><a href="/fr/tarifs/">Tarifs</a> <a href="https://example.com/">x</a></p>', $localized );
	}

	public function test_links_in_default_language_content_are_left_alone(): void {
		// Arrange.
		$this->visit( '/' );
		$html = '<p><a href="/tarieven/">Tarieven</a></p>';

		// Act.
		$localized = Links::localize_content_links( $html );

		// Assert.
		$this->assertSame( $html, $localized );
	}
}
