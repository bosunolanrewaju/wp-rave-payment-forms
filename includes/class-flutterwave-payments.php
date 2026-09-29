<?php
/**
 * Flutterwave base class
 *
 * @package Flutterwave_Payments
 */

use Flutterwave\WordPress\API\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class
 */
final class Flutterwave_Payments {

	/**
	 * Plugin name
	 * Plugin name
	 *
	 * @var string $plugin_name
	 */
	private string $plugin_name = 'flutterwave-payments';
	/**
	 * Plugin version
	 *
	 * @var string $plugin_version
	 */
	private string $plugin_version = '1.0.7';

	/**
	 * Allowed donation payment types.
	 *
	 * @var string[]
	 */
	const PAYMENT_TYPES = array( 'once', 'monthly', 'yearly' );

	/**
	 * Checkout attempts allowed per IP address within the rate limit window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX = 30;

	/**
	 * Rate limit window in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 600;

	/**
	 * Instance variable
	 *
	 * @var Flutterwave_Payments|null $instance
	 */
	protected static ?Flutterwave_Payments $instance = null;

	/**
	 * API Client
	 *
	 * @var Client $api_client
	 */
	protected Client $api_client;

	/**
	 * API Client
	 *
	 * @var object|null $settings
	 */
	private ?object $settings;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->define_constants();
		$this->include_files();
		$this->init();
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( string $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- only called with FLW_ constants.
		}
	}

	/**
	 * Define all constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		$this->define( 'FLW_PAY_VERSION', $this->plugin_version );
		$this->define( 'FLW_DIR_PATH', plugin_dir_path( FLW_PAY_PLUGIN_FILE ) );
		$this->define( 'FLW_DIR_URL', plugin_dir_url( FLW_PAY_PLUGIN_FILE ) );
	}

	/**
	 * Includes all required files
	 *
	 * @return void
	 */
	private function include_files() {
		require_once FLW_DIR_PATH . 'includes/class-flw-admin-settings.php';
		require_once FLW_DIR_PATH . 'includes/class-flw-payment-list.php';
		require_once FLW_DIR_PATH . 'includes/vc-elements/class-flw-vc-simple-form.php';
		require_once FLW_DIR_PATH . 'src/Exception/class-apiexception.php';
		require_once FLW_DIR_PATH . 'src/API/class-handler.php';
		require_once FLW_DIR_PATH . 'src/API/class-client.php';
		require_once FLW_DIR_PATH . 'src/Helper/class-webhookhelper.php';
		require_once FLW_DIR_PATH . 'includes/class-flw-form-config.php';
		require_once FLW_DIR_PATH . 'includes/class-flw-payment-record.php';
		require_once FLW_DIR_PATH . 'includes/admin/class-flw-settings.php';
		require_once FLW_DIR_PATH . 'includes/admin/class-flw-settings-controller.php';
		require_once FLW_DIR_PATH . 'includes/admin/class-flw-payments-controller.php';
		require_once FLW_DIR_PATH . 'includes/admin/class-flw-forms-controller.php';
		require_once FLW_DIR_PATH . 'includes/admin/class-flw-tour.php';
		require_once FLW_DIR_PATH . 'includes/observability/class-flw-signoz-logger.php';
		require_once FLW_DIR_PATH . 'includes/observability/class-flw-app-registration.php';
		require_once FLW_DIR_PATH . 'includes/blocks/class-flw-blocks.php';

		require_once FLW_DIR_PATH . 'includes/api/class-flw-transaction-rest-route.php';
		require_once FLW_DIR_PATH . 'includes/api/class-flw-webhook-rest-route.php';
		require_once FLW_DIR_PATH . 'includes/class-flw-shortcodes.php';
		require_once FLW_DIR_PATH . 'includes/integrations/class-flw-hosted-checkout.php';
		require_once FLW_DIR_PATH . 'includes/integrations/class-flw-integrations.php';
		require_once FLW_DIR_PATH . 'includes/integrations/class-flw-edd-gateway.php';
		require_once FLW_DIR_PATH . 'includes/integrations/class-flw-givewp.php';

		if ( is_admin() ) {
			require_once FLW_DIR_PATH . 'includes/class-flw-tinymce-plugin.php';
		}
	}

	/**
	 * Initialize all the included classe
	 *
	 * @return void
	 */
	private function init() {

		if ( ! shortcode_exists( 'flw-pay-button' ) && ! shortcode_exists( 'flw-donation-page' ) ) {
			// include shortcodes.
			require_once FLW_DIR_PATH . 'includes/shortcodes/class-abstract-flw-shortcode.php';
			require_once FLW_DIR_PATH . 'includes/shortcodes/class-flw-shortcode-donation-form.php';
			require_once FLW_DIR_PATH . 'includes/shortcodes/class-flw-shortcode-payment-form.php';

			// initialize shortcodes.
			FLW_Shortcodes::get_instance();
		}
		$this->settings = FLW_Admin_Settings::get_instance();
		// WP_List_Table translates strings in its constructor, which must not happen before init.
		add_action( 'init', array( 'FLW_Payment_List', 'get_instance' ), 0 );
		$this->api_client = Client::get_instance( $this->get_option( 'secret_key' ) );

		if ( is_admin() ) {
			// TODO: Introduce Advanced TinyMCE Plugin.
			FLW_Tinymce_Plugin::get_instance();
		}

		// Initiate Endpoints.
		new FLW_Transaction_Rest_Route();
		new FLW_Webhook_Rest_Route();

		FLW_Settings_Controller::register_hooks();
		FLW_Payments_Controller::register_hooks();
		FLW_Forms_Controller::register_hooks();
		FLW_Tour::register_hooks();
		FLW_Signoz_Logger::register_hooks();
		FLW_App_Registration::register_hooks();
		FLW_Blocks::register_hooks();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_get_payment_url', array( $this, 'get_payment_url' ) );
		add_action( 'wp_ajax_nopriv_get_payment_url', array( $this, 'get_payment_url' ) );

		FLW_Integrations::register_hooks();
	}

	/**
	 * Get Plugin options.
	 *
	 * @param string $name The name of the option.
	 *
	 * @return mixed
	 */
	private function get_option( string $name ) {
		return $this->settings->get_option_value( $name );
	}

	/**
	 * Adds admin settings page to the dashboard
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_options' ) || FLW_Admin_Settings::is_settings_screen() ) {
			return;
		}

		$no_public_key = empty( $this->get_option( 'public_key' ) ?? '' );
		$no_secret_key = empty( $this->get_option( 'secret_key' ) ?? '' );

		if ( $no_secret_key || $no_public_key ) {
			printf(
				'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
				esc_html__( 'Flutterwave Payments is installed.', 'rave-payment-forms' ),
				esc_html__( 'Connect your Flutterwave account to start accepting payments with your forms.', 'rave-payment-forms' ),
				esc_url( FLW_Admin_Settings::get_url() ),
				esc_html__( 'Set up Flutterwave', 'rave-payment-forms' )
			);
		}
	}

	/**
	 * Generate Payment Hash.
	 *
	 * @param array $payment_data data to hash.
	 *
	 * @return string
	 */
	private static function generate_payment_hash( array $payment_data ) {
		$data_to_join = array(
			'amount'     => $payment_data['amount'],
			'currency'   => $payment_data['currency'],
			'email'      => $payment_data['email'],
			'tx_ref'     => $payment_data['tx_ref'],
			'secret_key' => ( FLW_Admin_Settings::get_instance() )->get_option_value( 'secret_key' ),
		);

		$string_to_hash = '';
		foreach ( $data_to_join as $key => $value ) {
			if ( 'secret_key' === $key ) {
				$string_to_hash .= hash( 'sha256', $value );
			} else {
				$string_to_hash .= $value;
			}
		}

		return hash( 'sha256', $string_to_hash );
	}

	/**
	 * Generate Payment Link with Standard Endpoint.
	 *
	 * @return void
	 */
	public function get_payment_url() {
		check_ajax_referer( 'flw-rave-pay-nonce', 'flw_sec_code' );

		if ( $this->is_rate_limited() ) {
			$this->send_checkout_error( __( 'Too many payment attempts. Please wait a few minutes and try again.', 'rave-payment-forms' ), 429 );
			return;
		}

		$form_config = FLW_Form_Config::verify(
			isset( $_POST['flw_form_config'] ) ? sanitize_text_field( wp_unslash( $_POST['flw_form_config'] ) ) : '',
			isset( $_POST['flw_form_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['flw_form_sig'] ) ) : ''
		);

		if ( is_wp_error( $form_config ) ) {
			$this->send_checkout_error( $form_config->get_error_message() );
			return;
		}

		$terms = FLW_Form_Config::resolve_terms(
			$form_config,
			isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : null,
			isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : ''
		);

		if ( is_wp_error( $terms ) ) {
			$this->send_checkout_error( $terms->get_error_message() );
			return;
		}

		$payment_type = isset( $_POST['payment_type'] ) ? sanitize_key( wp_unslash( $_POST['payment_type'] ) ) : 'once';

		if ( ! in_array( $payment_type, self::PAYMENT_TYPES, true ) ) {
			$this->send_checkout_error( __( 'Invalid payment type.', 'rave-payment-forms' ) );
			return;
		}

		$amount          = $terms['amount'];
		$email           = isset( $_POST['customer']['email'] ) ? sanitize_email( wp_unslash( $_POST['customer']['email'] ) ) : null;
		$country         = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'NGN';
		$form_id         = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : null;
		$tx_ref          = 'WP_' . $form_id . wp_rand( 20, 15003 ) . '_' . time();
		$currency        = $terms['currency'];
		$name            = isset( $_POST['customer']['name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer']['name'] ) ) : null;
		$phone           = ( isset( $_POST['customer']['phone_number'] ) ) ? sanitize_text_field( wp_unslash( $_POST['customer']['phone_number'] ) ) : null;
		$payment_options = FLW_Settings::payment_options();
		$title           = '' !== FLW_Settings::get( 'modal_title' ) ? FLW_Settings::get( 'modal_title' ) : get_bloginfo( 'name' );

		$payment_hash_args = array(
			'amount'   => $amount,
			'currency' => $currency,
			'email'    => $email,
			'tx_ref'   => $tx_ref,
		);

		$payment_hash = $this->generate_payment_hash( $payment_hash_args );
		$ip_address   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';

		$args = array(
			'post_type'   => 'payment_list',
			'post_status' => 'publish',
			'post_title'  => $tx_ref,
		);

		$payment_record_id = wp_insert_post( $args, true );

		if ( ! is_wp_error( $payment_record_id ) ) {

			$post_meta = array(
				'_flw_rave_payment_amount'   => (float) $amount,
				'_flw_rave_payment_fullname' => $name,
				'_flw_rave_payment_customer' => $email,
				'_flw_rave_payment_currency' => $currency,
				'_flw_rave_payment_status'   => 'pending',
				'_flw_rave_payment_tx_ref'   => $tx_ref,
			);

			$source = self::source_post_id();

			if ( $source > 0 ) {
				$post_meta[ FLW_Payments_Controller::SOURCE_META ] = $source;
			}

			$this->add_post_meta( $payment_record_id, $post_meta );
		}
		$redirect_url = get_site_url() . '/wp-json/flutterwave/v1/verify-transaction?order=' . $payment_record_id;
		// check for payment type.

		$payload = array(
			'tx_ref'          => $tx_ref,
			'amount'          => $amount,
			'currency'        => $currency,
			'country'         => $country,
			'redirect_url'    => $redirect_url,
			'payment_options' => $payment_options,
			'payment_hash'    => $payment_hash,
			'customer'        => array(
				'email'       => $email,
				'phonenumber' => $phone,
				'name'        => $name,
			),
			'meta'            => array(
				'form_id'        => $form_id,
				'ip_address'     => $ip_address,
				'order_id'       => $payment_record_id,
				'order_amount'   => $amount,
				'order_currency' => $currency,
			),
			'customizations'  => array_filter(
				array(
					'title'       => $title,
					'description' => '' !== FLW_Settings::get( 'modal_desc' ) ? FLW_Settings::get( 'modal_desc' ) : 'Payment #' . $payment_record_id,
					'logo'        => FLW_Settings::get( 'modal_logo' ),
				)
			),
		);

		if ( 'once' !== $payment_type ) {
			$key = 'flw_plan_' . md5( $amount . '_' . $currency . '_' . $payment_type );
			// check if the payment_plan exists in transient.
			$plan_id = get_transient( $key );

			if ( ! $plan_id ) {

				$plan_id = $this->generate_payment_plan(
					array(
						'amount'   => $amount,
						'name'     => 'donation_' . $amount . '_' . $payment_type,
						'interval' => $payment_type,
						'currency' => $currency,
					)
				);

				if ( is_wp_error( $plan_id ) || empty( $plan_id ) ) {
					FLW_Signoz_Logger::instance()->track_error(
						'PAYMENT_PLAN_FAILED',
						is_wp_error( $plan_id ) ? $plan_id->get_error_message() : 'Payment plan creation returned no id',
						$tx_ref
					);
					$this->send_checkout_error( __( 'Unable to set up a recurring donation. Please try again.', 'rave-payment-forms' ) );
					return;
				}

				set_transient( $key, $plan_id, 30 * DAY_IN_SECONDS );
			}//end if
			$payload['payment_plan'] = $plan_id;
		}//end if

		FLW_Signoz_Logger::instance()->track_request_sent( 'POST', $tx_ref, '/payments' );

		$response = $this->api_client->request(
			'/payments',
			'POST',
			$payload
		);

		if ( is_wp_error( $response ) ) {
			FLW_Signoz_Logger::instance()->track_error( 'CHECKOUT_FAILED', $response->get_error_message(), $tx_ref );
			$this->send_checkout_error( $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		$link = is_object( $body ) && isset( $body->data->link ) && is_string( $body->data->link ) ? $body->data->link : '';

		// Flutterwave answers validation problems with a 400 and no payment link.
		if ( '' === $link ) {
			$reason = is_object( $body ) && isset( $body->message ) && is_string( $body->message ) ? $body->message : 'No payment link returned';
			FLW_Signoz_Logger::instance()->track_error( 'CHECKOUT_FAILED', $reason, $tx_ref );
			$this->send_checkout_error( __( 'Unable to start the payment. Please try again.', 'rave-payment-forms' ), 502 );
			return;
		}

		wp_send_json(
			array(
				'status' => 'success',
				'data'   => $payload,
				'url'    => $link,
			),
			200
		);

		wp_die();
	}

	/**
	 * Processes payment record information
	 *
	 * @param int $len length of string.
	 *
	 * @return string
	 */
	public static function gen_rand_string( $len = 4 ) {

		if ( version_compare( PHP_VERSION, '5.3.0' ) <= 0 ) {
			return substr( md5( wp_rand() ), 0, $len );
		}
		return bin2hex( openssl_random_pseudo_bytes( $len / 2 ) );
	}

	/**
	 * The page the checkout form was submitted from, so the Payment Forms screen
	 * can count payments per page. 0 when it cannot be told.
	 *
	 * @return int
	 */
	private static function source_post_id(): int {
		$referer = wp_get_referer();

		if ( ! is_string( $referer ) || '' === $referer ) {
			return 0;
		}

		$post_id = url_to_postid( $referer );

		if ( 0 === $post_id && untrailingslashit( strtok( $referer, '?' ) ) === untrailingslashit( home_url() ) ) {
			$post_id = (int) get_option( 'page_on_front' );
		}

		return $post_id > 0 && 'publish' === get_post_status( $post_id ) ? $post_id : 0;
	}

	/**
	 * Send a checkout error to the browser.
	 *
	 * @param string $message     The error message.
	 * @param int    $status_code HTTP status code.
	 *
	 * @return void
	 */
	private function send_checkout_error( string $message, int $status_code = 400 ): void {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => esc_html( $message ),
			),
			$status_code
		);
	}

	/**
	 * Count a checkout attempt for the current IP and report whether it is over the limit.
	 *
	 * Behind a proxy every visitor may share REMOTE_ADDR, so the limit can be raised or
	 * disabled (0) with the `flw_checkout_rate_limit` filter.
	 *
	 * @return bool
	 */
	private function is_rate_limited(): bool {
		$limit = (int) apply_filters( 'flw_checkout_rate_limit', self::RATE_LIMIT_MAX );

		if ( $limit <= 0 ) {
			return false;
		}

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key        = 'flw_rl_' . md5( $ip_address );
		$attempts   = (int) get_transient( $key );

		if ( $attempts >= $limit ) {
			return true;
		}

		set_transient( $key, $attempts + 1, self::RATE_LIMIT_WINDOW );

		return false;
	}

	/**
	 * Adds metadata to payment list post type
	 *
	 * @param [int]   $post_id  The ID of the post to add metadata to.
	 * @param [array] $data     Collection of the data to be added to the post.
	 */
	private function add_post_meta( $post_id, $data ) {

		foreach ( $data as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Generate Payment Plan.
	 *
	 * @param array $data  create a payment plan.
	 *
	 * @return int|WP_Error
	 */
	protected function generate_payment_plan( array $data ) {
		// amount, name, interval.
		$response = $this->api_client->request(
			'/payment-plans',
			'POST',
			$data
		);

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $body->data->id;
	}

	/**
	 * Gets the instance of this class
	 *
	 * @return object the single instance of this class.
	 */
	public static function get_instance(): object {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
