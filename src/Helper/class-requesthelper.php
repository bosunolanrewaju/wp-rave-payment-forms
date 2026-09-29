<?php
/**
 * Request Helper class
 *
 * @package Flutterwave\WordPress\Helper;
 */

namespace Flutterwave\WordPress\Helper;

/**
 * Request Helper class.
 */
final class RequestHelper {

	/**
	 * Generate Payment Hash.
	 *
	 * @param array       $payload the payload.
	 * @param string|null $secret_key the secret key.
	 *
	 * @return string
	 */
	public static function generate_hash( array $payload, ?string $secret_key = null ): string {
		// format: sha256(amount+currency+customeremail+txref+sha256(secretkey)).
		// assumes payload has amount, currency, email, and tx_ref.
		$string_to_hash = '';
		foreach ( $payload as $value ) {
				$string_to_hash .= $value;
		}
		$string_to_hash = $string_to_hash . hash( 'sha256', $secret_key );
		return hash( 'sha256', $string_to_hash );
	}

	/**
	 * Default Payment Methods.
	 *
	 * @return string
	 */
	public static function get_default_payment_options(): string {
		return 'card, ussd, mobilemoneyghana, account, banktransfer, mpesa, mobilemoneyfranco, mobilemoneyuganda, mobilemoneyrwanda, mobilemoneyzambia';
	}
}
