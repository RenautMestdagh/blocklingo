<?php
/**
 * Deleting the plugin removes its settings and the translations of titles and addresses.
 * Translations inside blocks are ignored by WordPress without the plugin and disappear the
 * next time a page is saved; the original content is never touched.
 *
 * @package Blocklingo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'blocklingo_settings' );
delete_metadata( 'post', 0, 'blocklingo', '', true );
delete_metadata( 'post', 0, '_blocklingo_slug', '', true );
