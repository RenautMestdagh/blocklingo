<?php
/**
 * Switching the default language: translations become the content, the content becomes a
 * translation.
 *
 * @package Blocklingo
 */

use Blocklingo\Default_Language;
use Blocklingo\Languages;
use Blocklingo\Posts;
use Blocklingo\Settings_Page;

/**
 * Tests for the Default_Language class and its settings handler.
 *
 * @covers \Blocklingo\Default_Language
 * @covers \Blocklingo\Settings_Page
 */
class DefaultLanguageSwitchTest extends Blocklingo_TestCase {

	/**
	 * Page "Tarieven" (French "Tarifs") with every kind of block.
	 *
	 * @var int
	 */
	private $page;

	public function set_up(): void {
		parent::set_up();
		// Content as an administrator saves it (with unfiltered HTML).
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->page = $this->create_page( $this->content(), 'tarieven', 'Tarieven' );
		$this->translate(
			$this->page,
			[
				'fr' => [
					'title' => 'Tarifs',
					'slug'  => 'tarifs',
				],
			]
		);
	}

	public function tear_down(): void {
		delete_option( Default_Language::PROGRESS );
		parent::tear_down();
	}

	/**
	 * Creates a page. wp_insert_post() expects slashed data, so the JSON in block comments
	 * keeps its escapes.
	 *
	 * @param string $content Content.
	 * @param string $slug    Slug.
	 * @param string $title   Title.
	 * @param string $type    Post type.
	 */
	private function create_page( string $content, string $slug = '', string $title = '', string $type = 'page' ): int {
		return self::factory()->post->create(
			wp_slash(
				[
					'post_type'    => $type,
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => $content,
				]
			)
		);
	}

	/**
	 * A block comment with attributes.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes.
	 * @param string $html  Saved markup ('' for a self-closing block).
	 */
	private function block( string $name, array $attrs, string $html = '' ): string {
		$comment = '<!-- wp:' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' );
		return '' === $html ? $comment . ' /-->' : $comment . ' -->' . $html . '<!-- /wp:' . $name . ' -->';
	}

	/**
	 * Content: translated paragraph (French and English), untranslated paragraph, outdated
	 * paragraph, menu link (attribute in the comment) and image alt text.
	 */
	private function content(): string {
		return $this->block(
			'paragraph',
			[
				'blocklingo' => [
					'fr' => [ 'content' => [ 'Hallo', 'Bonjour' ] ],
					'en' => [ 'content' => [ 'Hallo', 'Hello' ] ],
				],
			],
			'<p>Hallo</p>'
		)
			. $this->block( 'paragraph', [], '<p>Alleen Nederlands</p>' )
			. $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Oud', 'Vieux' ] ] ] ], '<p>Nieuw</p>' )
			. $this->block(
				'navigation-link',
				[
					'label'      => 'Over mij',
					'blocklingo' => [ 'fr' => [ 'label' => [ 'Over mij', 'À propos' ] ] ],
				]
			)
			. $this->block( 'image', [ 'blocklingo' => [ 'fr' => [ 'alt' => [ 'Foto', 'Photo' ] ] ] ], '<figure class="wp-block-image"><img src="/a.jpg" alt="Foto"/></figure>' );
	}

	/**
	 * The page's parsed blocks after a switch.
	 *
	 * @return array[]
	 */
	private function blocks(): array {
		return parse_blocks( get_post_field( 'post_content', $this->page ) );
	}

	public function test_dry_run_reports_and_changes_nothing(): void {
		// Arrange.
		$before = get_post_field( 'post_content', $this->page );

		// Act.
		$totals = Default_Language::run( 'fr', true );

		// Assert.
		$this->assertSame( 3, $totals['swapped'] );
		$this->assertSame( 1, $totals['untranslated'] );
		$this->assertSame( 1, $totals['outdated'] );
		$this->assertSame( 2, $totals['fields'] );
		$this->assertSame( $before, get_post_field( 'post_content', $this->page ) );
		$this->assertSame( 'nl', Languages::default_code() );
	}

	public function test_switch_makes_the_language_the_default(): void {
		// Arrange: the page from set_up.

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$this->assertSame( 'fr', Languages::default_code() );
		$this->assertTrue( Languages::is_enabled( 'nl' ) );
		$this->assertNull( Default_Language::unfinished() );
	}

	public function test_translation_becomes_the_content_and_the_content_a_translation(): void {
		// Arrange: "Hallo" with French "Bonjour" and English "Hello".

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$paragraph = $this->blocks()[0];
		$this->assertSame( '<p>Bonjour</p>', $paragraph['innerHTML'] );
		$this->assertSame(
			[
				'en' => [ 'content' => [ 'Bonjour', 'Hello' ] ],
				'nl' => [ 'content' => [ 'Bonjour', 'Hallo' ] ],
			],
			$paragraph['attrs']['blocklingo']
		);
	}

	public function test_untranslated_block_keeps_its_text(): void {
		// Arrange: "Alleen Nederlands" has no translation.

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$this->assertSame( '<p>Alleen Nederlands</p>', $this->blocks()[1]['innerHTML'] );
	}

	public function test_outdated_translation_is_not_used(): void {
		// Arrange: translated when the text was "Oud"; it is "Nieuw" now.

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$paragraph = $this->blocks()[2];
		$this->assertSame( '<p>Nieuw</p>', $paragraph['innerHTML'] );
		$this->assertArrayNotHasKey( 'blocklingo', $paragraph['attrs'] );
	}

	public function test_attribute_in_the_block_comment_is_swapped(): void {
		// Arrange: a menu link "Over mij" with French "À propos".

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$link = $this->blocks()[3];
		$this->assertSame( 'À propos', $link['attrs']['label'] );
		$this->assertSame( [ 'nl' => [ 'label' => [ 'À propos', 'Over mij' ] ] ], $link['attrs']['blocklingo'] );
	}

	public function test_text_attribute_in_markup_is_swapped(): void {
		// Arrange: an image with alt "Foto", French "Photo".

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$this->assertStringContainsString( 'alt="Photo"', $this->blocks()[4]['innerHTML'] );
	}

	public function test_title_and_slug_are_swapped(): void {
		// Arrange: "Tarieven" / tarieven with French "Tarifs" / tarifs.

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$post = get_post( $this->page );
		$this->assertSame( 'Tarifs', $post->post_title );
		$this->assertSame( 'tarifs', $post->post_name );
		$this->assertSame( 'Tarieven', Posts::field( $this->page, 'nl', 'title' ) );
		$this->assertSame( 'tarieven', Posts::field( $this->page, 'nl', 'slug' ) );
		$this->assertContains( 'tarieven', get_post_meta( $this->page, '_wp_old_slug', false ) );
	}

	public function test_site_shows_the_new_default_and_the_old_one_as_a_translation(): void {
		// Arrange.
		Default_Language::run( 'fr' );

		// Act.
		$this->visit( '/tarifs/' );
		$french = do_blocks( get_post_field( 'post_content', $this->page ) );
		$this->visit( '/nl/tarieven/' );
		$dutch = do_blocks( get_post_field( 'post_content', $this->page ) );

		// Assert.
		$this->assertStringContainsString( '>Bonjour</p>', $french );
		$this->assertStringContainsString( '>Hallo</p>', $dutch );
		$this->assertTrue( is_page( $this->page ) );
	}

	public function test_template_parts_are_switched_too(): void {
		// Arrange.
		$part = $this->create_page( $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Welkom', 'Bienvenue' ] ] ] ], '<p>Welkom</p>' ), '', '', 'wp_template_part' );

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$this->assertStringContainsString( '<p>Bienvenue</p>', get_post_field( 'post_content', $part ) );
	}

	public function test_interrupted_switch_continues_after_its_checkpoint(): void {
		// Arrange: a switch that stopped after this page; a later page is still Dutch.
		$later = $this->create_page( $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Later', 'Plus tard' ] ] ] ], '<p>Later</p>' ) );
		update_option(
			Default_Language::PROGRESS,
			[
				'from'    => 'nl',
				'to'      => 'fr',
				'last_id' => $this->page,
			]
		);
		$this->set_languages( [ 'default' => 'fr' ] );

		// Act.
		Default_Language::run( 'fr' );

		// Assert.
		$this->assertStringContainsString( '<p>Plus tard</p>', get_post_field( 'post_content', $later ) );
		$this->assertStringContainsString( '<p>Hallo</p>', get_post_field( 'post_content', $this->page ) );
		$this->assertNull( Default_Language::unfinished() );
	}

	public function test_admin_html_survives_the_switch(): void {
		// Arrange: an administrator's embed, which a contributor couldn't save.
		$embed = $this->create_page( $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Kaart <iframe src="https://example.com/nl"></iframe>', 'Carte <iframe src="https://example.com/fr"></iframe>' ] ] ] ], '<p>Kaart <iframe src="https://example.com/nl"></iframe></p>' ) );
		// The switch itself may run without a user (WP-CLI), with WordPress's HTML filters on.
		wp_set_current_user( 0 );
		kses_init_filters();

		// Act.
		Default_Language::run( 'fr' );
		kses_remove_filters();

		// Assert.
		$this->assertStringContainsString( '<iframe src="https://example.com/fr"></iframe>', get_post_field( 'post_content', $embed ) );
	}

	/**
	 * Requests that are refused.
	 *
	 * @dataProvider provide_refused_targets
	 *
	 * @param string $to Requested language.
	 */
	public function test_refused_targets( string $to ): void {
		// Arrange: nl is the default.

		// Act.
		$result = Default_Language::run( $to );

		// Assert.
		$this->assertWPError( $result );
		$this->assertSame( 'nl', Languages::default_code() );
	}

	public function provide_refused_targets(): array {
		return [
			'the current default' => [ 'nl' ],
			'unknown language'    => [ 'de' ],
		];
	}

	public function test_only_administrators_can_switch(): void {
		// Arrange.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_POST['to']          = 'fr';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'blocklingo_switch_default' );

		// Act.
		try {
			Settings_Page::handle_switch();
		} catch ( WPDieException $exception ) {
			unset( $exception );
		}

		// Assert.
		$this->assertSame( 'nl', Languages::default_code() );
		unset( $_POST['to'], $_REQUEST['_wpnonce'] );
	}

	public function test_the_same_text_twice_in_a_block_is_swapped_once_for_both(): void {
		// Arrange.
		$pairs = [
			'body.0.cells.0.content' => [ 'Ja', 'Oui' ],
			'body.1.cells.0.content' => [ 'Ja', 'Oui' ],
		];
		$block = parse_blocks( $this->block( 'table', [ 'blocklingo' => [ 'fr' => $pairs ] ], '<figure class="wp-block-table"><table><tbody><tr><td>Ja</td></tr><tr><td>Ja</td></tr></tbody></table></figure>' ) )[0];
		$stats = [
			'swapped'      => 0,
			'untranslated' => 0,
			'outdated'     => 0,
		];

		// Act.
		$switched = Default_Language::switch_block( $block, 'nl', 'fr', $stats );

		// Assert.
		$this->assertStringContainsString( '<tr><td>Oui</td></tr><tr><td>Oui</td></tr>', $switched['innerHTML'] );
		$this->assertSame( 2, $stats['swapped'] );
		$this->assertSame( 0, $stats['outdated'] );
		$this->assertSame( [ 'Oui', 'Ja' ], $switched['attrs']['blocklingo']['nl']['body.1.cells.0.content'] );
	}

	public function test_link_in_markup_is_swapped(): void {
		// Arrange.
		$pairs = [
			'url'  => [ 'https://rosa.be/nl/', 'https://rosa.be/fr/' ],
			'text' => [ 'Afspraak', 'Rendez-vous' ],
		];
		$block = parse_blocks( $this->block( 'button', [ 'blocklingo' => [ 'fr' => $pairs ] ], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://rosa.be/nl/">Afspraak</a></div>' ) )[0];
		$stats = [
			'swapped'      => 0,
			'untranslated' => 0,
			'outdated'     => 0,
		];

		// Act.
		$switched = Default_Language::switch_block( $block, 'nl', 'fr', $stats );

		// Assert.
		$this->assertStringContainsString( 'href="https://rosa.be/fr/">Rendez-vous</a>', $switched['innerHTML'] );
		$this->assertSame( 2, $stats['swapped'] );
		$this->assertSame( [ 'https://rosa.be/fr/', 'https://rosa.be/nl/' ], $switched['attrs']['blocklingo']['nl']['url'] );
	}
}
