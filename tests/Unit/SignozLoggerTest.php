<?php
/**
 * Tests for FLW_Signoz_Logger.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FLW_Signoz_Logger;

/**
 * @covers FLW_Signoz_Logger
 */
class SignozLoggerTest extends TestCase {

	/**
	 * HTTP requests made, as [ method, url, args ].
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Canned responses for POST /events, used in order; the last one repeats.
	 *
	 * @var array
	 */
	private $post_responses = array();

	/**
	 * Canned response for GET /health/ready.
	 *
	 * @var mixed
	 */
	private $health_response;

	/**
	 * Fresh logger and stubbed HTTP for each test.
	 */
	protected function set_up() {
		parent::set_up();

		$this->reset_logger();
		$this->requests        = array();
		$this->post_responses  = array( $this->response( 200, array( 'ok' => true ) ) );
		$this->health_response = $this->response( 200, array( 'status' => 'ok' ) );
		$this->configure( 'FLWPUBK_TEST-public-X' );

		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args = array() ) {
				$this->requests[] = array( 'GET', $url, $args );
				return $this->health_response;
			}
		);
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args = array() ) {
				$this->requests[] = array( 'POST', $url, $args );
				return count( $this->post_responses ) > 1 ? array_shift( $this->post_responses ) : $this->post_responses[0];
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return $response['response']['code'] ?? '';
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return $response['body'] ?? '';
			}
		);
	}

	/**
	 * Replace the singleton so no trace context leaks between tests.
	 */
	private function reset_logger(): void {
		$property = new \ReflectionProperty( FLW_Signoz_Logger::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	/**
	 * Set the merchant's public key.
	 *
	 * @param string $public_key Public key.
	 */
	private function configure( string $public_key ): void {
		$this->options['flw_rave_options'] = array( 'public_key' => $public_key );
	}

	/**
	 * A fake HTTP response.
	 *
	 * @param int   $code Status code.
	 * @param array $body JSON body.
	 *
	 * @return array
	 */
	private function response( int $code, array $body = array() ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => json_encode( $body ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);
	}

	/**
	 * Events queued for background dispatch, as [ name, data, timestamp ].
	 *
	 * @return array
	 */
	private function queued(): array {
		return array_values(
			array_map(
				static function ( $event ) {
					return $event[1];
				},
				array_filter(
					$this->scheduled,
					static function ( $event ) {
						return FLW_Signoz_Logger::SCHEDULED_SEND_HOOK === $event[0];
					}
				)
			)
		);
	}

	/**
	 * Requests to a path.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path on the SigNoz service.
	 *
	 * @return array
	 */
	private function requests_to( string $method, string $path ): array {
		return array_values(
			array_filter(
				$this->requests,
				static function ( $request ) use ( $method, $path ) {
					return $method === $request[0] && FLW_Signoz_Logger::BASE_URL . $path === $request[1];
				}
			)
		);
	}

	// Configuration.

	public function test_telemetry_can_be_disabled_with_a_filter() {
		Filters\expectApplied( 'flw_signoz_enabled' )->andReturn( false );

		FLW_Signoz_Logger::instance()->track_request_sent( 'POST', 'WP_1', '/payments' );
		FLW_Signoz_Logger::instance()->track_error( 'X', 'y' );

		$this->assertSame( array(), $this->queued() );
	}

	public function test_environment_follows_the_key_mode() {
		$this->assertSame( 'sandbox', FLW_Signoz_Logger::instance()->get_current_environment() );

		$this->configure( 'FLWPUBK-live-X' );
		$this->assertSame( 'production', FLW_Signoz_Logger::instance()->get_current_environment() );
	}

	public function test_app_id_prefers_the_registered_id_then_the_public_key() {
		$this->configure( '' );
		$this->assertSame( 'unknown', FLW_Signoz_Logger::instance()->get_app_id() );

		$this->configure( 'FLWPUBK_TEST-public-X' );
		$this->assertSame( 'FLWPUBK_TEST-public-X', FLW_Signoz_Logger::instance()->get_app_id() );

		$this->options[ FLW_Signoz_Logger::STATE_OPTION ] = array( 'app_id' => ' my app ' );
		$this->assertSame( 'my-app', FLW_Signoz_Logger::instance()->get_app_id() );
	}

	// Events.

	public function test_request_sent_is_queued_not_sent_inline() {
		FLW_Signoz_Logger::instance()->track_request_sent( 'POST', 'WP_abc_1', '/payments' );

		$this->assertSame( array(), $this->requests );

		$queued = $this->queued();
		$this->assertCount( 1, $queued );
		$this->assertSame( 'request.sent', $queued[0][0] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.000Z$/', $queued[0][2] );
		$this->assertSame(
			array(
				'app_id'          => 'FLWPUBK_TEST-public-X',
				'environment'     => 'sandbox',
				'api_version'     => 'v3',
				'library'         => 'WordPress',
				'library_version' => FLW_PAY_VERSION,
				'method'          => 'POST',
				'path'            => '/payments',
				'reference'       => 'WP_abc_1',
			),
			array_diff_key( $queued[0][1], array( 'trace_context' => true ) )
		);
	}

	public function test_request_sent_is_deduplicated_per_reference_and_normalised() {
		$logger = FLW_Signoz_Logger::instance();
		$logger->track_request_sent( 'POST', 'WP abc/1', '/payments' );
		$logger->track_request_sent( 'POST', 'WP abc/1', '/payments' );
		$logger->track_request_sent( 'POST', 'WP_other', '/payments' );

		$queued = $this->queued();
		$this->assertCount( 2, $queued );
		$this->assertSame( 'WP-abc-1', $queued[0][1]['reference'] );
	}

	public function test_events_for_one_payment_share_a_trace() {
		$logger = FLW_Signoz_Logger::instance();
		$logger->track_request_sent( 'POST', 'WP_trace', '/payments' );
		$logger->track_transaction( 'WP_trace', 'NGN', 5000.0, 'card', 70.0 );

		$request     = $this->queued()[0][1]['trace_context'];
		$transaction = $this->queued()[1][1]['trace_context'];

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $request['trace_id'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $request['span_id'] );
		$this->assertSame( $request['trace_id'], $transaction['trace_id'] );
		$this->assertSame( $request['span_id'], $transaction['parent_span_id'] );
	}

	public function test_explicit_traceparent_is_continued() {
		FLW_Signoz_Logger::instance()->track_transaction(
			'WP_tp',
			'NGN',
			1.0,
			'card',
			0.0,
			array( 'traceparent' => '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01' )
		);

		$context = $this->queued()[0][1]['trace_context'];
		$this->assertSame( '0af7651916cd43dd8448eb211c80319c', $context['trace_id'] );
		$this->assertSame( 'b7ad6b7169203331', $context['parent_span_id'] );
	}

	public function test_transaction_payload() {
		$this->configure( 'FLWPUBK-live-X' );
		FLW_Signoz_Logger::instance()->track_transaction( 'WP_t', 'KES', 12.5, 'mobilemoney', 0.25 );

		$event = $this->queued()[0];
		$this->assertSame( 'app.transaction', $event[0] );
		$this->assertSame(
			array(
				'app_id'    => 'FLWPUBK-live-X',
				'reference' => 'WP_t',
				'library'   => 'WordPress',
				'currency'  => 'KES',
				'amount'    => 12.5,
				'fee'       => 0.25,
				'method'    => 'mobilemoney',
			),
			array_diff_key( $event[1], array( 'trace_context' => true ) )
		);
	}

	public function test_error_payload_is_truncated() {
		FLW_Signoz_Logger::instance()->track_error(
			'CHECKOUT_FAILED',
			str_repeat( 'é', FLW_Signoz_Logger::ERROR_MESSAGE_MAX_LENGTH + 10 ),
			'WP_e',
			null,
			str_repeat( 's', FLW_Signoz_Logger::ERROR_STACKTRACE_MAX_LENGTH + 10 )
		);

		$event = $this->queued()[0];
		$this->assertSame( 'app.error', $event[0] );
		$this->assertSame( 'CHECKOUT_FAILED', $event[1]['error_code'] );
		$this->assertSame( FLW_Signoz_Logger::ERROR_MESSAGE_MAX_LENGTH, mb_strlen( $event[1]['error_message'] ) );
		$this->assertSame( FLW_Signoz_Logger::ERROR_STACKTRACE_MAX_LENGTH, strlen( $event[1]['error_stacktrace'] ) );
		$this->assertSame( 'WP_e', $event[1]['reference'] );
	}

	public function test_error_without_reference_has_no_trace() {
		FLW_Signoz_Logger::instance()->track_error( 'X', 'Something failed' );

		$this->assertArrayNotHasKey( 'reference', $this->queued()[0][1] );
		$this->assertArrayNotHasKey( 'trace_context', $this->queued()[0][1] );
	}

	public function test_repeated_errors_are_sent_once_and_capped() {
		$logger = FLW_Signoz_Logger::instance();

		$logger->track_error( 'PAYMENT_VERIFY_FAILED', 'Timeout', 'WP_1' );
		$logger->track_error( 'PAYMENT_VERIFY_FAILED', 'Timeout', 'WP_1' );
		$this->assertCount( 1, $this->queued() );

		for ( $i = 0; $i < FLW_Signoz_Logger::ERROR_BUDGET + 20; $i++ ) {
			$logger->track_error( 'PAYMENT_VERIFY_FAILED', 'Timeout', 'WP_flood_' . $i );
		}

		$this->assertCount( FLW_Signoz_Logger::ERROR_BUDGET, $this->queued() );
	}

	public function test_events_are_dropped_rather_than_sent_inline_when_scheduling_fails() {
		Functions\when( 'wp_schedule_single_event' )->justReturn( false );

		FLW_Signoz_Logger::instance()->track_error( 'X', 'y' );

		$this->assertSame( array(), $this->requests );
	}

	// Transport.

	public function test_scheduled_send_posts_the_event_after_a_health_check() {
		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'app.error', array( 'a' => 1 ), '2026-01-01T00:00:00.000Z' );

		$this->assertCount( 1, $this->requests_to( 'GET', '/health/ready' ) );
		$posts = $this->requests_to( 'POST', '/events' );
		$this->assertCount( 1, $posts );
		$this->assertSame(
			array(
				'name'      => 'app.error',
				'data'      => array( 'a' => 1 ),
				'timestamp' => '2026-01-01T00:00:00.000Z',
			),
			json_decode( $posts[0][2]['body'], true )
		);
		$this->assertSame( 2, $posts[0][2]['timeout'] );
	}

	public function test_scheduled_send_ignores_malformed_jobs() {
		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'app.error', 'not-an-array', null );

		$this->assertSame( array(), $this->requests );
	}

	public function test_a_passing_health_check_is_cached() {
		$logger = FLW_Signoz_Logger::instance();
		$logger->handle_scheduled_send( 'a', array(), 't' );
		$logger->handle_scheduled_send( 'b', array(), 't' );

		$this->assertCount( 1, $this->requests_to( 'GET', '/health/ready' ) );
		$this->assertCount( 2, $this->requests_to( 'POST', '/events' ) );
	}

	public function test_unhealthy_service_gets_no_events() {
		$this->health_response = $this->response( 200, array( 'status' => 'degraded' ) );

		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'a', array(), 't' );

		$this->assertSame( array(), $this->requests_to( 'POST', '/events' ) );
	}

	public function test_circuit_opens_after_repeated_failures() {
		$this->post_responses = array( $this->response( 500 ) );
		$logger               = FLW_Signoz_Logger::instance();

		for ( $i = 0; $i < FLW_Signoz_Logger::CB_FAILURE_THRESHOLD; $i++ ) {
			$logger->handle_scheduled_send( 'a', array(), 't' );
		}

		$this->assertTrue( $this->transients[ FLW_Signoz_Logger::CB_OPEN_KEY ] );
		$this->requests = array();

		$logger->handle_scheduled_send( 'a', array(), 't' );
		$this->assertSame( array(), $this->requests, 'An open circuit drops events without any request.' );
	}

	public function test_success_resets_the_failure_count() {
		$this->transients[ FLW_Signoz_Logger::CB_FAILURES_KEY ] = 2;

		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'a', array(), 't' );

		$this->assertArrayNotHasKey( FLW_Signoz_Logger::CB_FAILURES_KEY, $this->transients );
	}

	public function test_503_is_retried_and_other_errors_are_not() {
		$this->post_responses = array( $this->response( 503 ), $this->response( 503 ), $this->response( 200 ) );
		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'a', array(), 't' );
		$this->assertCount( 3, $this->requests_to( 'POST', '/events' ) );

		$this->requests       = array();
		$this->post_responses = array( $this->response( 400 ), $this->response( 200 ) );
		FLW_Signoz_Logger::instance()->handle_scheduled_send( 'a', array(), 't' );
		$this->assertCount( 1, $this->requests_to( 'POST', '/events' ) );
	}

	// Registration.

	public function test_app_created_is_sent_immediately_and_stores_the_app_id() {
		$this->post_responses = array( $this->response( 201, array( 'app_id' => 'app 123' ) ) );

		$app_id = FLW_Signoz_Logger::instance()->track_app_created( 'FLWPUBK_TEST-public-X' );

		$this->assertSame( 'app-123', $app_id );
		$this->assertSame( array(), $this->queued() );
		$this->assertSame(
			array(
				'client_id'       => null,
				'public_key'      => 'FLWPUBK_TEST-public-X',
				'library'         => 'WordPress',
				'library_version' => FLW_PAY_VERSION,
			),
			json_decode( $this->requests_to( 'POST', '/events' )[0][2]['body'], true )['data']
		);
		$this->assertSame(
			array(
				'app_id'     => 'app-123',
				'public_key' => 'FLWPUBK_TEST-public-X',
			),
			$this->options[ FLW_Signoz_Logger::STATE_OPTION ]
		);
	}

	public function test_app_created_reuses_the_app_id_for_the_same_key_only() {
		$this->options[ FLW_Signoz_Logger::STATE_OPTION ] = array(
			'app_id'     => 'existing',
			'public_key' => 'FLWPUBK_TEST-public-X',
		);
		$this->post_responses                             = array( $this->response( 201, array( 'app_id' => 'new-app' ) ) );

		$this->assertSame( 'existing', FLW_Signoz_Logger::instance()->track_app_created( 'FLWPUBK_TEST-public-X' ) );
		$this->assertSame( array(), $this->requests );

		$this->assertSame( 'new-app', FLW_Signoz_Logger::instance()->track_app_created( 'FLWPUBK-live-X' ) );
	}

	public function test_app_created_returns_null_when_the_service_is_down() {
		$this->health_response = new \WP_Error( 'http', 'down' );

		$this->assertNull( FLW_Signoz_Logger::instance()->track_app_created( 'FLWPUBK_TEST-public-X' ) );
		$this->assertArrayNotHasKey( FLW_Signoz_Logger::STATE_OPTION, $this->options );
	}
}
