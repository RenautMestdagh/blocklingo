<?php
/**
 * Classic menus (Appearance → Menus) in every language.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Menu items get, per language, their own label, title attribute and (for custom links)
 * address, set in Appearance → Menus. Without them an item shows the translated title of
 * the page it links to, and links to this site go to the same language. Block themes'
 * Navigation block is made of blocks and translated in the Site Editor instead.
 *
 * Meta `_blocklingo_menu` on the menu item: { "fr": { "title": "", "attr_title": "", "url": "" } }.
 */
class Menus {

	const META   = '_blocklingo_menu';
	const FIELDS = [ 'title', 'attr_title', 'url' ];

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_nav_menu_item_custom_fields', [ __CLASS__, 'render_fields' ], 10, 2 );
		add_action( 'wp_update_nav_menu_item', [ __CLASS__, 'save_fields' ], 10, 2 );
		add_filter( 'wp_nav_menu_objects', [ __CLASS__, 'translate_items' ], 20 );
	}

	/**
	 * Translations of one menu item.
	 *
	 * @param int $item_id Menu item ID.
	 * @return array
	 */
	public static function translations( int $item_id ): array {
		$stored = get_post_meta( $item_id, self::META, true );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Cleans submitted translations of a menu item.
	 *
	 * @param mixed $input { lang: { title, attr_title, url } }.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$clean = [];
		foreach ( (array) $input as $code => $fields ) {
			$code = (string) $code;
			if ( ! Languages::get( $code ) || Languages::is_default( $code ) || ! is_array( $fields ) ) {
				continue;
			}
			$entry = [
				'title'      => wp_kses_post( (string) ( $fields['title'] ?? '' ) ),
				'attr_title' => sanitize_text_field( (string) ( $fields['attr_title'] ?? '' ) ),
				'url'        => esc_url_raw( (string) ( $fields['url'] ?? '' ) ),
			];
			$entry = array_filter( $entry, fn ( $value ) => '' !== trim( $value ) );
			if ( $entry ) {
				$clean[ $code ] = $entry;
			}
		}
		return $clean;
	}

	/**
	 * The fields under every menu item in Appearance → Menus.
	 *
	 * @param int      $item_id Menu item ID.
	 * @param \WP_Post $item    Menu item (with nav menu properties).
	 */
	public static function render_fields( $item_id, $item ): void {
		$languages = array_diff_key( Languages::enabled(), [ Languages::default_code() => true ] );
		if ( ! $languages ) {
			return;
		}
		$translations = self::translations( (int) $item_id );
		$is_custom    = isset( $item->type ) && 'custom' === $item->type;
		?>
		<fieldset class="field-blocklingo description description-wide" style="margin: 8px 0;">
			<legend><strong><?php esc_html_e( 'Translations', 'blocklingo' ); ?></strong></legend>
			<?php foreach ( $languages as $code => $language ) : ?>
				<?php
				$name    = 'blocklingo_menu[' . (int) $item_id . '][' . $code . ']';
				$current = $translations[ $code ] ?? [];
				?>
				<p class="description description-wide">
					<label>
						<?php
						/* translators: %s: language name. */
						echo esc_html( sprintf( __( 'Navigation label (%s)', 'blocklingo' ), $language['name'] ) );
						?>
						<br />
						<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $current['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Empty: the translated page title', 'blocklingo' ); ?>" />
					</label>
				</p>
				<p class="description description-wide">
					<label>
						<?php
						/* translators: %s: language name. */
						echo esc_html( sprintf( __( 'Title attribute (%s)', 'blocklingo' ), $language['name'] ) );
						?>
						<br />
						<input type="text" class="widefat" name="<?php echo esc_attr( $name ); ?>[attr_title]" value="<?php echo esc_attr( $current['attr_title'] ?? '' ); ?>" />
					</label>
				</p>
				<?php if ( $is_custom ) : ?>
					<p class="description description-wide">
						<label>
							<?php
							/* translators: %s: language name. */
							echo esc_html( sprintf( __( 'URL (%s)', 'blocklingo' ), $language['name'] ) );
							?>
							<br />
							<input type="url" class="widefat code" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $current['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Empty: the same address in this language', 'blocklingo' ); ?>" />
						</label>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Saves the fields when Appearance → Menus is saved (WordPress has checked the nonce
	 * and the user's right to edit menus before this action runs).
	 *
	 * @param int $menu_id Menu ID.
	 * @param int $item_id Menu item ID.
	 */
	public static function save_fields( $menu_id, $item_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by nav-menus.php (update-nav_menu).
		if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['blocklingo_menu'][ $item_id ] ) ) {
			return;
		}
		$input = wp_unslash( $_POST['blocklingo_menu'][ $item_id ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$clean = self::sanitize( $input );
		if ( $clean ) {
			update_post_meta( (int) $item_id, self::META, $clean );
		} else {
			delete_post_meta( (int) $item_id, self::META );
		}
	}

	/**
	 * Menu items in the language being shown: translated labels and title attributes, and
	 * links in that language (a translated address, or the same address localized).
	 *
	 * @param \WP_Post[] $items Menu items.
	 * @return \WP_Post[]
	 */
	public static function translate_items( $items ) {
		$code = Links::lang();
		if ( ! Request::is_frontend() || ! Languages::is_multilingual() || ! is_array( $items ) ) {
			return $items;
		}
		$translate = ! Languages::is_default( $code );
		foreach ( $items as $item ) {
			$translation = $translate ? ( self::translations( (int) $item->ID )[ $code ] ?? [] ) : [];
			if ( ! empty( $translation['title'] ) ) {
				$item->title = $translation['title'];
			}
			if ( ! empty( $translation['attr_title'] ) ) {
				$item->attr_title = $translation['attr_title'];
			}
			if ( ! empty( $translation['url'] ) ) {
				$item->url = $translation['url'];
			} elseif ( 'custom' === ( $item->type ?? '' ) && ! empty( $item->url ) ) {
				// Links to pages carry their language already; custom links to this site get it.
				$item->url = Links::localize_url( (string) $item->url, $code );
			}
		}
		return $items;
	}
}
