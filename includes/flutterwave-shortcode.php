<?php
  /**
   * Shortcode Class
   */

  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  if ( ! class_exists( 'FLW_Shortcode' ) ) {

    class FLW_Shortcode {

      /**
       * Class instance variable
       *
       * @var $instance
       */
      protected static $instance = null;

      function __construct() {

        add_shortcode( 'flw-pay-button', array( $this, 'pay_button_shortcode' ) );

      }

      /**
       * Get the instance of this class
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
       * Generates Pay Now button from shortcode
       *
       * @param  array $attr Array of attributes from the shortcode
       *
       * @return string      Pay Now button html content
       */
      public function pay_button_shortcode( $attr, $content="" ) {

        global $admin_settings;

        if ( ! $admin_settings->is_public_key_present() ) return;

        $btn_text = empty( $content ) ? $this->pay_button_text() : $content;
        $email = $this->use_current_user_email( $attr ) ? wp_get_current_user()->user_email : '';
        $attr['logo'] = $this->get_logo_url( $attr );

        $atts = shortcode_atts( array(
          'amount'    => '',
          'cb_url'    => admin_url( 'admin-ajax.php' ),
          'country'   => $admin_settings->get_option_value( 'country' ),
          'currency'  => $admin_settings->get_option_value( 'currency' ),
          'desc'      => $admin_settings->get_option_value( 'modal_desc' ),
          'email'     => $email,
          'logo'      => '',
          'pbkey'     => $admin_settings->get_option_value( 'public_key' ),
          'title'     => $admin_settings->get_option_value( 'modal_title' ),
        ), $attr );

        $this->load_static_files( $atts );

        include_once( FLW_DIR_PATH . 'views/pay-now-form.php' );
      }

      /**
       * Loads static javascript files
       *
       * @return void
       */
      private function load_static_files( $atts ) {

        wp_enqueue_style( 'flw_css', FLW_DIR_URL . 'assets/css/flw.css' );

        wp_enqueue_script( 'flwpbf_inline_js', '//flw-pms-dev.eu-west-1.elasticbeanstalk.com/flwv3-pug/getpaidx/api/flwpbf-inline.js', array(), '1.0.0', true );
        wp_enqueue_script( 'flw_js', FLW_DIR_URL . 'assets/js/flw.js', array( 'flwpbf_inline_js', 'jquery' ), '1.0.0', true );

        wp_localize_script( 'flw_js', 'flw_payment_args', $atts );

      }

      /**
       * Get pay now button text
       *
       * @return string Button text
       */
      private function pay_button_text() {

        global $admin_settings;

        $text = $admin_settings->get_option_value( 'btn_text' );
        if ( empty( $text ) ) {
          $text = 'PAY NOW';
        }

        return $text;

      }

      /**
       * Checks if the loggedin user email should be used
       *
       * @param  array $attr attributes from shortcode
       *
       * @return boolean
       */
      private function use_current_user_email( $attr ) {

        return isset( $attr['use_current_user_email'] ) && $attr['use_current_user_email'] === 'yes';

      }

      private function get_logo_url($attr) {

        global $admin_settings;

        $logo = $admin_settings->get_option_value( 'modal_logo' );
        if ( ! empty( $attr['logo'] ) ) {
          $logo = strpos( $attr['logo'], 'http' ) != false ? $attr['logo'] : wp_get_attachment_url( $attr['logo'] );
        }

        return $logo;

      }

    }

  }
?>
