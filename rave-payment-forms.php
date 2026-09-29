<?php
/**
 * Plugin Name: Flutterwave Payments
 * Plugin URI: http://flutterwave.com/
 * Description: Flutterwave payment gateway forms, accept local and international payments securely.
 * Version: 1.0.7
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Flutterwave Developers
 * Author URI: https://developer.flutterwave.com/
 * Copyright: Â© 2023 Flutterwave Technology Solutions
 * License: MIT License
 * Text Domain: rave-payment-forms
 * Domain Path: i18n/languages
 * Requires at least:      5.6
 * Requires PHP:           7.4
 *
 * @package Flutterwave Payments
 **/

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FLW_PAY_PLUGIN_FILE' ) ) {
	define( 'FLW_PAY_PLUGIN_FILE', __FILE__ );
}

if ( ! class_exists( 'Flutterwave_Payments' ) ) {
	require_once dirname( FLW_PAY_PLUGIN_FILE ) . '/includes/class-flutterwave-payments.php';
	$flw_pay_class = Flutterwave_Payments::get_instance();
}
