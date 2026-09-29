<?php
/**
 * Payment record helpers.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads payment_list records and updates them only from data verified with Flutterwave.
 */
final class FLW_Payment_Record {

	const POST_TYPE = 'payment_list';

	const API_BASE_URL = 'https://api.flutterwave.com/v3/';

	/**
	 * Get a payment record.
	 *
	 * @param mixed $post_id The post id.
	 *
	 * @return WP_Post|null
	 */
	public static function get( $post_id ): ?WP_Post {
		$post_id = absint( $post_id );

		if ( 0 === $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Find a payment record by its transaction reference.
	 *
	 * @param string $tx_ref The transaction reference.
	 *
	 * @return WP_Post|null
	 */
	public static function find_by_tx_ref( string $tx_ref ): ?WP_Post {
		if ( '' === $tx_ref ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'meta_key'         => '_flw_rave_payment_tx_ref', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $tx_ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
			)
		);

		return empty( $posts ) ? null : $posts[0];
	}

	/**
	 * Current status of a payment record.
	 *
	 * @param int $post_id The post id.
	 *
	 * @return string
	 */
	public static function get_status( int $post_id ): string {
		return (string) get_post_meta( $post_id, '_flw_rave_payment_status', true );
	}

	/**
	 * Work out the record status from what was expected and what was received.
	 *
	 * @param float  $expected_amount   Amount stored when checkout started.
	 * @param string $expected_currency Currency stored when checkout started.
	 * @param float  $received_amount   Amount Flutterwave says was paid.
	 * @param string $received_currency Currency Flutterwave says was paid.
	 * @param string $gateway_status    Transaction status from Flutterwave.
	 *
	 * @return string
	 */
	public static function resolve_status( float $expected_amount, string $expected_currency, float $received_amount, string $received_currency, string $gateway_status ): string {
		if ( 'successful' !== $gateway_status ) {
			$status = sanitize_key( $gateway_status );
			return '' === $status ? 'failed' : $status;
		}

		if ( '' === $expected_currency || $received_currency !== $expected_currency ) {
			return 'currency diff';
		}

		$difference = round( $expected_amount - $received_amount, 2 );

		if ( abs( $difference ) < 0.005 ) {
			return 'successful';
		}

		if ( $difference > 0 ) {
			return 'paid less - remains' . $difference;
		}

		return 'paid more - refund' . abs( $difference );
	}

	/**
	 * Update a payment record from a transaction fetched from Flutterwave's verify endpoints.
	 *
	 * The record must exist, be a payment_list post and carry the same tx_ref as the
	 * transaction. The paid amount is compared with the amount stored on the record,
	 * never with the metadata sent along with the transaction.
	 *
	 * @param array $data     The `data` object of a Flutterwave verify response.
	 * @param int   $order_id The record to update. Falls back to data.meta.order_id.
	 *
	 * @return string|WP_Error The resulting status.
	 */
	public static function apply_verified_transaction( array $data, int $order_id = 0 ) {
		if ( 0 === $order_id ) {
			$order_id = absint( $data['meta']['order_id'] ?? 0 );
		}

		$order = self::get( $order_id );

		if ( null === $order ) {
			return new WP_Error( 'flw-unknown-order', __( 'Unknown payment record.', 'rave-payment-forms' ) );
		}

		$stored_tx_ref = (string) get_post_meta( $order->ID, '_flw_rave_payment_tx_ref', true );
		$tx_ref        = (string) ( $data['tx_ref'] ?? '' );

		if ( '' === $stored_tx_ref || ! hash_equals( $stored_tx_ref, $tx_ref ) ) {
			FLW_Signoz_Logger::instance()->track_error( 'VERIFY_REJECTED', 'Verified transaction does not belong to the payment record.', $stored_tx_ref );
			return new WP_Error( 'flw-tx-ref-mismatch', __( 'Transaction does not belong to this payment record.', 'rave-payment-forms' ) );
		}

		$current_status = self::get_status( $order->ID );

		if ( 'successful' === $current_status ) {
			return $current_status;
		}

		$status = self::resolve_status(
			(float) get_post_meta( $order->ID, '_flw_rave_payment_amount', true ),
			(string) get_post_meta( $order->ID, '_flw_rave_payment_currency', true ),
			(float) ( $data['amount'] ?? 0 ),
			(string) ( $data['currency'] ?? '' ),
			(string) ( $data['status'] ?? '' )
		);

		$post_meta = array(
			'_flw_rave_payment_status' => $status,
		);

		if ( ! empty( $data['id'] ) ) {
			$post_meta['_flw_rave_payment_id'] = absint( $data['id'] );
		}

		if ( isset( $data['customer']['name'] ) ) {
			$post_meta['_flw_rave_payment_fullname'] = sanitize_text_field( (string) $data['customer']['name'] );
		}

		if ( isset( $data['customer']['email'] ) ) {
			$post_meta['_flw_rave_payment_customer'] = sanitize_email( (string) $data['customer']['email'] );
		}

		foreach ( $post_meta as $meta_key => $meta_value ) {
			update_post_meta( $order->ID, $meta_key, $meta_value );
		}

		self::track_outcome( $status, $data );

		return $status;
	}

	/**
	 * Report a newly verified payment to the SigNoz service.
	 *
	 * @param string $status The record's new status.
	 * @param array  $data   The verified Flutterwave transaction.
	 *
	 * @return void
	 */
	private static function track_outcome( string $status, array $data ): void {
		$logger    = FLW_Signoz_Logger::instance();
		$reference = (string) ( $data['tx_ref'] ?? '' );

		if ( 'successful' === $status ) {
			// Like the WooCommerce plugin, only live payments count as transactions.
			if ( 'production' === $logger->get_current_environment() ) {
				$logger->track_transaction(
					$reference,
					(string) ( $data['currency'] ?? '' ),
					(float) ( $data['amount'] ?? 0 ),
					(string) ( $data['payment_type'] ?? 'card' ),
					(float) ( $data['app_fee'] ?? 0 )
				);
			}
			return;
		}

		// Still processing; the webhook reports the final outcome.
		if ( 'pending' === $status ) {
			return;
		}

		if ( 'successful' === ( $data['status'] ?? '' ) ) {
			$logger->track_error( 'PAYMENT_AMOUNT_MISMATCH', 'Verified payment did not match the order: ' . $status, $reference );
			return;
		}

		$logger->track_error( 'PAYMENT_FAILED', (string) ( $data['processor_response'] ?? 'Payment ' . $status ), $reference );
	}

	/**
	 * Mark a pending payment record as cancelled.
	 *
	 * @param int    $order_id The record id.
	 * @param string $tx_ref   The transaction reference, which must match the record.
	 *
	 * @return bool
	 */
	public static function cancel( int $order_id, string $tx_ref ): bool {
		$order = self::get( $order_id );

		if ( null === $order ) {
			return false;
		}

		$stored_tx_ref = (string) get_post_meta( $order->ID, '_flw_rave_payment_tx_ref', true );

		if ( '' === $stored_tx_ref || ! hash_equals( $stored_tx_ref, $tx_ref ) || 'pending' !== self::get_status( $order->ID ) ) {
			return false;
		}

		update_post_meta( $order->ID, '_flw_rave_payment_status', 'cancelled' );

		return true;
	}

	/**
	 * Fetch a transaction from Flutterwave by id.
	 *
	 * @param int $transaction_id The Flutterwave transaction id.
	 *
	 * @return array|WP_Error The transaction `data` object.
	 */
	public static function fetch_verified( int $transaction_id ) {
		if ( $transaction_id <= 0 ) {
			return new WP_Error( 'flw-invalid-transaction', __( 'Invalid transaction id.', 'rave-payment-forms' ) );
		}

		return self::fetch( self::API_BASE_URL . 'transactions/' . $transaction_id . '/verify' );
	}

	/**
	 * Fetch a transaction from Flutterwave by reference.
	 *
	 * @param string $tx_ref The transaction reference.
	 *
	 * @return array|WP_Error The transaction `data` object.
	 */
	public static function fetch_verified_by_reference( string $tx_ref ) {
		if ( '' === $tx_ref ) {
			return new WP_Error( 'flw-invalid-transaction', __( 'Invalid transaction reference.', 'rave-payment-forms' ) );
		}

		return self::fetch( add_query_arg( 'tx_ref', rawurlencode( $tx_ref ), self::API_BASE_URL . 'transactions/verify_by_reference' ) );
	}

	/**
	 * GET a verify endpoint and return its `data` object.
	 *
	 * @param string $url The endpoint.
	 *
	 * @return array|WP_Error
	 */
	private static function fetch( string $url ) {
		$options = get_option( 'flw_rave_options' );
		$token   = is_array( $options ) ? (string) ( $options['secret_key'] ?? '' ) : '';

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return new WP_Error( 'flw-unverified', __( 'Unable to verify transaction.', 'rave-payment-forms' ) );
		}

		return $body['data'];
	}
}
