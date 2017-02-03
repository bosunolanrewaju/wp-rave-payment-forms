<?php
  /**
   * Rave base class
   */

  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  if ( ! class_exists( 'FLW_Rave_Pay' ) ) {

    /**
     * Main Plugin Class
     */
    class FLW_Rave_Pay {

      private $plugin_name = 'rave-payment-forms';

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

        add_action( 'wp_ajax_process_payment', array( $this, 'process_payment' ) );
        add_action( 'wp_ajax_nopriv_process_payment', array( $this, 'process_payment' ) );

      }

      /**
       * Includes all required files
       *
       * @return void
       */
      private function include_files() {

        require_once( FLW_DIR_PATH . 'includes/rave-shortcode.php' );
        require_once( FLW_DIR_PATH . 'includes/rave-admin-settings.php' );
        require_once( FLW_DIR_PATH . 'includes/rave-payment-list-class.php' );
        require_once( FLW_DIR_PATH . 'includes/vc-elements/simple-vc-pay-now-form.php' );

        if ( is_admin() ) {
          require_once( FLW_DIR_PATH . 'includes/rave-tinymce-plugin-class.php' );
        }

      }

      /**
       * Initialize all the included classe
       *
       * @return void
       */
      private function init() {

        global $admin_settings;
        global $payment_list;

        new FLW_Rave_Shortcode;

        $admin_settings = FLW_Rave_Admin_Settings::get_instance();
        $payment_list   = FLW_Rave_Payment_List::get_instance();

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

        $options = get_option( 'flw_rave_options' );

        if ( ! array_key_exists('public_key', $options ) || empty( $options['public_key'] ) ) {
          echo '<div class="updated"><p>';
          echo  __( 'Rave payment form plugin is installed. - ', 'rave-pay' );
          echo "<a href=" . esc_url( add_query_arg( 'page', $this->plugin_name, admin_url( 'admin.php' ) ) ) . " class='button-primary'>" . __( 'Enter your Rave "Pay Button" Public Key to start accepting payments', 'rave' ) . "</a>";
          echo '</p></div>';
        }

      }

      /**
       * Processes payment record information
       *
       * @return void
       */
      public function process_payment() {

        global $admin_settings;

        check_ajax_referer( 'flw-rave-pay-nonce', 'flw_sec_code' );

        $response_code = ( $_POST['paymentType'] === 'account' ) ? $_POST['acctvalrespcode'] : $_POST['vbvrespcode'];
        $status =  ( $response_code == '00' ) ? 'successful' : 'failed';
        $args   =  array(
          'post_type'   => 'payment_list',
          'post_status' => 'publish',
          'post_title'       => $_POST['txRef'],
        );

        $payment_record_id = wp_insert_post( $args, true );

        if ( ! is_wp_error( $payment_record_id )) {

          $post_meta = array(
            '_flw_rave_payment_amount'   => $_POST['amount'],
            '_flw_rave_payment_customer' => $_POST['customer']['email'],
            '_flw_rave_payment_status'   => $status,
            '_flw_rave_payment_tx_ref'   => $_POST['txRef'],
          );

          $this->add_post_meta( $payment_record_id, $post_meta );

        }

        $redirect_url_key = $status === 'successful' ? 'success_redirect_url' : 'failed_redirect_url';

        echo json_encode( array( 'status' => $status, 'redirect_url' => $admin_settings->get_option_value( $redirect_url_key ) ) );
        die();

      }

      public static function gen_rand_string($len=4) {
        if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
            return substr( md5(rand()), 0, $len);
        }
        return bin2hex( openssl_random_pseudo_bytes( $len/2 ) );
      }

      /**
       * Adds metadata to payment list post type
       *
       * @param [int]   $post_id  The ID of the post to add metadata to
       * @param [array] $data     Collection of the data to be added to the post
       */
      private function add_post_meta( $post_id, $data ) {

        foreach ($data as $meta_key => $meta_value) {
          update_post_meta( $post_id, $meta_key, $meta_value );
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

    }


  }


?>
