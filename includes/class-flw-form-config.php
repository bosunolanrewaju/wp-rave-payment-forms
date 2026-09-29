<?php
/**
 * Signed payment form configuration.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signs the amount/currency rules of a rendered form so the server can
 * enforce them when the customer starts a checkout.
 */
final class FLW_Form_Config {

	/**
	 * Sign a form configuration.
	 *
	 * @param float $amount     Fixed amount, or 0 when the customer chooses the amount.
	 * @param array $currencies Currencies the customer may pay in.
	 *
	 * @return array{payload: string, signature: string}
	 */
	public static function sign( float $amount, array $currencies ): array {
		$payload = base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			wp_json_encode(
				array(
					'amount'     => $amount,
					'currencies' => array_values( $currencies ),
				)
			)
		);

		return array(
			'payload'   => $payload,
			'signature' => hash_hmac( 'sha256', $payload, self::key() ),
		);
	}

	/**
	 * Verify and decode a signed form configuration.
	 *
	 * @param string $payload   The encoded configuration.
	 * @param string $signature The signature sent with it.
	 *
	 * @return array|WP_Error
	 */
	public static function verify( string $payload, string $signature ) {
		if ( '' === $payload || '' === $signature || ! hash_equals( hash_hmac( 'sha256', $payload, self::key() ), $signature ) ) {
			return new WP_Error( 'flw-invalid-form', __( 'This payment form is invalid or has expired. Please reload the page and try again.', 'rave-payment-forms' ) );
		}

		$config = json_decode( (string) base64_decode( $payload, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! is_array( $config ) || ! isset( $config['amount'], $config['currencies'] ) || ! is_array( $config['currencies'] ) ) {
			return new WP_Error( 'flw-invalid-form', __( 'This payment form is invalid or has expired. Please reload the page and try again.', 'rave-payment-forms' ) );
		}

		return $config;
	}

	/**
	 * Work out the amount and currency to charge.
	 *
	 * A fixed amount from the form always wins over what the browser sent.
	 *
	 * @param array  $config             Verified form configuration.
	 * @param mixed  $requested_amount   Amount sent by the browser.
	 * @param string $requested_currency Currency sent by the browser.
	 *
	 * @return array{amount: float, currency: string}|WP_Error
	 */
	public static function resolve_terms( array $config, $requested_amount, string $requested_currency ) {
		$fixed_amount = (float) $config['amount'];

		if ( $fixed_amount > 0 ) {
			$amount = $fixed_amount;
		} elseif ( is_numeric( $requested_amount ) && (float) $requested_amount > 0 ) {
			$amount = round( (float) $requested_amount, 2 );
		} else {
			return new WP_Error( 'flw-invalid-amount', __( 'Please enter a valid amount.', 'rave-payment-forms' ) );
		}

		$currency = strtoupper( trim( $requested_currency ) );

		if ( ! in_array( $currency, $config['currencies'], true ) ) {
			return new WP_Error( 'flw-invalid-currency', __( 'The selected currency is not accepted by this form.', 'rave-payment-forms' ) );
		}

		return array(
			'amount'   => $amount,
			'currency' => $currency,
		);
	}

	/**
	 * Turn a comma separated currency list into a clean array.
	 *
	 * @param string $currency_list Comma separated currency codes.
	 * @param array  $fallback      Currencies to use when the list is empty.
	 *
	 * @return array
	 */
	public static function parse_currencies( string $currency_list, array $fallback = array() ): array {
		$currencies = array_filter(
			array_map(
				function ( string $currency ): string {
					return strtoupper( trim( $currency ) );
				},
				explode( ',', $currency_list )
			)
		);

		$currencies = array_values( array_unique( $currencies ) );

		return empty( $currencies ) ? array_values( $fallback ) : $currencies;
	}

	/**
	 * Signing key.
	 *
	 * @return string
	 */
	private static function key(): string {
		return wp_salt( 'auth' ) . '|flw-form-config';
	}
}
