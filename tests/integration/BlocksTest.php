<?php
/**
 * Translations stored in blocks, shown on the site.
 *
 * @package Blocklingo
 */

use Blocklingo\Blocks;

/**
 * Tests for the Blocks class.
 *
 * @covers \Blocklingo\Blocks
 */
class BlocksTest extends Blocklingo_TestCase {

	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'blocklingo-test/dynamic' ) ) {
			unregister_block_type( 'blocklingo-test/dynamic' );
		}
		parent::tear_down();
	}

	/**
	 * Block comment with a blocklingo attribute.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes, including blocklingo.
	 * @param string $html  Saved markup.
	 */
	private function block( string $name, array $attrs, string $html ): string {
		return '<!-- wp:' . $name . ' ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:' . $name . ' -->';
	}

	public function test_every_block_type_has_the_attribute(): void {
		// Arrange.
		$paragraph = WP_Block_Type_Registry::get_instance()->get_registered( 'core/paragraph' );

		// Act.
		$attributes = $paragraph->get_attributes();

		// Assert.
		$this->assertSame( [ 'type' => 'object' ], $attributes['blocklingo'] );
	}

	public function test_paragraph_is_translated_on_a_page_in_that_language(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$content = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo <strong>wereld</strong>', 'Bonjour <strong>le monde</strong>' ] ] ] ], '<p>Hallo <strong>wereld</strong></p>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Bonjour <strong>le monde</strong></p>', $html );
	}

	public function test_default_language_shows_the_original(): void {
		// Arrange.
		$this->visit( '/' );
		$content = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo', 'Bonjour' ] ] ] ], '<p>Hallo</p>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Hallo</p>', $html );
	}

	public function test_language_without_translation_shows_the_original(): void {
		// Arrange.
		$this->visit( '/en/' );
		$content = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo', 'Bonjour' ] ] ] ], '<p>Hallo</p>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Hallo</p>', $html );
	}

	public function test_outdated_translation_shows_the_current_original(): void {
		// Arrange: translated when the text was "Hallo"; it is "Hallo allemaal" now.
		$this->visit( '/fr/' );
		$content = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo', 'Bonjour' ] ] ] ], '<p>Hallo allemaal</p>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Hallo allemaal</p>', $html );
	}

	public function test_image_alt_text_is_translated(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$content = $this->block( 'image', [ 'blocklingo' => [ 'fr' => [ 'alt' => [ 'Een kind & een spel', 'Un enfant & un jeu' ] ] ] ], '<figure class="wp-block-image"><img src="/a.jpg" alt="Een kind &amp; een spel"/></figure>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( 'alt="Un enfant &amp; un jeu"', $html );
	}

	public function test_plain_text_in_a_translated_attribute_is_kept(): void {
		// Arrange: alt text with characters that look like markup.
		$this->visit( '/fr/' );
		$content = $this->block( 'image', [ 'blocklingo' => [ 'fr' => [ 'alt' => [ 'Foto', 'Tom & Jerry <3' ] ] ] ], '<figure class="wp-block-image"><img src="/a.jpg" alt="Foto"/></figure>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( 'alt="Tom &amp; Jerry &lt;3"', $html );
	}

	public function test_short_original_is_not_replaced_inside_markup(): void {
		// Arrange: "a" is also a tag name in the markup.
		$this->visit( '/fr/' );
		$content = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'a', 'b' ] ] ] ], '<p class="a"><a href="/x/">link</a></p>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertMatchesRegularExpression( '#<p class="a( [^"]*)?"><a href="/fr/x/">link</a></p>#', $html );
	}

	public function test_inner_blocks_are_translated_separately(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$child   = $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Kind', 'Enfant' ] ] ] ], '<p>Kind</p>' );
		$content = '<!-- wp:group --><div class="wp-block-group">' . $child . '</div><!-- /wp:group -->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '>Enfant</p>', $html );
	}

	public function test_attribute_of_a_server_rendered_block_is_translated(): void {
		// Arrange.
		register_block_type(
			'blocklingo-test/dynamic',
			[
				'attributes'      => [ 'label' => [ 'type' => 'string' ] ],
				'render_callback' => fn ( $attributes ) => '<span>' . esc_html( $attributes['label'] ) . '</span>',
			]
		);
		$this->visit( '/fr/' );
		$content = '<!-- wp:blocklingo-test/dynamic ' . wp_json_encode(
			[
				'label'      => 'Over mij',
				'blocklingo' => [ 'fr' => [ 'label' => [ 'Over mij', 'À propos' ] ] ],
			]
		) . ' /-->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertSame( '<span>À propos</span>', $html );
	}

	public function test_block_rendered_without_the_data_filter_is_translated_in_its_output(): void {
		// Arrange: the top level of a block list, as the Navigation block renders its links.
		$this->visit( '/fr/' );
		$parsed = parse_blocks( $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Contact', 'Contactez-moi' ] ] ] ], '<p>Contact</p>' ) )[0];
		$block  = new WP_Block( $parsed );

		// Act.
		$html = $block->render();

		// Assert.
		$this->assertStringContainsString( '>Contactez-moi</p>', $html );
	}

	public function test_block_in_a_template_part_is_translated(): void {
		// Arrange: a header template part, as saved in the Site Editor.
		$part = self::factory()->post->create(
			[
				'post_type'    => 'wp_template_part',
				'post_name'    => 'blocklingo-header',
				'post_status'  => 'publish',
				'post_content' => $this->block( 'paragraph', [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Welkom', 'Bienvenue' ] ] ] ], '<p>Welkom</p>' ),
			]
		);
		wp_set_post_terms( $part, get_stylesheet(), 'wp_theme' );
		$this->visit( '/fr/' );

		// Act.
		$html = do_blocks( '<!-- wp:template-part {"slug":"blocklingo-header","theme":"' . get_stylesheet() . '"} /-->' );

		// Assert.
		$this->assertStringContainsString( '>Bienvenue</p>', $html );
	}

	public function test_malformed_translations_are_ignored(): void {
		// Arrange.
		$parsed = [
			'attrs' => [
				'blocklingo' => [
					'fr' => [
						'content' => [ 'Hallo', 'Bonjour' ],
						'empty'   => [ '', 'Rien' ],
						'short'   => [ 'Een' ],
						'object'  => [ [ 'x' ], 'y' ],
						0         => [ 'a', 'b' ],
					],
				],
			],
		];

		// Act.
		$pairs = Blocks::translations( $parsed, 'fr' );

		// Assert.
		$this->assertSame( [ 'content' => [ 'Hallo', 'Bonjour' ] ], $pairs );
	}

	public function test_table_cells_are_translated_one_by_one(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$pairs   = [
			'head.0.cells.0.content' => [ 'Zorg', 'Soin' ],
			'body.0.cells.0.content' => [ 'Evaluatiezitting', 'Séance d’évaluation' ],
		];
		$content = $this->block( 'table', [ 'blocklingo' => [ 'fr' => $pairs ] ], '<figure class="wp-block-table"><table><thead><tr><th>Zorg</th><th>Prijs</th></tr></thead><tbody><tr><td>Evaluatiezitting</td><td>€ 76,73</td></tr></tbody></table></figure>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '<th>Soin</th><th>Prijs</th>', $html );
		$this->assertStringContainsString( '<td>Séance d’évaluation</td><td>€ 76,73</td>', $html );
	}

	public function test_lists_with_text_in_their_items_are_translatable(): void {
		// Arrange.
		$block_name = 'core/table';

		// Act.
		$names = Blocks::translatable_attributes( $block_name );

		// Assert.
		$this->assertEqualsCanonicalizing( [ 'caption', 'head', 'body', 'foot' ], $names );
	}
}
