<?php
/**
 * The translation tools in the block editor and the Site Editor.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the editor script (built from src/editor) with the languages it needs, and its
 * styles inside the editor canvas.
 */
class Editor {

	const HANDLE = 'blocklingo-editor';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_script' ] );
		add_action( 'enqueue_block_assets', [ __CLASS__, 'enqueue_canvas_style' ] );
	}

	/**
	 * Built asset metadata (dependencies, version), or null when not built.
	 *
	 * @return array|null
	 */
	private static function asset(): ?array {
		$file = BLOCKLINGO_DIR . '/build/editor/index.asset.php';
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$asset = require $file;
		return is_array( $asset ) ? $asset : null;
	}

	/**
	 * What the script needs to know, for users who can edit.
	 *
	 * @return array
	 */
	public static function data(): array {
		$languages = [];
		foreach ( Languages::enabled() as $code => $language ) {
			$languages[] = [
				'code' => $code,
				'name' => $language['name'],
			];
		}
		return [
			'defaultLanguage' => Languages::default_code(),
			'languages'       => $languages,
			'postTypes'       => array_values( Languages::post_types() ),
		];
	}

	/**
	 * The script, with its data and translations.
	 */
	public static function enqueue_script(): void {
		$asset = self::asset();
		if ( ! $asset || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_script( self::HANDLE, plugins_url( 'build/editor/index.js', BLOCKLINGO_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_add_inline_script( self::HANDLE, 'window.blocklingoEditor = ' . wp_json_encode( self::data() ) . ';', 'before' );
		wp_set_script_translations( self::HANDLE, 'blocklingo' );
	}

	/**
	 * Translation markers in the editor canvas (which is an iframe, so they are block assets).
	 */
	public static function enqueue_canvas_style(): void {
		if ( ! is_admin() || ! Languages::is_multilingual() ) {
			return;
		}
		$asset = self::asset();
		if ( $asset && file_exists( BLOCKLINGO_DIR . '/build/editor/index.css' ) ) {
			wp_enqueue_style( self::HANDLE, plugins_url( 'build/editor/index.css', BLOCKLINGO_FILE ), [], $asset['version'] );
		}
	}
}
