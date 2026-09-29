<?php
/**
 * Guided tips for the admin screens.
 *
 * Each screen has a short tour that starts the first time a merchant opens it
 * after onboarding. Whether tips are on, and which tours a user has seen, is
 * kept per user, so every administrator gets the tour once and can turn it off
 * for themselves.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tour preferences and the route that updates them.
 */
class FLW_Tour {

	/**
	 * User meta key.
	 */
	const META_KEY = 'flw_tour';

	/**
	 * Most tours remembered per user; older entries are dropped first.
	 */
	const MAX_SEEN = 50;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/tour',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'enabled' => array(
						'type' => 'boolean',
					),
					'seen'    => array(
						'type'    => 'string',
						'pattern' => '^[a-z-]+@[0-9]+$',
					),
					'reset'   => array(
						'type' => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * Whether the current user can use the admin screens.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * A user's tour preferences.
	 *
	 * @param int $user_id User id; the current user by default.
	 *
	 * @return array{enabled: bool, seen: string[], updated: int}
	 */
	public static function get( int $user_id = 0 ): array {
		$user_id = $user_id ? $user_id : get_current_user_id();
		$stored  = get_user_meta( $user_id, self::META_KEY, true );
		$stored  = is_array( $stored ) ? $stored : array();

		return array(
			'enabled' => ! isset( $stored['enabled'] ) || (bool) $stored['enabled'],
			'seen'    => array_values( array_filter( (array) ( $stored['seen'] ?? array() ), 'is_string' ) ),
			'updated' => (int) ( $stored['updated'] ?? 0 ),
		);
	}

	/**
	 * POST /tour: turn tips on or off, record a finished tour, or forget them all.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function update( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$state   = self::get( $user_id );

		if ( null !== $request->get_param( 'enabled' ) ) {
			$state['enabled'] = (bool) $request->get_param( 'enabled' );
		}

		if ( $request->get_param( 'reset' ) ) {
			$state['seen'] = array();
		}

		$seen = (string) $request->get_param( 'seen' );

		if ( '' !== $seen && ! in_array( $seen, $state['seen'], true ) ) {
			$state['seen'][] = $seen;
			$state['seen']   = array_slice( $state['seen'], -self::MAX_SEEN );
		}

		// When it changed, so the screen can tell whether its own copy is newer.
		$state['updated'] = time();

		update_user_meta( $user_id, self::META_KEY, $state );

		return rest_ensure_response( $state );
	}
}
