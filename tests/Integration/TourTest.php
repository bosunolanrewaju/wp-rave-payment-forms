<?php
/**
 * The /flutterwave/v1/tour route behind the guided tips.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Tour;
use WP_REST_Request;

/**
 * @covers FLW_Tour
 */
class TourTest extends TestCase {

	/**
	 * POST /tour.
	 *
	 * @param array $body JSON body.
	 *
	 * @return \WP_REST_Response
	 */
	private function save( array $body ) {
		$request = new WP_REST_Request( 'POST', '/flutterwave/v1/tour' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	private function login_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	public function test_tips_are_on_for_new_users() {
		$this->login_as( 'administrator' );

		$this->assertSame(
			array(
				'enabled' => true,
				'seen'    => array(),
				'updated' => 0,
			),
			FLW_Tour::get()
		);
	}

	public function test_records_seen_tours_once() {
		$this->login_as( 'administrator' );

		$this->save( array( 'seen' => 'forms@1' ) );
		$data = $this->save( array( 'seen' => 'forms@1' ) )->get_data();

		$this->assertSame( array( 'forms@1' ), $data['seen'] );
		$this->assertGreaterThan( 0, $data['updated'] );
		$this->assertSame( array( 'forms@1' ), FLW_Tour::get()['seen'] );
	}

	public function test_turning_tips_off_and_resetting() {
		$this->login_as( 'administrator' );
		$this->save( array( 'seen' => 'transactions@1' ) );

		$this->assertFalse( $this->save( array( 'enabled' => false ) )->get_data()['enabled'] );
		$this->assertSame( array( 'transactions@1' ), FLW_Tour::get()['seen'] );

		$data = $this->save(
			array(
				'enabled' => true,
				'reset'   => true,
			)
		)->get_data();

		$this->assertTrue( $data['enabled'] );
		$this->assertSame( array(), $data['seen'] );
	}

	public function test_is_kept_per_user() {
		$this->login_as( 'administrator' );
		$this->save( array( 'enabled' => false ) );

		$this->login_as( 'administrator' );

		$this->assertTrue( FLW_Tour::get()['enabled'] );
	}

	public function test_rejects_bad_keys() {
		$this->login_as( 'administrator' );

		$this->assertSame( 400, $this->save( array( 'seen' => '<script>' ) )->get_status() );
		$this->assertSame( 400, $this->save( array( 'seen' => 'forms' ) )->get_status() );
		$this->assertSame( array(), FLW_Tour::get()['seen'] );
	}

	public function test_keeps_a_bounded_history() {
		$this->login_as( 'administrator' );

		for ( $version = 1; $version <= FLW_Tour::MAX_SEEN + 5; $version++ ) {
			$this->save( array( 'seen' => 'forms@' . $version ) );
		}

		$seen = FLW_Tour::get()['seen'];

		$this->assertCount( FLW_Tour::MAX_SEEN, $seen );
		$this->assertSame( 'forms@' . ( FLW_Tour::MAX_SEEN + 5 ), end( $seen ) );
	}

	public function test_requires_manage_options() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->save( array( 'enabled' => false ) )->get_status() );

		$this->login_as( 'editor' );
		$this->assertSame( 403, $this->save( array( 'enabled' => false ) )->get_status() );
	}
}
