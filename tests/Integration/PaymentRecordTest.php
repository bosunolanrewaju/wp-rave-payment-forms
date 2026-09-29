<?php
/**
 * FLW_Payment_Record against a real WordPress database.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Payment_Record;

/**
 * @covers FLW_Payment_Record
 */
class PaymentRecordTest extends TestCase {

	public function test_underpayment_with_forged_meta_is_not_successful() {
		$post_id = $this->create_record();
		$status  = FLW_Payment_Record::apply_verified_transaction(
			$this->transaction(
				$post_id,
				array(
					'amount' => 1,
					'meta'   => array(
						'order_id'       => $post_id,
						'order_amount'   => 1,
						'order_currency' => 'NGN',
					),
				)
			)
		);

		$this->assertStringStartsWith( 'paid less', $status );
		$this->assertStringStartsWith( 'paid less', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
	}

	public function test_transaction_cannot_target_other_post_types() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertWPError( FLW_Payment_Record::apply_verified_transaction( $this->transaction( $page_id ) ) );
		$this->assertSame( '', get_post_meta( $page_id, '_flw_rave_payment_status', true ) );
	}

	public function test_full_payment_is_successful_and_not_downgraded() {
		$post_id = $this->create_record();

		$this->assertSame( 'successful', FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id ) ) );
		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id, array( 'status' => 'failed' ) ) );

		$this->assertSame( 'successful', get_post_meta( $post_id, '_flw_rave_payment_status', true ) );
		$this->assertSame( '4242', get_post_meta( $post_id, '_flw_rave_payment_id', true ) );
	}

	public function test_find_by_tx_ref() {
		$post_id = $this->create_record();

		$this->assertSame( $post_id, FLW_Payment_Record::find_by_tx_ref( 'WP_TEST_' . $post_id )->ID );
		$this->assertNull( FLW_Payment_Record::find_by_tx_ref( 'WP_UNKNOWN' ) );
		$this->assertNull( FLW_Payment_Record::find_by_tx_ref( '' ) );
	}

	public function test_fetch_verified_returns_transaction_data() {
		$this->configure_plugin();
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => array( 'id' => 7 ),
			)
		);

		$this->assertSame( array( 'id' => 7 ), FLW_Payment_Record::fetch_verified( 7 ) );
		$this->assertSame( 'https://api.flutterwave.com/v3/transactions/7/verify', $this->http_requests[0]['url'] );
		$this->assertSame( 'Bearer FLWSECK_TEST-secret', $this->http_requests[0]['args']['headers']['Authorization'] );
	}
}
