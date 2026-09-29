<?php
/**
 * Integration test bootstrap. Loads the WordPress test suite (provided by wp-env at /wordpress-phpunit).
 *
 * @package Flutterwave\WordPress\Tests
 */

$flw_plugin_dir = dirname( __DIR__, 2 );

require_once $flw_plugin_dir . '/vendor/autoload.php';

$flw_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $flw_tests_dir ) {
	$flw_tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ? getenv( 'WP_PHPUNIT__DIR' ) : '/wordpress-phpunit';
}

if ( ! file_exists( $flw_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test suite in {$flw_tests_dir}. Run the integration tests with `npm run test:integration` (requires `npm run env:start`)." . PHP_EOL ); // phpcs:ignore
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $flw_plugin_dir . '/vendor/yoast/phpunit-polyfills' );

require_once $flw_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $flw_plugin_dir ) {
		require $flw_plugin_dir . '/rave-payment-forms.php';
	}
);

// Never sleep while verifying in tests.
tests_add_filter( 'flw_verify_delay', '__return_zero' );

require $flw_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/TestCase.php';
