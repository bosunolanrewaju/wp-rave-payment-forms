<?php
/**
 * Tests for FLW_Settings, the model behind the onboarding wizard and settings screen.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use Brain\Monkey\Functions;
use FLW_Settings;

/**
 * @covers FLW_Settings
 */
class SettingsTest extends TestCase {

	/**
	 * The stored option, as update_option() last wrote it.
	 *
	 * @var mixed
	 */
	private $option = false;

	/**
	 * Back the option with an in-memory value.
	 */
	protected function set_up() {
		parent::set_up();

		Functions\when( 'get_option' )->alias(
			function ( $name, $fallback = false ) {
				return FLW_Settings::OPTION_KEY === $name && false !== $this->option ? $this->option : $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->option = $value;
				return true;
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );
		Functions\when( 'rest_url' )->alias(
			static function ( $path ) {
				return 'https://shop.test/wp-json/' . $path;
			}
		);
	}

	public function test_selected_methods_become_payment_options() {
		$this->assertSame( 'card', FLW_Settings::option_from_methods( array( 'card' ) ) );
		$this->assertSame( 'card,banktransfer,account', FLW_Settings::option_from_methods( array( 'card', 'banktransfer' ) ) );
		$this->assertSame( 'card,ussd', FLW_Settings::option_from_methods( array( 'card', 'bogus' ), array( 'ussd', 'evil' ) ) );
	}

	public function test_mobile_money_expands_to_country_variants() {
		$option = FLW_Settings::option_from_methods( array( 'mobilemoney' ) );

		$this->assertStringContainsString( 'mobilemoneyghana', $option );
		$this->assertStringContainsString( 'mpesa', $option );
	}

	public function test_legacy_all_option_round_trips() {
		$legacy = FLW_Settings::LEGACY_METHODS['all'];

		$this->assertSame( array( 'card', 'banktransfer', 'mobilemoney' ), FLW_Settings::methods_from_option( $legacy ) );
		$this->assertSame( array( 'ussd', 'qr', 'credit', 'barter' ), FLW_Settings::advanced_from_option( $legacy ) );
	}

	/**
	 * @dataProvider legacy_methods
	 *
	 * @param mixed  $stored   Stored settings.
	 * @param string $expected Payment options sent to Flutterwave.
	 */
	public function test_payment_options_fall_back_to_legacy_method( $stored, string $expected ) {
		$this->option = $stored;

		$this->assertSame( $expected, FLW_Settings::payment_options() );
	}

	/**
	 * Stored settings and the payment options they produce.
	 *
	 * @return array
	 */
	public static function legacy_methods(): array {
		return array(
			'nothing saved'    => array( false, FLW_Settings::LEGACY_METHODS['all'] ),
			'legacy card only' => array( array( 'method' => 'card' ), 'card' ),
			'legacy both'      => array( array( 'method' => 'both' ), 'card,account' ),
			'legacy unknown'   => array( array( 'method' => 'nope' ), FLW_Settings::LEGACY_METHODS['all'] ),
			'new setting wins' => array(
				array(
					'method'          => 'card',
					'payment_options' => 'applepay',
				),
				'applepay',
			),
		);
	}

	public function test_fresh_install_is_not_onboarded() {
		$this->assertFalse( FLW_Settings::is_onboarded() );
	}

	public function test_upgraded_site_with_keys_counts_as_onboarded() {
		$this->option = array( 'public_key' => 'FLWPUBK_TEST-x' );

		$this->assertTrue( FLW_Settings::is_onboarded() );
	}

	public function test_payload_for_fresh_install() {
		$payload = FLW_Settings::to_payload();

		$this->assertFalse( $payload['onboardingComplete'] );
		$this->assertSame( '', $payload['title'] );
		$this->assertSame( 'https://shop.test/', $payload['successUrl'] );
		$this->assertSame( 'https://shop.test/wp-json/flutterwave/v1/webhook', $payload['webhookUrl'] );
		$this->assertSame( array( 'card', 'banktransfer', 'mobilemoney' ), $payload['paymentMethods'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $payload['secretHash'] );
		$this->assertSame( $payload['secretHash'], $this->option['secret_hash'], 'The generated hash is stored so it survives a reload.' );
	}

	public function test_payload_maps_stored_values() {
		$this->option = array(
			'modal_title'          => 'Acme',
			'public_key'           => 'FLWPUBK-live',
			'currency'             => 'KES',
			'success_redirect_url' => 'https://shop.test/thanks',
			'theme_style'          => 'yes',
			'secret_hash'          => 'kept',
		);

		$payload = FLW_Settings::to_payload();

		$this->assertSame( 'Acme', $payload['title'] );
		$this->assertSame( 'live', $payload['mode'] );
		$this->assertSame( 'KES', $payload['currency'] );
		$this->assertSame( 'https://shop.test/thanks', $payload['successUrl'] );
		$this->assertTrue( $payload['useThemeStyle'] );
		$this->assertSame( 'kept', $payload['secretHash'] );
	}

	public function test_unknown_stored_currency_reads_as_unset() {
		$this->option = array( 'currency' => 'BTC' );

		$this->assertSame( '', FLW_Settings::to_payload()['currency'] );
	}

	public function test_update_only_touches_keys_in_the_patch() {
		$this->option = array(
			'modal_title' => 'Old title',
			'public_key'  => 'FLWPUBK_TEST-keep',
			'secret_hash' => 'keep',
		);

		FLW_Settings::update( array( 'title' => 'New <b>title</b>' ) );

		$this->assertSame( 'New title', $this->option['modal_title'] );
		$this->assertSame( 'FLWPUBK_TEST-keep', $this->option['public_key'] );
		$this->assertSame( 'keep', $this->option['secret_hash'] );
	}

	public function test_update_sanitises_every_value() {
		FLW_Settings::update(
			array(
				'successUrl'      => 'javascript:alert(1)',
				'currency'        => 'BTC',
				'secretKey'       => " FLWSECK_TEST-x\n",
				'paymentMethods'  => array( 'card', '<script>' ),
				'advancedMethods' => array( 'ussd' ),
				'useThemeStyle'   => 'true',
				'unknownKey'      => 'dropped',
			)
		);

		$this->assertSame( '', $this->option['success_redirect_url'] );
		$this->assertArrayNotHasKey( 'currency', $this->option );
		$this->assertSame( 'FLWSECK_TEST-x', $this->option['secret_key'] );
		$this->assertSame( 'card,ussd', $this->option['payment_options'] );
		$this->assertSame( 'yes', $this->option['theme_style'] );
		$this->assertArrayNotHasKey( 'unknownKey', $this->option );
	}

	public function test_update_never_stores_a_blank_secret_hash() {
		FLW_Settings::update( array( 'secretHash' => '   ' ) );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $this->option['secret_hash'] );
	}

	public function test_saving_methods_keeps_advanced_tokens_when_not_sent() {
		$this->option = array( 'payment_options' => 'card,ussd,barter' );

		FLW_Settings::update( array( 'paymentMethods' => array( 'banktransfer' ) ) );

		$this->assertSame( 'banktransfer,account,ussd,barter', $this->option['payment_options'] );
	}

	public function test_onboarding_flag() {
		FLW_Settings::update( array( 'onboardingComplete' => true ) );
		$this->assertSame( 'yes', $this->option['onboarding_complete'] );
		$this->assertTrue( FLW_Settings::is_onboarded() );

		FLW_Settings::update( array( 'onboardingComplete' => false ) );
		$this->assertArrayNotHasKey( 'onboarding_complete', $this->option );
	}

	/**
	 * @dataProvider key_modes
	 *
	 * @param string $key      Public key.
	 * @param string $expected Mode.
	 */
	public function test_key_mode( string $key, string $expected ) {
		$this->assertSame( $expected, FLW_Settings::key_mode( $key ) );
	}

	/**
	 * Keys and their modes.
	 *
	 * @return array
	 */
	public static function key_modes(): array {
		return array(
			'test' => array( 'FLWPUBK_TEST-abc-X', 'test' ),
			'live' => array( 'FLWPUBK-abc-X', 'live' ),
			'none' => array( 'pk_live_123', '' ),
		);
	}
}
