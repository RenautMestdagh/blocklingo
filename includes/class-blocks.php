<?php
/**
 * Translations stored in blocks, shown on the site.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Every block can carry an attribute `blocklingo`:
 *
 *     { "fr": { "content": [ "<original>", "<translation>" ] } }
 *
 * per language and per translated attribute: the original as it was when translated, and
 * the translation. On the site the original is swapped for the translation in the block's
 * own markup (or in its attributes, for blocks rendered on the server). When the original
 * has changed since, nothing matches and the current original shows: the editor marks the
 * block as outdated. This works for any block, without knowing how it saves its content.
 */
class Blocks {

	const ATTRIBUTE = 'blocklingo';

	/**
	 * Key set on a parsed block once its translations are applied.
	 */
	const APPLIED = 'blocklingoApplied';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'register_block_type_args', [ __CLASS__, 'add_attribute' ] );
		add_filter( 'render_block_data', [ __CLASS__, 'translate_parsed_block' ], 10 );
		add_filter( 'render_block', [ __CLASS__, 'translate_rendered_block' ], 10, 2 );
	}

	/**
	 * Registers the attribute on every block type, so WordPress keeps it.
	 *
	 * @param array $args Block type arguments.
	 * @return array
	 */
	public static function add_attribute( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$args['attributes']                    = isset( $args['attributes'] ) && is_array( $args['attributes'] ) ? $args['attributes'] : [];
		$args['attributes'][ self::ATTRIBUTE ] = [ 'type' => 'object' ];
		return $args;
	}

	/**
	 * Attributes of a block type that hold text to translate: content attributes (role
	 * "content") that are strings or rich text. The same rule as the editor uses
	 * (src/editor/translations.js).
	 *
	 * @param string $block_name Block name.
	 * @return string[]
	 */
	public static function translatable_attributes( string $block_name ): array {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( ! $type || ! is_array( $type->attributes ) ) {
			return [];
		}
		$names = [];
		foreach ( $type->attributes as $name => $attribute ) {
			$role    = $attribute['role'] ?? ( $attribute['__experimentalRole'] ?? '' );
			$is_text = in_array( $attribute['type'] ?? '', [ 'string', 'rich-text' ], true ) || in_array( $attribute['source'] ?? '', [ 'html', 'rich-text', 'text' ], true );
			if ( 'content' === $role && $is_text && ! in_array( $name, [ 'url', 'href', 'src', 'id', 'ref', 'linkTarget', 'rel', 'linkClass', 'anchor' ], true ) ) {
				$names[] = (string) $name;
			}
		}
		return $names;
	}

	/**
	 * The translations of a block in a language: attribute name => [ original, translation ].
	 * Only well-formed pairs with a non-empty original.
	 *
	 * @param array  $parsed_block Parsed block.
	 * @param string $code         Language code.
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function translations( array $parsed_block, string $code ): array {
		$all = $parsed_block['attrs'][ self::ATTRIBUTE ][ $code ] ?? null;
		if ( ! is_array( $all ) ) {
			return [];
		}
		$pairs = [];
		foreach ( $all as $name => $pair ) {
			if ( is_string( $name ) && is_array( $pair ) && isset( $pair[0], $pair[1] ) && is_string( $pair[0] ) && is_string( $pair[1] ) && '' !== $pair[0] ) {
				$pairs[ $name ] = [ $pair[0], $pair[1] ];
			}
		}
		return $pairs;
	}

	/**
	 * Language whose translations are shown, or '' when the original is shown.
	 */
	private static function target_language(): string {
		if ( ! Languages::is_multilingual() || ! Request::is_frontend() ) {
			return '';
		}
		$code = Links::lang();
		return Languages::is_default( $code ) ? '' : $code;
	}

	/**
	 * Applies translations before a block renders: attributes kept in the block comment
	 * (used by blocks rendered on the server) and the block's own saved markup. Inner
	 * blocks are left to their own turn.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return array
	 */
	public static function translate_parsed_block( $parsed_block ) {
		$code = self::target_language();
		if ( '' === $code || ! is_array( $parsed_block ) || ! empty( $parsed_block[ self::APPLIED ] ) ) {
			return $parsed_block;
		}
		$pairs = self::translations( $parsed_block, $code );
		if ( ! $pairs ) {
			return $parsed_block;
		}

		foreach ( $pairs as $name => [ $original, $translation ] ) {
			if ( isset( $parsed_block['attrs'][ $name ] ) ) {
				if ( $parsed_block['attrs'][ $name ] === $original ) {
					$parsed_block['attrs'][ $name ] = $translation;
				}
				continue;
			}
			$parsed_block['innerHTML'] = self::replace( (string) ( $parsed_block['innerHTML'] ?? '' ), $original, $translation );
			foreach ( (array) ( $parsed_block['innerContent'] ?? [] ) as $index => $chunk ) {
				if ( is_string( $chunk ) ) {
					$parsed_block['innerContent'][ $index ] = self::replace( $chunk, $original, $translation );
				}
			}
		}
		$parsed_block[ self::APPLIED ] = true;
		return $parsed_block;
	}

	/**
	 * Applies translations to the rendered markup too. Needed where the render_block_data
	 * changes don't reach the output: the top level of a block list rendered directly (the
	 * links of a Navigation block), and inner blocks before WordPress 6.7, which render
	 * from a copy of their markup taken before the filter. Where the markup was already
	 * translated, the original no longer matches and this changes nothing.
	 *
	 * @param string $html         Rendered block.
	 * @param array  $parsed_block Parsed block.
	 * @return string
	 */
	public static function translate_rendered_block( $html, $parsed_block ) {
		$code = self::target_language();
		if ( '' === $code || ! is_array( $parsed_block ) || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		foreach ( self::translations( $parsed_block, $code ) as [ $original, $translation ] ) {
			$html = self::replace( $html, $original, $translation );
		}
		return $html;
	}

	/**
	 * A translation may hold no more than what WordPress allows in posts, unless its original
	 * already held more (an administrator's embed or script, saved with unfiltered HTML). So
	 * a translation can never add markup its original couldn't have, whoever stored it.
	 *
	 * @param string $original    Original markup.
	 * @param string $translation Translated markup.
	 */
	private static function safe_markup( string $original, string $translation ): string {
		if ( false === strpos( $translation, '<' ) ) {
			return $translation;
		}
		return wp_kses_post( $original ) === $original ? wp_kses_post( $translation ) : $translation;
	}

	/**
	 * Puts a translation where the original is: as the whole content of an element (rich
	 * text, also escaped plain text), or as the value of an HTML attribute (alt, title…).
	 * Never inside tag names or other attributes, so a short original like "a" is safe.
	 *
	 * @param string $html        Markup.
	 * @param string $original    Original value.
	 * @param string $translation Translated value.
	 */
	public static function replace( string $html, string $original, string $translation ): string {
		// Markup is cleaned; attribute values are escaped by the tag processor instead.
		return self::replace_value( $html, $original, self::safe_markup( $original, $translation ), $translation );
	}

	/**
	 * Puts one value where another is, without cleaning it: for content that is already
	 * stored (switching the default language), not for showing translations.
	 *
	 * @param string      $html         Markup.
	 * @param string      $from         Value to find.
	 * @param string      $to           Value to put there as element content.
	 * @param string|null $to_attribute Value to put there as an attribute value ($to when null).
	 */
	public static function replace_value( string $html, string $from, string $to, ?string $to_attribute = null ): string {
		$original    = $from;
		$translation = $to_attribute ?? $to;
		if ( '' === $html || '' === $original ) {
			return $html;
		}
		$variants = [ $original => $to ];
		$escaped  = esc_html( $original );
		if ( $escaped !== $original ) {
			$variants[ $escaped ] = esc_html( $translation );
		}
		foreach ( $variants as $find => $put ) {
			// Between the end of a tag (or the start of a chunk) and the next tag (or the end).
			$pattern = '/(?<=>|^)' . preg_quote( $find, '/' ) . '(?=<|$)/';
			$count   = 0;
			$result  = preg_replace_callback( $pattern, fn () => $put, $html, -1, $count );
			if ( $count > 0 && is_string( $result ) ) {
				return $result;
			}
		}

		/**
		 * Filters the HTML attributes that hold text visitors read or hear, and so may be
		 * translated. Never structural ones like class or href.
		 *
		 * @param string[] $attributes Attribute names.
		 */
		$text_attributes = (array) apply_filters( 'blocklingo_text_attributes', [ 'alt', 'title', 'aria-label', 'aria-description', 'placeholder', 'label', 'value', 'summary' ] );

		$tags     = new \WP_HTML_Tag_Processor( $html );
		$replaced = false;
		while ( $tags->next_tag() ) {
			foreach ( $text_attributes as $attribute ) {
				if ( $tags->get_attribute( $attribute ) === $original ) {
					$tags->set_attribute( $attribute, $translation );
					$replaced = true;
				}
			}
		}
		return $replaced ? $tags->get_updated_html() : $html;
	}
}
