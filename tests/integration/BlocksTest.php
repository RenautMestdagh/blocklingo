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
		foreach ( [ 'blocklingo-test/dynamic', 'blocklingo-test/list' ] as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
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
		// Arrange: rows of cells with text (like a table), and a list without text.
		$cell = [
			'type'   => 'rich-text',
			'source' => 'rich-text',
			'role'   => 'content',
		];
		register_block_type(
			'blocklingo-test/list',
			[
				'attributes' => [
					'rows'  => [
						'type'   => 'array',
						'source' => 'query',
						'query'  => [
							'cells' => [
								'type'   => 'array',
								'source' => 'query',
								'query'  => [ 'content' => $cell ],
							],
						],
					],
					'links' => [
						'type'   => 'array',
						'source' => 'query',
						'query'  => [
							'url' => [
								'type'   => 'string',
								'source' => 'attribute',
							],
						],
					],
				],
			]
		);

		// Act.
		$names = Blocks::translatable_attributes( 'blocklingo-test/list' );

		// Assert.
		$this->assertSame( [ 'rows' ], $names );
	}

	public function test_button_links_to_its_address_in_that_language(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$pairs   = [ 'url' => [ 'https://rosa.be/nl/', 'https://rosa.be/fr/' ] ];
		$content = $this->block( 'button', [ 'blocklingo' => [ 'fr' => $pairs ] ], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://rosa.be/nl/">Afspraak</a></div>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( 'href="https://rosa.be/fr/"', $html );
		$this->assertStringNotContainsString( 'rosa.be/nl/', $html );
	}

	public function test_link_in_the_block_comment_is_translated(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$attrs = [
			'url'        => 'https://rosa.be/nl/',
			'service'    => 'chain',
			'label'      => 'Rosa',
			'blocklingo' => [ 'fr' => [ 'url' => [ 'https://rosa.be/nl/', 'https://rosa.be/fr/' ] ] ],
		];

		// Act.
		$html = do_blocks( '<!-- wp:social-links --><ul class="wp-block-social-links"><!-- wp:social-link ' . wp_json_encode( $attrs ) . ' /--></ul><!-- /wp:social-links -->' );

		// Assert.
		$this->assertStringContainsString( 'href="https://rosa.be/fr/"', $html );
	}

	public function test_translated_link_is_kept_a_safe_address(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$pairs   = [ 'url' => [ 'https://rosa.be/nl/', 'javascript:alert(1)' ] ];
		$content = $this->block( 'button', [ 'blocklingo' => [ 'fr' => $pairs ] ], '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://rosa.be/nl/">Afspraak</a></div>' );

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * @dataProvider provide_links_of_core_blocks
	 *
	 * @param string $name      Block name.
	 * @param array  $attrs     Attributes, without blocklingo.
	 * @param string $html      Saved markup ('' for a block saved as a comment only).
	 * @param string $attribute Attribute holding the link.
	 */
	public function test_links_of_core_blocks_are_translated( string $name, array $attrs, string $html, string $attribute ): void {
		// Arrange.
		$this->visit( '/fr/' );
		$attrs['blocklingo'] = [ 'fr' => [ $attribute => [ 'https://example.com/nl', 'https://example.com/fr' ] ] ];
		$comment             = '<!-- wp:' . $name . ' ' . wp_json_encode( $attrs );
		$content             = '' === $html ? $comment . ' /-->' : $comment . ' -->' . $html . '<!-- /wp:' . $name . ' -->';

		// Act.
		$rendered = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( 'https://example.com/fr', $rendered );
		$this->assertStringNotContainsString( 'https://example.com/nl', $rendered );
	}

	public function provide_links_of_core_blocks(): array {
		return [
			'image link'          => [ 'image', [], '<figure class="wp-block-image"><a href="https://example.com/nl"><img src="/a.jpg" alt=""/></a></figure>', 'href' ],
			'file'                => [ 'file', [ 'href' => 'https://example.com/nl' ], '<div class="wp-block-file"><a href="https://example.com/nl">Brochure</a><a href="https://example.com/nl" class="wp-block-file__button wp-element-button" download>Download</a></div>', 'href' ],
			'file name link'      => [ 'file', [], '<div class="wp-block-file"><a href="https://example.com/nl">Brochure</a></div>', 'textLinkHref' ],
			'media and text'      => [ 'media-text', [ 'mediaType' => 'image' ], '<div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="https://example.com/nl" alt=""/></figure><div class="wp-block-media-text__content"></div></div>', 'mediaUrl' ],
			'media and text link' => [ 'media-text', [ 'mediaType' => 'image' ], '<div class="wp-block-media-text"><figure class="wp-block-media-text__media"><a href="https://example.com/nl"><img src="/a.jpg" alt=""/></a></figure><div class="wp-block-media-text__content"></div></div>', 'href' ],
			'cover image'         => [ 'cover', [ 'url' => 'https://example.com/nl' ], '<div class="wp-block-cover"><img class="wp-block-cover__image-background" alt="" src="https://example.com/nl" data-object-fit="cover"/><div class="wp-block-cover__inner-container"></div></div>', 'url' ],
			'menu link'           => [
				'navigation-link',
				[
					'label' => 'Rosa',
					'url'   => 'https://example.com/nl',
					'kind'  => 'custom',
				],
				'',
				'url',
			],
		];
	}
}
