<?php
  /**
   * Visual Composer element for a simple PAY NOW form
   */
  if ( ! defined( 'ABSPATH' ) ) { exit; }

  /**
   * Simple PAY NOW form Class
   */
  class FLW_VC_Simple_Form {

    /**
     * Class Constructor
     */
    function __construct() {

      add_action( 'init', array( $this, 'flw_simple_form_mapping' ) );

    }

    /**
     * Visual Composer Form elements mapping
     *
     * @return void
     */
    public function flw_simple_form_mapping() {

      // Stop all if VC is not enabled
      if ( !defined( 'WPB_VC_VERSION' ) ) {
        return;
      }

      // Map the block with vc_map()
      vc_map(
        array(
          'name' => __('Rave Simple Form', 'rave-pay'),
          'base' => 'flw-pay-button',
          'description' => __('Rave Simple Pay Now Form', 'rave-pay'),
          'category' => __('Rave Forms', 'rave-pay'),
          'icon' => FLW_DIR_URL . 'assets/images/rave-icon.png',
          'params' => array(
            array(
              'type' => 'textfield',
              'class' => 'title-class',
              'holder' => 'p',
              'heading' => __( 'Amount', 'rave-pay' ),
              'param_name' => 'amount',
              'value' => __( '', 'rave-pay' ),
              'description' => __( 'If left blank, user will be asked to enter the amount to complete the payment.', 'rave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'checkbox',
              'heading' => __( "Use logged-in user's email?", 'rave-pay' ),
              'description' => __( "Check this if you want the logged-in user's email to be used. If unchecked or user is not logged in, they will be asked to fill in their email address to complete payment.", 'rave-pay' ),
              'param_name' => 'use_current_user_email',
              'std' => '',
              'value' => array(
                __( 'Yes', 'rave-pay' ) => 'yes'
              ),
              'group' => 'Form Attributes'
            ),

            array(
              'type' => 'textfield',
              'heading' => __( 'Button Text', 'rave-pay' ),
              'param_name' => 'content',
              'value' => __( '', 'rave-pay' ),
              'description' => __( '(Optional) The text on the PAY NOW button. Default: "PAY NOW"', 'rave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

          )
        )
      );

    }

  }

  // Element Class Init
  new FLW_VC_Simple_Form();
?>
