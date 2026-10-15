<?php
/**
 * The hreflang links, <html lang> and the language switcher.
 *
 * @package Blocklingo
 */

use Blocklingo\Seo;
use Blocklingo\Switcher_Block;

/**
 * Tests for the Seo and Switcher_Block classes.
 *
 * @covers \Blocklingo\Seo
 * @covers \Blocklingo\Switcher_Block
 */
class SeoTest extends Blocklingo_TestCase {

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
				'post_type' => 'page',
				'post_name' => 'tarieven',
			]
		);
		$this->translate( $this->page, [ 'fr' => [ 'slug' => 'tarifs' ] ] );
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	private function alternates(): string {
		ob_start();
		Seo::print_alternates();
		return (string) ob_get_clean();
	}

	public function test_alternates_for_every_language_and_x_default(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = $this->alternates();

		// Assert.
		$this->assertStringContainsString( 'hreflang="nl-NL" href="' . $this->home() . '/tarieven/"', $html );
		$this->assertStringContainsString( 'hreflang="fr-FR" href="' . $this->home() . '/fr/tarifs/"', $html );
		$this->assertStringContainsString( 'hreflang="en-GB" href="' . $this->home() . '/en/tarieven/"', $html );
		$this->assertStringContainsString( 'hreflang="x-default" href="' . $this->home() . '/tarieven/"', $html );
	}

	public function test_no_alternates_with_one_language(): void {
		// Arrange.
		$this->set_languages(
			[
				'languages' => [
					[
						'code'    => 'nl',
						'enabled' => true,
					],
				],
			]
		);
		$this->visit( '/tarieven/' );

		// Act.
		$html = $this->alternates();

		// Assert.
		$this->assertSame( '', $html );
	}

	public function test_alternates_can_be_turned_off(): void {
		// Arrange.
		add_filter( 'blocklingo_print_hreflang', '__return_false' );
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = $this->alternates();

		// Assert.
		$this->assertSame( '', $html );
	}

	public function test_locale_follows_the_language(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$locale = get_locale();

		// Assert.
		$this->assertSame( 'fr_FR', $locale );
	}

	public function test_switcher_links_to_the_same_page_in_each_language(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = Switcher_Block::render( [ 'showCurrent' => true ] );

		// Assert.
		$this->assertStringContainsString( 'href="' . $this->home() . '/tarieven/" hreflang="nl-NL"', $html );
		$this->assertStringContainsString( 'href="' . $this->home() . '/en/tarieven/" hreflang="en-GB"', $html );
		$this->assertMatchesRegularExpression( '#is-current"><a href="' . preg_quote( $this->home(), '#' ) . '/fr/tarifs/"[^>]*aria-current="true"#', $html );
	}

	public function test_switcher_can_hide_the_current_language(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = Switcher_Block::render(
			[
				'showCurrent' => false,
				'label'       => 'code',
			]
		);

		// Assert.
		$this->assertStringNotContainsString( '/fr/tarifs/', $html );
		$this->assertStringContainsString( '>NL</a>', $html );
	}

	public function test_switcher_list_can_be_vertical(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = Switcher_Block::render( [ 'orientation' => 'vertical' ] );

		// Assert.
		$this->assertStringContainsString( '<ul class="wp-block-blocklingo-language-switcher__list is-vertical">', $html );
		$this->assertStringNotContainsString( '__dropdown', $html );
	}

	public function test_switcher_dropdown_shows_the_current_language_and_opens_the_links(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = Switcher_Block::render(
			[
				'display'     => 'dropdown',
				'showCurrent' => false,
			]
		);

		// Assert.
		$this->assertStringContainsString( '<div class="wp-block-blocklingo-language-switcher__dropdown"><button type="button" class="wp-block-blocklingo-language-switcher__toggle" lang="fr-FR">Français</button><ul', $html );
		$this->assertStringContainsString( 'href="' . $this->home() . '/en/tarieven/"', $html );
		$this->assertStringNotContainsString( '/fr/tarifs/', $html );
	}

	public function test_switcher_dropdown_with_codes_names_the_current_language(): void {
		// Arrange.
		$this->visit( '/fr/tarifs/' );

		// Act.
		$html = Switcher_Block::render(
			[
				'display' => 'dropdown',
				'label'   => 'code',
			]
		);

		// Assert.
		$this->assertStringContainsString( 'lang="fr-FR" aria-label="FR, Français">FR</button>', $html );
	}
}
