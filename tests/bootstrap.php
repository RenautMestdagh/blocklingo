<?php
/**
 * PHPUnit bootstrap: WordPress's own test suite (wp-phpunit) with Blocklingo loaded as a
 * must-use plugin. Database settings in tests/wp-tests-config.php.
 *
 * @package Blocklingo
 */

$root = dirname( __DIR__ );
require_once $root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
$tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ? getenv( 'WP_PHPUNIT__DIR' ) : $root . '/vendor/wp-phpunit/wp-phpunit';

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $root ) {
		require $root . '/blocklingo.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-blocklingo-testcase.php';
