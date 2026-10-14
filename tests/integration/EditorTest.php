<?php
/**
 * What the editor gets: its data, and saving translations through the REST API.
 *
 * @package Blocklingo
 */

use Blocklingo\Editor;
use Blocklingo\Posts;

/**
 * Tests for the Editor class and the REST side of post translations.
 *
 * @covers \Blocklingo\Editor
 * @covers \Blocklingo\Posts
 */
class EditorTest extends Blocklingo_TestCase {

	/**
	 * Sends a REST request as a user with a role.
	 *
	 * @param string $role   Role.
	 * @param int    $page   Page ID.
	 * @param array  $params Request body.
	 */
	private function update_page_as( string $role, int $page, array $params ): WP_REST_Response {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page );
		$request->set_body_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	public function test_data_lists_enabled_languages_with_the_default_first(): void {
		// Arrange.
		$this->set_languages( [ 'default' => 'fr' ] );

		// Act.
		$data = Editor::data();

		// Assert.
		$this->assertSame( 'fr', $data['defaultLanguage'] );
		$this->assertSame( [ 'fr', 'nl', 'en' ], array_column( $data['languages'], 'code' ) );
		$this->assertSame( [ 'page', 'post' ], $data['postTypes'] );
	}

	public function test_editor_can_save_translations_through_the_rest_api(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		// Act.
		$response = $this->update_page_as(
			'editor',
			$page,
			[
				'meta' => [
					'blocklingo' => [
						'fr' => [
							'title' => 'Tarifs',
							'slug'  => 'Tarifs',
						],
					],
				],
			]
		);

		// Assert.
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'tarifs', Posts::field( $page, 'fr', 'slug' ) );
	}

	public function test_rest_api_rejects_unknown_fields(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		// Act.
		$response = $this->update_page_as( 'editor', $page, [ 'meta' => [ 'blocklingo' => [ 'fr' => [ 'script' => 'x' ] ] ] ] );

		// Assert.
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_contributor_cannot_change_translations_of_a_published_page(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );

		// Act.
		$response = $this->update_page_as( 'contributor', $page, [ 'meta' => [ 'blocklingo' => [ 'fr' => [ 'title' => 'Piraté' ] ] ] ] );

		// Assert.
		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertSame( '', Posts::field( $page, 'fr', 'title' ) );
	}

	public function test_translations_are_readable_in_the_editor_context(): void {
		// Arrange.
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->translate( $page, [ 'fr' => [ 'title' => 'Tarifs' ] ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$request = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $page );
		$request->set_param( 'context', 'edit' );

		// Act.
		$response = rest_get_server()->dispatch( $request );

		// Assert.
		$this->assertSame( 'Tarifs', $response->get_data()['meta']['blocklingo']['fr']['title'] );
	}
}
