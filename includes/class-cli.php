<?php
/**
 * WP-CLI commands.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Manages Blocklingo from the command line.
 */
class Cli {

	/**
	 * Registers the commands when WP-CLI runs.
	 */
	public static function init(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'blocklingo switch-default', [ __CLASS__, 'switch_default' ] );
		}
	}

	/**
	 * Makes another language the default: its translations become the content, the current
	 * content becomes a translation. Back up the database first.
	 *
	 * ## OPTIONS
	 *
	 * <language>
	 * : Code of the new default language, e.g. fr.
	 *
	 * [--dry-run]
	 * : Only count what would change.
	 *
	 * [--yes]
	 * : Don't ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blocklingo switch-default fr --dry-run
	 *     wp blocklingo switch-default fr
	 *
	 * @param string[] $args       Positional arguments.
	 * @param array    $assoc_args Flags.
	 */
	public static function switch_default( $args, $assoc_args ): void {
		$to      = Languages::sanitize_code( (string) ( $args[0] ?? '' ) );
		$dry_run = ! empty( $assoc_args['dry-run'] );

		if ( ! $dry_run ) {
			\WP_CLI::confirm(
				sprintf(
					/* translators: 1: current default language, 2: new default language. */
					__( 'Switch the default language from %1$s to %2$s? Back up the database first.', 'blocklingo' ),
					Languages::default_code(),
					$to
				),
				$assoc_args
			);
		}

		$result = Default_Language::run(
			$to,
			$dry_run,
			function ( $done, $total ) {
				\WP_CLI::log( sprintf( '%d / %d', $done, $total ) );
			}
		);
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::log( self::summary( $result ) );
		if ( $dry_run ) {
			\WP_CLI::success( __( 'Dry run: nothing was changed.', 'blocklingo' ) );
		} else {
			/* translators: %s: language code. */
			\WP_CLI::success( sprintf( __( 'The default language is now %s.', 'blocklingo' ), $to ) );
		}
	}

	/**
	 * A readable summary of a switch.
	 *
	 * @param array $totals Totals from Default_Language::run().
	 */
	public static function summary( array $totals ): string {
		return sprintf(
			/* translators: 1: posts, 2: texts swapped, 3: titles/slugs/excerpts swapped, 4: blocks without translation, 5: outdated translations. */
			__( 'Posts: %1$d. Texts swapped: %2$d. Titles, slugs and excerpts swapped: %3$d. Blocks without a translation (they keep their text): %4$d. Outdated translations (not used): %5$d.', 'blocklingo' ),
			$totals['posts'],
			$totals['swapped'],
			$totals['fields'],
			$totals['untranslated'],
			$totals['outdated']
		);
	}
}
