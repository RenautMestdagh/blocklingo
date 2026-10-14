<?php
/**
 * Security: what users without the right to post unfiltered HTML can put on the site
 * through translations, and how request data is handled.
 *
 * @package Blocklingo
 */

use Blocklingo\Blocks;
use Blocklingo\Posts;
use Blocklingo\Request;
use Blocklingo\Router;

/**
 * Security tests across classes.
 *
 * @covers \Blocklingo\Blocks
 * @covers \Blocklingo\Posts
 * @covers \Blocklingo\Request
 * @covers \Blocklingo\Router
 */
class SecurityTest extends Blocklingo_TestCase {

	public function tear_down(): void {
		kses_remove_filters();
		parent::tear_down();
	}

	/**
	 * Saves a post as a contributor, with WordPress's HTML filters on as they are for them.
	 *
	 * @param string $content Post content.
	 * @return string Content as stored.
	 */
	private function save_as_contributor( string $content ): string {
		$user = self::factory()->user->create( [ 'role' => 'contributor' ] );
		wp_set_current_user( $user );
		kses_init_filters();
		$post_id = wp_insert_post(
			[
				'post_author'  => $user,
				'post_content' => $content,
				'post_status'  => 'draft',
			]
		);
		return get_post_field( 'post_content', $post_id );
	}

	public function test_script_in_a_translation_is_removed_when_a_contributor_saves(): void {
		// Arrange.
		$attrs   = wp_json_encode( [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo', 'Bonjour<script>alert(1)</script>' ] ] ] ] );
		$content = '<!-- wp:paragraph ' . $attrs . ' --><p>Hallo</p><!-- /wp:paragraph -->';

		// Act.
		$stored = $this->save_as_contributor( $content );

		// Assert.
		$this->assertStringNotContainsString( '<script', $stored );
		$this->assertStringNotContainsString( '<script', $stored );
	}

	public function test_unsafe_markup_in_a_translation_of_safe_content_is_not_shown(): void {
		// Arrange: content stored without the filters (e.g. imported), original is plain text.
		$this->visit( '/fr/' );
		$content = '<!-- wp:paragraph ' . wp_json_encode( [ 'blocklingo' => [ 'fr' => [ 'content' => [ 'Hallo', 'Bonjour<script>alert(1)</script><img src=x onerror=alert(1)>' ] ] ] ] ) . ' --><p>Hallo</p><!-- /wp:paragraph -->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringContainsString( 'Bonjour', $html );
	}

	public function test_markup_the_original_already_had_is_kept_in_the_translation(): void {
		// Arrange: an administrator's embed (unfiltered HTML) and its translation.
		$this->visit( '/fr/' );
		$original    = 'Kaart <iframe src="https://example.com/nl"></iframe>';
		$translation = 'Carte <iframe src="https://example.com/fr"></iframe>';
		$content     = '<!-- wp:paragraph ' . wp_json_encode( [ 'blocklingo' => [ 'fr' => [ 'content' => [ $original, $translation ] ] ] ] ) . ' --><p>' . $original . '</p><!-- /wp:paragraph -->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringContainsString( '<iframe src="https://example.com/fr"></iframe>', $html );
	}

	public function test_translated_html_attribute_is_escaped(): void {
		// Arrange.
		$this->visit( '/fr/' );
		$content = '<!-- wp:image ' . wp_json_encode( [ 'blocklingo' => [ 'fr' => [ 'alt' => [ 'Foto', '"><script>alert(1)</script>' ] ] ] ] ) . ' --><figure class="wp-block-image"><img src="/a.jpg" alt="Foto"/></figure><!-- /wp:image -->';

		// Act.
		$html = do_blocks( $content );

		// Assert.
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'alt="&quot;&gt;&lt;script&gt;', $html );
	}

	public function test_post_translations_are_sanitized(): void {
		// Arrange.
		$input = [
			'fr' => [
				'title'   => 'Tarifs<script>alert(1)</script>',
				'slug'    => '../../etc/passwd',
				'excerpt' => '<img src=x onerror=alert(1)>Résumé',
			],
		];

		// Act.
		$clean = Posts::sanitize( $input );

		// Assert.
		$this->assertStringNotContainsString( '<', $clean['fr']['title'] . $clean['fr']['excerpt'] );
		$this->assertSame( 'etc-passwd', $clean['fr']['slug'] );
	}

	public function test_contributor_cannot_edit_translations_of_someone_elses_post(): void {
		// Arrange.
		$post        = self::factory()->post->create( [ 'post_author' => self::factory()->user->create( [ 'role' => 'editor' ] ) ] );
		$contributor = self::factory()->user->create( [ 'role' => 'contributor' ] );
		wp_set_current_user( $contributor );

		// Act.
		$allowed = current_user_can( 'edit_post_meta', $post, Posts::META );

		// Assert.
		$this->assertFalse( $allowed );
	}

	public function test_lang_parameter_only_accepts_enabled_languages(): void {
		// Arrange.
		$this->set_permalink_structure( '' );
		$_GET['lang']           = '<script>';
		$_SERVER['REQUEST_URI'] = '/?lang=%3Cscript%3E';

		// Act.
		Request::detect();

		// Assert.
		$this->assertSame( 'nl', Request::lang() );
	}

	/**
	 * Paths that look like another host or a header must not take the redirect off the site.
	 *
	 * @dataProvider provide_hostile_paths
	 *
	 * @param string $path Requested path.
	 */
	public function test_language_redirect_never_leaves_the_site( string $path ): void {
		// Arrange.
		$this->set_languages( [ 'prefix_default' => true ] );
		$this->visit( $path );

		// Act.
		$target = Router::language_address();

		// Assert.
		$this->assertTrue( '' === $target || 0 === strpos( $target, untrailingslashit( get_option( 'home' ) ) . '/' ), $target );
		$this->assertStringNotContainsString( "\n", $target );
	}

	public function provide_hostile_paths(): array {
		return [
			'protocol relative' => [ '//evil.example/' ],
			'backslashes'       => [ '/\\\\evil.example/' ],
			'encoded newline'   => [ '/x%0d%0aLocation:%20https://evil.example/' ],
			'at sign'           => [ '/@evil.example/' ],
		];
	}

	public function test_replace_never_touches_structural_attributes(): void {
		// Arrange.
		$html = '<a href="Hallo" class="Hallo" title="Hallo">x</a>';

		// Act.
		$replaced = Blocks::replace( $html, 'Hallo', 'Bonjour' );

		// Assert.
		$this->assertSame( '<a href="Hallo" class="Hallo" title="Bonjour">x</a>', $replaced );
	}
}
