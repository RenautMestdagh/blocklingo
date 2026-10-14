<?php
/**
 * The Language Switcher block.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block (built to build/blocks/language-switcher) and renders it.
 */
class Switcher_Block {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	/**
	 * Registers the block type from its block.json.
	 */
	public static function register(): void {
		$dir = BLOCKLINGO_DIR . '/build/blocks/language-switcher';
		if ( file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir, [ 'render_callback' => [ __CLASS__, 'render' ] ] );
		}
	}

	/**
	 * One link per enabled language, to the page being shown in that language.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ): string {
		$languages = Languages::enabled();
		if ( count( $languages ) < 2 ) {
			return '';
		}
		$label   = ( $attributes['label'] ?? 'name' ) === 'code' ? 'code' : 'name';
		$current = Links::lang();
		$items   = '';
		foreach ( $languages as $code => $language ) {
			$is_current = $code === $current;
			if ( $is_current && empty( $attributes['showCurrent'] ) ) {
				continue;
			}
			$text   = 'code' === $label ? strtoupper( $code ) : $language['name'];
			$items .= sprintf(
				'<li class="wp-block-blocklingo-language-switcher__item%1$s"><a href="%2$s" hreflang="%3$s" lang="%3$s"%4$s%5$s>%6$s</a></li>',
				$is_current ? ' is-current' : '',
				esc_url( Seo::current_url_in( $code ) ),
				esc_attr( Languages::html_lang( $code ) ),
				$is_current ? ' aria-current="true"' : '',
				'code' === $label ? ' title="' . esc_attr( $language['name'] ) . '"' : '',
				esc_html( $text )
			);
		}
		return sprintf(
			'<nav %1$s><ul class="wp-block-blocklingo-language-switcher__list">%2$s</ul></nav>',
			get_block_wrapper_attributes( [ 'aria-label' => esc_attr__( 'Language', 'blocklingo' ) ] ),
			$items
		);
	}
}
