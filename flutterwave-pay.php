<?php
  /*
  Plugin Name: Flutterwave Pay
  Plugin URI: http://flutterwave.com/
  Description: A payment gateway for Flutterwave Pay.
  Version: 0.0.1
  Author: Bosun Olanrewaju
  Author URI: http://twitter.com/bosunolanrewaju
    Copyright: © 2016 Bosun Olanrewaju.
    License: MIT License
  */

  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  if ( ! defined( 'FLW_PAY_PLUGIN_FILE' ) ) {
    define( 'FLW_PAY_PLUGIN_FILE', __FILE__ );
  }

  // Plugin folder path
  if ( ! defined( 'FLW_DIR_PATH' ) ) {
    define( 'FLW_DIR_PATH', plugin_dir_path( __FILE__ ) );
  }

  //Plugin folder path
  if ( ! defined( 'FLW_DIR_URL' ) ) {
    define( 'FLW_DIR_URL', plugin_dir_url( __FILE__ ) );
  }

  require_once( FLW_DIR_PATH . 'includes/flutterwave-base-class.php' );

  global $flw_pay_class;

  $flw_pay_class = FLW_Flutterwave_Pay::get_instance();

?>
