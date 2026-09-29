<?php
/**
 * Visual Composer element for a simple PAY NOW form
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simple PAY NOW form Class.
 */
class FLW_VC_Simple_Form {


	/**
	 * Class Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'flw_simple_form_mapping' ) );
	}

	/**
	 * Visual Composer Form elements mapping.
	 *
	 * @return void
	 */
	public function flw_simple_form_mapping() {
		// Stop all if VC is not enabled.
		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			return;
		}

		// Map the block with vc_map().
		vc_map(
			array(
				'name'        => __( 'Flutterwave Simple Form', 'rave-payment-forms' ),
				'base'        => 'flw-pay-button',
				'description' => __( 'Flutterwave Simple Pay Now Form', 'rave-payment-forms' ),
				'category'    => __( 'Flutterwave Forms', 'rave-payment-forms' ),
				'icon'        => FLW_DIR_URL . 'assets/images/rave-icon.png',
				'params'      => array(
					array(
						'type'        => 'textfield',
						'class'       => 'title-class',
						'holder'      => 'p',
						'heading'     => __( 'Amount', 'rave-payment-forms' ),
						'param_name'  => 'amount',
						'value' => __('', 'rave-payment-forms'), //phpcs:ignore.
						'description' => __( 'If left blank, user will be asked to enter the amount to complete the payment.', 'rave-payment-forms' ),
						'admin_label' => false,
						'weight'      => 0,
						'group'       => 'Form Attributes',
					),

					array(
						'type'        => 'checkbox',
						'heading'     => __( "Use logged-in user's email?", 'rave-payment-forms' ),
						'description' => __( "Check this if you want the logged-in user's email to be used. If unchecked or user is not logged in, they will be asked to fill in their email address to complete payment.", 'rave-payment-forms' ),
						'param_name'  => 'use_current_user_email',
						'std'         => '',
						'value'       => array(
							__( 'Yes', 'rave-payment-forms' ) => 'yes',
						),
						'group'       => 'Form Attributes',
					),

					array(
						'type'        => 'textfield',
						'heading'     => __( 'Button Text', 'rave-payment-forms' ),
						'param_name'  => 'content',
						'value' => __('', 'rave-payment-forms'), //phpcs:ignore.
						'description' => __( '(Optional) The text on the PAY NOW button. Default: "PAY NOW"', 'rave-payment-forms' ),
						'admin_label' => false,
						'weight'      => 0,
						'group'       => 'Form Attributes',
					),

				),
			)
		);
	}
}

// Element Class Init.
new FLW_VC_Simple_Form();
