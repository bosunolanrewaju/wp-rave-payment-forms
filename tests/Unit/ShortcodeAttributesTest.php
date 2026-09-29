<?php
/**
 * Tests for the shortcode attribute helpers.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use FLW_Form_Config;

/**
 * @covers Abstract_FLW_Shortcode
 */
class ShortcodeAttributesTest extends TestCase {

	/**
	 * Load the shortcode base class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once dirname( __DIR__, 2 ) . '/includes/shortcodes/class-abstract-flw-shortcode.php';
	}

	/**
	 * Call a protected static helper.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 *
	 * @return mixed
	 */
	private function call( string $method, array $args ) {
		$reflection = new \ReflectionMethod( \Abstract_FLW_Shortcode::class, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		return $reflection->invokeArgs( null, $args );
	}

	public function test_attribute_values_cannot_break_out_of_quotes() {
		$html = $this->call( 'build_data_attributes', array( array( 'country' => 'NG onmouseover=alert(document.domain)//"' ) ) );

		$this->assertSame( ' data-country="NG onmouseover=alert(document.domain)//&quot;"', $html );
	}

	public function test_attribute_keys_are_sanitised() {
		$html = $this->call( 'build_data_attributes', array( array( 'x" onclick="alert(1)' => 'v' ) ) );

		$this->assertSame( ' data-xonclickalert1="v"', $html );
	}

	public function test_arrays_and_skipped_keys_are_left_out_and_booleans_flattened() {
		$html = $this->call(
			'build_data_attributes',
			array(
				array(
					'amount'        => 0,
					'custom_fields' => array( 'a' => 'text' ),
					'split_name'    => true,
					'email'         => false,
				),
				array( 'amount' ),
			)
		);

		$this->assertSame( ' data-split_name="1" data-email=""', $html );
	}

	/**
	 * @dataProvider open_amounts
	 *
	 * @param mixed $amount An amount that means "customer chooses".
	 */
	public function test_open_amount_is_not_written_as_a_fixed_price( $amount ) {
		$html = $this->call(
			'build_data_attributes',
			array(
				array(
					'amount'   => $amount,
					'currency' => 'NGN',
				),
			)
		);

		$this->assertSame( ' data-currency="NGN"', $html );
	}

	/**
	 * Amounts that leave the choice to the customer.
	 *
	 * @return array
	 */
	public static function open_amounts(): array {
		return array(
			'integer zero' => array( 0 ),
			'string zero'  => array( '0' ),
			'empty'        => array( '' ),
			'negative'     => array( '-5' ),
			'not a number' => array( 'abc' ),
		);
	}

	public function test_fixed_amount_is_written() {
		$this->assertSame( ' data-amount="5000"', $this->call( 'build_data_attributes', array( array( 'amount' => '5000' ) ) ) );
	}

	public function test_signed_config_fields_carry_a_verifiable_config() {
		$html = $this->call( 'get_signed_config_fields', array( '2500', 'ngn, usd' ) );

		$this->assertSame( 1, preg_match( '/name="flw_form_config" value="([^"]+)"/', $html, $payload ) );
		$this->assertSame( 1, preg_match( '/name="flw_form_sig" value="([^"]+)"/', $html, $signature ) );

		$config = FLW_Form_Config::verify( html_entity_decode( $payload[1] ), $signature[1] );

		$this->assertEquals( 2500, $config['amount'] );
		$this->assertSame( array( 'NGN', 'USD' ), $config['currencies'] );
	}

	public function test_signed_config_treats_non_numeric_amount_as_open() {
		$html = $this->call( 'get_signed_config_fields', array( 'abc', '' ) );

		preg_match( '/name="flw_form_config" value="([^"]+)"/', $html, $payload );
		preg_match( '/name="flw_form_sig" value="([^"]+)"/', $html, $signature );
		$config = FLW_Form_Config::verify( html_entity_decode( $payload[1] ), $signature[1] );

		$this->assertEquals( 0, $config['amount'] );
		$this->assertContains( 'NGN', $config['currencies'] );
	}
}
