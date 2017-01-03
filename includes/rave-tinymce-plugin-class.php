<?php
  /**
   * Tinymce plugin to add shortcode button to tinymce editor
   */
  if ( ! defined( 'ABSPATH' ) ) { exit; }

  if ( ! class_exists( 'FLW_Tinymce_Plugin' ) ) {

    /**
    * Rave Tinymce Plugin Class
    */
    class FLW_Tinymce_Plugin {

      /**
       * Class $instance
       *
       * @var null
       */
      protected static $instance = null;

      /**
       * Class constructor
       */
      function __construct() {

        add_action( 'admin_init', array( $this, 'flw_rave_shortcode_button_init' ) );

      }

      /**
       * Initialize our shortcode button
       *
       * @return void
       */
      public function flw_rave_shortcode_button_init() {

        if ( current_user_can('edit_posts') && current_user_can('edit_pages') ) {

          add_filter( 'mce_external_plugins', array( $this, 'flw_rave_register_tinymce_plugin' ) );
          add_filter( 'mce_buttons', array( $this, 'flw_rave_add_tinymce_button' ) );

        }

      }

      /**
       * Registers the tinymce plugin
       *
       * @param  array $plugins List of existing plugins
       *
       * @return array          List of all the plugins including this one
       */
      public function flw_rave_register_tinymce_plugin( $plugins ) {

        $plugins['flw_button'] = FLW_DIR_URL . 'assets/js/flw-tinymce.js';
        return $plugins;

      }

      /**
       * Adds the rave tinymce button
       *
       * @param  array $buttons Existing buttons
       *
       * @return array          Existing button including our own
       */
      public function flw_rave_add_tinymce_button( $buttons ) {

          array_push( $buttons, 'separator', 'flw_button' );
          return $buttons;

      }

      /**
       * Gets the class instance
       *
       * @return object The instance of this class
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
