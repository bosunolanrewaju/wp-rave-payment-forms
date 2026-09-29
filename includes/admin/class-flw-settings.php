<?php
/**
 * Read/write model for the plugin settings used by the admin app.
 *
 * Everything still lives in the `flw_rave_options` option so the shortcodes,
 * REST routes and checkout keep working unchanged. This class translates between
 * that flat array and the shape the onboarding wizard and settings screen use.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings model.
 */
final class FLW_Settings {

	/**
	 * Option that stores the plugin settings.
	 */
	const OPTION_KEY = 'flw_rave_options';

	/**
	 * REST namespace used by the admin app.
	 */
	const REST_NAMESPACE = 'flutterwave/v1';

	/**
	 * Payment option tokens behind each checkbox on the "Payment methods" step.
	 *
	 * Writing a method stores its whole group; reading back, a method counts as
	 * enabled when any token of its group is present.
	 *
	 * @var array<string, array<int, string>>
	 */
	const METHOD_MAP = array(
		'card'         => array( 'card' ),
		'stablecoin'   => array( 'stablecoin' ),
		'banktransfer' => array( 'banktransfer', 'account' ),
		'mobilemoney'  => array(
			'mobilemoneyghana',
			'mobilemoneyfranco',
			'mobilemoneyrwanda',
			'mobilemoneyzambia',
			'mobilemoneyuganda',
			'mobilemoneytanzania',
			'mpesa',
		),
		'applepay'     => array( 'applepay' ),
		'googlepay'    => array( 'googlepay' ),
		'opay'         => array( 'opay' ),
	);

	/**
	 * Payment option tokens without a checkbox, editable under "Advanced".
	 *
	 * @var array<int, string>
	 */
	const ADVANCED_METHODS = array( 'ussd', 'qr', 'nqr', 'credit', 'barter' );

	/**
	 * Payment options for the legacy "Payment Method" dropdown, used until the
	 * merchant saves methods from the new screen.
	 *
	 * @var array<string, string>
	 */
	const LEGACY_METHODS = array(
		'both'    => 'card,account',
		'card'    => 'card',
		'account' => 'account',
		'all'     => 'card,account,ussd,qr,mpesa,banktransfer,mobilemoneyghana,mobilemoneyfranco,mobilemoneyuganda,mobilemoneyrwanda,mobilemoneyzambia,barter,credit',
	);

	/**
	 * Currencies a merchant can choose as the default for their forms.
	 *
	 * @var array<int, string>
	 */
	const CURRENCIES = array( 'NGN', 'GHS', 'KES', 'USD', 'GBP', 'EUR', 'ZAR', 'TZS', 'UGX', 'RWF', 'ZMW' );

	/**
	 * Countries a merchant can charge from.
	 *
	 * @var array<int, string>
	 */
	const COUNTRIES = array( 'NG', 'GH', 'KE', 'ZA', 'TZ', 'UG', 'RW', 'ZM', 'US' );

	/**
	 * Map of admin app keys to stored option keys for plain text values.
	 *
	 * @var array<string, string>
	 */
	const TEXT_FIELDS = array(
		'title'               => 'modal_title',
		'description'         => 'modal_desc',
		'buttonText'          => 'btn_text',
		'donationTitle'       => 'donation_title',
		'donationDescription' => 'donation_desc',
		'publicKey'           => 'public_key',
		'secretKey'           => 'secret_key',
		'secretHash'          => 'secret_hash',
		'currency'            => 'currency',
		'country'             => 'country',
	);

	/**
	 * Map of admin app keys to stored option keys for URLs.
	 *
	 * @var array<string, string>
	 */
	const URL_FIELDS = array(
		'logoUrl'    => 'modal_logo',
		'successUrl' => 'success_redirect_url',
		'failedUrl'  => 'failed_redirect_url',
		'pendingUrl' => 'pending_redirect_url',
	);

	/**
	 * The stored settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function raw(): array {
		$stored = get_option( self::OPTION_KEY, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * One stored setting as a string.
	 *
	 * @param string $key The stored option key.
	 *
	 * @return string
	 */
	public static function get( string $key ): string {
		$settings = self::raw();

		return isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	}

	/**
	 * Whether the merchant has finished (or previously configured) setup.
	 *
	 * Merchants upgrading from an older version never ran the wizard but already
	 * have a public key saved, so they are treated as onboarded.
	 *
	 * @return bool
	 */
	public static function is_onboarded(): bool {
		return 'yes' === self::get( 'onboarding_complete' ) || '' !== trim( self::get( 'public_key' ) );
	}

	/**
	 * The comma separated payment options sent to Flutterwave.
	 *
	 * @return string
	 */
	public static function payment_options(): string {
		$options = self::get( 'payment_options' );

		if ( '' !== $options ) {
			return $options;
		}

		$legacy = self::get( 'method' );

		return self::LEGACY_METHODS[ $legacy ] ?? self::LEGACY_METHODS['all'];
	}

	/**
	 * The payload the admin app consumes.
	 *
	 * @return array<string, mixed>
	 */
	public static function to_payload(): array {
		self::ensure_secret_hash();

		$onboarded = self::is_onboarded();
		$options   = self::payment_options();
		$payload   = array();

		foreach ( self::TEXT_FIELDS as $incoming => $stored ) {
			$payload[ $incoming ] = self::get( $stored );
		}

		foreach ( self::URL_FIELDS as $incoming => $stored ) {
			$payload[ $incoming ] = self::get( $stored );
		}

		// Forms need all three redirects, so an unset one is offered as the home
		// page. The last wizard step then only needs changing for custom pages.
		foreach ( array( 'successUrl', 'failedUrl', 'pendingUrl' ) as $key ) {
			if ( '' === $payload[ $key ] ) {
				$payload[ $key ] = home_url( '/' );
			}
		}

		// "any" lets customers pick; the wizard offers it as a choice as well.
		if ( ! in_array( $payload['currency'], array_merge( array( 'any' ), self::CURRENCIES ), true ) ) {
			$payload['currency'] = '';
		}

		$payload['mode']               = self::key_mode( $payload['publicKey'] );
		$payload['useThemeStyle']      = 'yes' === self::get( 'theme_style' );
		$payload['paymentMethods']     = self::methods_from_option( $options );
		$payload['advancedMethods']    = self::advanced_from_option( $options );
		$payload['onboardingComplete'] = $onboarded;
		$payload['webhookUrl']         = self::webhook_url();

		return $payload;
	}

	/**
	 * Persist a partial update from the admin app.
	 *
	 * Only keys present in the patch are touched, so each wizard step saves
	 * independently without clobbering the others.
	 *
	 * @param array<string, mixed> $patch Request body.
	 *
	 * @return array<string, mixed> The refreshed payload.
	 */
	public static function update( array $patch ): array {
		$settings = self::raw();

		foreach ( array_merge( self::TEXT_FIELDS, self::URL_FIELDS ) as $incoming => $stored ) {
			if ( array_key_exists( $incoming, $patch ) && is_scalar( $patch[ $incoming ] ) ) {
				$settings[ $stored ] = (string) $patch[ $incoming ];
			}
		}

		if ( array_key_exists( 'useThemeStyle', $patch ) ) {
			$settings['theme_style'] = filter_var( $patch['useThemeStyle'], FILTER_VALIDATE_BOOLEAN ) ? 'yes' : '';
		}

		if ( array_key_exists( 'paymentMethods', $patch ) ) {
			$methods  = is_array( $patch['paymentMethods'] ) ? array_map( 'sanitize_key', $patch['paymentMethods'] ) : array();
			$advanced = array_key_exists( 'advancedMethods', $patch ) && is_array( $patch['advancedMethods'] )
				? array_map( 'sanitize_key', $patch['advancedMethods'] )
				: self::advanced_from_option( self::payment_options() );

			$settings['payment_options'] = self::option_from_methods( $methods, $advanced );
		}

		if ( array_key_exists( 'onboardingComplete', $patch ) ) {
			$settings['onboarding_complete'] = filter_var( $patch['onboardingComplete'], FILTER_VALIDATE_BOOLEAN ) ? 'yes' : '';
		}

		$settings = FLW_Admin_Settings::sanitize( $settings );

		// Webhooks are rejected without a secret hash, so never store a blank one.
		if ( '' === trim( (string) ( $settings['secret_hash'] ?? '' ) ) ) {
			$settings['secret_hash'] = self::generate_secret_hash();
		}

		update_option( self::OPTION_KEY, $settings );

		return self::to_payload();
	}

	/**
	 * Which payment-method checkboxes are on, given a stored option string.
	 *
	 * @param string $option_string Comma separated Flutterwave payment options.
	 *
	 * @return array<int, string>
	 */
	public static function methods_from_option( string $option_string ): array {
		$tokens  = self::tokenize( $option_string );
		$enabled = array();

		foreach ( self::METHOD_MAP as $key => $group ) {
			if ( ! empty( array_intersect( $group, $tokens ) ) ) {
				$enabled[] = $key;
			}
		}

		return $enabled;
	}

	/**
	 * Enabled tokens that the checkbox list does not cover.
	 *
	 * @param string $option_string Comma separated Flutterwave payment options.
	 *
	 * @return array<int, string>
	 */
	public static function advanced_from_option( string $option_string ): array {
		return array_values( array_intersect( self::ADVANCED_METHODS, self::tokenize( $option_string ) ) );
	}

	/**
	 * Build the option string Flutterwave expects from the admin selection.
	 *
	 * @param array<int, string> $methods  Checkbox keys.
	 * @param array<int, string> $advanced Advanced tokens.
	 *
	 * @return string
	 */
	public static function option_from_methods( array $methods, array $advanced = array() ): string {
		$tokens = array();

		foreach ( $methods as $method ) {
			if ( isset( self::METHOD_MAP[ $method ] ) ) {
				$tokens = array_merge( $tokens, self::METHOD_MAP[ $method ] );
			}
		}

		$tokens = array_merge( $tokens, array_intersect( self::ADVANCED_METHODS, $advanced ) );

		return implode( ',', array_values( array_unique( $tokens ) ) );
	}

	/**
	 * Every payment option token the plugin knows about.
	 *
	 * @return array<int, string>
	 */
	public static function known_tokens(): array {
		$tokens = self::ADVANCED_METHODS;

		foreach ( self::METHOD_MAP as $group ) {
			$tokens = array_merge( $tokens, $group );
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Whether a public key is a test or live key.
	 *
	 * @param string $public_key The public key.
	 *
	 * @return string `test`, `live`, or an empty string when unknown.
	 */
	public static function key_mode( string $public_key ): string {
		if ( 0 === strpos( $public_key, 'FLWPUBK_TEST' ) ) {
			return 'test';
		}

		return 0 === strpos( $public_key, 'FLWPUBK-' ) ? 'live' : '';
	}

	/**
	 * The webhook URL merchants paste into their Flutterwave dashboard.
	 *
	 * @return string
	 */
	public static function webhook_url(): string {
		return rest_url( self::REST_NAMESPACE . '/webhook' );
	}

	/**
	 * A random 64-character hex secret hash.
	 *
	 * @return string
	 */
	public static function generate_secret_hash(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Store a generated secret hash when none is set, so the API & webhook step
	 * shows a real value before anything has been saved.
	 *
	 * @return void
	 */
	public static function ensure_secret_hash(): void {
		if ( '' !== trim( self::get( 'secret_hash' ) ) ) {
			return;
		}

		$settings                = self::raw();
		$settings['secret_hash'] = self::generate_secret_hash();

		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Split and normalise a comma separated option string.
	 *
	 * @param string $option_string Raw option value.
	 *
	 * @return array<int, string>
	 */
	private static function tokenize( string $option_string ): array {
		return array_values(
			array_filter(
				array_map( 'trim', explode( ',', $option_string ) ),
				static function ( string $part ): bool {
					return '' !== $part;
				}
			)
		);
	}
}
