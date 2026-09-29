<?php
/**
 * Unit test bootstrap. Runs without WordPress; WordPress functions are stubbed with Brain Monkey.
 *
 * @package Flutterwave\WordPress\Tests
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'FLW_PAY_VERSION' ) ) {
	define( 'FLW_PAY_VERSION', '1.0.7' );
}

if ( ! defined( 'FLW_PAY_PLUGIN_FILE' ) ) {
	define( 'FLW_PAY_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/rave-payment-forms.php' );
}

require_once __DIR__ . '/stubs.php';

$flw_plugin_dir = dirname( __DIR__, 2 );

require_once $flw_plugin_dir . '/includes/class-flw-form-config.php';
require_once $flw_plugin_dir . '/includes/class-flw-payment-record.php';
require_once $flw_plugin_dir . '/includes/class-flw-admin-settings.php';
require_once $flw_plugin_dir . '/includes/admin/class-flw-settings.php';
require_once $flw_plugin_dir . '/src/Helper/class-webhookhelper.php';
require_once $flw_plugin_dir . '/includes/observability/class-flw-signoz-logger.php';
require_once $flw_plugin_dir . '/includes/observability/class-flw-app-registration.php';
