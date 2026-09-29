<?php
/**
 * Flutterwave Handler Class.
 *
 * @package Flutterwave\WordPress\API;
 */

namespace Flutterwave\WordPress\API;

use Flutterwave\WordPress\Exception\ApiException;

/**
 * Flutterwave Handler Class.
 */
final class Handler {

	/**
	 * Handle Errors from the Flutterwave's side.
	 *
	 * @param array|WP_Error $response response from Api.
	 *
	 * @return void
	 * @throws ApiException Exception.
	 */
	public static function handle_api_errors( $response ) {
		$response_status_code = \wp_remote_retrieve_response_code( $response );

		$error_hash_table = self::get_error_hash_table();

		if ( isset( $error_hash_table[ $response_status_code ] ) && 400 !== $error_hash_table[ $response_status_code ] ) {
			throw new ApiException( $error_hash_table[ $response_status_code ] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- carries a WP_Error, never rendered directly.
		}
	}

	/**
	 * Get error table.
	 *
	 * @return \WP_Error[]
	 */
	public static function get_error_hash_table(): array {
		return array(
			500 => new \WP_Error( 'flw-unavailable', __( 'This Services are Currently Unavailable.  Please contact support.', 'rave-payment-forms' ) ),
			401 => new \WP_Error( 'flw-unauthorized', __( 'You do not have the right permission to this service. please ensure your secret_key has been supplied.', 'rave-payment-forms' ) ),
		);
	}
}
