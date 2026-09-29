<?php
/**
 * Abstract Flutterwave Shortcode Class.
 *
 * @package Flutterwave-Payments
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- public class name kept for backwards compatibility.
/**
 * Abstract Shortcode.
 */
abstract class Abstract_FLW_Shortcode {
	// phpcs:enable

	/**
	 * Shortcode type.
	 *
	 * @since 1.0.6
	 * @var   string
	 */
	protected string $type = '';

	/**
	 * Attributes.
	 *
	 * @since 1.0.6
	 * @var   array
	 */
	protected array $attributes = array();

	/**
	 * Query args.
	 *
	 * @since 1.0.6
	 * @var   array
	 */
	protected array $query_args = array();

	/**
	 * Set custom visibility.
	 *
	 * @since 1.0.6
	 * @var   bool
	 */
	protected bool $custom_visibility = false;

	/**
	 * Settings.
	 *
	 * @since 1.0.6
	 * @var   FLW_Admin_Settings|null
	 */
	protected ?FLW_Admin_Settings $settings;

	/**
	 * Parse Attributes.
	 *
	 * @param array $attributes attributes array.
	 *
	 * @return array
	 */
	abstract protected function parse_attributes( array $attributes = array() ): array;

	/**
	 * Parse Query Args.
	 *
	 * @return array
	 */
	abstract protected function parse_query_args(): array;

	/**
	 * Render Form.
	 *
	 * @return void
	 */
	abstract public function render(): void;

	/**
	 * Load Scripts.
	 *
	 * @return void
	 */
	abstract public function load_scripts(): void;

	/**
	 * Shortcode Constructor.
	 *
	 * @param array  $attributes Attribute array.
	 * @param string $type Shortcode type or Name.
	 */
	public function __construct( array $attributes, string $type ) {
		$this->type       = $type;
		$this->settings   = FLW_Admin_Settings::get_instance();
		$this->attributes = $this->parse_attributes( $attributes );
		$this->query_args = $this->parse_query_args();
	}

	/**
	 * Checks if the loggedin user email should be used.
	 *
	 * @param [array] $attr Attributes array.
	 *
	 * @return boolean
	 */
	protected static function use_current_user_email( $attr ): bool {

		return isset( $attr['use_current_user_email'] ) && 'yes' === $attr['use_current_user_email'];
	}

	/**
	 * Get the current user email
	 *
	 * @param [array] $attr The Attribute array.
	 *
	 * @return string
	 */
	protected static function get_logo_url( $attr ): string {
		$admin_settings = FLW_Admin_Settings::get_instance();
		$logo           = $admin_settings->get_option_value( 'modal_logo' );
		if ( ! empty( $attr['logo'] ) ) {
			$logo = strpos( $attr['logo'], 'http' ) ? $attr['logo'] : wp_get_attachment_url( $attr['logo'] );
		}
		return $logo;
	}

	/**
	 * Get Supported Country.
	 *
	 * @return string[]
	 */
	protected static function get_supported_country(): array {
		return array(
			'NGN' => 'NG',
			'EUR' => 'NG',
			'GBP' => 'NG',
			'USD' => 'US',
			'KES' => 'KE',
			'ZAR' => 'ZA',
			'TZS' => 'TZ',
			'UGX' => 'UG',
			'GHS' => 'GH',
			'ZMW' => 'ZM',
			'RWF' => 'RW',
		);
	}

	/**
	 * Get Payment Options.
	 *
	 * @return string[]
	 */
	protected static function get_payment_options(): array {
		return array(
			'both'    => 'card,account',
			'card'    => 'card',
			'account' => 'account',
			'all'     => 'card,account,ussd,qr,mpesa,banktransfer,mobilemoneyghana,mobilemoneyfranco,mobilemoneyuganda,mobilemoneyrwanda,mobilemoneyzambia,barter,credit',
		);
	}

	/**
	 * Get field data type.
	 *
	 * @param string|null $key the field_name.
	 *
	 * @return array|mixed
	 */
	protected function get_field_data_type( ?string $key = null ) {

		$data = array(
			'email'           => array(
				'id'          => 'flw-customer-email',
				'name'        => 'email',
				'class'       => 'flw-form-input-text',
				'type'        => 'email',
				'placeholder' => __( 'Email', 'rave-payment-forms' ),
			),
			'amount'          => array(
				'id'          => 'flw-amount',
				'name'        => 'amount',
				'class'       => 'flw-form-input-text',
				'type'        => 'number',
				'placeholder' => __( 'Amount', 'rave-payment-forms' ),
			),
			'currency'        => array(
				'id'    => 'flw-currency',
				'name'  => 'custom_currency',
				'class' => 'flw-form-select',
				'type'  => 'select',
				'label' => __( 'Currency', 'rave-payment-forms' ),
			),
			'custom_currency' => array(
				'id'    => 'flw-currency',
				'name'  => 'custom_currency',
				'class' => 'flw-form-select',
				'type'  => 'select',
				'label' => __( 'Currency', 'rave-payment-forms' ),
			),
			'fullname'        => array(
				'id'          => 'flw-full-name',
				'name'        => 'fullname',
				'class'       => 'flw-form-input-text',
				'type'        => 'text',
				'placeholder' => __( 'Full name', 'rave-payment-forms' ),
			),
			'phone'           => array(
				'id'          => 'flw-phone',
				'name'        => 'phone',
				'class'       => 'flw-form-input-text',
				'type'        => 'tel',
				'placeholder' => __( 'Phone number', 'rave-payment-forms' ),
			),
			'firstname'       => array(
				'id'          => 'flw-first-name',
				'name'        => 'firstname',
				'class'       => 'flw-form-input-text',
				'type'        => 'text',
				'placeholder' => __( 'First name', 'rave-payment-forms' ),
			),
			'lastname'        => array(
				'id'          => 'flw-last-name',
				'name'        => 'lastname',
				'class'       => 'flw-form-input-text',
				'type'        => 'text',
				'placeholder' => __( 'Last name', 'rave-payment-forms' ),
			),
			'country'         => 'text',
		);

		if ( is_null( $key ) ) {
			return $data;
		}

		return $data[ $key ];
	}

	/**
	 * Build the form's data-* attributes, escaping each key and value.
	 *
	 * @param array $atts   Shortcode attributes.
	 * @param array $skip   Attribute keys to leave out.
	 *
	 * @return string
	 */
	protected static function build_data_attributes( array $atts, array $skip = array() ): string {
		$data_attr = '';
		foreach ( $atts as $att_key => $att_value ) {
			if ( is_array( $att_value ) || in_array( $att_key, $skip, true ) ) {
				continue;
			}

			// data-amount marks a fixed price. An open-amount form must not carry
			// one, or the script would send it instead of what the customer typed.
			if ( 'amount' === $att_key && ! ( is_numeric( $att_value ) && (float) $att_value > 0 ) ) {
				continue;
			}

			if ( is_bool( $att_value ) ) {
				$att_value = $att_value ? '1' : '';
			}

			$data_attr .= ' data-' . sanitize_key( $att_key ) . '="' . esc_attr( (string) $att_value ) . '"';
		}
		return $data_attr;
	}

	/**
	 * Hidden fields carrying the signed amount/currency rules for this form.
	 *
	 * @param mixed  $amount     The fixed amount, or 0 when the customer chooses.
	 * @param string $currencies Comma separated list of accepted currencies.
	 *
	 * @return string
	 */
	protected static function get_signed_config_fields( $amount, string $currencies ): string {
		$config = FLW_Form_Config::sign(
			is_numeric( $amount ) ? (float) $amount : 0.0,
			FLW_Form_Config::parse_currencies( $currencies, array_keys( self::get_supported_country() ) )
		);

		return '<input type="hidden" name="flw_form_config" value="' . esc_attr( $config['payload'] ) . '" />'
			. '<input type="hidden" name="flw_form_sig" value="' . esc_attr( $config['signature'] ) . '" />';
	}

	/**
	 * Get allowed html for kses function.
	 *
	 * @return array
	 */
	protected static function get_allowed_html() {
		return array(
			'div'    => array(
				'class' => array(),
			),
			'input'  => array(
				'id'          => array(),
				'class'       => array(),
				'type'        => array(),
				'placeholder' => array(),
				'required'    => array(),
			),
			'select' => array(
				'id'       => array(),
				'class'    => array(),
				'required' => array(),
			),
			'option' => array(
				'value' => array(),
			),
			'label'  => array(
				'class' => array(),
				'for'   => array(),
			),
		);
	}
}
