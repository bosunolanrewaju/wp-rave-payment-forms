<?php
/**
 * Tests for FLW_Payment_Record.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use FLW_Payment_Record;

/**
 * @covers FLW_Payment_Record
 */
class PaymentRecordTest extends TestCase {

	/**
	 * Set up a pending payment record.
	 */
	protected function set_up() {
		parent::set_up();

		$this->add_post(
			10,
			'payment_list',
			array(
				'_flw_rave_payment_tx_ref'   => 'WP_ABC_1',
				'_flw_rave_payment_amount'   => 50000.0,
				'_flw_rave_payment_currency' => 'NGN',
				'_flw_rave_payment_status'   => 'pending',
			)
		);
		$this->add_post( 11, 'page' );
	}

	/**
	 * Build a verified transaction payload.
	 *
	 * @param array $overrides Fields to override.
	 *
	 * @return array
	 */
	private function transaction( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'       => 99,
				'tx_ref'   => 'WP_ABC_1',
				'amount'   => 50000,
				'currency' => 'NGN',
				'status'   => 'successful',
				'meta'     => array(
					'order_id'       => 10,
					'order_amount'   => 1,
					'order_currency' => 'NGN',
				),
				'customer' => array(
					'name'  => '<img src=x onerror=alert(1)>Bob',
					'email' => 'bob@example.com',
				),
			),
			$overrides
		);
	}

	public function test_underpayment_is_not_successful_even_with_forged_meta() {
		$status = FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'amount' => 1 ) ) );

		$this->assertSame( 'paid less - remains49999', $status );
		$this->assertSame( 'paid less - remains49999', $this->meta[10]['_flw_rave_payment_status'] );
	}

	public function test_full_payment_is_successful() {
		$this->assertSame( 'successful', FLW_Payment_Record::apply_verified_transaction( $this->transaction() ) );
		$this->assertSame( 99, $this->meta[10]['_flw_rave_payment_id'] );
	}

	public function test_customer_details_are_sanitised() {
		FLW_Payment_Record::apply_verified_transaction( $this->transaction() );

		$this->assertSame( 'Bob', $this->meta[10]['_flw_rave_payment_fullname'] );
		$this->assertSame( 'bob@example.com', $this->meta[10]['_flw_rave_payment_customer'] );
	}

	public function test_other_post_types_are_rejected() {
		$result = FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'meta' => array( 'order_id' => 11 ) ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayNotHasKey( '_flw_rave_payment_status', $this->meta[11] );
	}

	public function test_unknown_record_is_rejected() {
		$this->assertInstanceOf( \WP_Error::class, FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'meta' => array( 'order_id' => 404 ) ) ) ) );
	}

	public function test_tx_ref_mismatch_is_rejected() {
		$result = FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'tx_ref' => 'SOMEONE_ELSE' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'pending', $this->meta[10]['_flw_rave_payment_status'] );
	}

	public function test_explicit_order_id_takes_precedence_over_meta() {
		$result = FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'meta' => array( 'order_id' => 11 ) ) ), 10 );

		$this->assertSame( 'successful', $result );
	}

	public function test_successful_record_is_never_downgraded() {
		FLW_Payment_Record::apply_verified_transaction( $this->transaction() );

		$this->assertSame( 'successful', FLW_Payment_Record::apply_verified_transaction( $this->transaction( array( 'status' => 'failed' ) ) ) );
		$this->assertSame( 'successful', $this->meta[10]['_flw_rave_payment_status'] );
	}

	/**
	 * @dataProvider statuses
	 *
	 * @param float  $received_amount   Amount received.
	 * @param string $received_currency Currency received.
	 * @param string $gateway_status    Flutterwave status.
	 * @param string $expected          Expected record status.
	 */
	public function test_resolve_status( float $received_amount, string $received_currency, string $gateway_status, string $expected ) {
		$this->assertSame( $expected, FLW_Payment_Record::resolve_status( 100.0, 'NGN', $received_amount, $received_currency, $gateway_status ) );
	}

	/**
	 * Status cases.
	 *
	 * @return array
	 */
	public static function statuses(): array {
		return array(
			'exact'            => array( 100.0, 'NGN', 'successful', 'successful' ),
			'float noise'      => array( 100.001, 'NGN', 'successful', 'successful' ),
			'underpaid'        => array( 60.0, 'NGN', 'successful', 'paid less - remains40' ),
			'overpaid'         => array( 150.0, 'NGN', 'successful', 'paid more - refund50' ),
			'other currency'   => array( 100.0, 'USD', 'successful', 'currency diff' ),
			'gateway failed'   => array( 100.0, 'NGN', 'failed', 'failed' ),
			'gateway empty'    => array( 100.0, 'NGN', '', 'failed' ),
			'gateway injected' => array( 100.0, 'NGN', '<b>pending</b>', 'bpendingb' ),
		);
	}

	public function test_cancel_requires_matching_tx_ref() {
		$this->assertFalse( FLW_Payment_Record::cancel( 10, 'WRONG' ) );
		$this->assertSame( 'pending', $this->meta[10]['_flw_rave_payment_status'] );

		$this->assertTrue( FLW_Payment_Record::cancel( 10, 'WP_ABC_1' ) );
		$this->assertSame( 'cancelled', $this->meta[10]['_flw_rave_payment_status'] );
	}

	public function test_cancel_leaves_completed_records_alone() {
		$this->meta[10]['_flw_rave_payment_status'] = 'successful';

		$this->assertFalse( FLW_Payment_Record::cancel( 10, 'WP_ABC_1' ) );
		$this->assertSame( 'successful', $this->meta[10]['_flw_rave_payment_status'] );
	}

	public function test_fetch_rejects_invalid_ids_without_calling_the_api() {
		\Brain\Monkey\Functions\expect( 'wp_safe_remote_get' )->never();

		$this->assertInstanceOf( \WP_Error::class, FLW_Payment_Record::fetch_verified( 0 ) );
		$this->assertInstanceOf( \WP_Error::class, FLW_Payment_Record::fetch_verified_by_reference( '' ) );
	}

	public function test_fetch_uses_numeric_path_and_secret_key() {
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'secret_key' => 'FLWSECK_TEST-x' ) );
		\Brain\Monkey\Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"status":"success","data":{"id":42}}' );
		\Brain\Monkey\Functions\expect( 'wp_safe_remote_get' )
			->once()
			->with(
				'https://api.flutterwave.com/v3/transactions/42/verify',
				\Mockery::on(
					static function ( $args ) {
						return 'Bearer FLWSECK_TEST-x' === $args['headers']['Authorization'];
					}
				)
			)
			->andReturn( array() );

		$this->assertSame( array( 'id' => 42 ), FLW_Payment_Record::fetch_verified( 42 ) );
	}

	public function test_fetch_by_reference_encodes_tx_ref() {
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( array( 'secret_key' => 'k' ) );
		\Brain\Monkey\Functions\when( 'add_query_arg' )->alias(
			static function ( $key, $value, $url ) {
				return $url . '?' . $key . '=' . $value;
			}
		);
		\Brain\Monkey\Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"status":"error"}' );
		\Brain\Monkey\Functions\expect( 'wp_safe_remote_get' )
			->once()
			->with( 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=a%26b%3Dc', \Mockery::any() )
			->andReturn( array() );

		$this->assertInstanceOf( \WP_Error::class, FLW_Payment_Record::fetch_verified_by_reference( 'a&b=c' ) );
	}
}
