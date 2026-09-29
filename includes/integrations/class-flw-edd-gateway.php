<?php
/**
 * Flutterwave gateway for Easy Digital Downloads.
 *
 * The customer checks out as usual, is sent to Flutterwave to pay, and the
 * order is completed once the payment is verified on their return or by the
 * webhook, whichever arrives first.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Easy Digital Downloads integration.
 */
class FLW_EDD_Gateway {

	/**
	 * Transaction reference prefix.
	 */
	const PREFIX = 'EDD';

	/**
	 * Gateway id in Easy Digital Downloads.
	 */
	const ID = 'flutterwave';

	/**
	 * Order meta holding the transaction reference.
	 */
	const REF_META = '_flw_tx_ref';

	/**
	 * Whether Easy Digital Downloads 3 is active.
	 *
	 * @return bool
	 */
	public static function is_host_active(): bool {
		return function_exists( 'EDD' ) && function_exists( 'edd_build_order' ) && function_exists( 'edd_get_order' );
	}

	/**
	 * The store currency.
	 *
	 * @return string
	 */
	public static function host_currency(): string {
		return function_exists( 'edd_get_currency' ) ? strtoupper( (string) edd_get_currency() ) : '';
	}

	/**
	 * Hook the gateway into Easy Digital Downloads.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'edd_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		add_action( 'edd_' . self::ID . '_cc_form', array( __CLASS__, 'checkout_note' ) );
		add_action( 'edd_gateway_' . self::ID, array( __CLASS__, 'process_purchase' ) );
	}

	/**
	 * Add the gateway to the list Easy Digital Downloads offers.
	 *
	 * @param array $gateways Registered gateways.
	 *
	 * @return array
	 */
	public static function add_gateway( $gateways ): array {
		$gateways             = is_array( $gateways ) ? $gateways : array();
		$gateways[ self::ID ] = array(
			'admin_label'    => 'Flutterwave',
			'checkout_label' => __( 'Flutterwave', 'rave-payment-forms' ),
			'supports'       => array(),
		);

		return $gateways;
	}

	/**
	 * Turn the gateway on or off in the Easy Digital Downloads payment settings.
	 *
	 * @param bool $enabled Whether customers can choose it.
	 *
	 * @return void
	 */
	public static function set_host_enabled( bool $enabled ): void {
		$gateways = (array) edd_get_option( 'gateways', array() );

		if ( $enabled ) {
			$gateways[ self::ID ] = 1;
		} else {
			unset( $gateways[ self::ID ] );
		}

		edd_update_option( 'gateways', $gateways );
	}

	/**
	 * Shown in place of card fields when the gateway is chosen.
	 *
	 * @return void
	 */
	public static function checkout_note(): void {
		printf(
			'<fieldset id="edd_cc_fields" class="edd-do-validate"><p class="flw-edd-note">%s</p></fieldset>',
			esc_html__( 'You will be taken to Flutterwave to pay securely by card, bank transfer or mobile money.', 'rave-payment-forms' )
		);
	}

	/**
	 * Create the pending order and send the customer to Flutterwave.
	 *
	 * @param array $purchase_data Purchase data from Easy Digital Downloads.
	 *
	 * @return void
	 */
	public static function process_purchase( $purchase_data ): void {
		if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'] ?? '', 'edd-gateway' ) ) {
			wp_die( esc_html__( 'Nonce verification has failed', 'rave-payment-forms' ), esc_html__( 'Error', 'rave-payment-forms' ), array( 'response' => 403 ) );
		}

		$payment_data = array(
			'price'        => $purchase_data['price'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => self::ID,
			'status'       => 'pending',
		);

		if ( ! empty( $purchase_data['date'] ) ) {
			$payment_data['date'] = $purchase_data['date'];
		}

		$order_id = (int) edd_build_order( $payment_data );
		$order    = $order_id ? edd_get_order( $order_id ) : false;

		if ( ! $order ) {
			edd_record_gateway_error( __( 'Payment Error', 'rave-payment-forms' ), __( 'Flutterwave: the order could not be created before payment.', 'rave-payment-forms' ) );
			edd_send_back_to_checkout( '?payment-mode=' . self::ID );
			return;
		}

		$tx_ref = FLW_Hosted_Checkout::reference( self::PREFIX, $order_id );
		edd_update_order_meta( $order_id, self::REF_META, $tx_ref );

		$user_info = (array) ( $purchase_data['user_info'] ?? array() );
		$link      = FLW_Hosted_Checkout::create_link(
			array(
				'tx_ref'       => $tx_ref,
				'amount'       => (float) $order->total,
				'currency'     => (string) $order->currency,
				'redirect_url' => rest_url( FLW_Settings::REST_NAMESPACE . '/return/easy-digital-downloads' ),
				'email'        => (string) $purchase_data['user_email'],
				'name'         => trim( ( $user_info['first_name'] ?? '' ) . ' ' . ( $user_info['last_name'] ?? '' ) ),
				/* translators: %s: order number. */
				'description'  => sprintf( __( 'Order #%s', 'rave-payment-forms' ), $order->get_number() ),
				'meta'         => array(
					'order_id' => $order_id,
					'source'   => 'easy-digital-downloads',
				),
			)
		);

		if ( is_wp_error( $link ) ) {
			edd_record_gateway_error( __( 'Payment Error', 'rave-payment-forms' ), $link->get_error_message(), $order_id );
			edd_update_order_status( $order_id, 'failed' );
			edd_set_error( 'flw_checkout', $link->get_error_message() );
			edd_send_back_to_checkout( '?payment-mode=' . self::ID );
			return;
		}

		// The payment link is on checkout.flutterwave.com, so wp_safe_redirect() would refuse it.
		wp_redirect( $link ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * The customer is back from Flutterwave: confirm the payment and pick the page to show.
	 *
	 * @param array $returned Parameters Flutterwave appended.
	 *
	 * @return string URL.
	 */
	public static function handle_return( array $returned ): string {
		$order = self::order( $returned['tx_ref'] );

		if ( null === $order ) {
			return edd_get_checkout_uri();
		}

		$order_id = (int) $order->id;

		// Only a transaction verified with Flutterwave changes the order; the query
		// arguments on this URL are not trusted.
		if ( 'complete' !== $order->status && $returned['transaction_id'] > 0 ) {
			$transaction = FLW_Payment_Record::fetch_verified( $returned['transaction_id'] );

			if ( ! is_wp_error( $transaction ) ) {
				self::complete( $transaction );
			}
		}

		if ( 'complete' === self::current_status( $order_id ) ) {
			$args = array( 'id' => $order_id );

			if ( method_exists( $order, 'get_receipt_hash' ) ) {
				$args['order'] = $order->get_receipt_hash();
			}

			return add_query_arg( $args, edd_get_success_page_uri() );
		}

		// A pending payment (e.g. a bank transfer still clearing) is completed later by the webhook.
		if ( 'pending' === $returned['status'] ) {
			return edd_get_success_page_uri();
		}

		// No payment-id here: Easy Digital Downloads marks the order in that argument as
		// failed, and this URL is not proof of anything. A cancelled payment is closed
		// by the webhook instead.
		return edd_get_failed_transaction_uri();
	}

	/**
	 * Complete the order a verified transaction paid for.
	 *
	 * @param array $transaction Verified transaction.
	 *
	 * @return bool Whether the order is now complete.
	 */
	public static function complete( array $transaction ): bool {
		$tx_ref = (string) ( $transaction['tx_ref'] ?? '' );
		$order  = self::order( $tx_ref );

		if ( null === $order ) {
			return false;
		}

		$order_id = (int) $order->id;
		$done     = FLW_Hosted_Checkout::with_lock(
			$tx_ref,
			static function () use ( $transaction, $tx_ref, $order, $order_id ): bool {
				// Re-read under the lock: the webhook or the return may have just completed it.
				if ( 'complete' === self::current_status( $order_id ) ) {
					return true;
				}

				$checked = FLW_Hosted_Checkout::check( $transaction, $tx_ref, (float) $order->total, (string) $order->currency );

				if ( is_wp_error( $checked ) ) {
					/* translators: %s: reason. */
					edd_insert_payment_note( $order_id, sprintf( __( 'Flutterwave payment not accepted: %s', 'rave-payment-forms' ), $checked->get_error_message() ) );
					return false;
				}

				edd_set_payment_transaction_id( $order_id, (string) $transaction['id'] );
				/* translators: %s: Flutterwave transaction id. */
				edd_insert_payment_note( $order_id, sprintf( __( 'Paid with Flutterwave. Transaction ID: %s', 'rave-payment-forms' ), $transaction['id'] ) );
				edd_update_order_status( $order_id, 'complete' );

				return true;
			}
		);

		return true === $done;
	}

	/**
	 * The customer cancelled on Flutterwave.
	 *
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return bool Whether a pending order was marked abandoned.
	 */
	public static function cancel( string $tx_ref ): bool {
		$order = self::order( $tx_ref );

		if ( null === $order ) {
			return false;
		}

		$order_id = (int) $order->id;

		return true === FLW_Hosted_Checkout::with_lock(
			$tx_ref,
			static function () use ( $order_id ): bool {
				if ( 'pending' !== self::current_status( $order_id ) ) {
					return false;
				}

				edd_update_order_status( $order_id, 'abandoned' );

				return true;
			}
		);
	}

	/**
	 * An order's status straight from the database, bypassing the object cache
	 * so a change made by a concurrent request is seen.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return string
	 */
	private static function current_status( int $order_id ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must not come from the cache.
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}edd_orders WHERE id = %d", $order_id ) );
	}

	/**
	 * The Flutterwave order a reference belongs to, checked against the stored reference.
	 *
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return EDD\Orders\Order|null
	 */
	private static function order( string $tx_ref ) {
		$order_id = FLW_Hosted_Checkout::id_from_reference( self::PREFIX, $tx_ref );
		$order    = $order_id ? edd_get_order( $order_id ) : false;

		if ( ! $order || self::ID !== $order->gateway ) {
			return null;
		}

		return hash_equals( (string) edd_get_order_meta( $order_id, self::REF_META, true ), $tx_ref ) ? $order : null;
	}
}
