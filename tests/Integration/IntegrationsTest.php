<?php
/**
 * The Integrations screen routes and the hosted checkout the integrations share.
 *
 * Easy Digital Downloads and GiveWP are not installed in the test site, so these
 * cover the parts that do not need them; the gateways are exercised end to end
 * against the real plugins in the development site.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Hosted_Checkout;
use FLW_Integrations;
use WP_REST_Request;

/**
 * @covers FLW_Integrations
 * @covers FLW_Hosted_Checkout
 */
class IntegrationsTest extends TestCase {

	public function set_up() {
		parent::set_up();
		$this->configure_plugin();
		delete_option( FLW_Integrations::OPTION_KEY );
	}

	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * POST /integrations/{id}.
	 *
	 * @param string $id      Integration id.
	 * @param bool   $enabled Whether to switch it on.
	 *
	 * @return \WP_REST_Response
	 */
	private function toggle( string $id, bool $enabled ) {
		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/integrations/' . $id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'enabled' => $enabled ) ) );

		return rest_do_request( $request );
	}

	public function test_lists_the_catalogue_with_plugin_state() {
		$this->login_as( 'administrator' );

		$data  = rest_do_request( new WP_REST_Request( 'GET', '/flutterwave/v1/integrations' ) )->get_data();
		$items = array_column( $data['items'], null, 'id' );

		$this->assertSame( 'extension', $items['woocommerce']['kind'] );
		$this->assertSame( 'missing', $items['woocommerce']['host']['state'] );
		$this->assertSame( 'woocommerce/woocommerce', $items['woocommerce']['host']['rest'] );
		$this->assertSame( 'rave-woocommerce-payment-gateway', $items['woocommerce']['extension']['slug'] );
		$this->assertSame( 'built-in', $items['easy-digital-downloads']['kind'] );
		$this->assertFalse( $items['easy-digital-downloads']['enabled'] );
		$this->assertSame( 'built-in', $items['givewp']['kind'] );
		$this->assertCount( 8, wp_list_filter( $data['items'], array( 'kind' => 'coming-soon' ) ) );
		$this->assertContains( 'NGN', $data['currencies'] );
		$this->assertArrayHasKey( 'canInstall', $data );
	}

	public function test_routes_require_manage_options() {
		$this->login_as( 'editor' );

		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/flutterwave/v1/integrations' ) )->get_status() );
		$this->assertSame( 403, $this->toggle( 'givewp', true )->get_status() );
	}

	public function test_only_built_in_integrations_can_be_switched_on() {
		$this->login_as( 'administrator' );

		$this->assertSame( 404, $this->toggle( 'woocommerce', true )->get_status() );
		$this->assertSame( 404, $this->toggle( 'wpforms', true )->get_status() );
	}

	public function test_needs_the_host_plugin_before_switching_on() {
		$this->login_as( 'administrator' );

		$this->assertSame( 409, $this->toggle( 'easy-digital-downloads', true )->get_status() );
		$this->assertFalse( FLW_Integrations::is_enabled( 'easy-digital-downloads' ) );

		// Switching off is always allowed, e.g. after the host plugin was removed.
		$this->assertSame( 200, $this->toggle( 'easy-digital-downloads', false )->get_status() );
	}

	public function test_return_route_goes_home_for_inactive_integrations() {
		$request = new WP_REST_Request( 'GET', '/flutterwave/v1/return/givewp' );
		$request->set_query_params(
			array(
				'status'         => 'successful',
				'tx_ref'         => 'GIVE-1-abc',
				'transaction_id' => 5,
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 302, $response->get_status() );
		$this->assertSame( home_url( '/' ), $response->get_headers()['Location'] );
	}

	public function test_webhooks_for_other_payments_are_left_alone() {
		$this->assertNull( FLW_Integrations::handle_webhook( array( 'tx_ref' => 'WP_12_345' ) ) );
		// A reference from an integration whose plugin is not active is not claimed either.
		$this->assertNull( FLW_Integrations::handle_webhook( array( 'tx_ref' => 'EDD-4-abcd1234' ) ) );
	}

	public function test_reference_round_trip() {
		$ref = FLW_Hosted_Checkout::reference( 'EDD', 42 );

		$this->assertMatchesRegularExpression( '/^EDD-42-[a-z0-9]{8}$/', $ref );
		$this->assertSame( 42, FLW_Hosted_Checkout::id_from_reference( 'EDD', $ref ) );
		$this->assertSame( 0, FLW_Hosted_Checkout::id_from_reference( 'GIVE', $ref ) );
		$this->assertSame( 0, FLW_Hosted_Checkout::id_from_reference( 'EDD', 'EDD-x-abc' ) );
		$this->assertSame( 'FLW_EDD_Gateway', FLW_Integrations::for_reference( $ref ) );
		$this->assertNull( FLW_Integrations::for_reference( 'WP_12_345' ) );
	}

	/**
	 * @dataProvider transactions
	 *
	 * @param array       $overrides Transaction fields.
	 * @param string|null $error     Expected error code, or null when accepted.
	 */
	public function test_check_transaction( array $overrides, ?string $error ) {
		$transaction = array_merge(
			array(
				'id'       => 9,
				'tx_ref'   => 'EDD-1-abcdefgh',
				'status'   => 'successful',
				'amount'   => 25,
				'currency' => 'USD',
			),
			$overrides
		);

		$result = FLW_Hosted_Checkout::check( $transaction, 'EDD-1-abcdefgh', 25.0, 'usd' );

		if ( null === $error ) {
			$this->assertSame( $transaction, $result );
		} else {
			$this->assertWPError( $result );
			$this->assertSame( $error, $result->get_error_code() );
		}
	}

	/**
	 * Transactions and whether they pay for a USD 25 order.
	 *
	 * @return array
	 */
	public static function transactions(): array {
		return array(
			'exact'           => array( array(), null ),
			'overpaid'        => array( array( 'amount' => 30 ), null ),
			'short by a cent' => array( array( 'amount' => 24.99 ), 'flw-amount-mismatch' ),
			'other currency'  => array( array( 'currency' => 'NGN' ), 'flw-currency-mismatch' ),
			'failed'          => array( array( 'status' => 'failed' ), 'flw-not-successful' ),
			'other reference' => array( array( 'tx_ref' => 'EDD-1-zzzzzzzz' ), 'flw-reference-mismatch' ),
		);
	}

	/**
	 * Insert a lock row as another request would.
	 *
	 * @param string $key     Lock key.
	 * @param int    $expires Expiry timestamp.
	 */
	private function hold_lock( string $key, int $expires ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- simulates another request's lock.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => 'flw_lock_' . md5( $key ),
				'option_value' => (string) $expires,
				'autoload'     => 'off',
			)
		);
	}

	/**
	 * Whether a lock row exists.
	 *
	 * @param string $key Lock key.
	 *
	 * @return bool
	 */
	private function is_locked( string $key ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reads the lock row.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'flw_lock_' . md5( $key ) ) );
	}

	public function test_lock_runs_the_callback_and_releases() {
		$result = FLW_Hosted_Checkout::with_lock(
			'EDD-1-abcdefgh',
			function () {
				$this->assertTrue( $this->is_locked( 'EDD-1-abcdefgh' ) );
				return 'done';
			}
		);

		$this->assertSame( 'done', $result );
		$this->assertFalse( $this->is_locked( 'EDD-1-abcdefgh' ) );
	}

	public function test_lock_held_elsewhere_blocks_until_timeout() {
		$this->hold_lock( 'EDD-2-abcdefgh', time() + 30 );
		$ran = false;

		$result = FLW_Hosted_Checkout::with_lock(
			'EDD-2-abcdefgh',
			static function () use ( &$ran ) {
				$ran = true;
			},
			0
		);

		$this->assertNull( $result );
		$this->assertFalse( $ran );
		// Another request's lock is left alone.
		$this->assertTrue( $this->is_locked( 'EDD-2-abcdefgh' ) );
	}

	public function test_stale_lock_is_cleared() {
		$this->hold_lock( 'EDD-3-abcdefgh', time() - 60 );

		$this->assertSame(
			'done',
			FLW_Hosted_Checkout::with_lock(
				'EDD-3-abcdefgh',
				static function () {
					return 'done';
				},
				0
			)
		);
	}

	public function test_lock_is_released_when_the_callback_throws() {
		try {
			FLW_Hosted_Checkout::with_lock(
				'EDD-4-abcdefgh',
				static function () {
					throw new \RuntimeException( 'boom' );
				}
			);
			$this->fail( 'The exception should propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}

		$this->assertFalse( $this->is_locked( 'EDD-4-abcdefgh' ) );
	}

	public function test_create_link_sends_the_payment_to_flutterwave() {
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => array( 'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/abc' ),
			)
		);

		$link = FLW_Hosted_Checkout::create_link(
			array(
				'tx_ref'       => 'GIVE-7-abcdefgh',
				'amount'       => 50,
				'currency'     => 'ngn',
				'redirect_url' => 'https://example.com/return',
				'email'        => 'grace@example.com',
				'name'         => 'Grace',
				'meta'         => array( 'donation_id' => 7 ),
			)
		);

		$this->assertSame( 'https://checkout.flutterwave.com/v3/hosted/pay/abc', $link );
		$this->assertStringEndsWith( '/v3/payments', $this->http_requests[0]['url'] );

		$body = json_decode( $this->http_requests[0]['args']['body'], true );
		$this->assertSame( 'NGN', $body['currency'] );
		$this->assertSame( 'grace@example.com', $body['customer']['email'] );
		$this->assertSame( 7, $body['meta']['donation_id'] );
	}

	public function test_create_link_needs_a_link_back() {
		$this->mock_flutterwave(
			array(
				'status'  => 'error',
				'message' => 'Invalid currency',
			),
			400
		);

		$this->assertWPError(
			FLW_Hosted_Checkout::create_link(
				array(
					'tx_ref'       => 'EDD-1-abcdefgh',
					'amount'       => 5,
					'currency'     => 'XYZ',
					'redirect_url' => 'https://example.com/return',
				)
			)
		);
	}

	public function test_create_link_needs_keys() {
		$this->configure_plugin( array( 'secret_key' => '' ) );

		$result = FLW_Hosted_Checkout::create_link(
			array(
				'tx_ref'       => 'EDD-1-abcdefgh',
				'amount'       => 5,
				'currency'     => 'NGN',
				'redirect_url' => 'https://example.com/return',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'flw-not-configured', $result->get_error_code() );
	}
}
