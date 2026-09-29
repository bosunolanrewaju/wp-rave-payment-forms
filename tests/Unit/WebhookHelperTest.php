<?php
/**
 * Tests for WebhookHelper.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use Flutterwave\WordPress\Helper\WebhookHelper;

/**
 * @covers \Flutterwave\WordPress\Helper\WebhookHelper
 */
class WebhookHelperTest extends TestCase {

	/**
	 * @dataProvider hash_pairs
	 *
	 * @param string $expected Stored hash.
	 * @param string $actual   Received hash.
	 * @param bool   $valid    Whether the pair should authenticate.
	 */
	public function test_compare_secret_hash( string $expected, string $actual, bool $valid ) {
		$this->assertSame( $valid, WebhookHelper::compare_secret_hash( $expected, $actual ) );
	}

	/**
	 * Hash pairs.
	 *
	 * @return array
	 */
	public static function hash_pairs(): array {
		return array(
			'unconfigured and missing header' => array( '', '', false ),
			'missing header'                  => array( 'secret', '', false ),
			'unconfigured'                    => array( '', 'secret', false ),
			'wrong hash'                      => array( 'secret', 'secreT', false ),
			'matching hash'                   => array( 'secret', 'secret', true ),
		);
	}

	public function test_validate_hook_body() {
		$this->assertTrue(
			WebhookHelper::validate_hook_body(
				array(
					'event' => 'charge.completed',
					'data'  => array(),
				)
			)
		);
		$this->assertFalse( WebhookHelper::validate_hook_body( array( 'event' => 'charge.completed' ) ) );
		$this->assertFalse(
			WebhookHelper::validate_hook_body(
				array(
					'event' => array(),
					'data'  => array(),
				)
			)
		);
		$this->assertFalse(
			WebhookHelper::validate_hook_body(
				array(
					'event' => 'x',
					'data'  => 'string',
				)
			)
		);
	}
}
