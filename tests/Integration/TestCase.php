<?php
/**
 * Base integration test case.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

/**
 * Helpers shared by the integration tests.
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Outgoing HTTP requests captured by mock_flutterwave().
	 *
	 * @var array
	 */
	protected $http_requests = array();

	/**
	 * Configure the plugin with test settings.
	 *
	 * @param array $overrides Settings to override.
	 */
	protected function configure_plugin( array $overrides = array() ): void {
		update_option(
			'flw_rave_options',
			array_merge(
				array(
					'public_key'           => 'FLWPUBK_TEST-public',
					'secret_key'           => 'FLWSECK_TEST-secret',
					'secret_hash'          => 'webhook-secret',
					'success_redirect_url' => 'https://example.com/success',
					'failed_redirect_url'  => 'https://example.com/failed',
					'pending_redirect_url' => 'https://example.com/pending',
					'currency'             => 'NGN',
					'country'              => 'NG',
				),
				$overrides
			)
		);
	}

	/**
	 * Create a pending payment record.
	 *
	 * @param float  $amount   Expected amount.
	 * @param string $currency Expected currency.
	 *
	 * @return int
	 */
	protected function create_record( float $amount = 5000.0, string $currency = 'NGN' ): int {
		$post_id = self::factory()->post->create( array( 'post_type' => 'payment_list' ) );
		update_post_meta( $post_id, '_flw_rave_payment_tx_ref', 'WP_TEST_' . $post_id );
		update_post_meta( $post_id, '_flw_rave_payment_amount', $amount );
		update_post_meta( $post_id, '_flw_rave_payment_currency', $currency );
		update_post_meta( $post_id, '_flw_rave_payment_status', 'pending' );
		return $post_id;
	}

	/**
	 * Build a Flutterwave transaction for a record.
	 *
	 * @param int   $post_id   The record id.
	 * @param array $overrides Fields to override.
	 *
	 * @return array
	 */
	protected function transaction( int $post_id, array $overrides = array() ): array {
		return array_merge(
			array(
				'id'       => 4242,
				'tx_ref'   => 'WP_TEST_' . $post_id,
				'amount'   => 5000,
				'currency' => 'NGN',
				'status'   => 'successful',
				'meta'     => array(
					'order_id'       => $post_id,
					'order_amount'   => 5000,
					'order_currency' => 'NGN',
				),
				'customer' => array(
					'name'  => 'Test Customer',
					'email' => 'customer@example.com',
				),
			),
			$overrides
		);
	}

	/**
	 * Intercept requests to api.flutterwave.com and answer with a canned body.
	 *
	 * @param array $body Response body, JSON encoded before returning.
	 * @param int   $code HTTP status code.
	 */
	protected function mock_flutterwave( array $body, int $code = 200 ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $body, $code ) {
				if ( 0 !== strpos( $url, 'https://api.flutterwave.com/' ) ) {
					return $pre;
				}

				$this->http_requests[] = array(
					'url'  => $url,
					'args' => $args,
				);

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array(
						'code'    => $code,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}
}
