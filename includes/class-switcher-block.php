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
	 * One link per enabled language, to the page being shown in that language: as a list
	 * (horizontal or vertical), or as a dropdown that shows the current language and opens
	 * the list.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ): string {
		$languages = Languages::enabled();
		if ( count( $languages ) < 2 ) {
			return '';
		}
		$by_code  = ( $attributes['label'] ?? 'name' ) === 'code';
		$dropdown = ( $attributes['display'] ?? 'list' ) === 'dropdown';
		$vertical = ( $attributes['orientation'] ?? 'horizontal' ) === 'vertical';
		$current  = Links::lang();
		$items    = '';
		foreach ( $languages as $code => $language ) {
			$is_current = $code === $current;
			if ( $is_current && empty( $attributes['showCurrent'] ) ) {
				continue;
			}
			$items .= sprintf(
				'<li class="wp-block-blocklingo-language-switcher__item%1$s"><a href="%2$s" hreflang="%3$s" lang="%3$s"%4$s%5$s>%6$s</a></li>',
				$is_current ? ' is-current' : '',
				esc_url( Seo::current_url_in( $code ) ),
				esc_attr( Languages::html_lang( $code ) ),
				$is_current ? ' aria-current="true"' : '',
				$by_code ? ' title="' . esc_attr( $language['name'] ) . '"' : '',
				esc_html( $by_code ? strtoupper( $code ) : $language['name'] )
			);
		}

		$list = sprintf(
			'<ul class="wp-block-blocklingo-language-switcher__list%1$s">%2$s</ul>',
			$vertical || $dropdown ? ' is-vertical' : '',
			$items
		);
		if ( $dropdown && isset( $languages[ $current ] ) ) {
			$name = $languages[ $current ]['name'];
			// Voice control users say what they see ("FR"): the name a screen reader hears starts with it.
			$list = sprintf(
				'<div class="wp-block-blocklingo-language-switcher__dropdown"><button type="button" class="wp-block-blocklingo-language-switcher__toggle" lang="%1$s"%2$s>%3$s</button>%4$s</div>',
				esc_attr( Languages::html_lang( $current ) ),
				$by_code ? ' aria-label="' . esc_attr( strtoupper( $current ) . ', ' . $name ) . '"' : '',
				esc_html( $by_code ? strtoupper( $current ) : $name ),
				$list
			);
		}

		$label = esc_attr__( 'Language', 'blocklingo' );
		// Outside a block render (called from PHP) there are no block supports to apply; WordPress
		// before 6.7 even fails on it.
		$wrapper = \WP_Block_Supports::$block_to_render
			? get_block_wrapper_attributes( [ 'aria-label' => $label ] )
			: 'class="wp-block-blocklingo-language-switcher" aria-label="' . $label . '"';
		return sprintf( '<nav %1$s>%2$s</nav>', $wrapper, $list );
	}
}
