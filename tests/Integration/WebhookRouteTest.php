<?php
/**
 * The /flutterwave/v1/webhook route.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use WP_REST_Request;

/**
 * @covers FLW_Webhook_Rest_Route
 */
class WebhookRouteTest extends TestCase {

	/**
	 * Send a webhook.
	 *
	 * @param array       $body The webhook body.
	 * @param string|null $hash The verif-hash header, or null to omit it.
	 *
	 * @return \WP_REST_Response
	 */
	private function send( array $body, ?string $hash = 'webhook-secret' ) {
		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/webhook' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		if ( null !== $hash ) {
			$request->set_header( 'verif-hash', $hash );
		}

		return rest_do_request( $request );
	}

	public function test_rejected_when_secret_hash_is_not_configured() {
		$this->configure_plugin( array( 'secret_hash' => '' ) );

		$this->assertSame( 401, $this->send( array( 'event' => 'charge.completed' ), null )->get_status() );
		$this->assertSame( 401, $this->send( array( 'event' => 'charge.completed' ), '' )->get_status() );
	}

	public function test_rejected_with_wrong_hash() {
		$this->configure_plugin();

		$this->assertSame( 401, $this->send( array( 'event' => 'charge.completed' ), 'guess' )->get_status() );
	}

	public function test_successful_charge_is_verified_with_flutterwave() {
		$this->configure_plugin();
		$post_id = $this->create_record();
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $post_id ),
			)
		);

		$response = $this->send(
			array(
				'event' => 'charge.completed',
				'data'  => array(
					'id'     => 4242,
					'status' => 'successful',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://api.flutterwave.com/v3/transactions/4242/verify', $this->http_requests[0]['url'] );
		$this->assertSame( 'successful', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_webhook_body_is_not_trusted_over_verification() {
		$this->configure_plugin();
		$post_id = $this->create_record();
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $post_id, array( 'amount' => 10 ) ),
			)
		);

		$this->send(
			array(
				'event' => 'charge.completed',
				'data'  => array(
					'id'     => 4242,
					'status' => 'successful',
					'amount' => 5000,
				),
			)
		);

		$this->assertStringStartsWith( 'paid less', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_transaction_id_is_forced_to_an_integer() {
		$this->configure_plugin();
		$this->mock_flutterwave( array( 'status' => 'error' ) );

		$this->send(
			array(
				'event' => 'charge.completed',
				'data'  => array(
					'id'     => '../../balances',
					'status' => 'successful',
				),
			)
		);

		$this->assertSame( array(), $this->http_requests );
	}

	public function test_cancelled_charge_cancels_pending_record() {
		$this->configure_plugin();
		$post_id = $this->create_record();

		$this->send(
			array(
				'event' => 'charge.completed',
				'data'  => array(
					'status' => 'cancelled',
					'tx_ref' => 'WP_TEST_' . $post_id,
				),
			)
		);

		$this->assertSame( 'cancelled', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}
}
