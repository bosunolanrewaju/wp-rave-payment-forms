<?php
/**
 * Flutterwave hosted checkout for the plugin integrations.
 *
 * Integrations with other plugins (Easy Digital Downloads, GiveWP) send the
 * customer to a Flutterwave payment link and confirm the payment when they come
 * back or when the webhook arrives. This class holds the parts they share.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

use Flutterwave\WordPress\API\Client;

/**
 * Creates payment links and verifies the transactions they produce.
 */
class FLW_Hosted_Checkout {

	/**
	 * Seconds after which a lock is treated as abandoned.
	 */
	const LOCK_TTL = 30;

	/**
	 * A transaction reference that carries the integration and its record id,
	 * e.g. EDD-42-a1b2c3d4, so returns and webhooks can be routed back.
	 *
	 * @param string $prefix Integration prefix.
	 * @param int    $id     The integration's record id.
	 *
	 * @return string
	 */
	public static function reference( string $prefix, int $id ): string {
		return strtoupper( $prefix ) . '-' . $id . '-' . strtolower( wp_generate_password( 8, false ) );
	}

	/**
	 * The record id a reference was made for, or 0 when it belongs to another prefix.
	 *
	 * @param string $prefix Integration prefix.
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return int
	 */
	public static function id_from_reference( string $prefix, string $tx_ref ): int {
		$pattern = '/^' . preg_quote( strtoupper( $prefix ), '/' ) . '-(\d+)-[a-z0-9]+$/';

		return 1 === preg_match( $pattern, $tx_ref, $matches ) ? (int) $matches[1] : 0;
	}

	/**
	 * Create a Flutterwave payment link.
	 *
	 * @param array $args Payment details: tx_ref, amount, currency, redirect_url, and
	 *                    optionally email, name, description and meta.
	 *
	 * @return string|WP_Error The payment link.
	 */
	public static function create_link( array $args ) {
		$secret = FLW_Settings::get( 'secret_key' );

		if ( '' === $secret ) {
			return new WP_Error( 'flw-not-configured', __( 'Flutterwave is not set up yet.', 'rave-payment-forms' ) );
		}

		$payload = array(
			'tx_ref'          => (string) $args['tx_ref'],
			'amount'          => round( (float) $args['amount'], 2 ),
			'currency'        => strtoupper( (string) $args['currency'] ),
			'redirect_url'    => (string) $args['redirect_url'],
			'payment_options' => FLW_Settings::payment_options(),
			'customer'        => array_filter(
				array(
					'email' => (string) ( $args['email'] ?? '' ),
					'name'  => (string) ( $args['name'] ?? '' ),
				)
			),
			'meta'            => (array) ( $args['meta'] ?? array() ),
			'customizations'  => array_filter(
				array(
					'title'       => '' !== FLW_Settings::get( 'modal_title' ) ? FLW_Settings::get( 'modal_title' ) : get_bloginfo( 'name' ),
					'description' => (string) ( $args['description'] ?? '' ),
					'logo'        => FLW_Settings::get( 'modal_logo' ),
				)
			),
		);

		FLW_Signoz_Logger::instance()->track_request_sent( 'POST', $payload['tx_ref'], '/payments' );

		$response = Client::get_instance( $secret )->request( '/payments', 'POST', $payload );

		if ( is_wp_error( $response ) ) {
			FLW_Signoz_Logger::instance()->track_error( 'CHECKOUT_FAILED', $response->get_error_message(), $payload['tx_ref'] );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$link = is_array( $body ) ? ( $body['data']['link'] ?? '' ) : '';

		if ( ! is_string( $link ) || 0 !== strpos( $link, 'https://' ) ) {
			$reason = is_array( $body ) && is_string( $body['message'] ?? null ) ? $body['message'] : 'No payment link returned';
			FLW_Signoz_Logger::instance()->track_error( 'CHECKOUT_FAILED', $reason, $payload['tx_ref'] );
			return new WP_Error( 'flw-no-link', __( 'Unable to start the payment. Please try again.', 'rave-payment-forms' ) );
		}

		return $link;
	}

	/**
	 * Check a verified transaction against the expected reference, amount and currency.
	 *
	 * @param array  $transaction Verified transaction `data`.
	 * @param string $tx_ref      Expected reference.
	 * @param float  $amount      Expected amount.
	 * @param string $currency    Expected currency.
	 *
	 * @return array|WP_Error
	 */
	public static function check( array $transaction, string $tx_ref, float $amount, string $currency ) {
		if ( ! hash_equals( $tx_ref, (string) ( $transaction['tx_ref'] ?? '' ) ) ) {
			return new WP_Error( 'flw-reference-mismatch', __( 'The payment reference does not match this order.', 'rave-payment-forms' ) );
		}

		if ( 'successful' !== ( $transaction['status'] ?? '' ) ) {
			return new WP_Error( 'flw-not-successful', __( 'The payment was not successful.', 'rave-payment-forms' ) );
		}

		if ( strtoupper( $currency ) !== strtoupper( (string) ( $transaction['currency'] ?? '' ) ) ) {
			return new WP_Error( 'flw-currency-mismatch', __( 'The payment was made in a different currency.', 'rave-payment-forms' ) );
		}

		// Cents are compared as integers so floating point rounding cannot pass a short payment.
		if ( (int) round( (float) ( $transaction['amount'] ?? 0 ) * 100 ) < (int) round( $amount * 100 ) ) {
			return new WP_Error( 'flw-amount-mismatch', __( 'The amount paid is less than the amount due.', 'rave-payment-forms' ) );
		}

		return $transaction;
	}

	/**
	 * Run a callback while holding a lock, so the customer's return and the
	 * webhook cannot both complete the same order when they arrive together.
	 *
	 * The lock is a row in the options table: the unique option name makes
	 * INSERT IGNORE atomic, which add_option() (check, then insert) is not. A
	 * request that finds the lock taken waits for it, then runs with fresh data.
	 *
	 * @param string   $key      What is being locked, e.g. the transaction reference.
	 * @param callable $callback Work to do while holding the lock.
	 * @param int      $wait_ms  How long to wait for the lock.
	 *
	 * @return mixed The callback's result, or null when the lock could not be taken.
	 */
	public static function with_lock( string $key, callable $callback, int $wait_ms = 5000 ) {
		global $wpdb;

		$name     = 'flw_lock_' . md5( $key );
		$deadline = microtime( true ) + $wait_ms / 1000;

		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic insert is the lock.
			$taken = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					$name,
					(string) ( time() + self::LOCK_TTL )
				)
			);

			if ( 1 === $taken ) {
				break;
			}

			// Clear a lock left behind by a request that died while holding it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lock row is never cached.
			$cleared = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value < %d", $name, time() ) );

			if ( $cleared ) {
				continue;
			}

			if ( microtime( true ) >= $deadline ) {
				return null;
			}

			usleep( 200000 );
		}//end while

		try {
			return $callback();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- releases the lock row.
			$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		}
	}

	/**
	 * The query arguments Flutterwave appends to the redirect URL.
	 *
	 * @param WP_REST_Request $request Return request.
	 *
	 * @return array{status: string, tx_ref: string, transaction_id: int}
	 */
	public static function returned( WP_REST_Request $request ): array {
		return array(
			'status'         => sanitize_key( (string) $request->get_param( 'status' ) ),
			'tx_ref'         => sanitize_text_field( (string) $request->get_param( 'tx_ref' ) ),
			'transaction_id' => absint( $request->get_param( 'transaction_id' ) ),
		);
	}
}
