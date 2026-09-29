<?php
/**
 * SigNoz events wired into checkout, verification, webhooks and onboarding.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_App_Registration;
use FLW_Payment_Record;
use FLW_Signoz_Logger;
use WP_REST_Request;

/**
 * @covers FLW_Signoz_Logger
 * @covers FLW_App_Registration
 */
class SignozTest extends TestCase {

	/**
	 * Start every test with an empty cron queue and no dedup/budget state.
	 */
	public function set_up() {
		parent::set_up();
		_set_cron_array( array() );
	}

	/**
	 * SigNoz events waiting in WP-Cron, as [ name, data ].
	 *
	 * @return array
	 */
	private function queued_events(): array {
		$events = array();

		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( (array) $hooks as $hook => $jobs ) {
				if ( FLW_Signoz_Logger::SCHEDULED_SEND_HOOK !== $hook ) {
					continue;
				}
				foreach ( $jobs as $job ) {
					$events[] = array( $job['args'][0], $job['args'][1] );
				}
			}
		}

		return $events;
	}

	/**
	 * Names of queued events.
	 *
	 * @return array
	 */
	private function queued_names(): array {
		return array_map(
			static function ( $event ) {
				return $event[0];
			},
			$this->queued_events()
		);
	}

	public function test_hooks_are_registered() {
		$this->assertNotFalse( has_action( FLW_Signoz_Logger::SCHEDULED_SEND_HOOK ) );
		$this->assertNotFalse( has_action( FLW_App_Registration::REGISTER_HOOK ) );
		$this->assertNotFalse( has_action( 'flw_onboarding_complete', array( FLW_App_Registration::instance(), 'maybe_enqueue_registration' ) ) );
	}

	public function test_live_payment_queues_a_transaction_event() {
		$this->configure_plugin( array( 'public_key' => 'FLWPUBK-live-X' ) );
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id, array( 'app_fee' => 70 ) ) );

		$events = $this->queued_events();
		$this->assertSame( array( 'app.transaction' ), $this->queued_names() );
		$this->assertSame( 'WP_TEST_' . $post_id, $events[0][1]['reference'] );
		$this->assertEquals( 5000, $events[0][1]['amount'] );
		$this->assertEquals( 70, $events[0][1]['fee'] );
	}

	public function test_test_mode_payment_queues_nothing() {
		$this->configure_plugin();
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id ) );

		$this->assertSame( array(), $this->queued_events() );
	}

	public function test_already_successful_payment_is_not_counted_twice() {
		$this->configure_plugin( array( 'public_key' => 'FLWPUBK-live-X' ) );
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id ) );
		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id ) );

		$this->assertSame( array( 'app.transaction' ), $this->queued_names() );
	}

	public function test_underpayment_is_reported_as_an_error() {
		$this->configure_plugin();
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id, array( 'amount' => 10 ) ) );

		$events = $this->queued_events();
		$this->assertSame( array( 'app.error' ), $this->queued_names() );
		$this->assertSame( 'PAYMENT_AMOUNT_MISMATCH', $events[0][1]['error_code'] );
	}

	public function test_pending_payment_is_not_reported() {
		$this->configure_plugin();
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id, array( 'status' => 'pending' ) ) );

		$this->assertSame( array(), $this->queued_events() );
	}

	public function test_unauthenticated_webhook_queues_nothing() {
		$this->configure_plugin();

		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/webhook' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'verif-hash', 'wrong' );
		$request->set_body(
			wp_json_encode(
				array(
					'event' => 'charge.completed',
					'data'  => array( 'id' => 1 ),
				)
			)
		);
		rest_do_request( $request );

		$this->assertSame( array(), $this->queued_events() );
	}

	public function test_completing_onboarding_queues_registration() {
		$this->configure_plugin();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/onboarding/complete' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( '{}' );
		rest_do_request( $request );

		$this->assertNotFalse( wp_next_scheduled( FLW_App_Registration::REGISTER_HOOK, array( 'FLWPUBK_TEST-public' ) ) );
	}

	public function test_telemetry_constant_filter_disables_everything() {
		add_filter( 'flw_signoz_enabled', '__return_false' );
		$this->configure_plugin( array( 'public_key' => 'FLWPUBK-live-X' ) );
		$post_id = $this->create_record();

		FLW_Payment_Record::apply_verified_transaction( $this->transaction( $post_id ) );
		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertSame( array(), $this->queued_events() );
		$this->assertFalse( wp_next_scheduled( FLW_App_Registration::REGISTER_HOOK, array( 'FLWPUBK-live-X' ) ) );
	}
}
