<?php
/**
 * Flutterwave Integrations.
 *
 * @package Flutterwave_Payments
 * @version 1.0.6
 */

use Flutterwave\WordPress\Integrations\AbstractService;


/**
 * Flutterwave ThirdParty Integration Class.
 */
class FLW_Thirdparty_Integrations {

	/**
	 * Integration Registry.
	 *
	 * @var array
	 */
	public static array $integrations = array();

	/**
	 * Instance.
	 *
	 * @var FLW_Thirdparty_Integrations|null
	 */
	public static ?FLW_Thirdparty_Integrations $instance = null;
	/**
	 * Third Party Class Contructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'get_admin_script' ) );
		$this->init_settings();
	}

	/**
	 * Get an instance of a class.
	 *
	 * @return FLW_Thirdparty_Integrations
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers admin setting
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'flw-itegration-group', 'flw_integrations_options' );
	}

	/**
	 * Init admin setting
	 *
	 * @return void
	 */
	private function init_settings() {
		if ( false === get_option( 'flw_integrations_options' ) ) {
			update_option( 'flw_integrations_options', array() );
		}
	}

	/**
	 * Registers Third Party Integration.
	 *
	 * @param array $services list of third party integrations.
	 *
	 * @return void
	 */
	public static function register( array $services = array() ) {
		foreach ( $services as $service ) {
			$service = new $service( 'YOUR-API-KEY' );
			if ( ! $service instanceof AbstractService ) {
				continue;
			} else {
				$owner = $service->get_info()['owner'];
				$name  = $service->get_info()['name'];

				$default_values = array(
					'name'      => ucfirst( $name ),
					'developer' => ucfirst( $owner ),
					'key'       => $service->get_key(),
				);

				add_option( 'flw_integration_' . $owner . '_' . $name, $default_values );

				if ( ! isset( self::$integrations[ $owner ][ $name ] ) ) {

					self::$integrations[ $owner ] = array( $name => $service );
				} else {

					self::$integrations[ $owner ][ $name ] = $service;
				}
			}//end if
		}//end foreach
	}

	/**
	 * Get a ThirdParty Service.
	 *
	 * @param string $service_name the service name.
	 *
	 * @return AbstractService|null
	 */
	public function get( string $service_name ): ?AbstractService {

		if ( ! isset( self::$integrations[ $service_name ] ) ) {
			return null;
		}

		return self::$integrations[ $service_name ];
	}

	/**
	 * Fetches admin option settings from the db.
	 *
	 * @param [string] $attr The option.
	 *
	 * @return mixed  The value of the option fetched.
	 */
	public function get_option_value( $attr ) {

		$options = get_option( 'flw_rave_options' );

		if ( array_key_exists( $attr, $options ) ) {

			return $options[ $attr ];
		}

		return '';
	}

	/**
	 * Handle Admin Javascript and CSS.
	 *
	 * @return void
	 */
	public function get_admin_script() {
		wp_enqueue_style( 'flw-integration-css', FLW_DIR_URL . 'assets/css/admin/integrations.css', array(), FLW_PAY_VERSION, false );
		wp_enqueue_style( 'flw-integration-css' );
		wp_enqueue_script( 'flw-intergration-js', FLW_DIR_URL . 'assets/js/admin/integrations.js', array( 'jquery' ), FLW_PAY_VERSION, false );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'flutterwave-payments',
			__( 'Flutterwave Payments Integrations', 'rave-payment-forms' ),
			__( 'Integrations', 'rave-payment-forms' ),
			'manage_options',
			'flutterwave-payments-integrations',
			array( __CLASS__, 'flw_integration_page' )
		);
	}

	/**
	 * Admin Integration page content
	 *
	 * @return void
	 */
	public static function flw_integration_page() {
		$integrations = self::$integrations;

		include_once dirname( FLW_PAY_PLUGIN_FILE ) . '/views/admin-integrations-page.php';
	}
}
