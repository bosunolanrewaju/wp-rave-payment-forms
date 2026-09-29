<?php
/**
 * The Flutterwave payment gateway class GiveWP registers.
 *
 * Loaded only from the givewp_register_payment_gateway action, when GiveWP's
 * PaymentGateway base class is available.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\Exceptions\PaymentGatewayException;
use Give\Framework\PaymentGateways\PaymentGateway;

/**
 * Offsite gateway: donors pay on Flutterwave's hosted checkout.
 */
class FLW_GiveWP_Gateway extends PaymentGateway {

	/**
	 * Gateway id.
	 *
	 * @return string
	 */
	public static function id(): string {
		return FLW_GiveWP::ID;
	}

	/**
	 * Gateway id.
	 *
	 * @return string
	 */
	public function getId(): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- GiveWP API.
		return self::id();
	}

	/**
	 * Name shown in the GiveWP settings.
	 *
	 * @return string
	 */
	public function getName(): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- GiveWP API.
		return 'Flutterwave';
	}

	/**
	 * Label shown to donors.
	 *
	 * @return string
	 */
	public function getPaymentMethodLabel(): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- GiveWP API.
		return __( 'Flutterwave', 'rave-payment-forms' );
	}

	/**
	 * Settings passed to the visual builder form script.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	public function formSettings( int $form_id ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- GiveWP API.
		return array(
			'message' => self::message(),
		);
	}

	/**
	 * Register the gateway on visual builder forms.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public function enqueueScript( int $form_id ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- GiveWP API.
		wp_enqueue_script( 'flw-givewp-gateway', FLW_DIR_URL . 'assets/js/givewp-gateway.js', array(), FLW_PAY_VERSION, true );
	}

	/**
	 * Markup shown on classic forms when the gateway is chosen.
	 *
	 * @param int   $form_id Form id.
	 * @param array $args    Form arguments.
	 *
	 * @return string
	 */
	public function getLegacyFormFieldMarkup( int $form_id, array $args ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- GiveWP API.
		return '<fieldset class="no-fields"><p class="flw-givewp-note">' . esc_html( self::message() ) . '</p></fieldset>';
	}

	/**
	 * Send the donor to Flutterwave.
	 *
	 * @param Donation $donation     The pending donation.
	 * @param array    $gateway_data Gateway data, including GiveWP's return URLs.
	 *
	 * @return RedirectOffsite
	 *
	 * @throws PaymentGatewayException When the payment link cannot be created.
	 */
	public function createPayment( Donation $donation, $gateway_data ): RedirectOffsite { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- GiveWP API.
		$link = FLW_GiveWP::create_payment( $donation, (array) $gateway_data );

		if ( is_wp_error( $link ) ) {
			throw new PaymentGatewayException( esc_html( $link->get_error_message() ) );
		}

		return new RedirectOffsite( $link );
	}

	/**
	 * Refunds are made from the Flutterwave dashboard.
	 *
	 * @param Donation $donation Donation.
	 *
	 * @return void
	 *
	 * @throws PaymentGatewayException Always.
	 */
	public function refundDonation( Donation $donation ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- GiveWP API.
		throw new PaymentGatewayException( esc_html__( 'Refund this donation from your Flutterwave dashboard.', 'rave-payment-forms' ) );
	}

	/**
	 * What donors see when they pick Flutterwave.
	 *
	 * @return string
	 */
	private static function message(): string {
		return __( 'You will be taken to Flutterwave to donate securely by card, bank transfer or mobile money.', 'rave-payment-forms' );
	}
}
