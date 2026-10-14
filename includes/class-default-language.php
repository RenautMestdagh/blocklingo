<?php
/**
 * Changing the language content is written in, by swapping originals and translations.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Switching the default from, say, Dutch to French: in every post, template part, menu
 * and synced pattern, the French translations become the content and the Dutch content
 * becomes the Dutch translation. Translations into other languages are kept and re-anchored
 * to the new original. Texts without a French translation, or with an outdated one, stay as
 * they are; a dry run counts them first, so they can be translated before switching.
 *
 * Posts are processed in order of ID with a checkpoint (option blocklingo_switch), so an
 * interrupted switch continues where it stopped; the default changes only at the end.
 * Content is written to the database directly: wp_update_post() would run the HTML filters
 * of the current user (none, under WP-CLI) over content that was already filtered when it
 * was saved, and would make revisions and run other plugins' save hooks.
 */
class Default_Language {

	const PROGRESS = 'blocklingo_switch';

	/**
	 * Post types with content to switch: the translated post types plus the Site Editor's.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		return array_values( array_unique( array_merge( Languages::post_types(), [ 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_block' ] ) ) );
	}

	/**
	 * Swaps one block (and its inner blocks) to a new default language.
	 *
	 * @param array  $block Parsed block.
	 * @param string $from  Current default language.
	 * @param string $to    New default language.
	 * @param array  $stats Counts: swapped, untranslated, outdated (changed by reference).
	 * @return array Parsed block.
	 */
	public static function switch_block( array $block, string $from, string $to, array &$stats ): array {
		foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $index => $inner ) {
			$block['innerBlocks'][ $index ] = self::switch_block( $inner, $from, $to, $stats );
		}
		if ( empty( $block['blockName'] ) ) {
			return $block;
		}

		$all   = $block['attrs'][ Blocks::ATTRIBUTE ] ?? [];
		$all   = is_array( $all ) ? $all : [];
		$pairs = Blocks::translations( $block, $to );

		// Report blocks with text but no translation into the new language: their text
		// will count as written in it.
		if ( ! $pairs && Blocks::translatable_attributes( (string) $block['blockName'] ) && '' !== trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) ) ) {
			++$stats['untranslated'];
		}

		// Originals already swapped in the markup, with their translation: the same text
		// twice in a block (two table cells) is replaced everywhere by its first pair.
		$done = [];
		foreach ( $pairs as $name => [ $original, $translation ] ) {
			if ( isset( $done[ $original ] ) ) {
				$swapped = $done[ $original ] === $translation;
			} elseif ( isset( $block['attrs'][ $name ] ) ) {
				$swapped = $block['attrs'][ $name ] === $original;
				if ( $swapped ) {
					$block['attrs'][ $name ] = $translation;
				}
			} else {
				$html    = (string) ( $block['innerHTML'] ?? '' );
				$replace = in_array( $name, Blocks::LINKS, true ) ? [ Blocks::class, 'replace_link' ] : [ Blocks::class, 'replace_value' ];
				$new     = $replace( $html, $original, $translation );
				$swapped = $new !== $html;
				if ( $swapped ) {
					$done[ $original ]  = $translation;
					$block['innerHTML'] = $new;
					foreach ( (array) ( $block['innerContent'] ?? [] ) as $index => $chunk ) {
						if ( is_string( $chunk ) ) {
							$block['innerContent'][ $index ] = $replace( $chunk, $original, $translation );
						}
					}
				}
			}
			if ( ! $swapped ) {
				// The original changed after it was translated: the translation is out of
				// date, so the current text stays (and is reported); the stale pair goes.
				++$stats['outdated'];
				unset( $all[ $to ][ $name ] );
				continue;
			}

			// The old default becomes a translation, of the new original.
			$all[ $from ][ $name ] = [ $translation, $original ];
			// Other languages translated the same text: anchor them to the new original.
			foreach ( $all as $code => $translations ) {
				if ( $code !== $from && $code !== $to && isset( $translations[ $name ][0] ) && $translations[ $name ][0] === $original ) {
					$all[ $code ][ $name ][0] = $translation;
				}
			}
			unset( $all[ $to ][ $name ] );
			++$stats['swapped'];
		}

		if ( isset( $all[ $to ] ) && ! $all[ $to ] ) {
			unset( $all[ $to ] );
		}
		if ( $all ) {
			$block['attrs'][ Blocks::ATTRIBUTE ] = $all;
		} else {
			unset( $block['attrs'][ Blocks::ATTRIBUTE ] );
		}
		return $block;
	}

	/**
	 * Swaps a post's content, title, excerpt and slug. Returns what changed.
	 *
	 * @param \WP_Post $post    Post.
	 * @param string   $from    Current default language.
	 * @param string   $to      New default language.
	 * @param bool     $dry_run Only count, don't write.
	 * @return array{swapped: int, untranslated: int, outdated: int, fields: int}
	 */
	public static function switch_post( \WP_Post $post, string $from, string $to, bool $dry_run = false ): array {
		global $wpdb;

		$stats  = [
			'swapped'      => 0,
			'untranslated' => 0,
			'outdated'     => 0,
			'fields'       => 0,
		];
		$update = [];

		if ( has_blocks( $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $index => $block ) {
				$blocks[ $index ] = self::switch_block( $block, $from, $to, $stats );
			}
			if ( $stats['swapped'] ) {
				$update['post_content'] = serialize_blocks( $blocks );
			}
		}

		$meta   = get_post_meta( $post->ID, Posts::META, true );
		$meta   = is_array( $meta ) ? $meta : [];
		$target = $meta[ $to ] ?? [];
		$old    = [];
		$fields = [
			'title'   => 'post_title',
			'excerpt' => 'post_excerpt',
			'slug'    => 'post_name',
		];
		foreach ( $fields as $field => $column ) {
			if ( ! empty( $target[ $field ] ) ) {
				$old[ $field ]     = (string) $post->$column;
				$update[ $column ] = (string) $target[ $field ];
				++$stats['fields'];
			}
		}

		if ( $dry_run || ! $update ) {
			return $stats;
		}

		$wpdb->update( $wpdb->posts, $update, [ 'ID' => $post->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see class description.
		if ( isset( $update['post_name'] ) && '' !== $post->post_name && $update['post_name'] !== $post->post_name ) {
			add_post_meta( $post->ID, '_wp_old_slug', $post->post_name );
		}
		unset( $meta[ $to ] );
		if ( $old ) {
			$meta[ $from ] = array_merge( $meta[ $from ] ?? [], $old );
		}
		// Through the meta API, so the slug lookup rows follow.
		update_post_meta( $post->ID, Posts::META, $meta );
		clean_post_cache( $post->ID );
		return $stats;
	}

	/**
	 * Runs (or simulates) the switch over every post, in batches, from the checkpoint.
	 *
	 * @param string        $to       New default language.
	 * @param bool          $dry_run  Only count.
	 * @param callable|null $progress Called after each batch with ( processed, total ).
	 * @return array|\WP_Error Totals.
	 */
	public static function run( string $to, bool $dry_run = false, ?callable $progress = null ) {
		if ( ! Languages::get( $to ) ) {
			return new \WP_Error( 'blocklingo_unknown_language', __( 'That language is not set up.', 'blocklingo' ) );
		}

		// An interrupted switch to the same language continues where it stopped.
		$checkpoint = get_option( self::PROGRESS, [] );
		$resuming   = ! $dry_run && is_array( $checkpoint ) && ( $checkpoint['to'] ?? '' ) === $to && ! empty( $checkpoint['from'] );
		$from       = $resuming ? (string) $checkpoint['from'] : Languages::default_code();
		$last_id    = $resuming ? (int) $checkpoint['last_id'] : 0;
		if ( $to === $from ) {
			return new \WP_Error( 'blocklingo_same_language', __( 'That is already the default language.', 'blocklingo' ) );
		}

		if ( ! $dry_run && ! $resuming ) {
			update_option(
				self::PROGRESS,
				[
					'from'    => $from,
					'to'      => $to,
					'last_id' => 0,
				],
				false
			);
			// The new default first: the old default's texts are stored as translations,
			// which is only allowed for languages other than the default.
			self::set_default( $to );
		}

		$totals = [
			'posts'        => 0,
			'swapped'      => 0,
			'untranslated' => 0,
			'outdated'     => 0,
			'fields'       => 0,
		];
		$total  = self::count_posts();
		$done   = 0;

		do {
			$ids = self::next_ids( $last_id, 50 );
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( $post ) {
					$stats = self::switch_post( $post, $from, $to, $dry_run );
					foreach ( $stats as $key => $count ) {
						$totals[ $key ] += $count;
					}
					if ( array_sum( $stats ) > 0 ) {
						++$totals['posts'];
					}
				}
				$last_id = $id;
				++$done;
			}
			if ( ! $dry_run && $ids ) {
				update_option(
					self::PROGRESS,
					[
						'from'    => $from,
						'to'      => $to,
						'last_id' => $last_id,
					],
					false
				);
			}
			if ( $progress ) {
				$progress( $done, $total );
			}
		} while ( $ids );

		if ( ! $dry_run ) {
			delete_option( self::PROGRESS );
		}
		return $totals;
	}

	/**
	 * Saves a new default language (the old one stays enabled).
	 *
	 * @param string $code Language code.
	 */
	private static function set_default( string $code ): void {
		$settings              = Languages::settings();
		$settings['default']   = $code;
		$settings['languages'] = array_values( $settings['languages'] );
		Languages::save( $settings );
		Request::detect();
	}

	/**
	 * An unfinished switch, or null: [ from, to, last_id ].
	 *
	 * @return array|null
	 */
	public static function unfinished(): ?array {
		$checkpoint = get_option( self::PROGRESS, [] );
		return is_array( $checkpoint ) && ! empty( $checkpoint['to'] ) ? $checkpoint : null;
	}

	/**
	 * Number of posts that will be looked at.
	 */
	private static function count_posts(): int {
		global $wpdb;
		$types        = self::post_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status NOT IN ('auto-draft', 'inherit')", $types ) );
	}

	/**
	 * The next batch of post IDs after a checkpoint.
	 *
	 * @param int $after Last processed ID.
	 * @param int $limit Batch size.
	 * @return int[]
	 */
	private static function next_ids( int $after, int $limit ): array {
		global $wpdb;
		$types        = self::post_types();
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$args         = array_merge( $types, [ $after, $limit ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status NOT IN ('auto-draft', 'inherit') AND ID > %d ORDER BY ID ASC LIMIT %d", $args ) ) );
	}
}
