<?php
/**
 * The other languages' addresses in WordPress's own sitemap.
 *
 * @package Blocklingo
 */

namespace Blocklingo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the provider once WordPress's sitemaps start. WordPress lists every post in the
 * default language; this adds /wp-sitemap-blocklingo-fr-1.xml and so on, one sitemap per
 * other language, with the home page and every translated post type in that language.
 */
class Sitemap {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_sitemaps_init', [ __CLASS__, 'register' ] );
	}

	/**
	 * Adds the provider.
	 *
	 * @param \WP_Sitemaps $sitemaps WordPress's sitemaps.
	 */
	public static function register( $sitemaps ): void {
		if ( ! Languages::is_multilingual() || ! class_exists( '\WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once __DIR__ . '/class-sitemap-provider.php';
		$sitemaps->registry->add_provider( Sitemap_Provider::NAME, new Sitemap_Provider() );
	}
}
