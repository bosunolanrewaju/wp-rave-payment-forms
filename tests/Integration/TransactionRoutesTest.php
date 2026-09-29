<?php
/**
 * The verify-transaction, update-transaction and transactions routes.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use WP_REST_Request;

/**
 * @covers FLW_Transaction_Rest_Route
 */
class TransactionRoutesTest extends TestCase {

	/**
	 * Send a GET request to a plugin route.
	 *
	 * @param string $route  Route below /flutterwave/v1.
	 * @param array  $params Query parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function get( string $route, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/flutterwave/v1/' . $route );
		$request->set_query_params( $params );
		return rest_do_request( $request );
	}

	public function test_verify_redirects_to_success_and_marks_paid() {
		$this->configure_plugin();
		$post_id = $this->create_record();
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $post_id ),
			)
		);

		$response = $this->get(
			'verify-transaction',
			array(
				'order'          => $post_id,
				'tx_ref'         => 'WP_TEST_' . $post_id,
				'transaction_id' => 4242,
				'status'         => 'successful',
			)
		);

		$this->assertSame( 302, $response->get_status() );
		$this->assertSame( 'https://example.com/success', $response->get_headers()['Location'] );
		$this->assertSame( 'successful', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_verify_rejects_transaction_for_another_tx_ref() {
		$this->configure_plugin();
		$post_id = $this->create_record();
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $post_id, array( 'tx_ref' => 'ATTACKER_TX' ) ),
			)
		);

		$response = $this->get(
			'verify-transaction',
			array(
				'order'          => $post_id,
				'tx_ref'         => 'WP_TEST_' . $post_id,
				'transaction_id' => 4242,
			)
		);

		$this->assertSame( 'https://example.com/failed?status=failed', $response->get_headers()['Location'] );
		$this->assertSame( 'pending', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_verify_cancel_requires_matching_tx_ref() {
		$this->configure_plugin();
		$post_id = $this->create_record();

		$this->get(
			'verify-transaction',
			array(
				'order'  => $post_id,
				'tx_ref' => 'WRONG',
				'status' => 'cancelled',
			)
		);
		$this->assertSame( 'pending', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );

		$this->get(
			'verify-transaction',
			array(
				'order'  => $post_id,
				'tx_ref' => 'WP_TEST_' . $post_id,
				'status' => 'cancelled',
			)
		);
		$this->assertSame( 'cancelled', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	/**
	 * @dataProvider admin_routes
	 *
	 * @param string $route Route below /flutterwave/v1.
	 */
	public function test_admin_routes_require_manage_options( string $route ) {
		$this->configure_plugin();
		$post_id = $this->create_record();

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->get( $route, array( 'post_id' => $post_id ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->get( $route, array( 'post_id' => $post_id ) )->get_status() );
	}

	/**
	 * Admin-only routes.
	 *
	 * @return array
	 */
	public static function admin_routes(): array {
		return array(
			'update-transaction' => array( 'update-transaction' ),
			'transactions'       => array( 'transactions' ),
		);
	}

	public function test_update_transaction_uses_stored_tx_ref() {
		$this->configure_plugin();
		$post_id = $this->create_record();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $post_id ),
			)
		);

		$response = $this->get(
			'update-transaction',
			array(
				'post_id' => $post_id,
				'tx_ref'  => 'ignored&evil=1',
			)
		);

		$this->assertSame( 302, $response->get_status() );
		$this->assertSame(
			'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=WP_TEST_' . $post_id,
			$this->http_requests[0]['url']
		);
		$this->assertSame( 'successful', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_transactions_page_param_is_numeric() {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->mock_flutterwave( array( 'status' => 'success' ) );

		$this->get( 'transactions', array( 'page' => '2&status=failed' ) );

		$this->assertSame( 'https://api.flutterwave.com/v3/transactions/?page=2', $this->http_requests[0]['url'] );
	}
}
