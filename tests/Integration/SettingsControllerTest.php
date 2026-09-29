<?php
/**
 * The /flutterwave/v1/settings and /onboarding/complete routes used by the admin app.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use WP_REST_Request;

/**
 * @covers FLW_Settings_Controller
 * @covers FLW_Settings
 */
class SettingsControllerTest extends TestCase {

	/**
	 * Send a JSON request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /flutterwave/v1.
	 * @param array  $body   JSON body.
	 *
	 * @return \WP_REST_Response
	 */
	private function request( string $method, string $route, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/flutterwave/v1/' . $route );

		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_do_request( $request );
	}

	/**
	 * Log in as a user with the given role.
	 *
	 * @param string $role Role name.
	 */
	private function login_as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * @dataProvider routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below /flutterwave/v1.
	 */
	public function test_routes_require_manage_options( string $method, string $route ) {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( $method, $route )->get_status() );

		$this->login_as( 'editor' );
		$this->assertSame( 403, $this->request( $method, $route )->get_status() );
	}

	/**
	 * Admin app routes.
	 *
	 * @return array
	 */
	public static function routes(): array {
		return array(
			'read settings'       => array( 'GET', 'settings' ),
			'save settings'       => array( 'POST', 'settings' ),
			'complete onboarding' => array( 'POST', 'onboarding/complete' ),
		);
	}

	public function test_fresh_install_payload() {
		delete_option( 'flw_rave_options' );
		$this->login_as( 'administrator' );

		$data = $this->request( 'GET', 'settings' )->get_data();

		$this->assertFalse( $data['onboardingComplete'] );
		$this->assertSame( home_url( '/' ), $data['successUrl'] );
		$this->assertSame( rest_url( 'flutterwave/v1/webhook' ), $data['webhookUrl'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $data['secretHash'] );
	}

	public function test_saving_one_step_leaves_the_others_alone() {
		$this->configure_plugin();
		$this->login_as( 'administrator' );

		$data = $this->request(
			'POST',
			'settings',
			array(
				'title'    => 'Acme <script>x</script>',
				'currency' => 'KES',
			)
		)->get_data();

		// sanitize_text_field() drops <script> elements along with their contents.
		$this->assertSame( 'Acme', $data['title'] );
		$this->assertSame( 'KES', $data['currency'] );
		$this->assertSame( 'FLWPUBK_TEST-public', $data['publicKey'] );
		$this->assertSame( 'webhook-secret', get_option( 'flw_rave_options' )['secret_hash'] );
	}

	public function test_saving_methods_writes_payment_options() {
		$this->configure_plugin();
		$this->login_as( 'administrator' );

		$data = $this->request(
			'POST',
			'settings',
			array(
				'paymentMethods'  => array( 'card', 'applepay' ),
				'advancedMethods' => array(),
			)
		)->get_data();

		$this->assertSame( array( 'card', 'applepay' ), $data['paymentMethods'] );
		$this->assertSame( 'card,applepay', \FLW_Settings::payment_options() );
	}

	public function test_completing_onboarding_sets_the_flag_and_fires_the_action() {
		delete_option( 'flw_rave_options' );
		$this->login_as( 'administrator' );
		$fired = did_action( 'flw_onboarding_complete' );

		$data = $this->request(
			'POST',
			'onboarding/complete',
			array( 'successUrl' => 'https://example.com/thanks' )
		)->get_data();

		$this->assertTrue( $data['onboardingComplete'] );
		$this->assertSame( 'https://example.com/thanks', $data['successUrl'] );
		$this->assertSame( $fired + 1, did_action( 'flw_onboarding_complete' ) );
	}
}
