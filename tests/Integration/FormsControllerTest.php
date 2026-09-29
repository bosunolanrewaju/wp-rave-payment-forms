<?php
/**
 * The /flutterwave/v1/forms routes behind the Payment Forms screen.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Forms_Controller;
use WP_REST_Request;

/**
 * @covers FLW_Forms_Controller
 */
class FormsControllerTest extends TestCase {

	public function set_up() {
		parent::set_up();
		$this->configure_plugin();
	}

	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * POST /forms/page.
	 *
	 * @param array $body JSON body.
	 *
	 * @return \WP_REST_Response
	 */
	private function create( array $body ) {
		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/forms/page' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	public function test_parse_content_finds_blocks_and_shortcodes() {
		$content = implode(
			"\n",
			array(
				'<!-- wp:group --><div class="wp-block-group"><!-- wp:flutterwave/pricing-card {"planName":"Pro","amount":25000} /--></div><!-- /wp:group -->',
				'<!-- wp:flutterwave/donation-form /-->',
				'[flw-pay-form amount="5000" currency="KES" layout="compact"]Pay now[/flw-pay-form]',
				'[flw-pay-form]',
				'[[flw-pay-form amount="1"]]',
				'[flw-donation-form]',
			)
		);

		$forms = FLW_Forms_Controller::parse_content( $content );

		$this->assertSame(
			array(
				array( 'block', 'pricing-card' ),
				array( 'block', 'donation-form' ),
				array( 'shortcode', 'payment-button' ),
				array( 'shortcode', 'payment-form' ),
				array( 'shortcode', 'donation-form' ),
			),
			array_map(
				static function ( $form ) {
					return array( $form['kind'], $form['type'] );
				},
				$forms
			)
		);
		$this->assertSame( 'Pro', $forms[0]['attributes']['planName'] );
		$this->assertSame( 'Pay now', $forms[2]['attributes']['buttonText'] );
		$this->assertSame( 'KES', $forms[2]['attributes']['currency'] );
	}

	public function test_lists_forms_with_page_payment_totals() {
		$this->login_as( 'administrator' );
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Workshop',
				'post_content' => '<!-- wp:flutterwave/payment-button {"amount":15000,"currency":"NGN"} /-->',
			)
		);
		self::factory()->post->create( array( 'post_content' => 'No forms here' ) );

		foreach ( array( 'successful', 'successful', 'failed' ) as $status ) {
			$id = $this->create_record( 15000.0, 'NGN' );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
			update_post_meta( $id, '_flw_rave_payment_status', $status );
			update_post_meta( $id, '_flw_rave_payment_source', $page );
		}

		$items = rest_do_request( new WP_REST_Request( 'GET', '/flutterwave/v1/forms' ) )->get_data()['items'];

		$this->assertCount( 1, $items );
		$this->assertSame( 'payment-button', $items[0]['type'] );
		$this->assertSame( 'Workshop', $items[0]['page']['title'] );
		$this->assertSame( 2, $items[0]['payments']['count'] );
		$this->assertEquals( 30000, $items[0]['payments']['totals'][0]['amount'] );
	}

	public function test_list_requires_manage_options() {
		$this->login_as( 'editor' );

		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/flutterwave/v1/forms' ) )->get_status() );
	}

	public function test_create_page_makes_a_draft_with_known_attributes_only() {
		$this->login_as( 'administrator' );

		$response = $this->create(
			array(
				'title'      => 'Buy <b>now</b>',
				'block'      => 'payment-button',
				'attributes' => array(
					'amount'   => 5000,
					'currency' => 'NGN',
					'onclick'  => 'alert(1)',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$page   = get_post( $response->get_data()['id'] );
		$blocks = parse_blocks( $page->post_content );

		$this->assertSame( 'draft', $page->post_status );
		$this->assertSame( 'page', $page->post_type );
		$this->assertSame( 'Buy now', $page->post_title );
		$this->assertSame( 'flutterwave/payment-button', $blocks[0]['blockName'] );
		$this->assertSame(
			array(
				'amount'   => 5000,
				'currency' => 'NGN',
			),
			$blocks[0]['attrs']
		);
	}

	public function test_create_page_rejects_unknown_blocks() {
		$this->login_as( 'administrator' );

		$this->assertSame( 400, $this->create( array( 'block' => 'core/html' ) )->get_status() );
	}

	public function test_create_page_requires_manage_options() {
		$this->login_as( 'editor' );

		$this->assertSame( 403, $this->create( array( 'block' => 'payment-button' ) )->get_status() );
	}
}
