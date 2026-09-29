<?php
/**
 * Tests for FLW_Form_Config.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use FLW_Form_Config;

/**
 * @covers FLW_Form_Config
 */
class FormConfigTest extends TestCase {

	/**
	 * Sign and verify a configuration.
	 *
	 * @param float $amount     Fixed amount.
	 * @param array $currencies Allowed currencies.
	 *
	 * @return array
	 */
	private function signed_config( float $amount, array $currencies ): array {
		$signed = FLW_Form_Config::sign( $amount, $currencies );
		return FLW_Form_Config::verify( $signed['payload'], $signed['signature'] );
	}

	public function test_valid_signature_round_trips() {
		$config = $this->signed_config( 50000, array( 'NGN' ) );

		$this->assertSame( array( 'NGN' ), $config['currencies'] );
		$this->assertEquals( 50000, $config['amount'] );
	}

	public function test_forged_payload_is_rejected() {
		$signed = FLW_Form_Config::sign( 50000, array( 'NGN' ) );
		$forged = base64_encode( json_encode( array( 'amount' => 1, 'currencies' => array( 'NGN' ) ) ) ); // phpcs:ignore

		$this->assertInstanceOf( \WP_Error::class, FLW_Form_Config::verify( $forged, $signed['signature'] ) );
	}

	public function test_missing_signature_is_rejected() {
		$signed = FLW_Form_Config::sign( 50000, array( 'NGN' ) );

		$this->assertInstanceOf( \WP_Error::class, FLW_Form_Config::verify( $signed['payload'], '' ) );
		$this->assertInstanceOf( \WP_Error::class, FLW_Form_Config::verify( '', '' ) );
	}

	public function test_signature_depends_on_site_salt() {
		$signed = FLW_Form_Config::sign( 50000, array( 'NGN' ) );

		\Brain\Monkey\Functions\when( 'wp_salt' )->justReturn( 'another-site' );

		$this->assertInstanceOf( \WP_Error::class, FLW_Form_Config::verify( $signed['payload'], $signed['signature'] ) );
	}

	public function test_fixed_amount_overrides_browser_amount() {
		$terms = FLW_Form_Config::resolve_terms( $this->signed_config( 50000, array( 'NGN' ) ), '1', 'NGN' );

		$this->assertSame( 50000.0, $terms['amount'] );
		$this->assertSame( 'NGN', $terms['currency'] );
	}

	public function test_open_amount_uses_positive_browser_amount() {
		$terms = FLW_Form_Config::resolve_terms( $this->signed_config( 0, array( 'NGN', 'USD' ) ), '2500.555', 'usd' );

		$this->assertSame( 2500.56, $terms['amount'] );
		$this->assertSame( 'USD', $terms['currency'] );
	}

	/**
	 * @dataProvider invalid_amounts
	 *
	 * @param mixed $amount Invalid browser amount.
	 */
	public function test_open_amount_rejects_invalid_values( $amount ) {
		$result = FLW_Form_Config::resolve_terms( $this->signed_config( 0, array( 'NGN' ) ), $amount, 'NGN' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'flw-invalid-amount', $result->get_error_code() );
	}

	/**
	 * Invalid amounts.
	 *
	 * @return array
	 */
	public static function invalid_amounts(): array {
		return array(
			'zero'     => array( '0' ),
			'negative' => array( '-5' ),
			'text'     => array( 'abc' ),
			'empty'    => array( '' ),
			'null'     => array( null ),
		);
	}

	public function test_currency_outside_form_is_rejected() {
		$result = FLW_Form_Config::resolve_terms( $this->signed_config( 100, array( 'NGN' ) ), '100', 'USD' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'flw-invalid-currency', $result->get_error_code() );
	}

	public function test_parse_currencies_normalises_list() {
		$this->assertSame( array( 'NGN', 'USD' ), FLW_Form_Config::parse_currencies( ' ngn, USD,,ngn' ) );
	}

	public function test_parse_currencies_uses_fallback_when_empty() {
		$this->assertSame( array( 'NGN', 'KES' ), FLW_Form_Config::parse_currencies( '', array( 'NGN', 'KES' ) ) );
	}
}
