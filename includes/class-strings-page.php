<?php
/**
 * Settings → Blocklingo → Strings: translating texts outside blocks.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * One table per group: the original, and a field per language.
 */
class Strings_Page {

	const OPTION_GROUP = 'blocklingo_strings';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register' ] );
	}

	/**
	 * The option, merged into what is stored on save.
	 */
	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			Strings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			]
		);
	}

	/**
	 * Merges the submitted translations into the stored ones: texts not on the screen
	 * (e.g. of a theme that is switched off) keep their translations; emptied fields
	 * remove theirs.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		// options.php has already unslashed the submitted value.
		$input  = is_array( $input ) ? $input : [];
		$stored = Strings::all();
		foreach ( $input as $code => $groups ) {
			if ( ! is_array( $groups ) ) {
				continue;
			}
			foreach ( $groups as $group => $texts ) {
				foreach ( (array) $texts as $key => $pair ) {
					unset( $stored[ $code ][ $group ][ $key ] );
				}
			}
		}
		$merged = array_replace_recursive( $stored, Strings::sanitize( $input ) );
		return Strings::sanitize( $merged );
	}

	/**
	 * The screen (inside the Blocklingo settings page).
	 */
	public static function render(): void {
		// Every language, the default too: texts are in the language they were written in.
		$languages = Languages::enabled();
		$groups    = Strings::registered();
		?>
		<p><?php esc_html_e( 'Texts that are not in blocks: the site title and tagline, and texts of your theme and plugins that support Blocklingo. An empty field shows the original.', 'blocklingo' ); ?></p>
		<?php if ( count( $languages ) < 2 ) : ?>
			<p><?php esc_html_e( 'Add a second language first.', 'blocklingo' ); ?></p>
			<?php
			return;
		endif;
		?>
		<form method="post" action="options.php">
			<?php settings_fields( self::OPTION_GROUP ); ?>
			<?php foreach ( $groups as $group => $texts ) : ?>
				<h2><?php echo esc_html( $group ); ?></h2>
				<table class="widefat striped blocklingo-strings">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Original', 'blocklingo' ); ?></th>
							<?php foreach ( $languages as $language ) : ?>
								<th scope="col"><?php echo esc_html( $language['name'] ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $texts as $key => $text ) : ?>
							<tr>
								<td>
									<?php if ( $text['label'] !== $text['original'] ) : ?>
										<strong><?php echo esc_html( $text['label'] ); ?></strong><br />
									<?php endif; ?>
									<?php echo esc_html( $text['original'] ); ?>
								</td>
								<?php foreach ( $languages as $code => $language ) : ?>
									<?php
									$name  = Strings::OPTION . '[' . $code . '][' . $group . '][' . $key . ']';
									$pair  = Strings::pair( $text['original'], (string) $group, $code );
									$value = $pair ? $pair[1] : '';
									$label = sprintf(
										/* translators: 1: language name, 2: original text. */
										__( '%1$s: %2$s', 'blocklingo' ),
										$language['name'],
										$text['label']
									);
									?>
									<td>
										<input type="hidden" name="<?php echo esc_attr( $name ); ?>[0]" value="<?php echo esc_attr( $text['original'] ); ?>" />
										<?php if ( $text['multiline'] ) : ?>
											<textarea class="large-text" rows="3" name="<?php echo esc_attr( $name ); ?>[1]" aria-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
										<?php else : ?>
											<input type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[1]" value="<?php echo esc_attr( $value ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" />
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}
}
