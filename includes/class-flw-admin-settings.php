<?php
/**
 * Adming Settings Page Class
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;


/**
 * Admin Settings class
 */
class FLW_Admin_Settings {

	/**
	 * Class instance
	 *
	 * @var $instance
	 */
	public static $instance = null;

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'flutterwave-payments';

	/**
	 * Hook suffix WordPress assigns to the top-level settings page.
	 */
	const HOOK_SUFFIX = 'toplevel_page_flutterwave-payments';

	/**
	 * Payment Forms page slug.
	 */
	const FORMS_SLUG = 'flutterwave-payment-forms';

	/**
	 * Transactions page slug (kept from the old list table so links still work).
	 */
	const TRANSACTIONS_SLUG = 'flutterwave-payments-transactions';

	/**
	 * Integrations page slug.
	 */
	const INTEGRATIONS_SLUG = 'flutterwave-payments-integrations';

	/**
	 * Admin app screens by hook suffix.
	 *
	 * @var array<string, string>
	 */
	private static $screens = array( self::HOOK_SUFFIX => 'settings' );

	/**
	 * Admin options array
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Class constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'flw_rave_add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_app' ) );
		add_action( 'admin_init', array( $this, 'flw_rave_register_settings' ) );
		$this->init_settings();
	}

	/**
	 * Registers admin setting
	 *
	 * @return void
	 */
	public function flw_rave_register_settings() {

		register_setting(
			'flw-rave-settings-group',
			'flw_rave_options',
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);
	}

	/**
	 * Sanitize settings before they are saved (register_setting callback).
	 *
	 * @param mixed $input Submitted settings.
	 *
	 * @return array
	 */
	public function sanitize_options( $input ): array {
		return self::sanitize( $input );
	}

	/**
	 * Sanitize settings. Unknown keys are dropped.
	 *
	 * @param mixed $input Settings to clean.
	 *
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		// Credentials are used as-is in API headers, so only whitespace and control characters are stripped.
		foreach ( array( 'public_key', 'secret_key', 'secret_hash' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = preg_replace( '/[\x00-\x1F\x7F]/', '', trim( (string) $input[ $key ] ) );
			}
		}

		foreach ( array( 'modal_title', 'modal_desc', 'donation_title', 'donation_desc', 'donation_phone', 'donation_payment_plan', 'btn_text' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		foreach ( array( 'modal_logo', 'pending_redirect_url', 'success_redirect_url', 'failed_redirect_url' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = esc_url_raw( (string) $input[ $key ], array( 'http', 'https' ) );
			}
		}

		foreach ( array( 'go_live', 'theme_style', 'onboarding_complete' ) as $key ) {
			if ( isset( $input[ $key ] ) && 'yes' === $input[ $key ] ) {
				$clean[ $key ] = 'yes';
			}
		}

		if ( isset( $input['payment_options'] ) && is_string( $input['payment_options'] ) ) {
			$tokens = array_intersect(
				array_map( 'trim', explode( ',', $input['payment_options'] ) ),
				FLW_Settings::known_tokens()
			);

			$clean['payment_options'] = implode( ',', array_values( array_unique( $tokens ) ) );
		}

		$choices = array(
			'method'   => array( 'all', 'both', 'card', 'account' ),
			'currency' => array_merge( array( 'any' ), FLW_Settings::CURRENCIES ),
			'country'  => FLW_Settings::COUNTRIES,
		);

		foreach ( $choices as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$clean[ $key ] = $input[ $key ];
			}
		}

		return $clean;
	}

	/**
	 * Initialize Flutterwave Settings.
	 *
	 * @return void
	 */
	private function init_settings(): void {

		if ( false === get_option( 'flw_rave_options' ) ) {
			update_option( 'flw_rave_options', array() );
		}
	}

	/**
	 * Fetches admin option settings from the db.
	 *
	 * @param string $attr attributes for gateway.
	 *
	 * @return mixed The value of the option fetched.
	 */
	public function get_option_value( string $attr ) {
		return FLW_Settings::raw()[ $attr ] ?? '';
	}

	/**
	 * Checks if public key has been set
	 *
	 * @return boolean
	 */
	public function is_public_key_present() {

		$options = get_option( 'flw_rave_options' );

		if ( false === $options ) {
			return false;
		}

		return array_key_exists( 'public_key', $options ) && ! empty( $options['public_key'] );
	}

	/**
	 * Are redirect urls present.
	 *
	 * @return bool
	 */
	public function are_redirect_urls_present() {
		$options = get_option( 'flw_rave_options' );

		if ( false === $options ) {
			return false;
		}

		return array_key_exists( 'failed_redirect_url', $options ) && ! empty( $options['failed_redirect_url'] ) && array_key_exists( 'success_redirect_url', $options ) && ! empty( $options['success_redirect_url'] ) && ! empty( $options['pending_redirect_url'] );
	}

	/**
	 * Get the instance of the class
	 *
	 * @return object   An instance of this class
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {

			self::$instance = new self();

		}

		return self::$instance;
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function flw_rave_add_admin_menu() {

		add_menu_page(
			__( 'Flutterwave Payments', 'rave-payment-forms' ),
			'Flutterwave',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'flw_rave_admin_setting_page' ),
			self::menu_icon(),
			58
		);

		add_submenu_page(
			'flutterwave-payments',
			__( 'Flutterwave Payments Settings', 'rave-payment-forms' ),
			__( 'Settings', 'rave-payment-forms' ),
			'manage_options',
			'flutterwave-payments',
			array( __CLASS__, 'flw_rave_admin_setting_page' )
		);

		$forms = add_submenu_page(
			'flutterwave-payments',
			__( 'Payment Forms', 'rave-payment-forms' ),
			__( 'Payment Forms', 'rave-payment-forms' ),
			'manage_options',
			self::FORMS_SLUG,
			array( __CLASS__, 'render_forms_page' )
		);

		$transactions = add_submenu_page(
			'flutterwave-payments',
			__( 'Transactions', 'rave-payment-forms' ),
			__( 'Transactions', 'rave-payment-forms' ),
			'manage_options',
			self::TRANSACTIONS_SLUG,
			array( __CLASS__, 'render_transactions_page' )
		);

		$integrations = add_submenu_page(
			'flutterwave-payments',
			__( 'Integrations', 'rave-payment-forms' ),
			__( 'Integrations', 'rave-payment-forms' ),
			'manage_options',
			self::INTEGRATIONS_SLUG,
			array( __CLASS__, 'render_integrations_page' )
		);

		if ( $forms ) {
			self::$screens[ $forms ] = 'forms';
		}

		if ( $integrations ) {
			self::$screens[ $integrations ] = 'integrations';
		}

		if ( $transactions ) {
			self::$screens[ $transactions ] = 'transactions';
		}
	}

	/**
	 * URL of the settings page.
	 *
	 * @param string $tab Optional settings tab to open.
	 *
	 * @return string
	 */
	public static function get_url( string $tab = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		return '' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
	}

	/**
	 * Whether the current admin screen is one of the plugin's app screens.
	 *
	 * @return bool
	 */
	public static function is_settings_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $screen && isset( self::$screens[ $screen->id ] );
	}

	/**
	 * The app screen for an admin page hook, or null.
	 *
	 * @param string $hook_suffix Admin page hook.
	 *
	 * @return string|null
	 */
	public static function screen_for( string $hook_suffix ): ?string {
		return self::$screens[ $hook_suffix ] ?? null;
	}

	/**
	 * Load the admin app bundle on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_app( $hook_suffix ): void {
		$asset_path = FLW_DIR_PATH . 'build/admin.asset.php';
		$screen     = self::screen_for( (string) $hook_suffix );

		if ( null === $screen || ! file_exists( $asset_path ) ) {
			return;
		}

		// The form builder previews real forms, so it needs their styles.
		if ( 'forms' === $screen && wp_style_is( 'flw_css', 'registered' ) ) {
			wp_enqueue_style( 'flw_css' );
		}

		$asset = require $asset_path;

		wp_enqueue_style( 'flw-fonts', FLW_DIR_URL . 'assets/css/flw-fonts.css', array(), FLW_PAY_VERSION );
		wp_enqueue_script( 'flw-admin', FLW_DIR_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );

		if ( file_exists( FLW_DIR_PATH . 'build/admin.css' ) ) {
			wp_enqueue_style( 'flw-admin', FLW_DIR_URL . 'build/admin.css', array( 'flw-fonts' ), $asset['version'] );
			wp_style_add_data( 'flw-admin', 'rtl', 'replace' );
		}

		wp_set_script_translations( 'flw-admin', 'rave-payment-forms', FLW_DIR_PATH . 'i18n/languages' );

		wp_localize_script(
			'flw-admin',
			'flutterwaveAdminData',
			array(
				'assetsUrl'     => FLW_DIR_URL . 'assets/images/admin/',
				'documentation' => 'https://developer.flutterwave.com/docs',
				'support'       => 'https://support.flutterwave.com/',
				'dashboardUrl'  => 'https://app.flutterwave.com/dashboard/settings/apis/live',
				'newPageUrl'    => admin_url( 'post-new.php?post_type=page' ),
				'transactions'  => admin_url( 'admin.php?page=' . self::TRANSACTIONS_SLUG ),
				'formsUrl'      => admin_url( 'admin.php?page=' . self::FORMS_SLUG ),
				'pluginsUrl'    => admin_url( 'plugins.php' ),
				'supportForum'  => 'https://wordpress.org/support/plugin/rave-payment-forms/',
				'settingsUrl'   => self::get_url(),
				'exportUrl'     => FLW_Payments_Controller::export_url(),
				'currencies'    => FLW_Settings::CURRENCIES,
				'siteCurrency'  => FLW_Blocks::default_currency(),
				'canEditPages'  => current_user_can( 'edit_pages' ),
				'onboarded'     => FLW_Settings::is_onboarded(),
				'tour'          => FLW_Tour::get(),
				'userId'        => get_current_user_id(),
			)
		);
	}

	/**
	 * Admin page content: the mount point for the admin app.
	 *
	 * @return void
	 */
	public static function flw_rave_admin_setting_page() {
		self::render_screen( 'settings' );
	}

	/**
	 * Payment Forms page content.
	 *
	 * @return void
	 */
	public static function render_forms_page() {
		self::render_screen( 'forms' );
	}

	/**
	 * Transactions page content.
	 *
	 * @return void
	 */
	public static function render_transactions_page() {
		self::render_screen( 'transactions' );
	}

	/**
	 * Integrations page content.
	 *
	 * @return void
	 */
	public static function render_integrations_page() {
		self::render_screen( 'integrations' );
	}

	/**
	 * The mount point for an admin app screen.
	 *
	 * @param string $screen Screen name.
	 *
	 * @return void
	 */
	private static function render_screen( string $screen ) {
		if ( ! file_exists( FLW_DIR_PATH . 'build/admin.asset.php' ) ) {
			printf(
				'<div class="wrap"><h1>%1$s</h1><div class="notice notice-error"><p>%2$s</p></div></div>',
				esc_html__( 'Flutterwave Payments', 'rave-payment-forms' ),
				esc_html__( 'The settings screen has not been built. Run `npm run build:admin` in the plugin folder, or install the plugin from WordPress.org.', 'rave-payment-forms' )
			);
			return;
		}

		printf( '<div class="flw-admin-root" id="flutterwave-admin-root" data-screen="%s"></div>', esc_attr( $screen ) );
	}

	/**
	 * The Flutterwave mark for the admin menu, as an SVG data URI WordPress recolours to match the admin scheme.
	 *
	 * @return string
	 */
	private static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10.4 3.1c3-1.7 6.8-.6 8.4 2.4 1.6 3 .4 6.8-2.6 8.5l-6.6 3.7c-3 1.7-6.8.6-8.4-2.4-1.6-3-.4-6.8 2.6-8.5l6.6-3.7Zm-3 4.1a2.8 2.8 0 1 0 2.7 4.9 2.8 2.8 0 0 0-2.7-4.9Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the admin menu icon.
	}
}
