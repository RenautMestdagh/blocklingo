<?php
/**
 * Classic menus (Appearance → Menus) in every language.
 *
 * @package Blocklingo
 */

use Blocklingo\Menus;

/**
 * Tests for the Menus class.
 *
 * @covers \Blocklingo\Menus
 */
class MenusTest extends Blocklingo_TestCase {

	/**
	 * Menu with a page item (no own label), a page item with a label, and a custom link.
	 *
	 * @var int
	 */
	private $menu;

	/**
	 * Menu item IDs by name.
	 *
	 * @var int[]
	 */
	private $items = [];

	public function set_up(): void {
		parent::set_up();
		$page = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'tarieven',
				'post_title' => 'Tarieven',
			]
		);
		$this->translate(
			$page,
			[
				'fr' => [
					'title' => 'Tarifs',
					'slug'  => 'tarifs',
				],
			]
		);

		$this->menu              = wp_create_nav_menu( 'Hoofdmenu' );
		$this->items['page']     = wp_update_nav_menu_item(
			$this->menu,
			0,
			[
				'menu-item-object-id' => $page,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			]
		);
		$this->items['labelled'] = wp_update_nav_menu_item(
			$this->menu,
			0,
			[
				'menu-item-object-id' => $page,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-title'     => 'Prijzen',
				'menu-item-status'    => 'publish',
			]
		);
		$this->items['custom']   = wp_update_nav_menu_item(
			$this->menu,
			0,
			[
				'menu-item-title'  => 'Contact',
				'menu-item-url'    => home_url( '/contact/' ),
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			]
		);
		$this->items['external'] = wp_update_nav_menu_item(
			$this->menu,
			0,
			[
				'menu-item-title'  => 'Rosa',
				'menu-item-url'    => 'https://rosa.be/nl/',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			]
		);
	}

	/**
	 * The menu's items as the site shows them: label => URL.
	 *
	 * @return array<string, string>
	 */
	private function rendered(): array {
		$items = wp_get_nav_menu_items( $this->menu );
		$items = apply_filters( 'wp_nav_menu_objects', $items, (object) [ 'menu' => $this->menu ] );
		$shown = [];
		foreach ( $items as $item ) {
			$shown[ $item->title ] = $item->url;
		}
		return $shown;
	}

	private function home(): string {
		return untrailingslashit( get_option( 'home' ) );
	}

	public function test_item_without_label_shows_the_translated_page_title_and_address(): void {
		// Arrange.
		$this->visit( '/fr/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertSame( $this->home() . '/fr/tarifs/', $shown['Tarifs'] );
	}

	public function test_own_label_is_translated(): void {
		// Arrange.
		update_post_meta( $this->items['labelled'], Menus::META, [ 'fr' => [ 'title' => 'Nos prix' ] ] );
		$this->visit( '/fr/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertArrayHasKey( 'Nos prix', $shown );
		$this->assertArrayNotHasKey( 'Prijzen', $shown );
	}

	public function test_untranslated_own_label_stays(): void {
		// Arrange: "Prijzen" has no French label.
		$this->visit( '/fr/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertArrayHasKey( 'Prijzen', $shown );
	}

	public function test_custom_link_to_this_site_goes_to_the_same_language(): void {
		// Arrange.
		$this->visit( '/fr/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertSame( $this->home() . '/fr/contact/', $shown['Contact'] );
	}

	public function test_external_link_stays_unless_it_has_its_own_address(): void {
		// Arrange.
		update_post_meta( $this->items['external'], Menus::META, [ 'fr' => [ 'url' => 'https://rosa.be/fr/' ] ] );
		$this->visit( '/fr/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertSame( 'https://rosa.be/fr/', $shown['Rosa'] );
	}

	public function test_default_language_menu_is_unchanged(): void {
		// Arrange.
		update_post_meta( $this->items['labelled'], Menus::META, [ 'fr' => [ 'title' => 'Nos prix' ] ] );
		$this->visit( '/' );

		// Act.
		$shown = $this->rendered();

		// Assert.
		$this->assertSame( $this->home() . '/tarieven/', $shown['Prijzen'] );
		$this->assertSame( $this->home() . '/contact/', $shown['Contact'] );
	}

	public function test_saving_the_menu_screen_stores_cleaned_translations(): void {
		// Arrange.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST['blocklingo_menu'] = [
			$this->items['custom'] => [
				'fr' => [
					'title' => 'Contactez-moi<script>x</script>',
					'url'   => 'javascript:alert(1)',
				],
				'nl' => [ 'title' => 'Default language: ignored' ],
				'en' => [ 'title' => '' ],
			],
		];

		// Act.
		Menus::save_fields( $this->menu, $this->items['custom'] );

		// Assert.
		$this->assertSame( [ 'fr' => [ 'title' => 'Contactez-moix' ] ], Menus::translations( $this->items['custom'] ) );
		unset( $_POST['blocklingo_menu'] );
	}

	public function test_users_who_cannot_edit_menus_cannot_save_translations(): void {
		// Arrange.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$_POST['blocklingo_menu'] = [ $this->items['custom'] => [ 'fr' => [ 'title' => 'Piraté' ] ] ];

		// Act.
		Menus::save_fields( $this->menu, $this->items['custom'] );

		// Assert.
		$this->assertSame( [], Menus::translations( $this->items['custom'] ) );
		unset( $_POST['blocklingo_menu'] );
	}
}
