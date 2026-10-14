<?php
/**
 * Translated titles, slugs and excerpts of posts.
 *
 * @package Blocklingo
 */

use Blocklingo\Posts;

/**
 * Tests for the Posts class.
 *
 * @covers \Blocklingo\Posts
 */
class PostsTest extends Blocklingo_TestCase {

	public function test_sanitize_keeps_known_fields_of_translated_languages(): void {
		// Arrange.
		$input = [
			'nl' => [ 'title' => 'Origineel' ],
			'de' => [ 'title' => 'Unbekannt' ],
			'fr' => [
				'title' => '<b>Tarifs</b>',
				'slug'  => 'Mes Tarifs!',
				'other' => 'x',
			],
			'en' => [ 'title' => '' ],
		];

		// Act.
		$clean = Posts::sanitize( $input );

		// Assert.
		$this->assertSame(
			[
				'fr' => [
					'title'   => 'Tarifs',
					'slug'    => 'mes-tarifs',
					'excerpt' => '',
				],
			],
			$clean
		);
	}

	public function test_slug_index_has_a_row_per_translated_slug(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		// Act.
		$this->translate(
			$page,
			[
				'fr' => [ 'slug' => 'tarifs' ],
				'en' => [ 'slug' => 'rates' ],
			]
		);

		// Assert.
		$this->assertEqualSets( [ 'fr:tarifs', 'en:rates' ], get_post_meta( $page, Posts::SLUG_INDEX, false ) );
	}

	public function test_slug_index_follows_changed_translations(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->translate(
			$page,
			[
				'fr' => [ 'slug' => 'tarifs' ],
				'en' => [ 'slug' => 'rates' ],
			]
		);

		// Act.
		$this->translate( $page, [ 'fr' => [ 'slug' => 'prix' ] ] );

		// Assert.
		$this->assertSame( [ 'fr:prix' ], get_post_meta( $page, Posts::SLUG_INDEX, false ) );
	}

	public function test_slug_index_is_emptied_with_the_translations(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->translate( $page, [ 'fr' => [ 'slug' => 'tarifs' ] ] );

		// Act.
		delete_post_meta( $page, Posts::META );

		// Assert.
		$this->assertSame( [], get_post_meta( $page, Posts::SLUG_INDEX, false ) );
	}

	public function test_find_by_path_finds_a_child_by_its_translated_path(): void {
		// Arrange.
		[ , $child ] = $this->create_translated_parent_and_child();

		// Act.
		$found = Posts::find_by_path( 'parents/enfant', 'fr', [ 'page' ] );

		// Assert.
		$this->assertSame( $child, $found->ID );
	}

	public function test_find_by_path_needs_the_parent_in_the_path(): void {
		// Arrange.
		$this->create_translated_parent_and_child();

		// Act.
		$without_parent  = Posts::find_by_path( 'enfant', 'fr', [ 'page' ] );
		$original_parent = Posts::find_by_path( 'ouders/enfant', 'fr', [ 'page' ] );

		// Assert.
		$this->assertNull( $without_parent );
		$this->assertNull( $original_parent );
	}

	public function test_untranslatable_post_type_keeps_its_slug(): void {
		// Arrange.
		$this->set_languages( [ 'post_types' => [ 'page' ] ] );
		$post = self::factory()->post->create_and_get( [ 'post_name' => 'nieuws' ] );
		update_post_meta( $post->ID, Posts::META, [ 'fr' => [ 'slug' => 'nouvelles' ] ] );

		// Act.
		$slug = Posts::slug( $post, 'fr' );

		// Assert.
		$this->assertSame( 'nieuws', $slug );
	}

	public function test_translated_excerpt_on_the_site(): void {
		// Arrange.
		$page = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_excerpt' => 'Nederlandse samenvatting',
			]
		);
		$this->translate( $page, [ 'fr' => [ 'excerpt' => 'Résumé français' ] ] );
		$this->visit( '/fr/' );

		// Act.
		$excerpt = get_the_excerpt( $page );

		// Assert.
		$this->assertSame( 'Résumé français', $excerpt );
	}

	/**
	 * Page "ouders" (French "parents") with child "kind" (French "enfant").
	 *
	 * @return int[] Parent and child ID.
	 */
	private function create_translated_parent_and_child(): array {
		$parent = self::factory()->post->create(
			[
				'post_type' => 'page',
				'post_name' => 'ouders',
			]
		);
		$child  = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_name'   => 'kind',
				'post_parent' => $parent,
			]
		);
		$this->translate( $parent, [ 'fr' => [ 'slug' => 'parents' ] ] );
		$this->translate( $child, [ 'fr' => [ 'slug' => 'enfant' ] ] );
		return [ $parent, $child ];
	}
}
