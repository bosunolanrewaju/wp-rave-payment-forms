<?php
/**
 * Tests for FLW_Admin_Settings::sanitize_options().
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use FLW_Admin_Settings;

/**
 * @covers FLW_Admin_Settings::sanitize_options
 */
class AdminSettingsTest extends TestCase {

	/**
	 * Settings instance built without running the constructor's hooks.
	 *
	 * @return FLW_Admin_Settings
	 */
	private function settings(): FLW_Admin_Settings {
		return ( new \ReflectionClass( FLW_Admin_Settings::class ) )->newInstanceWithoutConstructor();
	}

	public function test_non_array_input_becomes_empty_array() {
		$this->assertSame( array(), $this->settings()->sanitize_options( 'nope' ) );
	}

	public function test_credentials_keep_their_characters_but_lose_control_characters() {
		$clean = $this->settings()->sanitize_options(
			array(
				'secret_key'  => " FLWSECK_TEST-abc%20def\r\n",
				'secret_hash' => "p@ss<wo>rd\x00",
			)
		);

		$this->assertSame( 'FLWSECK_TEST-abc%20def', $clean['secret_key'] );
		$this->assertSame( 'p@ss<wo>rd', $clean['secret_hash'] );
	}

	public function test_urls_are_restricted_to_http() {
		$clean = $this->settings()->sanitize_options(
			array(
				'success_redirect_url' => 'https://example.com/thanks',
				'failed_redirect_url'  => 'javascript:alert(1)',
			)
		);

		$this->assertSame( 'https://example.com/thanks', $clean['success_redirect_url'] );
		$this->assertSame( '', $clean['failed_redirect_url'] );
	}

	public function test_text_fields_are_stripped() {
		$clean = $this->settings()->sanitize_options( array( 'modal_title' => '<script>x</script>Pay' ) );

		$this->assertSame( 'xPay', $clean['modal_title'] );
	}

	public function test_choices_outside_allowed_values_are_dropped() {
		$clean = $this->settings()->sanitize_options(
			array(
				'method'      => 'card',
				'currency'    => 'BTC',
				'country'     => 'NG',
				'go_live'     => 'yes',
				'theme_style' => 'on',
			)
		);

		$this->assertSame( 'card', $clean['method'] );
		$this->assertArrayNotHasKey( 'currency', $clean );
		$this->assertSame( 'NG', $clean['country'] );
		$this->assertSame( 'yes', $clean['go_live'] );
		$this->assertArrayNotHasKey( 'theme_style', $clean );
	}

	public function test_payment_options_keep_only_known_tokens() {
		$clean = $this->settings()->sanitize_options(
			array(
				'payment_options'     => 'card, applepay,evil,card',
				'onboarding_complete' => 'yes',
			)
		);

		$this->assertSame( 'card,applepay', $clean['payment_options'] );
		$this->assertSame( 'yes', $clean['onboarding_complete'] );
	}

	public function test_currency_and_country_lists_match_the_settings_model() {
		$clean = $this->settings()->sanitize_options(
			array(
				'currency' => 'UGX',
				'country'  => 'TZ',
			)
		);

		$this->assertSame( 'UGX', $clean['currency'] );
		$this->assertSame( 'TZ', $clean['country'] );
	}

	public function test_unknown_keys_are_discarded() {
		$this->assertSame( array(), $this->settings()->sanitize_options( array( 'evil' => 'x' ) ) );
	}
}
