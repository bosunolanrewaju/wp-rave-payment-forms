<?php
/**
 * Integrations with other WordPress plugins.
 *
 * WooCommerce is served by the separate Flutterwave WooCommerce extension, which
 * the Integrations screen can install and activate. Easy Digital Downloads and
 * GiveWP are built in: switching one on registers a Flutterwave gateway with
 * that plugin. The rest of the catalogue is listed as coming soon.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integration catalogue, state and REST routes.
 */
class FLW_Integrations {

	/**
	 * Option holding which built-in integrations are switched on.
	 */
	const OPTION_KEY = 'flw_integrations';

	/**
	 * Built-in integrations and the classes that implement them.
	 *
	 * @var array<string, string>
	 */
	const BUILT_IN = array(
		'easy-digital-downloads' => 'FLW_EDD_Gateway',
		'givewp'                 => 'FLW_GiveWP',
	);

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 20 );
	}

	/**
	 * Register the gateways of the integrations that are switched on.
	 *
	 * @return void
	 */
	public static function boot(): void {
		foreach ( self::BUILT_IN as $id => $class ) {
			if ( self::is_enabled( $id ) && $class::is_host_active() ) {
				$class::register();
			}
		}
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/integrations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_integrations' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/integrations/(?P<id>[a-z-]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_integration' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		// Flutterwave sends the customer back here after paying on the hosted checkout.
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/return/(?P<id>[a-z-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_return' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Whether the current user can manage integrations.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether a built-in integration is switched on.
	 *
	 * @param string $id Integration id.
	 *
	 * @return bool
	 */
	public static function is_enabled( string $id ): bool {
		$enabled = get_option( self::OPTION_KEY, array() );

		return is_array( $enabled ) && ! empty( $enabled[ $id ] );
	}

	/**
	 * The integration class that made a transaction reference, if any.
	 *
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return string|null Class name.
	 */
	public static function for_reference( string $tx_ref ): ?string {
		foreach ( self::BUILT_IN as $class ) {
			if ( FLW_Hosted_Checkout::id_from_reference( $class::PREFIX, $tx_ref ) > 0 ) {
				return $class;
			}
		}

		return null;
	}

	/**
	 * The integration catalogue.
	 *
	 * @return array[]
	 */
	public static function catalogue(): array {
		return array(
			array(
				'id'          => 'woocommerce',
				'name'        => 'WooCommerce',
				'category'    => 'ecommerce',
				'description' => __( 'Take Flutterwave payments at your WooCommerce checkout with the official Flutterwave WooCommerce extension.', 'rave-payment-forms' ),
				'kind'        => 'extension',
				'host'        => array(
					'name' => 'WooCommerce',
					'slug' => 'woocommerce',
					'file' => 'woocommerce/woocommerce.php',
				),
				'extension'   => array(
					'name' => 'Flutterwave WooCommerce',
					'slug' => 'rave-woocommerce-payment-gateway',
					'file' => 'rave-woocommerce-payment-gateway/rave-woocommerce-payment-gateway.php',
				),
				'manageUrl'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rave' ),
			),
			array(
				'id'          => 'easy-digital-downloads',
				'name'        => 'Easy Digital Downloads',
				'category'    => 'digital',
				'description' => __( 'Sell ebooks, software, music and other downloads, and let customers pay with Flutterwave at checkout.', 'rave-payment-forms' ),
				'kind'        => 'built-in',
				'host'        => array(
					'name' => 'Easy Digital Downloads',
					'slug' => 'easy-digital-downloads',
					'file' => 'easy-digital-downloads/easy-digital-downloads.php',
				),
				'manageUrl'   => admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways' ),
			),
			array(
				'id'          => 'givewp',
				'name'        => 'GiveWP',
				'category'    => 'donations',
				'description' => __( 'Collect one-off donations through GiveWP forms and campaigns, paid with Flutterwave.', 'rave-payment-forms' ),
				'kind'        => 'built-in',
				'host'        => array(
					'name' => 'GiveWP',
					'slug' => 'give',
					'file' => 'give/give.php',
				),
				'manageUrl'   => admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways' ),
			),
			self::coming_soon( 'wpforms', 'WPForms', 'forms', __( 'Add a Flutterwave payment field to contact, order and registration forms.', 'rave-payment-forms' ) ),
			self::coming_soon( 'gravity-forms', 'Gravity Forms', 'forms', __( 'Charge for form submissions such as applications, bookings and orders.', 'rave-payment-forms' ) ),
			self::coming_soon( 'contact-form-7', 'Contact Form 7', 'forms', __( 'Ask for payment before a Contact Form 7 submission is sent.', 'rave-payment-forms' ) ),
			self::coming_soon( 'paid-memberships-pro', 'Paid Memberships Pro', 'memberships', __( 'Sell membership levels and restrict content to paying members.', 'rave-payment-forms' ) ),
			self::coming_soon( 'memberpress', 'MemberPress', 'memberships', __( 'Take membership and subscription payments with Flutterwave.', 'rave-payment-forms' ) ),
			self::coming_soon( 'learndash', 'LearnDash', 'courses', __( 'Sell online courses and let students pay in their local currency.', 'rave-payment-forms' ) ),
			self::coming_soon( 'tutor-lms', 'Tutor LMS', 'courses', __( 'Charge for courses and course bundles built with Tutor LMS.', 'rave-payment-forms' ) ),
			self::coming_soon( 'event-tickets', 'Event Tickets', 'events', __( 'Sell tickets for events managed with The Events Calendar.', 'rave-payment-forms' ) ),
		);
	}

	/**
	 * A catalogue entry that is not available yet.
	 *
	 * @param string $id          Id.
	 * @param string $name        Plugin name.
	 * @param string $category    Category.
	 * @param string $description Description.
	 *
	 * @return array
	 */
	private static function coming_soon( string $id, string $name, string $category, string $description ): array {
		return array(
			'id'          => $id,
			'name'        => $name,
			'category'    => $category,
			'description' => $description,
			'kind'        => 'coming-soon',
		);
	}

	/**
	 * Installed, active or missing.
	 *
	 * @param string $file Plugin file relative to the plugins directory.
	 *
	 * @return string
	 */
	private static function plugin_state( string $file ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( $file ) ) {
			return 'active';
		}

		return isset( get_plugins()[ $file ] ) ? 'inactive' : 'missing';
	}

	/**
	 * A catalogue entry with its state on this site.
	 *
	 * @param array $entry Catalogue entry.
	 *
	 * @return array
	 */
	public static function with_state( array $entry ): array {
		if ( 'coming-soon' === $entry['kind'] ) {
			return $entry;
		}

		$entry['host']['state'] = self::plugin_state( $entry['host']['file'] );
		$entry['host']['rest']  = self::rest_plugin_id( $entry['host']['file'] );

		if ( 'extension' === $entry['kind'] ) {
			$entry['extension']['state'] = self::plugin_state( $entry['extension']['file'] );
			$entry['extension']['rest']  = self::rest_plugin_id( $entry['extension']['file'] );
			$entry['enabled']            = 'active' === $entry['host']['state'] && 'active' === $entry['extension']['state'];

			return $entry;
		}

		$class = self::BUILT_IN[ $entry['id'] ];

		// Some hosts ship under another folder name (for example a Pro edition).
		if ( 'active' !== $entry['host']['state'] && $class::is_host_active() ) {
			$entry['host']['state'] = 'active';
		}

		$entry['enabled']  = self::is_enabled( $entry['id'] );
		$entry['currency'] = 'active' === $entry['host']['state'] ? $class::host_currency() : '';

		return $entry;
	}

	/**
	 * A plugin file as the /wp/v2/plugins route names it: the path without ".php".
	 *
	 * @param string $file Plugin file.
	 *
	 * @return string
	 */
	private static function rest_plugin_id( string $file ): string {
		return substr( $file, 0, -4 );
	}

	/**
	 * GET /integrations.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_integrations(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'items'       => array_map( array( __CLASS__, 'with_state' ), self::catalogue() ),
				'currencies'  => FLW_Settings::CURRENCIES,
				'canInstall'  => current_user_can( 'install_plugins' ) && wp_is_file_mod_allowed( 'flw_integrations' ),
				'canActivate' => current_user_can( 'activate_plugins' ),
			)
		);
	}

	/**
	 * POST /integrations/{id}: switch a built-in integration on or off.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_integration( WP_REST_Request $request ) {
		$id = (string) $request->get_param( 'id' );

		if ( ! isset( self::BUILT_IN[ $id ] ) ) {
			return new WP_Error( 'flw-unknown-integration', __( 'This integration cannot be switched on here.', 'rave-payment-forms' ), array( 'status' => 404 ) );
		}

		$class   = self::BUILT_IN[ $id ];
		$enabled = (bool) $request->get_param( 'enabled' );

		if ( $enabled && ! $class::is_host_active() ) {
			return new WP_Error( 'flw-host-inactive', __( 'Install and activate the plugin first.', 'rave-payment-forms' ), array( 'status' => 409 ) );
		}

		$stored        = get_option( self::OPTION_KEY, array() );
		$stored        = is_array( $stored ) ? $stored : array();
		$stored[ $id ] = $enabled;
		update_option( self::OPTION_KEY, $stored );

		if ( $class::is_host_active() ) {
			$class::set_host_enabled( $enabled );
		}

		foreach ( self::catalogue() as $entry ) {
			if ( $entry['id'] === $id ) {
				return rest_ensure_response( self::with_state( $entry ) );
			}
		}

		return rest_ensure_response( array() );
	}

	/**
	 * GET /return/{id}: confirm the payment and send the customer on.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_return( WP_REST_Request $request ): WP_REST_Response {
		$id  = (string) $request->get_param( 'id' );
		$url = home_url( '/' );

		if ( isset( self::BUILT_IN[ $id ] ) && self::is_enabled( $id ) && self::BUILT_IN[ $id ]::is_host_active() ) {
			$url = self::BUILT_IN[ $id ]::handle_return( FLW_Hosted_Checkout::returned( $request ) );
		}

		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', wp_validate_redirect( $url, home_url( '/' ) ) );

		return $response;
	}

	/**
	 * Handle a webhook for a payment made through an integration.
	 *
	 * @param array $data Webhook `data`.
	 *
	 * @return string|null Outcome, or null when no integration made this payment.
	 */
	public static function handle_webhook( array $data ): ?string {
		$tx_ref = sanitize_text_field( (string) ( $data['tx_ref'] ?? '' ) );
		$class  = self::for_reference( $tx_ref );

		if ( null === $class || ! $class::is_host_active() ) {
			return null;
		}

		if ( 'cancelled' === ( $data['status'] ?? '' ) ) {
			return $class::cancel( $tx_ref ) ? 'cancelled' : 'ignored';
		}

		$transaction = FLW_Payment_Record::fetch_verified( absint( $data['id'] ?? 0 ) );

		if ( is_wp_error( $transaction ) ) {
			FLW_Signoz_Logger::instance()->track_error( 'WEBHOOK_VERIFY_FAILED', $transaction->get_error_message(), $tx_ref );
			return 'error';
		}

		return $class::complete( $transaction ) ? 'success' : 'failed';
	}
}
