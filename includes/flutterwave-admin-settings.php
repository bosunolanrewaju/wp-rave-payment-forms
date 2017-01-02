<?php
  /**
   * Adming Settings Page Class
   */

  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  if ( ! class_exists( 'FLW_Admin_Settings' ) ) {

    /**
    * Admin Settings class
    */
    class FLW_Admin_Settings {

      /**
       * Class instance
       * @var $instance
       */
      public static $instance = null;

      /**
       * Admin options array
       *
       * @var array
       */
      protected $options;

      /**
       * Class constructor
       */
      private function __construct() {

        // $this->flw_add_admin_menu();
        add_action( 'admin_menu', array( $this, 'flw_add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        $this->init_settings();

      }

      /**
       * Registers admin setting
       *
       * @return void
       */
      public function register_settings() {

        register_setting( 'flw-flutterwave-pay-settings-group', 'flw_flutterwave_options' );

      }

      private function init_settings() {

        if ( false == get_option( 'flw_flutterwave_options' ) ) {
          update_option( 'flw_flutterwave_options', array() );
        }

      }

      /**
       * Fetches admin option settings from the db
       *
       * @param  string $setting The option to fetch
       *
       * @return mixed           The value of the option fetched
       */
      public function get_option_value( $attr ) {

        $options = get_option( 'flw_flutterwave_options' );

        if ( array_key_exists($attr, $options) ) {

          return $options[$attr];

        }

        return '';

      }

      /**
       * Checks if public key has been set
       *
       * @return boolean
       */
      public function is_public_key_present() {

        $options = get_option( 'flw_flutterwave_options' );

        if ( false == $options ) return false;

        return array_key_exists( 'public_key', $options ) && ! empty( $options['public_key'] );

      }

      /**
       * Get the instance of the class
       *
       * @return object   An instance of this class
       */
      public static function get_instance() {

        if ( null == self::$instance ) {

          self::$instance = new self;

        }

        return self::$instance;

      }

      /**
       * Add admin menu
       * @return void
       */
      public function flw_add_admin_menu() {

        add_menu_page(
          __( 'Flutterwave Pay', 'flw-flutterwave-pay' ),
          'Flutterwave Pay',
          'manage_options',
          'flutterwave-pay',
          array( $this, 'flw_admin_setting_page' ),
          FLW_DIR_URL . 'assets/images/flutterwave-icon.png',
          99
        );

      }

      /**
       * Admin page content
       * @return void
       */
      public function flw_admin_setting_page() {

        include_once( FLW_DIR_PATH . 'views/admin-settings-page.php' );

      }
    }

  }

?>
