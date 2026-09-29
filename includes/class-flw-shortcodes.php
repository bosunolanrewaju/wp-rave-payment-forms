<?php
/**
 * Shortcode Class
 *
 * @package Flutterwave_Payments
 * @version 1.0.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flutterwave Shortcode Class.
 */
class FLW_Shortcodes {


	/**
	 * Class instance variable
	 *
	 * @var ?FLW_Shortcodes $instance
	 */
	protected static ?FLW_Shortcodes $instance = null;

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_css_files' ) );
		$this->init();
	}

	/**
	 * Check if api keys are set.
	 *
	 * @return string;
	 */
	private static function check_settings_for_api_keys() {
		return self::setup_notice( __( 'Connect your Flutterwave account to show this payment form.', 'rave-payment-forms' ), 'api' );
	}

	/**
	 * Check if redirect urls are set.
	 *
	 * @return string;
	 */
	private static function check_redirect_urls() {
		return self::setup_notice( __( 'Add your redirect pages to show this payment form.', 'rave-payment-forms' ), 'redirects' );
	}

	/**
	 * Setup notice shown to editors in place of a form that cannot work yet.
	 *
	 * @param string $message What is missing.
	 * @param string $tab     Settings tab that fixes it.
	 *
	 * @return string
	 */
	private static function setup_notice( string $message, string $tab ): string {
		self::enqueue_styles();

		$url = FLW_Settings::is_onboarded() ? FLW_Admin_Settings::get_url( $tab ) : FLW_Admin_Settings::get_url();

		return sprintf(
			'<p class="flw-mssing-api-keys">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html( $message ),
			esc_url( $url ),
			esc_html__( 'Finish setting up Flutterwave →', 'rave-payment-forms' )
		);
	}

	/**
	 * Prevent Cloning.
	 */
	public function __clone() {
	}

	/**
	 * Prevent Access.
	 */
	public function __wakeup() {
	}

	/**
	 * Register Shortcode and assets.
	 *
	 * @return void
	 */
	public function init() {
		$shortcodes = array(
			'flw-pay-button'    => __CLASS__ . '::pay_button_shortcode',
			'flw-pay-form'      => __CLASS__ . '::pay_button_shortcode',
			'flw-donation-form' => __CLASS__ . '::donation_page_shortcode',
		);

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( $shortcode, $function );
		}
	}

	/**
	 * Get the instance of this class.
	 *
	 * @return object the single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Generates Pay Now button from shortcode
	 *
	 * @param [array]  $attr attributes array.
	 * @param [string] $content button text.
	 *
	 * @return string      Pay Now button html content.
	 */
	public static function pay_button_shortcode( $attr, $content ): string { //phpcs:ignore.
		$admin_settings = FLW_Admin_Settings::get_instance();

		if ( ! $admin_settings->is_public_key_present() && current_user_can( 'edit_others_posts' ) ) {
			return self::check_settings_for_api_keys();
		}

		if ( ! $admin_settings->are_redirect_urls_present() && current_user_can( 'edit_others_posts' ) ) {
			return self::check_redirect_urls();
		}

		$shortcode = new FLW_Shortcode_Payment_Form( (array) $attr, 'flw-pay-form' );
		$shortcode->set_button_text( $content );
		$shortcode->load_scripts();
		self::enqueue_styles();
		ob_start();
		$shortcode->render();
		$form = ob_get_contents();
		ob_end_clean();
		return $form;
	}

	/**
	 * Generates Donation page from shortcode.
	 *
	 * @param [array]  $attr attributes array.
	 * @param [string] $content button text.
	 *
	 * @return string      Pay Now button html content.
	 */
	public static function donation_page_shortcode( $attr, $content ): string { //phpcs:ignore.
		$admin_settings = FLW_Admin_Settings::get_instance();

		if ( ! $admin_settings->is_public_key_present() && current_user_can( 'edit_others_posts' ) ) {
			return self::check_settings_for_api_keys();
		}

		if ( ! $admin_settings->are_redirect_urls_present() && current_user_can( 'edit_others_posts' ) ) {
			return self::check_redirect_urls();
		}

		$shortcode = new FLW_Shortcode_Donation_Form( (array) $attr, 'flw-donation-page' );
		$shortcode->load_scripts();
		self::enqueue_styles();
		ob_start();
		$shortcode->render();
		$form = ob_get_contents();
		ob_end_clean();

		return $form;
	}

	/**
	 * Render Regular Form.
	 *
	 * @param [array]  $atts attributes array.
	 * @param [string] $btn_text button text.
	 *
	 * @return void
	 */
	public static function render_payment_form( $atts, $btn_text ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $btn_text is used by the included view.

		_deprecated_function( __FUNCTION__, '1.0.6', 'FLW_Shortcode_Payment_Form::render' );

		$data_attr = '';
		foreach ( $atts as $att_key => $att_value ) {

			if ( ! is_array( $att_value ) ) {
				$data_attr .= ' data-' . sanitize_key( $att_key ) . '="' . esc_attr( (string) $att_value ) . '"';
			}
		}
		include FLW_DIR_PATH . 'views/pay-now-form.php';
	}

	/**
	 * Loads css files.
	 *
	 * @return void
	 */
	public static function load_css_files() {
		wp_register_style( 'flw-fonts', FLW_DIR_URL . 'assets/css/flw-fonts.css', array(), FLW_PAY_VERSION );
		wp_register_style( 'flw_css', FLW_DIR_URL . 'assets/css/flw.css', array( 'flw-fonts' ), FLW_PAY_VERSION );

		// Load in the head when the page is known to contain a form, to avoid a flash of unstyled fields.
		$post = get_post();

		if ( is_singular() && $post instanceof WP_Post ) {
			foreach ( array( 'flw-pay-button', 'flw-pay-form', 'flw-donation-form' ) as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					self::enqueue_styles();
					break;
				}
			}
		}
	}

	/**
	 * Enqueue the form styles unless the merchant chose to use their theme's styles.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( 'yes' === FLW_Settings::get( 'theme_style' ) ) {
			return;
		}

		if ( ! wp_style_is( 'flw_css', 'registered' ) ) {
			self::load_css_files();
		}

		wp_enqueue_style( 'flw_css' );
	}

	/**
	 * Get admin logo.
	 *
	 * @param [array] $attr attribute list.
	 *
	 * @return string
	 */
	private static function get_logo_url( $attr ) {
		$admin_settings = FLW_Admin_Settings::get_instance();
		$logo           = $admin_settings->get_option_value( 'modal_logo' );
		if ( ! empty( $attr['logo'] ) ) {
			$logo = strpos( $attr['logo'], 'http' ) !== false ? $attr['logo'] : wp_get_attachment_url( $attr['logo'] );
		}
		return $logo;
	}
}
