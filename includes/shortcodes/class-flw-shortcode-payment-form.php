<?php
/**
 * Payment Form shortcode
 *
 * @package Flutterwave\Payments\Shortcodes
 * @version 1.0.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pay Now button shortcode
 */
final class FLW_Shortcode_Payment_Form extends Abstract_FLW_Shortcode {

	/**
	 * Button Text.
	 *
	 * @var string
	 */
	protected string $button_text = 'Proceed to Flutterwave';

	/**
	 * Allowed to exclude fields.
	 *
	 * @var array
	 */
	protected array $allowed_to_exclude = array();

	/**
	 * Custom Fields.
	 *
	 * @var array
	 */
	protected array $custom_fields = array();

	/**
	 * Initialize shortcode.
	 *
	 * @param array  $attributes  the attribute array.
	 * @param string $type the type of shortcode.
	 *
	 * @since 1.0.6
	 */
	public function __construct( array $attributes = array(), string $type = 'flw-pay-form' ) {
		parent::__construct( $attributes, $type );

		$this->query_args['name'] = $this->type;
	}

	/**
	 * Set button Attribute.
	 *
	 * @param [string] $content The content.
	 *
	 * @return void
	 */
	public function set_button_text( $content ) {
		$btn_text = $content;
		if ( empty( $btn_text ) ) {
			$admin_settings = FLW_Admin_Settings::get_instance();
			$btn_text       = $admin_settings->get_option_value( 'btn_text' );
		}
		$this->button_text = ( ! empty( $btn_text ) ) ? $btn_text : $this->button_text;
	}

	/**
	 * Get Attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return $this->attributes;
	}

	/**
	 * Parse shortcode attributes.
	 *
	 * @param array $attributes Shortcode attributes.
	 *
	 * @return array
	 * @since  1.0.6
	 */
	protected function parse_attributes( array $attributes = array() ): array {
		$email                      = self::use_current_user_email( $attributes ) ? wp_get_current_user()->user_email : '';
		$payment_method             = FLW_Settings::payment_options();
		$this->allowed_to_exclude[] = 'phone';
		$default_fields_order       = 'email,fullname,phone,amount,currency';
		$custom_currency            = '';
		$custom_fields              = array();
		$default_fullname           = '';
		$default_config             = array();

		// handle when a merchant allows clients to choose the currency to pay in.
		if ( ! isset( $attributes['currency'] ) ) {
			if ( 'any' === $this->settings->get_option_value( 'currency' ) ) {
				$default_config['custom_currency'] = 'USD,KES,ZAR,GHS,TZS,EUR,NGN,GBP,UGX,RWF,ZMW';
			} else {
				$merchant_base_currency            = $this->settings->get_option_value( 'currency' );
				$default_config['custom_currency'] = $merchant_base_currency;
			}
		} else {
			$default_config['custom_currency'] = $attributes['currency'];
		}

		// parses custom fields from shortcode attributes.
		if ( isset( $attributes['custom_fields'] ) ) {
			$custom_fields                   = $this->build_custom_fields( $attributes['custom_fields'], $attributes );
			$attributes['custom_fields']     = $custom_fields;
			$default_config['custom_fields'] = $custom_fields;
			$this->custom_fields             = $custom_fields;

			$default_config['order'] = $default_fields_order . ',' . implode( ',', array_keys( $this->custom_fields ) );
			// update allowed_to_exclude.
			$this->allowed_to_exclude = array_merge( $this->allowed_to_exclude, array_keys( $custom_fields ) );
		}

		// handles excluded fields both custom or not.
		if ( isset( $attributes['exclude'] ) ) {
			// trim spaces of each word.
			$proposed_fields = array_map(
				function ( string $item ) {
					return trim( $item );
				},
				explode( ',', $attributes['exclude'] )
			);

			// The name field can be left out too (e.g. the compact payment button).
			foreach ( array_merge( $this->allowed_to_exclude, array( 'fullname' ) ) as $keyword ) {
				if ( in_array( $keyword, $proposed_fields, true ) ) {
					$attributes[ 'should_collect_' . $keyword ]     = 0;
					$default_config[ 'should_collect_' . $keyword ] = 0;
				}
			}
		}

		if ( isset( $attributes['order'] ) ) {
			// check order array has valid fields.
			$custom_form_fields_order_array = explode( ',', $attributes['order'] );
			$default_fields                 = array_keys( $this->get_field_data_type() );
			$all_fields                     = array_merge( $default_fields, array_keys( $this->custom_fields ) );
			foreach ( $custom_form_fields_order_array as $index => $value ) {
				if ( ! in_array( $value, $all_fields, true ) ) {
					unset( $custom_form_fields_order_array[ $index ] );
				}
			}

			$attributes['order']     = implode( ',', $custom_form_fields_order_array );
			$default_config['order'] = ( ! empty( $custom_form_fields_order_array ) ) ? $attributes['order'] : $default_fields_order;
		}

		// If a fullname is passed. do not display names separately.
		if ( isset( $attributes['fullname'] ) ) {
			$attributes['split_name']   = 0;
			$default_config['fullname'] = $attributes['fullname'];
		}

		// should display last_name and first_name or not.
		$split_name = isset( $attributes['split_name'] ) && (bool) $attributes['split_name'];

		$defaults = array_merge(
			array(
				'amount'          => 0,
				'split_name'      => $split_name,
				'custom_currency' => $custom_currency,
				'country'         => $this->settings->get_option_value( 'country' ),
				'payment_method'  => $payment_method,
				'email'           => $email,
				'custom_fields'   => $custom_fields,
				'order'           => $default_fields_order,
				'heading'         => '',
				'description'     => '',
				'layout'          => 'card',
				'width'           => 'auto',
				'show_secured'    => '1',
			),
			$default_config
		);

		$atts = shortcode_atts( $defaults, $attributes, $this->type );

		// Shortcode values arrive as strings; the renderer compares against integers.
		$atts['split_name'] = (int) (bool) $atts['split_name'];

		return $atts;
	}

	/**
	 * Convert Options to and Array.
	 *
	 * @param array $string_key_value_array The key-value pair array.
	 *
	 * @return array
	 */
	private function convert_options_to_array( array $string_key_value_array ): array {
		$fields = array();
		foreach ( $string_key_value_array as $value ) {
			$pair               = explode( ':', $value );
			$fields[ $pair[0] ] = $pair[1];
		}
		return $fields;
	}

	/**
	 * Build Custom Fields.
	 *
	 * @param string $fields The fields.
	 * @param array  $attr  The attributes.
	 *
	 * @return array
	 */
	private function build_custom_fields( string $fields, array &$attr ): array {
		// convert string to array.
		$document = explode( ',', $fields );

		$custom_fields = array();

		foreach ( $document as $key_value_pair ) {
			if ( (bool) strpos( $key_value_pair, 'select' ) ) {
				// check if data type contains options.
				$option                             = explode( '|', $key_value_pair );
				$key_datatype                       = explode( ':', array_shift( $option ) );
				$key                                = $key_datatype[0];
				$datatype                           = $key_datatype[1];
				$custom_fields[ $key ][ $datatype ] = $this->convert_options_to_array( $option );
			} else {
				$pair                  = explode( ':', $key_value_pair );
				$key                   = $pair[0];
				$input_data_type       = $pair[1];
				$custom_fields[ $key ] = $input_data_type;
			}
		}
		return $custom_fields;
	}

	/**
	 * Parse Query Args.
	 *
	 * @return array
	 */
	protected function parse_query_args(): array {
		return array();
	}

	/**
	 * Handle Currency Fields.
	 *
	 * @param [string] $key The api key.
	 * @param [array]  $field  Field data.
	 * @param [string] $amount Amount.
	 * @param [string] $custom_currency Cyrrency.
	 * @param [array]  $html_array output array.
	 *
	 * @return void
	 */
	private function handle_currency_field( $key, $field, $amount, $custom_currency, &$html_array ) {

		if ( 'currency' !== $key ) {
			return;
		}

		$currencies = explode( ',', $custom_currency );

		if ( is_array( $field ) && isset( $field['type'] ) && 'select' === $field['type'] ) {
			$html_array[] = '<label class="pay-now" for="' . esc_attr( $field['id'] ) . '">' . esc_html( $field['label'] ?? ucfirst( $key ) ) . '</label>';
			$html_array[] = '<' . esc_html( $field['type'] ) . '  class="' . esc_html( $field['class'] ) . '" id="' . esc_html( $field['id'] ) . '" required>';
			if ( 'custom_currency' === $field['name'] ) {
				foreach ( $currencies as $currency ) {
					$html_array[] = '<option value="' . esc_attr( $currency ) . '">' . esc_attr( $currency ) . '</option>';
				}
			}
			$html_array[] = '</' . esc_html( $field['type'] ) . '>';
		}
	}

	/**
	 * Handle Special Fields.
	 *
	 * @param array $field the field.
	 * @param array $atts  current attibute tables.
	 * @param array $html_array html array.
	 *
	 * @return void
	 */
	private function handle_special_fields( array $field, array $atts, array &$html_array ) {
		$custom_currency       = $atts['custom_currency'];
		$split_name            = $atts['split_name'];
		$amount                = $atts['amount'];
		$country               = $atts['country'];
		$field_name            = $field['name'];
		$custom_currency_array = explode( ',', $custom_currency );

		if ( ! $this->is_special_field( $field_name ) ) {
			return;
		}

		// handle currency field: assume single currency and amount is set.
		if ( 'custom_currency' === $field_name && 1 === count( $custom_currency_array ) && 0 !== (int) $amount ) {
			$html_array[] = '<div class="flw_payment_overview">'
				. '<div class="flw_total_label">' . esc_html__( 'Total amount', 'rave-payment-forms' ) . '</div>'
				. '<div class="flw_amount_to_pay">' . esc_html( $custom_currency_array[0] . ' ' . number_format_i18n( (float) $amount, 2 ) ) . '</div>'
				. '</div>';
		}

		// handle currency field: assume multiple currencies and amount is set.
		if ( 'custom_currency' === $field_name && count( $custom_currency_array ) > 1 && $amount >= 0 ) {

			$this->handle_currency_field( 'currency', $field, $amount, $custom_currency, $html_array );
		}

		// handle amount.
		if ( 'amount' === $field_name && 0 === $amount ) {
			$this->handle_regular_fields( 'amount', $field, $html_array );
		}

		// handle name split.
		if ( ( 'firstname' === $field_name && 1 === $atts['split_name'] ) || ( 'lastname' === $field_name && 1 === $atts['split_name'] ) ) {
			$this->handle_regular_fields( $field_name, $field, $html_array );
		}

		// handle fullname: a preset name is sent from the data-fullname attribute, so no field is needed.
		if ( 'fullname' === $field_name && ! isset( $atts['fullname'] ) && ! isset( $atts['should_collect_fullname'] ) ) {
			if ( 1 === $atts['split_name'] ) {
				$this->handle_regular_fields( 'firstname', $this->get_field_data_type( 'firstname' ), $html_array );
				$this->handle_regular_fields( 'lastname', $this->get_field_data_type( 'lastname' ), $html_array );
			} else {
				$this->handle_regular_fields( $field_name, $field, $html_array );
			}
		}

		if ( ! isset( $atts[ 'should_collect_' . $field_name ] ) && in_array( $field_name, $this->allowed_to_exclude, true ) ) {
			// TODO: handle custom_fields.
			$this->handle_regular_fields( $field_name, $field, $html_array );
		}
	}

	/**
	 * Check if field is custom.
	 *
	 * @param string $field_name The field name.
	 *
	 * @return bool
	 */
	protected function is_custom_field( string $field_name ): bool {
		$custom_fields = array_keys( $this->custom_fields );

		return in_array( $field_name, $custom_fields, true );
	}

	/**
	 * Check if field is special.
	 *
	 * @param string $field_name Field name.
	 *
	 * @return bool
	 */
	protected function is_special_field( string $field_name ): bool {
		$special_fields = array( 'amount', 'currency', 'custom_currency', 'fullname', 'phone', 'firstname', 'lastname' );
		return in_array( $field_name, $special_fields, true );
	}

	/**
	 * Handle Regular Fields.
	 *
	 * @param string $key The field key.
	 * @param array  $field The field array.
	 * @param array  $html_array The html array.
	 *
	 * @return void
	 */
	private function handle_regular_fields( string $key, array $field, array &$html_array ) {
		if ( ! isset( $field['type'] ) || 'select' === $field['type'] ) {
			return;
		}

		$label = $field['placeholder'] ?? ucfirst( $key );

		$html_array[] = '<label class="pay-now" for="' . esc_attr( $field['id'] ) . '">' . esc_html( $label ) . '</label>';
		$html_array[] = '<input class="' . esc_attr( $field['class'] ) . '" id="' . esc_attr( $field['id'] ) . '" type="' .
			esc_attr( $field['type'] ) . '" placeholder="' . esc_attr( $label ) . '">';
	}

	/**
	 * Prepare Default Fields.
	 *
	 * @param array $atts Attribute array.
	 *
	 * @return string
	 */
	private function prepare_default_fields( array $atts ): string {
		$order         = explode( ',', $atts['order'] );
		$custom_fields = $atts['custom_fields'];
		$html_array    = array();
		$field         = array();

		foreach ( $order as $key ) {

			if ( $this->is_custom_field( $key ) ) {
				$current_custom_field_array = array( $key => $custom_fields[ $key ] );
				$html_array[]               = $this->prepare_custom_fields( $current_custom_field_array, $atts );
				continue;
			}

			// A known email (use_current_user_email) is sent from data-email, so no field is needed.
			if ( 'email' === $key && '' !== (string) $atts['email'] ) {
				continue;
			}

			$field = $this->get_field_data_type( $key );

			if ( ! $this->is_custom_field( $key ) && ! $this->is_special_field( $field['name'] ) ) {

				$this->handle_regular_fields( $key, $field, $html_array );

			}

			if ( ! $this->is_custom_field( $key ) && $this->is_special_field( $field['name'] ) ) {

				$this->handle_special_fields( $field, $atts, $html_array );
			}
		}//end foreach

		return implode( '', $html_array );
	}

	/**
	 * Prepare Custom Fields.
	 *
	 * @param array $fields Custom Fields.
	 * @param array $atts  Attributes.
	 *
	 * @return string
	 */
	private function prepare_custom_fields( array $fields, array $atts ): string {

		$html_array = array();

		foreach ( $fields as $name => $value ) {
			if ( is_array( $value ) ) {

				foreach ( $value as $element => $option ) {
					if ( ! empty( $option ) && ! isset( $atts[ 'should_collect_' . $name ] ) ) {
						$html_array[] = '<label class="pay-now">' . esc_attr( ucfirst( $name ) ) . '</label>';
						$html_array[] = '<' . esc_html( $element ) . '  class="flw-form-select flw-extra-fields" id="flw-extra" required>';
						foreach ( $option as $key => $val ) {
							$html_array[] = '<option value="' . esc_attr( $val ) . '">' . esc_html( $key ) . '</option>';
						}
						$html_array[] = '</' . esc_html( $element ) . '>';
					}
				}
			} elseif ( ! isset( $atts[ 'should_collect_' . $name ] ) ) {
					$html_array[] = '<label class="pay-now">' . esc_attr( ucfirst( $name ) ) . '</label>';
					$html_array[] = '<input class="flw-form-input-text flw-extra-fields" id="flw-extra" type="' .
					esc_attr( $value ) . '" placeholder="' . esc_attr( ucfirst( $name ) ) . '" required >';
			}
		}//end foreach

		return implode( '', $html_array );
	}

	/**
	 * Render Payment Form.
	 *
	 * @return void
	 */
	public function render(): void {
		$atts          = $this->get_attributes();
		$btn_text      = $this->button_label( $atts );
		$skip          = array( 'heading', 'description', 'layout', 'width', 'show_secured' );
		$data_attr     = self::build_data_attributes( $atts, $skip );
		$signed_config = self::get_signed_config_fields( $atts['amount'], (string) $atts['custom_currency'] );
		$form_classes  = array( 'flutterwave-payment-form' );

		if ( 'compact' === $atts['layout'] ) {
			$form_classes[] = 'flw-layout-compact';
		}

		if ( 'full' === $atts['width'] ) {
			$form_classes[] = 'flw-width-full';
		}

		$input_fields_html     = $this->prepare_default_fields( $atts );
		$allowed_html_elements = self::get_allowed_html();

		include FLW_DIR_PATH . 'views/pay-now-form.php';
	}

	/**
	 * The button label, with {amount} replaced by the formatted amount.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	private function button_label( array $atts ): string {
		$label = (string) $this->button_text;

		if ( false === strpos( $label, '{amount}' ) ) {
			return $label;
		}

		$amount     = is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : 0.0;
		$currencies = FLW_Form_Config::parse_currencies( (string) $atts['custom_currency'] );
		$formatted  = '';

		if ( $amount > 0 ) {
			$decimals  = floor( $amount ) === $amount ? 0 : 2;
			$formatted = trim( ( 1 === count( $currencies ) ? $currencies[0] . ' ' : '' ) . number_format_i18n( $amount, $decimals ) );
		}

		return trim( preg_replace( '/\s+/', ' ', str_replace( '{amount}', $formatted, $label ) ) );
	}

	/**
	 * Load Scripts.
	 *
	 * @return void
	 */
	public function load_scripts(): void {
		$settings = $this->settings;

		$payment_method = FLW_Settings::payment_options();

		$args = array(
			'cb_url'     => admin_url( 'admin-ajax.php' ),
			'country'    => $settings->get_option_value( 'country' ),
			'currency'   => $settings->get_option_value( 'currency' ),
			'desc'       => $settings->get_option_value( 'modal_desc' ),
			'logo'       => $settings->get_option_value( 'modal_logo' ),
			'method'     => $payment_method,
			'public_key' => $settings->get_option_value( 'public_key' ),
			'title'      => $settings->get_option_value( 'modal_title' ),
			'countries'  => self::get_supported_country(),
		);

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-effects-shake' );
		wp_enqueue_script( 'flw_pay_js', FLW_DIR_URL . 'assets/js/flw.min.js', array( 'jquery', 'jquery-effects-core' ), FLW_PAY_VERSION, false );
		wp_localize_script( 'flw_pay_js', 'flw_pay_options', $args );
	}
}
