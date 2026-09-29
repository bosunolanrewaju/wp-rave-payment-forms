<?php
/**
 * Tests for FLW_App_Registration.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use Brain\Monkey\Functions;
use FLW_App_Registration;
use FLW_Signoz_Logger;

/**
 * @covers FLW_App_Registration
 */
class AppRegistrationTest extends TestCase {

	/**
	 * Configure a public key.
	 */
	protected function set_up() {
		parent::set_up();
		$this->options['flw_rave_options'] = array( 'public_key' => 'FLWPUBK_TEST-public-X' );
	}

	/**
	 * Registration jobs queued so far.
	 *
	 * @return array
	 */
	private function jobs(): array {
		return array_values(
			array_filter(
				$this->scheduled,
				static function ( $event ) {
					return FLW_App_Registration::REGISTER_HOOK === $event[0];
				}
			)
		);
	}

	public function test_nothing_is_queued_without_a_public_key() {
		$this->options['flw_rave_options'] = array();

		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertSame( array(), $this->jobs() );
	}

	public function test_registration_is_queued_once() {
		FLW_App_Registration::instance()->maybe_enqueue_registration();
		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertSame( array( array( FLW_App_Registration::REGISTER_HOOK, array( 'FLWPUBK_TEST-public-X' ) ) ), $this->jobs() );
	}

	public function test_nothing_is_queued_when_registered_for_this_key_and_version() {
		$this->options[ FLW_Signoz_Logger::STATE_OPTION ] = array(
			'app_registered' => true,
			'public_key'     => 'FLWPUBK_TEST-public-X',
			'plugin_version' => FLW_PAY_VERSION,
		);

		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertSame( array(), $this->jobs() );
	}

	/**
	 * @dataProvider stale_states
	 *
	 * @param array $state Stored registration state.
	 */
	public function test_stale_registration_is_redone( array $state ) {
		$this->options[ FLW_Signoz_Logger::STATE_OPTION ] = $state;

		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertCount( 1, $this->jobs() );
	}

	/**
	 * Registration states that need redoing.
	 *
	 * @return array
	 */
	public static function stale_states(): array {
		return array(
			'older plugin version' => array(
				array(
					'app_registered' => true,
					'public_key'     => 'FLWPUBK_TEST-public-X',
					'plugin_version' => '1.0.0',
				),
			),
			'different key'        => array(
				array(
					'app_registered' => true,
					'public_key'     => 'FLWPUBK_TEST-old-X',
					'plugin_version' => FLW_PAY_VERSION,
				),
			),
		);
	}

	public function test_nothing_is_queued_when_telemetry_is_disabled() {
		\Brain\Monkey\Filters\expectApplied( 'flw_signoz_enabled' )->andReturn( false );

		FLW_App_Registration::instance()->maybe_enqueue_registration();

		$this->assertSame( array(), $this->jobs() );
	}

	public function test_upgrade_of_this_plugin_queues_registration() {
		$self = basename( dirname( FLW_PAY_PLUGIN_FILE ) ) . '/rave-payment-forms.php';

		FLW_App_Registration::instance()->maybe_trigger_on_upgrade(
			null,
			array(
				'type'    => 'plugin',
				'action'  => 'update',
				'plugins' => array( 'other/other.php' ),
			)
		);
		$this->assertSame( array(), $this->jobs() );

		FLW_App_Registration::instance()->maybe_trigger_on_upgrade(
			null,
			array(
				'type'   => 'plugin',
				'action' => 'update',
				'plugin' => $self,
			)
		);
		$this->assertCount( 1, $this->jobs() );
	}

	public function test_successful_registration_stamps_the_state() {
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 201 ) ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return $response['response']['code'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return 200 === $response['response']['code'] ? '{"status":"ok"}' : '{"app_id":"app-1"}';
			}
		);

		FLW_App_Registration::instance()->handle_scheduled_registration( 'FLWPUBK_TEST-public-X' );

		$this->assertSame(
			array(
				'app_id'         => 'app-1',
				'public_key'     => 'FLWPUBK_TEST-public-X',
				'app_registered' => true,
				'plugin_version' => FLW_PAY_VERSION,
			),
			$this->options[ FLW_Signoz_Logger::STATE_OPTION ]
		);
	}

	public function test_failed_registration_leaves_the_state_for_a_retry() {
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http', 'down' ) );

		FLW_App_Registration::instance()->handle_scheduled_registration( 'FLWPUBK_TEST-public-X' );

		$this->assertArrayNotHasKey( FLW_Signoz_Logger::STATE_OPTION, $this->options );
	}

	public function test_job_for_an_old_key_does_nothing() {
		Functions\expect( 'wp_remote_get' )->never();

		FLW_App_Registration::instance()->handle_scheduled_registration( 'FLWPUBK_TEST-old-X' );

		$this->assertArrayNotHasKey( FLW_Signoz_Logger::STATE_OPTION, $this->options );
	}
}
