<?php
/**
 * The get_payment_url AJAX action.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Form_Config;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * @covers Flutterwave_Payments::get_payment_url
 * @group ajax
 */
class CheckoutAjaxTest extends \WP_Ajax_UnitTestCase {

	/**
	 * Captured requests to Flutterwave.
	 *
	 * @var array
	 */
	private $http_requests = array();

	/**
	 * Configure the plugin and mock Flutterwave.
	 */
	public function set_up() {
		parent::set_up();

		update_option(
			'flw_rave_options',
			array(
				'public_key' => 'FLWPUBK_TEST-public',
				'secret_key' => 'FLWSECK_TEST-secret',
			)
		);

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				// Only Flutterwave is answered; anything else (core update checks
				// fired by admin_init) is refused so tests never touch the network.
				if ( 0 !== strpos( $url, 'https://api.flutterwave.com/' ) ) {
					return new \WP_Error( 'http_blocked', 'Blocked in tests.' );
				}

				$this->http_requests[] = array(
					'url'  => $url,
					'body' => json_decode( $args['body'] ?? '[]', true ),
				);

				$body = false !== strpos( $url, 'payment-plans' )
					? array( 'data' => array( 'id' => 321 ) )
					: array( 'data' => array( 'link' => 'https://checkout.flutterwave.com/pay/abc' ) );

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
	}

	/**
	 * Run the checkout action with a signed form config.
	 *
	 * @param array $post       Request fields.
	 * @param float $amount     Signed form amount.
	 * @param array $currencies Signed form currencies.
	 *
	 * @return array Decoded JSON response.
	 */
	private function checkout( array $post, float $amount = 5000, array $currencies = array( 'NGN' ) ): array {
		$signed               = FLW_Form_Config::sign( $amount, $currencies );
		$this->_last_response = '';

		$_POST = array_merge(
			array(
				'flw_sec_code'    => wp_create_nonce( 'flw-rave-pay-nonce' ),
				'flw_form_config' => $signed['payload'],
				'flw_form_sig'    => $signed['signature'],
				'amount'          => '5000',
				'currency'        => 'NGN',
				'form_id'         => 'abcd',
				'customer'        => array(
					'email' => 'customer@example.com',
					'name'  => 'Test Customer',
				),
			),
			$post
		);

		try {
			$this->_handleAjax( 'get_payment_url' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * Payment records created so far.
	 *
	 * @return \WP_Post[]
	 */
	private function records(): array {
		return get_posts(
			array(
				'post_type'   => 'payment_list',
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
	}

	public function test_tampered_amount_is_replaced_by_signed_amount() {
		$response = $this->checkout( array( 'amount' => '1' ) );

		$this->assertSame( 'success', $response['status'] );
		$this->assertEquals( 5000, $this->http_requests[0]['body']['amount'] );
		$this->assertEquals( 5000, $this->http_requests[0]['body']['meta']['order_amount'] );

		$records = $this->records();
		$this->assertCount( 1, $records );
		$this->assertEquals( 5000, get_post_meta( $records[0]->ID, '_flw_rave_payment_amount', true ) );
	}

	public function test_payment_options_and_branding_come_from_settings() {
		update_option(
			'flw_rave_options',
			array(
				'public_key'      => 'FLWPUBK_TEST-public',
				'secret_key'      => 'FLWSECK_TEST-secret',
				'payment_options' => 'card,applepay',
				'modal_title'     => 'Acme Studio',
				'modal_logo'      => 'https://example.com/logo.png',
			)
		);

		$this->checkout( array( 'payment_options' => 'barter' ) );

		$body = $this->http_requests[0]['body'];
		$this->assertSame( 'card,applepay', $body['payment_options'] );
		$this->assertSame( 'Acme Studio', $body['customizations']['title'] );
		$this->assertSame( 'https://example.com/logo.png', $body['customizations']['logo'] );
	}

	public function test_checkout_queues_request_sent() {
		_set_cron_array( array() );

		$this->checkout( array() );

		$names = array();
		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( (array) ( $hooks[ \FLW_Signoz_Logger::SCHEDULED_SEND_HOOK ] ?? array() ) as $job ) {
				$names[] = $job['args'][0];
			}
		}

		$this->assertSame( array( 'request.sent' ), $names );
	}

	public function test_missing_payment_link_is_an_error_not_a_blank_success() {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) {
				if ( 0 !== strpos( $url, 'https://api.flutterwave.com/' ) ) {
					return new \WP_Error( 'http_blocked', 'Blocked in tests.' );
				}

				return array(
					'headers'  => array(),
					'body'     => wp_json_encode(
						array(
							'status'  => 'error',
							'message' => 'Invalid currency',
						)
					),
					'response' => array(
						'code'    => 400,
						'message' => 'Bad Request',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);

		$response = $this->checkout( array() );

		$this->assertSame( 'error', $response['status'] );
	}

	public function test_forged_form_config_is_rejected() {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- building a forged payload.
		$forged = base64_encode( wp_json_encode( array( 'amount' => 1 ) + array( 'currencies' => array( 'NGN' ) ) ) );

		$response = $this->checkout( array( 'flw_form_config' => $forged ) );

		$this->assertSame( 'error', $response['status'] );
		$this->assertSame( array(), $this->records() );
		$this->assertSame( array(), $this->http_requests );
	}

	public function test_currency_outside_form_is_rejected() {
		$response = $this->checkout( array( 'currency' => 'USD' ) );

		$this->assertSame( 'error', $response['status'] );
		$this->assertSame( array(), $this->records() );
	}

	public function test_unknown_payment_type_is_rejected() {
		$response = $this->checkout( array( 'payment_type' => 'hourly' ) );

		$this->assertSame( 'error', $response['status'] );
		$this->assertSame( array(), $this->http_requests );
	}

	public function test_recurring_donation_reuses_cached_plan() {
		$this->checkout( array( 'payment_type' => 'monthly' ) );
		$this->checkout( array( 'payment_type' => 'monthly' ) );

		$plan_requests = array_filter(
			$this->http_requests,
			static function ( $request ) {
				return false !== strpos( $request['url'], 'payment-plans' );
			}
		);

		$this->assertCount( 1, $plan_requests );
		$this->assertSame( 321, end( $this->http_requests )['body']['payment_plan'] );
	}

	public function test_rate_limit() {
		add_filter(
			'flw_checkout_rate_limit',
			static function () {
				return 1;
			}
		);

		$this->assertSame( 'success', $this->checkout( array() )['status'] );
		$this->assertSame( 'error', $this->checkout( array() )['status'] );
		$this->assertCount( 1, $this->records() );
	}
}
