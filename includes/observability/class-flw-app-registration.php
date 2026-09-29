<?php
/**
 * Registers the site with the SigNoz service (app.created) without the merchant
 * having to do anything.
 *
 * Three triggers, all funnelling into the same guarded enqueue:
 *  - `flw_onboarding_complete` fires when a merchant finishes the setup wizard.
 *  - `upgrader_process_complete` fires right after this plugin is updated from
 *    the dashboard or WP-CLI.
 *  - `admin_init` checks the stored registration on admin requests, as a catch-all
 *    for updates that bypass the upgrader (manual uploads, host-level deploys).
 *
 * None of them do network I/O inline; they only queue a background job.
 * Ported from the Flutterwave WooCommerce plugin.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Automatic app registration.
 */
final class FLW_App_Registration {

	const REGISTER_HOOK = 'flw_signoz_register_app';

	/**
	 * Singleton instance.
	 *
	 * @var FLW_App_Registration|null
	 */
	private static $instance = null;

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
	 * Register hooks. Call once from the plugin bootstrap.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$self = self::instance();

		add_action( self::REGISTER_HOOK, array( $self, 'handle_scheduled_registration' ) );
		add_action( 'admin_init', array( $self, 'maybe_enqueue_registration' ) );
		add_action( 'flw_onboarding_complete', array( $self, 'maybe_enqueue_registration' ) );
		add_action( 'upgrader_process_complete', array( $self, 'maybe_trigger_on_upgrade' ), 10, 2 );
	}

	/**
	 * Fires after the WP upgrader updates plugins; only acts when this plugin was one of them.
	 *
	 * @param mixed $upgrader_object Unused; required by the hook signature.
	 * @param array $options         Upgrade context: type, action, plugins/plugin.
	 *
	 * @return void
	 */
	public function maybe_trigger_on_upgrade( $upgrader_object, $options ): void {
		unset( $upgrader_object );

		if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
			return;
		}

		$updated = array();

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$updated = array( $options['plugin'] );
		}

		if ( in_array( plugin_basename( FLW_PAY_PLUGIN_FILE ), $updated, true ) ) {
			$this->maybe_enqueue_registration();
		}
	}

	/**
	 * Queue registration when it is missing, stale for the running version, or
	 * for a different public key. Option reads only; no network calls.
	 *
	 * @return void
	 */
	public function maybe_enqueue_registration(): void {
		$logger = FLW_Signoz_Logger::instance();

		if ( ! $logger->is_enabled() ) {
			return;
		}

		$public_key = trim( FLW_Settings::get( 'public_key' ) );

		// Nothing to register until the merchant adds keys.
		if ( '' === $public_key ) {
			return;
		}

		if ( $this->is_registered( $public_key ) ) {
			return;
		}

		$args = array( $public_key );

		if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_enqueue_async_action' ) ) {
			if ( ! as_has_scheduled_action( self::REGISTER_HOOK, $args, FLW_Signoz_Logger::AS_GROUP ) ) {
				as_enqueue_async_action( self::REGISTER_HOOK, $args, FLW_Signoz_Logger::AS_GROUP );
			}
			return;
		}

		if ( false === wp_next_scheduled( self::REGISTER_HOOK, $args ) ) {
			wp_schedule_single_event( time(), self::REGISTER_HOOK, $args );
		}
	}

	/**
	 * Whether the site is registered for this key on the running plugin version.
	 *
	 * @param string $public_key Merchant public key.
	 *
	 * @return bool
	 */
	public function is_registered( string $public_key ): bool {
		$state = FLW_Signoz_Logger::instance()->get_state();

		return ! empty( $state['app_registered'] )
			&& ( $state['public_key'] ?? '' ) === $public_key
			&& ( $state['plugin_version'] ?? '' ) === ( defined( 'FLW_PAY_VERSION' ) ? FLW_PAY_VERSION : '' );
	}

	/**
	 * Background callback: register the app. On success the state is stamped so
	 * later checks short-circuit; on failure it is left alone so the next admin
	 * page load retries.
	 *
	 * @param mixed $public_key Merchant public key.
	 *
	 * @return void
	 */
	public function handle_scheduled_registration( $public_key ): void {
		try {
			if ( ! is_string( $public_key ) || '' === $public_key ) {
				return;
			}

			// The key may have changed since this job was queued.
			if ( trim( FLW_Settings::get( 'public_key' ) ) !== $public_key ) {
				return;
			}

			$app_id = FLW_Signoz_Logger::instance()->track_app_created( $public_key );

			// Service unavailable or circuit open: a later admin request retries.
			if ( null === $app_id ) {
				return;
			}

			FLW_Signoz_Logger::instance()->update_state(
				array(
					'app_registered' => true,
					'public_key'     => $public_key,
					'plugin_version' => defined( 'FLW_PAY_VERSION' ) ? FLW_PAY_VERSION : '',
				)
			);
		} catch ( \Throwable $e ) {
			// Observability must never break the site.
			unset( $e );
		}//end try
	}
}
