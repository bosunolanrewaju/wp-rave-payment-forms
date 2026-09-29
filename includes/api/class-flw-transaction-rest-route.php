<?php
/**
 * Flutterwave Transaction Route.
 *
 * @package Flutterwave Payment
 */

/**
 * FLW Transaction Rest Route.
 */
class FLW_Transaction_Rest_Route extends WP_REST_Controller {
	const PENDING = 'processing';
	const FAILED  = 'failed';
	const SUCCESS = 'successful';
	/**
	 * Payment base_url.
	 *
	 * @var string
	 */
	protected $flw_base_url = 'https://api.flutterwave.com/v3/';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'flutterwave/v1';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'transactions';


	/**
	 * Constructure for Transaction Route class.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'create_rest_routes' ) );
	}

	/**
	 * Create Rest Route.
	 *
	 * @return void
	 */
	public function create_rest_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(

				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'get_transactions_permission' ),

			)
		);

		register_rest_route(
			$this->namespace,
			'/verify-transaction',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'verifyPayment' ),
				'permission_callback' => array( $this, 'free_pass' ),

			)
		);

		register_rest_route(
			$this->namespace,
			'/update-transaction',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'update_transaction' ),
				'permission_callback' => array( $this, 'get_transactions_permission' ),
			)
		);
	}


	/**
	 * Trigger a transaction Update.
	 *
	 * Requires manage_options and a valid wp_rest nonce (the link in the transactions list carries one).
	 *
	 * @param WP_REST_Request $request The request from the transactions list.
	 */
	public function update_transaction( WP_REST_Request $request ) {
		$list_url = admin_url( 'admin.php?page=flutterwave-payments-transactions' );
		$order    = FLW_Payment_Record::get( $request->get_param( 'post_id' ) );

		if ( null === $order ) {
			return $this->redirect( $list_url );
		}

		if ( 'successful' === FLW_Payment_Record::get_status( $order->ID ) ) {
			return $this->redirect( add_query_arg( 'status', 'nope', $list_url ) );
		}

		$tx_ref      = (string) get_post_meta( $order->ID, '_flw_rave_payment_tx_ref', true );
		$transaction = FLW_Payment_Record::fetch_verified_by_reference( $tx_ref );

		if ( is_wp_error( $transaction ) || is_wp_error( FLW_Payment_Record::apply_verified_transaction( $transaction, $order->ID ) ) ) {
			return $this->redirect( add_query_arg( 'transaction_status', 'unverifed', $list_url ) );
		}

		return $this->redirect( add_query_arg( 'transaction_status', 'successful', $list_url ) );
	}

	/**
	 * Retrieve settings.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_transactions( WP_REST_Request $request ): WP_REST_Response {

		$page = max( 1, absint( $request->get_param( 'page' ) ) );

		$token = $this->get_setting( 'secret_key' );

		$response = wp_remote_get(
			add_query_arg( 'page', $page, $this->flw_base_url . 'transactions/' ),
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		return new WP_REST_Response( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	/**
	 * Route Permission.
	 *
	 * @return bool
	 */
	public function get_transactions_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Open to all.
	 *
	 * @return bool permission callback.
	 */
	public function free_pass() {
		return true;
	}

	/**
	 * Verify a payment after Flutterwave redirects the customer back.
	 *
	 * Open to all, so nothing from the query string is trusted: the transaction is
	 * fetched from Flutterwave and must match the stored record's tx_ref and amount.
	 *
	 * @param WP_REST_Request $request The request to verify transactions.
	 */
	public function verifyPayment( WP_REST_Request $request ) {
		$success_url    = $this->get_setting( 'success_redirect_url' );
		$failer_url     = $this->get_setting( 'failed_redirect_url' );
		$pending_url    = $this->get_setting( 'pending_redirect_url' );
		$order_id       = absint( $request->get_param( 'order' ) );
		$txref          = sanitize_text_field( (string) $request->get_param( 'tx_ref' ) );
		$transaction_id = absint( $request->get_param( 'transaction_id' ) );
		$status         = $request->get_param( 'status' );

		if ( 'cancelled' === $status ) {
			FLW_Payment_Record::cancel( $order_id, $txref );
			return $this->redirect( home_url() );
		}

		if ( '' === $txref || 0 === $transaction_id ) {
			return $this->redirect( home_url() );
		}

		/**
		 * Seconds to wait before verifying, giving Flutterwave time to settle the transaction.
		 *
		 * @param int $delay Delay in seconds.
		 */
		$delay = (int) apply_filters( 'flw_verify_delay', 2 );

		if ( $delay > 0 ) {
			sleep( $delay );
		}

		$transaction = FLW_Payment_Record::fetch_verified( $transaction_id );

		if ( is_wp_error( $transaction ) ) {
			FLW_Signoz_Logger::instance()->track_error( 'PAYMENT_VERIFY_FAILED', $transaction->get_error_message(), $txref );
			return $this->redirect( add_query_arg( 'status', self::PENDING, $pending_url ) );
		}

		if ( ! hash_equals( $txref, (string) ( $transaction['tx_ref'] ?? '' ) ) ) {
			return $this->redirect( add_query_arg( 'status', self::FAILED, $failer_url ) );
		}

		$result = FLW_Payment_Record::apply_verified_transaction( $transaction, $order_id );

		if ( is_wp_error( $result ) || self::SUCCESS !== $result ) {
			return $this->redirect( add_query_arg( 'status', self::FAILED, $failer_url ) );
		}

		return $this->redirect( $success_url );
	}

	/**
	 * Build a redirect response.
	 *
	 * @param string $location Where to send the browser.
	 *
	 * @return WP_REST_Response
	 */
	private function redirect( string $location ): WP_REST_Response {
		return rest_ensure_response( new WP_REST_Response( null, 302, array( 'Location' => $location ) ) );
	}

	/**
	 * Read a plugin setting.
	 *
	 * @param string $key The option key.
	 *
	 * @return string
	 */
	private function get_setting( string $key ): string {
		$options = get_option( 'flw_rave_options' );

		return is_array( $options ) ? (string) ( $options[ $key ] ?? '' ) : '';
	}
}
