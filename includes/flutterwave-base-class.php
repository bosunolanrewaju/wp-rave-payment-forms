<?php
  /**
   * Flutterwave Pay base class
   */

  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  if ( ! class_exists( 'FLW_Flutterwave_Pay' ) ) {

    /**
     * Main Plugin Class
     */
    class FLW_Flutterwave_Pay {

      private $plugin_name = 'flutterwave-pay';

      /**
       * Instance variable
       * @var $instance
       */
      protected static $instance = null;

      /**
       * Class constructor
       */
      function __construct() {

        $this->include_files();
        $this->init();

        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

      }

      /**
       * Includes all required files
       *
       * @return void
       */
      private function include_files() {

        require_once( FLW_DIR_PATH . 'includes/flutterwave-shortcode.php' );
        require_once( FLW_DIR_PATH . 'includes/admin-settings.php' );
        require_once( FLW_DIR_PATH . 'includes/vc-elements/simple-vc-pay-now-form.php' );

        if ( is_admin() ) {
          require_once( FLW_DIR_PATH . 'includes/flutterwave-tinymce-plugin-class.php' );
        }

      }

      /**
       * Initialize all the included classe
       *
       * @return void
       */
      private function init() {
        global $admin_settings;

        FLW_Shortcode::get_instance();
        $admin_settings = FLW_Admin_Settings::get_instance();

        if ( is_admin() ) {
          FLW_Tinymce_Plugin::get_instance();
        }


      }

      /**
       * Adds admin settings page to the dashboard
       *
       * @return void
       */
      public function admin_notices() {

        $options = get_option( 'flw_flutterwave_options' );

        if ( ! array_key_exists('public_key', $options ) || empty( $options['public_key'] ) ) {
          echo '<div class="updated"><p>';
          echo  __( 'Flutterwave Pay is installed. - ', 'flutterwave-pay' );
          echo "<a href=" . esc_url( add_query_arg( 'page', $this->plugin_name, admin_url( 'admin.php' ) ) ) . " class='button-primary'>" . __( 'Enter your Flutterwave Public Key to start accepting payments', 'flutterwave-pay' ) . "</a>";
          echo '</p></div>';
        }

      }

      /**
       * Gets the instance of this class
       *
       * @return object the single instance of this class
       */
      public static function get_instance() {

        if ( null == self::$instance ) {
          self::$instance = new self;
        }

        return self::$instance;

      }

      /**
       * Gets plugin name
       *
       * @return string The name of this plugin
       */
      public function plugin_name() {

        return $this->plugin_name;

      }


    }


  }


?>
