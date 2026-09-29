<?php
/**
 * SigNoz observability service integration.
 *
 * Sends integration events (app.created, request.sent, app.transaction, app.error)
 * to the Flutterwave SigNoz service for developer analytics and TTFS/TTGL tracking.
 * Ported from the Flutterwave WooCommerce plugin.
 *
 * Events are dispatched in the background, so a customer's request never waits
 * on the SigNoz service: through Action Scheduler when a plugin such as
 * WooCommerce provides it, otherwise through WP-Cron. The background worker
 * applies a health gate and circuit breaker, so failures are contained and never
 * surface to users. The only synchronous call is app.created, which needs the
 * response body (the backend-generated app_id) and only runs in the background
 * registration job.
 *
 * Telemetry can be switched off with the FLW_DISABLE_TELEMETRY constant or the
 * `flw_signoz_enabled` filter.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * SigNoz Logger: sends observability events to the Flutterwave analytics service.
 */
final class FLW_Signoz_Logger {

	const BASE_URL = 'https://signozservice-prod.f4b-flutterwave.com';
	const LIBRARY  = 'WordPress';

	// Plugin state that is not a merchant setting, kept apart from flw_rave_options
	// so the settings sanitiser never strips it.
	const STATE_OPTION = 'flw_signoz_state';

	// Background dispatch.
	const SCHEDULED_SEND_HOOK = 'flw_signoz_send_event';
	const AS_GROUP            = 'flutterwave-signoz';

	// Health check.
	const HEALTH_PATH      = '/health/ready';
	const HEALTH_CACHE_TTL = 60;
	const HEALTH_OK_KEY    = 'flw_signoz_health_ok';

	// Circuit breaker.
	const CB_FAILURE_THRESHOLD = 3;
	const CB_OPEN_TTL          = 120;
	const CB_FAILURES_KEY      = 'flw_signoz_cb_failures';
	const CB_OPEN_KEY          = 'flw_signoz_cb_open';

	// Retry and backoff (503 only).
	const MAX_ATTEMPTS  = 3;
	const BASE_DELAY_MS = 200;
	const MAX_DELAY_MS  = 1500;

	// Payload limits.
	const ERROR_MESSAGE_MAX_LENGTH    = 4096;
	const ERROR_STACKTRACE_MAX_LENGTH = 16384;

	// Error events can be triggered by anonymous requests (a forged verify URL,
	// for example), so each distinct error is sent once per window and the total
	// per window is capped. Without this, anyone could fill the cron queue.
	const ERROR_DEDUP_TTL     = 300;
	const ERROR_BUDGET        = 30;
	const ERROR_BUDGET_WINDOW = 600;
	const ERROR_BUDGET_KEY    = 'flw_signoz_error_budget';

	// request.sent is sent once per reference per window.
	const REQUEST_DEDUP_TTL = 300;

	// Trace context.
	const TRACE_CTX_TTL        = HOUR_IN_SECONDS;
	const TRACE_CTX_KEY_PREFIX = 'flw_signoz_trace_';

	/**
	 * Singleton instance.
	 *
	 * @var FLW_Signoz_Logger|null
	 */
	private static $instance = null;

	/**
	 * In-process trace context registry (warm cache in front of transients).
	 *
	 * @var array<string, array>
	 */
	private $trace_contexts_by_reference = array();

	/**
	 * Default trace context applied when none is registered for a reference.
	 *
	 * @var array|null
	 */
	private $default_trace_context = null;

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {}

	/**
	 * Get or create the singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register the background dispatch callback. Call once from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( self::SCHEDULED_SEND_HOOK, array( self::instance(), 'handle_scheduled_send' ), 10, 3 );
	}

	/**
	 * Whether telemetry is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( defined( 'FLW_DISABLE_TELEMETRY' ) && FLW_DISABLE_TELEMETRY ) {
			return false;
		}

		/**
		 * Filters whether events are sent to the Flutterwave SigNoz service.
		 *
		 * @param bool $enabled Whether telemetry is enabled.
		 */
		return (bool) apply_filters( 'flw_signoz_enabled', true );
	}

	// State.

	/**
	 * The stored registration state.
	 *
	 * @return array{app_id?: string, app_registered?: bool, plugin_version?: string, public_key?: string}
	 */
	public function get_state(): array {
		$state = get_option( self::STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Merge values into the stored registration state.
	 *
	 * @param array $values Values to store.
	 *
	 * @return void
	 */
	public function update_state( array $values ): void {
		update_option( self::STATE_OPTION, array_merge( $this->get_state(), $values ) );
	}

	/**
	 * The merchant's public key.
	 *
	 * @return string
	 */
	private function public_key(): string {
		return class_exists( 'FLW_Settings' ) ? trim( FLW_Settings::get( 'public_key' ) ) : '';
	}

	/**
	 * The current environment: "production" for live keys, otherwise "sandbox".
	 *
	 * @return string
	 */
	public function get_current_environment(): string {
		return 'live' === FLW_Settings::key_mode( $this->public_key() ) ? 'production' : 'sandbox';
	}

	/**
	 * The application identifier: the backend-generated app_id, else the public
	 * key, else an anonymous marker so a missing value can never fatal.
	 *
	 * @return string
	 */
	public function get_app_id(): string {
		$state = $this->get_state();

		if ( ! empty( $state['app_id'] ) && is_string( $state['app_id'] ) ) {
			return $this->normalize_app_id( $state['app_id'] );
		}

		$public_key = $this->public_key();

		return '' !== $public_key ? $this->normalize_app_id( $public_key ) : 'unknown';
	}

	// Trace context.

	/**
	 * Set the default trace context used when no reference-specific one exists.
	 *
	 * @param array|null $trace_context Trace context, or null to clear.
	 *
	 * @return void
	 */
	public function set_default_trace_context( ?array $trace_context ): void {
		$this->default_trace_context = $trace_context;
	}

	/**
	 * Register (or clear) the trace context for a transaction reference. Stored in
	 * a transient so it survives from checkout to the redirect or webhook.
	 *
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param array|null $trace_context Trace context, or null to delete.
	 *
	 * @return void
	 */
	public function set_trace_context_for_reference( string $reference, ?array $trace_context ): void {
		try {
			$key = $this->normalize_reference( $reference );

			if ( '' === $key ) {
				return;
			}

			if ( null === $trace_context ) {
				unset( $this->trace_contexts_by_reference[ $key ] );
				delete_transient( $this->trace_context_transient_key( $key ) );
				return;
			}

			$this->trace_contexts_by_reference[ $key ] = $trace_context;
			set_transient( $this->trace_context_transient_key( $key ), $trace_context, self::TRACE_CTX_TTL );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}
	}

	/**
	 * The trace context registered for a reference.
	 *
	 * @param string $reference Transaction reference (tx_ref).
	 *
	 * @return array|null
	 */
	public function get_trace_context_for_reference( string $reference ): ?array {
		try {
			$key = $this->normalize_reference( $reference );

			if ( '' === $key ) {
				return null;
			}

			if ( isset( $this->trace_contexts_by_reference[ $key ] ) ) {
				return $this->trace_contexts_by_reference[ $key ];
			}

			$stored = get_transient( $this->trace_context_transient_key( $key ) );

			if ( is_array( $stored ) ) {
				$this->trace_contexts_by_reference[ $key ] = $stored;
				return $stored;
			}
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}//end try

		return null;
	}

	/**
	 * Resolve the trace context for an event (explicit, then default, then by
	 * reference) and create a child span under it, so events for one payment
	 * share a trace.
	 *
	 * @param array|null $explicit  Trace context passed by the caller.
	 * @param string     $reference Transaction reference (tx_ref).
	 *
	 * @return array
	 */
	private function resolve_trace_context( ?array $explicit, string $reference ): array {
		$parent = $explicit ?? $this->default_trace_context ?? $this->get_trace_context_for_reference( $reference );

		$context = $this->build_trace_context( $parent );
		$this->set_trace_context_for_reference( $reference, $context );

		return $context;
	}

	/**
	 * Build a trace context, as a child of the parent when one is given.
	 *
	 * @param array|null $parent_context Optional parent trace context.
	 *
	 * @return array
	 */
	private function build_trace_context( ?array $parent_context = null ): array {
		$context = array(
			'trace_id' => $this->random_hex( 16 ),
			'span_id'  => $this->random_hex( 8 ),
		);

		if ( null !== $parent_context ) {
			$context['trace_id'] = $this->extract_trace_id( $parent_context ) ?? $context['trace_id'];
			$parent_span         = $this->extract_span_id( $parent_context );

			if ( null !== $parent_span && '' !== $parent_span ) {
				$context['parent_span_id'] = $parent_span;
			}
		}

		return $context;
	}

	/**
	 * Random lowercase hex string.
	 *
	 * @param int $bytes Number of random bytes.
	 *
	 * @return string
	 */
	private function random_hex( int $bytes ): string {
		try {
			return bin2hex( random_bytes( $bytes ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			return substr( md5( uniqid( '', true ) . microtime( true ) ), 0, $bytes * 2 );
		}
	}

	/**
	 * Trace id from a trace context (trace_id or W3C traceparent).
	 *
	 * @param array $context Trace context.
	 *
	 * @return string|null
	 */
	private function extract_trace_id( array $context ): ?string {
		if ( isset( $context['trace_id'] ) && is_string( $context['trace_id'] ) && '' !== trim( $context['trace_id'] ) ) {
			return $context['trace_id'];
		}

		if ( isset( $context['traceparent'] ) && is_string( $context['traceparent'] ) ) {
			return explode( '-', $context['traceparent'] )[1] ?? null;
		}

		return null;
	}

	/**
	 * Span id from a trace context (span_id or W3C traceparent).
	 *
	 * @param array $context Trace context.
	 *
	 * @return string|null
	 */
	private function extract_span_id( array $context ): ?string {
		if ( isset( $context['span_id'] ) && is_string( $context['span_id'] ) && '' !== trim( $context['span_id'] ) ) {
			return $context['span_id'];
		}

		if ( isset( $context['traceparent'] ) && is_string( $context['traceparent'] ) ) {
			return explode( '-', $context['traceparent'] )[2] ?? null;
		}

		return null;
	}

	/**
	 * Transient key for a normalized reference; hashed to stay within the key limit.
	 *
	 * @param string $normalized_reference Normalized transaction reference.
	 *
	 * @return string
	 */
	private function trace_context_transient_key( string $normalized_reference ): string {
		return self::TRACE_CTX_KEY_PREFIX . md5( $normalized_reference );
	}

	// Events.

	/**
	 * Fire `app.created` and store the backend-generated app_id. Idempotent: reuses
	 * the stored app_id when it was created for the same public key.
	 *
	 * Synchronous because the response carries the app_id; only called from the
	 * background registration job, never during a customer's request.
	 *
	 * @param string $public_key Merchant Flutterwave public key.
	 *
	 * @return string|null The app_id, or null when unavailable.
	 */
	public function track_app_created( string $public_key ): ?string {
		try {
			if ( '' === $public_key || ! $this->is_enabled() ) {
				return null;
			}

			$state = $this->get_state();

			if ( ! empty( $state['app_id'] ) && is_string( $state['app_id'] ) && ( $state['public_key'] ?? '' ) === $public_key ) {
				return $this->normalize_app_id( $state['app_id'] );
			}

			$response = $this->send_now(
				'app.created',
				array(
					'client_id'       => null,
					'public_key'      => $public_key,
					'library'         => self::LIBRARY,
					'library_version' => $this->library_version(),
				),
				$this->now()
			);

			if ( ! is_array( $response ) || empty( $response['app_id'] ) ) {
				return null;
			}

			$app_id = $this->normalize_app_id( (string) $response['app_id'] );

			$this->update_state(
				array(
					'app_id'     => $app_id,
					'public_key' => $public_key,
				)
			);

			return $app_id;
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}//end try

		return null;
	}

	/**
	 * Fire `request.sent` when a payment is initiated. Queued; never blocks.
	 *
	 * @param string     $method        HTTP method (e.g. "POST").
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param string     $path          Flutterwave API path (e.g. "/payments").
	 * @param array|null $trace_context Optional trace context override.
	 *
	 * @return void
	 */
	public function track_request_sent( string $method, string $reference, string $path, ?array $trace_context = null ): void {
		try {
			if ( ! $this->is_enabled() ) {
				return;
			}

			$safe_reference = $this->normalize_reference( $reference );
			$cache_key      = 'flw_signoz_req_' . md5( $safe_reference );

			if ( get_transient( $cache_key ) ) {
				return;
			}

			set_transient( $cache_key, true, self::REQUEST_DEDUP_TTL );

			$this->queue_event(
				'request.sent',
				array(
					'app_id'          => $this->get_app_id(),
					'environment'     => $this->get_current_environment(),
					'api_version'     => 'v3',
					'library'         => self::LIBRARY,
					'library_version' => $this->library_version(),
					'method'          => $method,
					'path'            => $path,
					'reference'       => $safe_reference,
					'trace_context'   => $this->resolve_trace_context( $trace_context, $safe_reference ),
				)
			);
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}//end try
	}

	/**
	 * Fire `app.transaction` after a successful live payment. Queued; never blocks.
	 *
	 * @param string     $reference     Transaction reference (tx_ref).
	 * @param string     $currency      ISO 4217 currency code.
	 * @param float      $amount        Transaction amount.
	 * @param string     $method        Payment method (e.g. "card").
	 * @param float      $fee           Transaction fee.
	 * @param array|null $trace_context Optional trace context override.
	 *
	 * @return void
	 */
	public function track_transaction( string $reference, string $currency, float $amount, string $method, float $fee, ?array $trace_context = null ): void {
		try {
			if ( ! $this->is_enabled() ) {
				return;
			}

			$safe_reference = $this->normalize_reference( $reference );

			$this->queue_event(
				'app.transaction',
				array(
					'app_id'        => $this->get_app_id(),
					'reference'     => $safe_reference,
					'library'       => self::LIBRARY,
					'currency'      => $currency,
					'amount'        => $amount,
					'fee'           => $fee,
					'method'        => $method,
					'trace_context' => $this->resolve_trace_context( $trace_context, $safe_reference ),
				)
			);
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}//end try
	}

	/**
	 * Fire `app.error`. Queued; never blocks. Deduplicated and capped per window.
	 *
	 * @param string      $error_code    Short machine-readable error code.
	 * @param string      $error_message Human-readable description.
	 * @param string      $reference     Optional transaction reference for trace correlation.
	 * @param array|null  $trace_context Optional trace context override.
	 * @param string|null $stack_trace   Optional stack trace (truncated before send).
	 *
	 * @return void
	 */
	public function track_error( string $error_code, string $error_message, string $reference = '', ?array $trace_context = null, ?string $stack_trace = null ): void {
		try {
			if ( ! $this->is_enabled() ) {
				return;
			}

			$safe_reference = $this->normalize_reference( $reference );

			if ( ! $this->claim_error_slot( $error_code . '|' . $safe_reference . '|' . $error_message ) ) {
				return;
			}

			$payload = array(
				'app_id'          => $this->get_app_id(),
				'library'         => self::LIBRARY,
				'library_version' => $this->library_version(),
				'error_code'      => $error_code,
				'error_message'   => $this->truncate_value( $error_message, self::ERROR_MESSAGE_MAX_LENGTH ),
			);

			if ( null !== $stack_trace && '' !== $stack_trace ) {
				$payload['error_stacktrace'] = $this->truncate_value( $stack_trace, self::ERROR_STACKTRACE_MAX_LENGTH );
			}

			if ( '' !== $safe_reference ) {
				$payload['reference']     = $safe_reference;
				$payload['trace_context'] = $this->resolve_trace_context( $trace_context, $safe_reference );
			}

			$this->queue_event( 'app.error', $payload );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}//end try
	}

	/**
	 * Reserve room for one error event: skips repeats of the same error within the
	 * dedup window and stops once the per-window budget is spent.
	 *
	 * @param string $fingerprint Identifies the error.
	 *
	 * @return bool Whether the event may be sent.
	 */
	private function claim_error_slot( string $fingerprint ): bool {
		$dedup_key = 'flw_signoz_err_' . md5( $fingerprint );

		if ( get_transient( $dedup_key ) ) {
			return false;
		}

		$sent = (int) get_transient( self::ERROR_BUDGET_KEY );

		if ( $sent >= self::ERROR_BUDGET ) {
			return false;
		}

		set_transient( self::ERROR_BUDGET_KEY, $sent + 1, self::ERROR_BUDGET_WINDOW );
		set_transient( $dedup_key, true, self::ERROR_DEDUP_TTL );

		return true;
	}

	// Background dispatch.

	/**
	 * Queue an event for background dispatch. The timestamp is captured now so
	 * events reflect when they happened, not when the worker ran.
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 *
	 * @return void
	 */
	private function queue_event( string $event_name, array $data ): void {
		$args = array( $event_name, $data, $this->now() );

		try {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::SCHEDULED_SEND_HOOK, $args, self::AS_GROUP );
				return;
			}

			if ( true === wp_schedule_single_event( time(), self::SCHEDULED_SEND_HOOK, $args, true ) ) {
				return;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		// Scheduling failed: drop the event rather than make a customer wait on
		// the network. Losing an analytics event is the safe outcome.
	}

	/**
	 * Background callback: dispatch a queued event. Never throws, so a failed
	 * send is not retried by the queue (the circuit breaker owns that decision).
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp captured at queue time.
	 *
	 * @return void
	 */
	public function handle_scheduled_send( $event_name, $data, $timestamp ): void {
		try {
			if ( is_string( $event_name ) && is_array( $data ) && is_string( $timestamp ) && $this->is_enabled() ) {
				$this->send_now( $event_name, $data, $timestamp );
			}
		} catch ( \Throwable $e ) {
			// Observability must never break payments, or the queue runner.
			unset( $e );
		}
	}

	// Transport: health gate, circuit breaker, retry.

	/**
	 * Send an event now. Guarded by the circuit breaker and health gate; never throws.
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp.
	 *
	 * @return array|null Decoded JSON response, or null when dropped or failed.
	 */
	private function send_now( string $event_name, array $data, string $timestamp ): ?array {
		try {
			if ( $this->is_circuit_open() ) {
				return null;
			}

			// Also acts as the half-open probe once the circuit's cooldown ends.
			if ( ! $this->is_service_healthy() ) {
				$this->record_failure();
				return null;
			}

			return $this->send_with_retry( $event_name, $data, $timestamp );
		} catch ( \Throwable $e ) {
			// Observability must never break payments.
			unset( $e );
		}

		return null;
	}

	/**
	 * POST the event, retrying 503 responses with jittered exponential backoff.
	 *
	 * @param string $event_name SigNoz event name.
	 * @param array  $data       Event payload.
	 * @param string $timestamp  ISO-8601 timestamp.
	 *
	 * @return array|null Decoded JSON response on success, otherwise null.
	 */
	private function send_with_retry( string $event_name, array $data, string $timestamp ): ?array {
		$args = array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'name'      => $event_name,
					'data'      => $data,
					'timestamp' => $timestamp,
				)
			),
			'timeout' => 2,
		);

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_post( self::BASE_URL . '/events', $args );

			if ( is_wp_error( $response ) ) {
				$this->record_failure();
				return null;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );

			if ( $status >= 200 && $status < 300 ) {
				$this->record_success();
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				return is_array( $decoded ) ? $decoded : null;
			}

			if ( 503 === $status && $attempt < self::MAX_ATTEMPTS ) {
				$this->backoff_sleep( $attempt );
				continue;
			}

			break;
		}//end for

		$this->record_failure();
		return null;
	}

	/**
	 * Sleep for random(0, min(MAX_DELAY, BASE * 2^(attempt-1))) milliseconds.
	 *
	 * @param int $attempt Attempt number, starting at 1.
	 *
	 * @return void
	 */
	private function backoff_sleep( int $attempt ): void {
		$ceiling_ms = min( self::MAX_DELAY_MS, self::BASE_DELAY_MS * ( 2 ** ( $attempt - 1 ) ) );

		try {
			$delay_ms = random_int( 0, $ceiling_ms );
		} catch ( \Throwable $e ) {
			$delay_ms = $ceiling_ms;
		}

		usleep( $delay_ms * 1000 );
	}

	/**
	 * GET /health/ready and require {"status":"ok"}. A pass is cached briefly.
	 *
	 * @return bool
	 */
	private function is_service_healthy(): bool {
		if ( get_transient( self::HEALTH_OK_KEY ) ) {
			return true;
		}

		try {
			$response = wp_remote_get( self::BASE_URL . self::HEALTH_PATH, array( 'timeout' => 1 ) );

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}

			$result  = json_decode( wp_remote_retrieve_body( $response ), true );
			$healthy = is_array( $result ) && 'ok' === ( $result['status'] ?? '' );

			if ( $healthy ) {
				set_transient( self::HEALTH_OK_KEY, true, self::HEALTH_CACHE_TTL );
			}

			return $healthy;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Whether the circuit is open (cooling down).
	 *
	 * @return bool
	 */
	private function is_circuit_open(): bool {
		return (bool) get_transient( self::CB_OPEN_KEY );
	}

	/**
	 * Reset breaker state after a successful send.
	 *
	 * @return void
	 */
	private function record_success(): void {
		delete_transient( self::CB_FAILURES_KEY );
		delete_transient( self::CB_OPEN_KEY );
	}

	/**
	 * Count a failure and open the circuit at the threshold.
	 *
	 * @return void
	 */
	private function record_failure(): void {
		$failures = (int) get_transient( self::CB_FAILURES_KEY ) + 1;
		set_transient( self::CB_FAILURES_KEY, $failures, self::CB_OPEN_TTL * 2 );

		if ( $failures >= self::CB_FAILURE_THRESHOLD ) {
			set_transient( self::CB_OPEN_KEY, true, self::CB_OPEN_TTL );
			delete_transient( self::CB_FAILURES_KEY );
			// Force a fresh health probe once the cooldown expires.
			delete_transient( self::HEALTH_OK_KEY );
		}
	}

	// Helpers.

	/**
	 * Current time as an ISO-8601 UTC timestamp.
	 *
	 * @return string
	 */
	private function now(): string {
		return gmdate( 'Y-m-d\TH:i:s.000\Z' );
	}

	/**
	 * Replace whitespace runs with dashes so app ids are URL safe.
	 *
	 * @param string $app_id Raw app identifier.
	 *
	 * @return string
	 */
	private function normalize_app_id( string $app_id ): string {
		$normalized = preg_replace( '/\s+/', '-', trim( $app_id ) );
		return null !== $normalized ? $normalized : $app_id;
	}

	/**
	 * Restrict references to [A-Za-z0-9_-] so cache keys and payloads are stable.
	 *
	 * @param string $reference Raw transaction reference.
	 *
	 * @return string
	 */
	private function normalize_reference( string $reference ): string {
		$normalized = preg_replace( '/[^A-Za-z0-9_-]+/', '-', trim( $reference ) );
		return null === $normalized ? $reference : trim( $normalized, '-' );
	}

	/**
	 * Truncate a value to a maximum length (multibyte safe).
	 *
	 * @param string $value      Value to truncate.
	 * @param int    $max_length Maximum length in characters.
	 *
	 * @return string
	 */
	private function truncate_value( string $value, int $max_length ): string {
		return mb_strlen( $value ) <= $max_length ? $value : mb_substr( $value, 0, $max_length );
	}

	/**
	 * The plugin version.
	 *
	 * @return string
	 */
	private function library_version(): string {
		return defined( 'FLW_PAY_VERSION' ) ? FLW_PAY_VERSION : '';
	}
}
