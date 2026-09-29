<?php
/**
 * Flutterwave Transaction Route.
 *
 * @package Flutterwave Payment
 */

use Flutterwave\WordPress\Helper\WebhookHelper;

/**
 * FLW Webhook Rest Route.
 */
class FLW_Webhook_Rest_Route extends WP_REST_Controller {
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
	protected $rest_base = 'webhook';

	/**
	 * Constructure for Webhook Route class.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'create_rest_routes' ) );
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
	 * Create Webhook route.
	 *
	 * @return void
	 */
	public function create_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'handle_hook' ),
				'permission_callback' => array( $this, 'free_pass' ),
			)
		);
	}

	/**
	 * Handle Webhooks from Flutterwave.
	 *
	 * @param WP_REST_Request $request The request to verify transactions.
	 */
	public function handle_hook( WP_REST_Request $request ) {
		$local_signature = $this->get_setting( 'secret_hash' );
		$signature       = (string) $request->get_header( 'verif_hash' );

		if ( ! WebhookHelper::compare_secret_hash( $local_signature, $signature ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Access Denied Hash does not match',
					'agent'   => 'Flutterwave Payments - WordPress Plugin.',
				),
				401
			);
		}

		$hook = array(
			'event' => $request->get_param( 'event' ),
			'data'  => $request->get_param( 'data' ),
		);

		if ( ! WebhookHelper::validate_hook_body( $hook ) ) {
			return wp_json_encode(
				array(
					'status'  => 'error',
					'message' => 'Hook sent does not contain an event parameter. Please assist.',
					'agent'   => 'Flutterwave Payments - WordPress Plugin.',
				)
			);
		}

		if ( 'charge.completed' !== $hook['event'] ) {
			return null;
		}

		$data = (array) $hook['data'];

		// Payments made through Easy Digital Downloads or GiveWP belong to those plugins.
		$outcome = FLW_Integrations::handle_webhook( $data );

		if ( null !== $outcome ) {
			return $this->acknowledge( $outcome );
		}

		if ( 'cancelled' === ( $data['status'] ?? '' ) ) {
			$tx_ref = sanitize_text_field( (string) ( $data['tx_ref'] ?? '' ) );
			$order  = FLW_Payment_Record::find_by_tx_ref( $tx_ref );

			if ( null !== $order ) {
				FLW_Payment_Record::cancel( $order->ID, $tx_ref );
			}

			return $this->acknowledge( 'cancelled' );
		}

		$transaction = FLW_Payment_Record::fetch_verified( absint( $data['id'] ?? 0 ) );

		if ( is_wp_error( $transaction ) ) {
			// Only reached after the secret hash matched, so this cannot be triggered anonymously.
			FLW_Signoz_Logger::instance()->track_error( 'WEBHOOK_VERIFY_FAILED', $transaction->get_error_message(), (string) ( $data['tx_ref'] ?? '' ) );
			return $this->acknowledge( 'error' );
		}

		$status = FLW_Payment_Record::apply_verified_transaction( $transaction );

		if ( is_wp_error( $status ) || 'successful' !== $status ) {
			return $this->acknowledge( 'failed' );
		}

		return $this->acknowledge( 'success' );
	}

	/**
	 * Acknowledge a webhook.
	 *
	 * @param string $status The processing outcome.
	 *
	 * @return string
	 */
	private function acknowledge( string $status ): string {
		return wp_json_encode(
			array(
				'message'  => 'Hook recieved with thanks. status: ' . $status,
				'site_url' => get_site_url(),
				'agent'    => 'Flutterwave Payments - WordPress Plugin.',
			)
		);
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
