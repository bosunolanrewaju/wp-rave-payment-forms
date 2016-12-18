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
          'name' => __('Flutterwave Simple Pay', 'flw-flutterwave-pay'),
          'base' => 'flw-pay-button',
          'description' => __('Flutterwave Simple Pay Now Form', 'flw-flutterwave-pay'),
          'category' => __('Flutterwave Forms', 'flw-flutterwave-pay'),
          'icon' => FLW_DIR_URL . 'assets/images/flutterwave-icon.png',
          'params' => array(
            array(
              'type' => 'textfield',
              'class' => 'title-class',
              'holder' => 'p',
              'heading' => __( 'Amount', 'flw-flutterwave-pay' ),
              'param_name' => 'amount',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( 'If left blank, user will be asked to enter the amount to complete the payment.', 'flw-flutterwave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'checkbox',
              'heading' => __( 'Use loggedin user email?', 'flw-flutterwave-pay' ),
              'description' => __( 'Check this if you want the loggedin user\'s email to be used. If unchecked, user will be asked to fill in their email address to complete payment.', 'flw-flutterwave-pay' ),
              'param_name' => 'use_current_user_email',
              'std' => '',
              'value' => array(
                __( 'Yes', 'flw-flutterwave-pay' ) => 'yes'
              ),
              'group' => 'Form Attributes'
            ),

            array(
              'type' => 'textfield',
              'heading' => __( 'Modal Title', 'flw-flutterwave-pay' ),
              'param_name' => 'title',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( '(Optional) Pay Now modal title. Default: FLW PAY', 'flw-flutterwave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'textfield',
              'heading' => __( 'Description', 'flw-flutterwave-pay' ),
              'param_name' => 'desc',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( '(Optional) Pay Now modal description. Default: FLW PAY MODAL', 'flw-flutterwave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'textfield',
              'heading' => __( 'Redirect URL', 'flw-flutterwave-pay' ),
              'param_name' => 'url',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( '(Optional) URL to redirect to after a successful charge. Default: ""', 'flw-flutterwave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'textfield',
              'heading' => __( 'Button Text', 'flw-flutterwave-pay' ),
              'param_name' => 'content',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( '(Optional) The text on the PAY NOW button. Default: "PAY NOW"', 'flw-flutterwave-pay' ),
              'admin_label' => false,
              'weight' => 0,
              'group' => 'Form Attributes',
            ),

            array(
              'type' => 'attach_image',
              'heading' => __( 'Modal Logo', 'flw-flutterwave-pay' ),
              'param_name' => 'logo',
              'value' => __( '', 'flw-flutterwave-pay' ),
              'description' => __( '(Optional) Logo that will show on the PAY NOW modal. Default: Flutterwave logo', 'flw-flutterwave-pay' ),
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
