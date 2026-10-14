<?php
/**
 * Settings → Blocklingo: which languages, which is the default, and URL options.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * The settings screen.
 */
class Settings_Page {

	const SLUG = 'blocklingo';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'update_option_' . Languages::OPTION, [ __CLASS__, 'after_save' ], 10, 2 );
		add_action( 'add_option_' . Languages::OPTION, [ __CLASS__, 'after_add' ], 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( BLOCKLINGO_FILE ), [ __CLASS__, 'action_links' ] );
		add_action( 'admin_post_blocklingo_switch_default', [ __CLASS__, 'handle_switch' ] );
	}

	/**
	 * Key of the stored result of a switch, per user.
	 */
	private static function result_key(): string {
		return 'blocklingo_switch_result_' . get_current_user_id();
	}

	/**
	 * Handles "Change the default language" (dry run or real), then returns to the screen.
	 */
	public static function handle_switch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'blocklingo' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'blocklingo_switch_default' );

		$to      = Languages::sanitize_code( isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '' );
		$dry_run = ! empty( $_POST['dry_run'] );
		if ( ! $dry_run && empty( $_POST['backup'] ) ) {
			$message = __( 'Confirm that you have a backup of the database first.', 'blocklingo' );
			$type    = 'error';
		} else {
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- a long, one-time job the administrator started.
			}
			$result = Default_Language::run( $to, $dry_run );
			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				$type    = 'error';
			} else {
				$message = ( $dry_run ? __( 'Dry run, nothing was changed.', 'blocklingo' ) . ' ' : __( 'Done.', 'blocklingo' ) . ' ' ) . Cli::summary( $result );
				$type    = $dry_run ? 'info' : 'success';
			}
		}
		set_transient(
			self::result_key(),
			[
				'message' => $message,
				'type'    => $type,
			],
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * The "Change the default language" section: its own form, outside the settings form.
	 *
	 * @param array $settings Current settings.
	 */
	private static function render_switch( array $settings ): void {
		$others = array_diff_key( Languages::enabled(), [ $settings['default'] => true ] );
		if ( ! $others ) {
			return;
		}
		$unfinished = Default_Language::unfinished();
		?>
		<hr />
		<h2><?php esc_html_e( 'Change the default language', 'blocklingo' ); ?></h2>
		<p><?php esc_html_e( 'Makes another language the one content is written in: its translations become the content, and the current content becomes its translation. Blocks without a translation keep their text. Run a dry run first to see what would change, and back up the database before switching.', 'blocklingo' ); ?></p>
		<?php if ( $unfinished ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				/* translators: %s: language code. */
				echo esc_html( sprintf( __( 'A switch to %s was interrupted. Run it again to finish it.', 'blocklingo' ), $unfinished['to'] ) );
				?>
			</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="blocklingo_switch_default" />
			<?php wp_nonce_field( 'blocklingo_switch_default' ); ?>
			<p>
				<label for="blocklingo-switch-to"><?php esc_html_e( 'New default language', 'blocklingo' ); ?></label>
				<select id="blocklingo-switch-to" name="to">
					<?php foreach ( $others as $code => $language ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $unfinished['to'] ?? '', $code ); ?>><?php echo esc_html( $language['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label><input type="checkbox" name="backup" value="1" /> <?php esc_html_e( 'I have a backup of the database', 'blocklingo' ); ?></label>
			</p>
			<p>
				<button type="submit" class="button" name="dry_run" value="1"><?php esc_html_e( 'Dry run', 'blocklingo' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Change the default language', 'blocklingo' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'For large sites, use WP-CLI: wp blocklingo switch-default <code>', 'blocklingo' ); ?></p>
		</form>
		<?php
	}

	/**
	 * The option, cleaned on save.
	 */
	public static function register(): void {
		register_setting(
			self::SLUG,
			Languages::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			]
		);
		register_setting(
			self::SLUG,
			Machine_Translation::OPTION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_deepl_key' ],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * The DeepL key field: empty keeps the saved key (it is never shown again), "remove"
	 * deletes it.
	 *
	 * @param mixed $input Submitted value: [ 'key' => string, 'remove' => '1' ] or a string.
	 * @return string
	 */
	public static function sanitize_deepl_key( $input ): string {
		if ( is_array( $input ) ) {
			if ( ! empty( $input['remove'] ) ) {
				return '';
			}
			$input = $input['key'] ?? '';
		}
		$key = preg_replace( '/[^A-Za-z0-9:\-]/', '', (string) $input );
		return '' !== $key ? $key : Machine_Translation::key();
	}

	/**
	 * Menu entry.
	 */
	public static function add_page(): void {
		add_options_page( __( 'Blocklingo', 'blocklingo' ), __( 'Blocklingo', 'blocklingo' ), 'manage_options', self::SLUG, [ __CLASS__, 'render' ] );
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'blocklingo' ) ) );
		return $links;
	}

	/**
	 * Script for adding and removing language rows.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue( $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_script( 'blocklingo-settings', plugins_url( 'assets/admin/settings.js', BLOCKLINGO_FILE ), [], BLOCKLINGO_VERSION, true );
		wp_enqueue_style( 'blocklingo-settings', plugins_url( 'assets/admin/settings.css', BLOCKLINGO_FILE ), [], BLOCKLINGO_VERSION );
	}

	/**
	 * Cleans the submitted settings and reports problems.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		// options.php has already unslashed the submitted value.
		$input     = is_array( $input ) ? $input : [];
		$languages = [];
		$slugs     = [];
		foreach ( (array) ( $input['languages'] ?? [] ) as $row ) {
			$code = Languages::sanitize_code( (string) ( $row['code'] ?? '' ) );
			if ( '' === $code ) {
				if ( ! empty( $row['code'] ) ) {
					/* translators: %s: language code as entered. */
					add_settings_error( Languages::OPTION, 'code', sprintf( __( '"%s" is not a valid language code (use e.g. fr, en or pt-br). That row was not saved.', 'blocklingo' ), sanitize_text_field( $row['code'] ) ) );
				}
				continue;
			}
			if ( isset( $languages[ $code ] ) ) {
				/* translators: %s: language code. */
				add_settings_error( Languages::OPTION, 'duplicate', sprintf( __( 'The language %s was listed twice; only the first was kept.', 'blocklingo' ), $code ) );
				continue;
			}
			$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
			$slug = '' !== $slug ? $slug : $code;
			if ( isset( $slugs[ $slug ] ) ) {
				/* translators: %s: URL prefix. */
				add_settings_error( Languages::OPTION, 'slug', sprintf( __( 'Two languages had the URL prefix "%s"; the second now uses its code.', 'blocklingo' ), $slug ) );
				$slug = $code;
			}
			$slugs[ $slug ]     = true;
			$languages[ $code ] = [
				'code'    => $code,
				'name'    => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
				'locale'  => preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $row['locale'] ?? '' ) ),
				'slug'    => $slug,
				'enabled' => ! empty( $row['enabled'] ),
			];
			if ( '' === $languages[ $code ]['name'] ) {
				$languages[ $code ]['name'] = $code;
			}
		}

		$post_types = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [] ) ), array_keys( self::post_type_choices() ) ) );

		$settings = Languages::normalize(
			[
				'languages'      => array_values( $languages ),
				'default'        => Languages::sanitize_code( (string) ( $input['default'] ?? '' ) ),
				'prefix_default' => ! empty( $input['prefix_default'] ),
				'post_types'     => $post_types,
			]
		);

		$previous = Languages::default_code();
		if ( $settings['default'] !== $previous && get_option( Languages::OPTION ) ) {
			add_settings_error(
				Languages::OPTION,
				'default-changed',
				sprintf(
					/* translators: 1: new default language, 2: previous default language. */
					__( 'The default language is now %1$s. Existing content is treated as written in %1$s; nothing was changed or lost, and choosing %2$s again restores it as it was. To turn translations into content, use "Change the default language" below instead.', 'blocklingo' ),
					$settings['languages'][ $settings['default'] ]['name'],
					Languages::get( $previous )['name'] ?? $previous
				),
				'warning'
			);
		}
		return $settings;
	}

	/**
	 * After the first save.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 */
	public static function after_add( $option, $value ): void {
		self::after_save( [], $value );
	}

	/**
	 * After saving: forget the cached settings and fetch WordPress's own translations for
	 * new languages, so its texts (dates, "Menu", form messages) appear in them too.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public static function after_save( $old_value, $value ): void {
		Languages::flush();
		if ( ! current_user_can( 'install_languages' ) || ! is_array( $value ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		if ( ! wp_can_install_language_pack() ) {
			return;
		}
		$installed = get_available_languages();
		foreach ( (array) ( $value['languages'] ?? [] ) as $language ) {
			$locale = (string) ( $language['locale'] ?? '' );
			if ( ! empty( $language['enabled'] ) && '' !== $locale && 'en_US' !== $locale && ! in_array( $locale, $installed, true ) ) {
				wp_download_language_pack( $locale );
			}
		}
	}

	/**
	 * Public post types that can be translated.
	 *
	 * @return array<string, string> Name → label.
	 */
	private static function post_type_choices(): array {
		$choices = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $post_type ) {
			if ( 'attachment' !== $post_type->name ) {
				$choices[ $post_type->name ] = $post_type->labels->name;
			}
		}
		return $choices;
	}

	/**
	 * Locales WordPress has translations for: code → native name.
	 *
	 * @return array<string, string>
	 */
	private static function locale_choices(): array {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$choices = [ 'en_US' => 'English (United States)' ];
		foreach ( wp_get_available_translations() as $locale => $translation ) {
			$choices[ $locale ] = $translation['native_name'];
		}
		foreach ( get_available_languages() as $locale ) {
			$choices[ $locale ] = $choices[ $locale ] ?? $locale;
		}
		asort( $choices );
		return $choices;
	}

	/**
	 * One row of the languages table.
	 *
	 * @param string $index    Row index (or __INDEX__ for the template).
	 * @param array  $language Language.
	 * @param bool   $is_default Whether it is the default.
	 * @param array  $locales  Locale choices.
	 */
	private static function render_row( string $index, array $language, bool $is_default, array $locales ): void {
		$name = Languages::OPTION . '[languages][' . $index . ']';
		?>
		<tr class="blocklingo-language">
			<td><input type="radio" name="<?php echo esc_attr( Languages::OPTION ); ?>[default]" value="<?php echo esc_attr( $language['code'] ); ?>" <?php checked( $is_default ); ?> aria-label="<?php esc_attr_e( 'Default language', 'blocklingo' ); ?>" class="blocklingo-default" /></td>
			<td><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $language['enabled'] || $is_default ); ?> aria-label="<?php esc_attr_e( 'Enabled', 'blocklingo' ); ?>" /></td>
			<td><input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $language['name'] ); ?>" placeholder="<?php esc_attr_e( 'Français', 'blocklingo' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'blocklingo' ); ?>" /></td>
			<td><input type="text" class="small-text blocklingo-code" name="<?php echo esc_attr( $name ); ?>[code]" value="<?php echo esc_attr( $language['code'] ); ?>" placeholder="fr" aria-label="<?php esc_attr_e( 'Code', 'blocklingo' ); ?>" /></td>
			<td><input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( $language['slug'] ); ?>" placeholder="fr" aria-label="<?php esc_attr_e( 'URL prefix', 'blocklingo' ); ?>" /></td>
			<td>
				<select name="<?php echo esc_attr( $name ); ?>[locale]" aria-label="<?php esc_attr_e( 'WordPress language', 'blocklingo' ); ?>">
					<?php foreach ( $locales as $locale => $label ) : ?>
						<option value="<?php echo esc_attr( $locale ); ?>" <?php selected( $language['locale'], $locale ); ?>><?php echo esc_html( $label . ' (' . $locale . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><button type="button" class="button-link button-link-delete blocklingo-remove"><?php esc_html_e( 'Remove', 'blocklingo' ); ?></button></td>
		</tr>
		<?php
	}

	/**
	 * Tabs: languages and strings.
	 *
	 * @param string $current Current tab.
	 */
	private static function render_tabs( string $current ): void {
		$tabs = [
			'languages' => __( 'Languages', 'blocklingo' ),
			'strings'   => __( 'Strings', 'blocklingo' ),
		];
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( add_query_arg( 'tab', $tab, admin_url( 'options-general.php?page=' . self::SLUG ) ) ),
				$tab === $current ? ' nav-tab-active' : '',
				$tab === $current ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * The screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab      = isset( $_GET['tab'] ) && 'strings' === $_GET['tab'] ? 'strings' : 'languages'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- choosing a tab to show.
		$settings = Languages::settings();
		$locales  = 'languages' === $tab ? self::locale_choices() : [];
		$empty    = [
			'code'    => '',
			'name'    => '',
			'locale'  => '',
			'slug'    => '',
			'enabled' => true,
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Blocklingo', 'blocklingo' ); ?></h1>
			<?php self::render_tabs( $tab ); ?>
			<?php
			if ( 'strings' === $tab ) {
				settings_errors( Strings::OPTION );
				Strings_Page::render();
				echo '</div>';
				return;
			}
			?>
			<?php
			$result = get_transient( self::result_key() );
			if ( is_array( $result ) ) {
				delete_transient( self::result_key() );
				printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $result['type'] ), esc_html( $result['message'] ) );
			}
			?>
			<p><?php esc_html_e( 'Content is written in the default language; the other languages are translations of it. Translate in the block editor with the language switch at the top.', 'blocklingo' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::SLUG ); ?>
				<h2><?php esc_html_e( 'Languages', 'blocklingo' ); ?></h2>
				<table class="widefat striped blocklingo-languages">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Default', 'blocklingo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'On', 'blocklingo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'blocklingo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Code', 'blocklingo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'URL prefix', 'blocklingo' ); ?></th>
							<th scope="col"><?php esc_html_e( 'WordPress language', 'blocklingo' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'blocklingo' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$index = 0;
						foreach ( $settings['languages'] as $language ) {
							self::render_row( (string) $index++, $language, $language['code'] === $settings['default'], $locales );
						}
						?>
					</tbody>
				</table>
				<template id="blocklingo-row-template"><?php self::render_row( '__INDEX__', $empty, false, $locales ); ?></template>
				<p><button type="button" class="button blocklingo-add"><?php esc_html_e( 'Add language', 'blocklingo' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'A language that is off is hidden from visitors; its translations are kept. WordPress\'s own translations for each language are installed on save when possible.', 'blocklingo' ); ?></p>

				<h2><?php esc_html_e( 'Addresses', 'blocklingo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Default language', 'blocklingo' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Languages::OPTION ); ?>[prefix_default]" value="1" <?php checked( $settings['prefix_default'] ); ?> />
								<?php esc_html_e( 'Also give the default language a prefix (example.com/en/ instead of example.com/)', 'blocklingo' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Old addresses without a prefix are then redirected permanently.', 'blocklingo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Translate', 'blocklingo' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Post types with translated titles and addresses', 'blocklingo' ); ?></legend>
								<?php foreach ( self::post_type_choices() as $post_type => $label ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( Languages::OPTION ); ?>[post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Machine translation (optional)', 'blocklingo' ); ?></h2>
				<p><?php esc_html_e( 'With a DeepL API key, the Translate sidebar in the editor can fill in blocks that have no translation yet. Texts are sent to DeepL only when you press that button. Free keys from deepl.com translate 500,000 characters a month.', 'blocklingo' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="blocklingo-deepl-key"><?php esc_html_e( 'DeepL API key', 'blocklingo' ); ?></label></th>
						<td>
							<?php $saved = Machine_Translation::key(); ?>
							<input type="password" class="regular-text" id="blocklingo-deepl-key" name="<?php echo esc_attr( Machine_Translation::OPTION ); ?>[key]" value="" autocomplete="off" />
							<?php if ( '' !== $saved ) : ?>
								<p class="description">
									<?php
									/* translators: %s: last characters of the saved key. */
									echo esc_html( sprintf( __( 'A key ending in %s is saved. Leave the field empty to keep it.', 'blocklingo' ), substr( $saved, -4 ) ) );
									?>
								</p>
								<label><input type="checkbox" name="<?php echo esc_attr( Machine_Translation::OPTION ); ?>[remove]" value="1" /> <?php esc_html_e( 'Remove the saved key', 'blocklingo' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php self::render_switch( $settings ); ?>
		</div>
		<?php
	}
}
