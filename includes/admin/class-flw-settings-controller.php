<?php
/**
 * REST endpoints backing the admin app.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings REST controller.
 */
final class FLW_Settings_Controller {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/onboarding/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'complete_onboarding' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Only administrators may read or write API keys.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings(): WP_REST_Response {
		return rest_ensure_response( FLW_Settings::to_payload() );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public static function update_settings( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( FLW_Settings::update( self::body( $request ) ) );
	}

	/**
	 * POST /onboarding/complete: saves the last step and marks setup as finished.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public static function complete_onboarding( WP_REST_Request $request ): WP_REST_Response {
		$body = self::body( $request );

		$body['onboardingComplete'] = true;

		$payload = FLW_Settings::update( $body );

		/**
		 * Fires once a merchant finishes the onboarding wizard.
		 *
		 * @param array $payload The saved settings, as returned to the admin app.
		 */
		do_action( 'flw_onboarding_complete', $payload );

		return rest_ensure_response( $payload );
	}

	/**
	 * The JSON body, falling back to form parameters.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return array<string, mixed>
	 */
	private static function body( WP_REST_Request $request ): array {
		$body = $request->get_json_params();

		return is_array( $body ) ? $body : (array) $request->get_body_params();
	}
}
