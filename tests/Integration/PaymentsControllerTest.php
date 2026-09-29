<?php
/**
 * The /flutterwave/v1/payments routes and CSV export behind the Transactions screen.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Payments_Controller;
use WP_REST_Request;

/**
 * @covers FLW_Payments_Controller
 */
class PaymentsControllerTest extends TestCase {

	public function set_up() {
		parent::set_up();
		$this->configure_plugin();
	}

	/**
	 * A payment record with the given status, currency and age.
	 *
	 * @param string $status   Stored status.
	 * @param string $currency Currency.
	 * @param int    $days_ago Age in days.
	 * @param array  $meta     Extra meta without the prefix.
	 *
	 * @return int
	 */
	private function payment( string $status, string $currency = 'NGN', int $days_ago = 0, array $meta = array() ): int {
		$id = $this->create_record( 5000.0, $currency );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
			)
		);
		update_post_meta( $id, '_flw_rave_payment_status', $status );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_flw_rave_payment_' . $key, $value );
		}

		return $id;
	}

	/**
	 * GET /payments with query parameters.
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array
	 */
	private function list( array $params = array() ): array {
		$request = new WP_REST_Request( 'GET', '/flutterwave/v1/payments' );
		$request->set_query_params( $params );

		return rest_do_request( $request )->get_data();
	}

	private function login_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * @dataProvider routes
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 */
	public function test_routes_require_manage_options( string $method, string $route ) {
		$id    = $this->payment( 'pending' );
		$route = str_replace( '{id}', (string) $id, $route );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( $method, $route ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( $method, $route ) )->get_status() );
		$this->assertNotNull( get_post( $id ) );
	}

	/**
	 * Payments routes.
	 *
	 * @return array
	 */
	public static function routes(): array {
		return array(
			'list'   => array( 'GET', '/flutterwave/v1/payments' ),
			'verify' => array( 'POST', '/flutterwave/v1/payments/{id}/verify' ),
			'delete' => array( 'DELETE', '/flutterwave/v1/payments/{id}' ),
		);
	}

	public function test_lists_payments_newest_first() {
		$this->login_admin();
		$older = $this->payment( 'successful', 'NGN', 3, array( 'fullname' => 'Ada Lovelace' ) );
		$newer = $this->payment( 'pending', 'USD', 1 );

		$data = $this->list();

		$this->assertSame( 2, $data['total'] );
		$this->assertSame( array( $newer, $older ), wp_list_pluck( $data['items'], 'id' ) );
		$this->assertSame( 'Ada Lovelace', $data['items'][1]['customerName'] );
		$this->assertEqualsCanonicalizing( array( 'NGN', 'USD' ), $data['currencies'] );
	}

	public function test_filters_by_status_group_and_currency() {
		$this->login_admin();
		$paid    = $this->payment( 'successful', 'NGN' );
		$review  = $this->payment( 'paid less', 'NGN' );
		$pending = $this->payment( 'pending', 'NGN' );
		$this->payment( 'successful', 'USD' );
		$this->payment( 'failed', 'NGN' );

		$ids = wp_list_pluck(
			$this->list(
				array(
					'status'   => 'successful,review',
					'currency' => 'ngn',
				)
			)['items'],
			'id'
		);
		$this->assertEqualsCanonicalizing( array( $paid, $review ), $ids );

		$ids = wp_list_pluck( $this->list( array( 'status' => array( 'pending' ) ) )['items'], 'id' );
		$this->assertSame( array( $pending ), $ids );
	}

	public function test_filters_by_date_range() {
		$this->login_admin();
		$recent = $this->payment( 'successful', 'NGN', 2 );
		$this->payment( 'successful', 'NGN', 40 );

		$data = $this->list(
			array(
				'from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
				'to'   => gmdate( 'Y-m-d' ),
			)
		);

		$this->assertSame( array( $recent ), wp_list_pluck( $data['items'], 'id' ) );
	}

	public function test_normalize_filters_limits_the_range_and_drops_junk() {
		$filters = FLW_Payments_Controller::normalize_filters(
			array(
				'from'     => '2020-01-01',
				'to'       => '2026-06-30',
				'status'   => array( 'successful', 'bogus', "' OR 1=1" ),
				'currency' => 'usd,NGN,DROP TABLE,NG',
			)
		);

		$this->assertSame( '2025-06-29', $filters['from'] );
		$this->assertSame( '2026-06-30', $filters['to'] );
		$this->assertSame( array( 'successful' ), $filters['status'] );
		$this->assertSame( array( 'USD', 'NGN' ), $filters['currency'] );

		$swapped = FLW_Payments_Controller::normalize_filters(
			array(
				'from' => '2026-05-10',
				'to'   => '2026-05-01',
			)
		);
		$this->assertSame( array( '2026-05-01', '2026-05-10' ), array( $swapped['from'], $swapped['to'] ) );

		$invalid = FLW_Payments_Controller::normalize_filters(
			array(
				'from' => '2026-02-31',
				'to'   => 'yesterday',
			)
		);
		$this->assertSame( array( '', '' ), array( $invalid['from'], $invalid['to'] ) );
	}

	/**
	 * @dataProvider statuses
	 *
	 * @param string $status Stored status.
	 * @param string $group  Expected group.
	 */
	public function test_status_group( string $status, string $group ) {
		$this->assertSame( $group, FLW_Payments_Controller::status_group( $status ) );
	}

	/**
	 * Stored statuses and their groups.
	 *
	 * @return array
	 */
	public static function statuses(): array {
		return array(
			array( 'successful', 'successful' ),
			array( 'failed', 'failed' ),
			array( 'cancelled', 'cancelled' ),
			array( 'paid less (5000 NGN)', 'review' ),
			array( 'paid more (5000 NGN)', 'review' ),
			array( 'currency diff', 'review' ),
			array( 'pending', 'pending' ),
			array( '', 'pending' ),
		);
	}

	public function test_item_links_the_page_the_payment_came_from() {
		$this->login_admin();
		$page = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Workshop tickets',
			)
		);
		$this->payment( 'successful', 'NGN', 0, array( 'source' => $page ) );

		$item = $this->list()['items'][0];

		$this->assertSame( $page, $item['source']['id'] );
		$this->assertSame( 'Workshop tickets', $item['source']['title'] );
	}

	public function test_delete_removes_only_payment_records() {
		$this->login_admin();
		$id   = $this->payment( 'failed' );
		$post = self::factory()->post->create();

		$response = rest_do_request( new WP_REST_Request( 'DELETE', '/flutterwave/v1/payments/' . $id ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $id ) );

		$response = rest_do_request( new WP_REST_Request( 'DELETE', '/flutterwave/v1/payments/' . $post ) );
		$this->assertSame( 404, $response->get_status() );
		$this->assertNotNull( get_post( $post ) );
	}

	public function test_verify_updates_a_pending_payment() {
		$this->login_admin();
		$id = $this->payment( 'pending' );
		$this->mock_flutterwave(
			array(
				'status' => 'success',
				'data'   => $this->transaction( $id ),
			)
		);

		$response = rest_do_request( new WP_REST_Request( 'POST', '/flutterwave/v1/payments/' . $id . '/verify' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'successful', $response->get_data()['statusGroup'] );
	}

	public function test_verify_skips_the_api_for_successful_payments() {
		$this->login_admin();
		$id = $this->payment( 'successful' );
		$this->mock_flutterwave( array( 'status' => 'error' ), 500 );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/flutterwave/v1/payments/' . $id . '/verify' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $this->http_requests );
	}

	public function test_csv_neutralises_formulas() {
		$this->assertSame( "'=HYPERLINK(\"x\")", FLW_Payments_Controller::csv_cell( '=HYPERLINK("x")' ) );
		$this->assertSame( "'+1", FLW_Payments_Controller::csv_cell( '+1' ) );
		$this->assertSame( "'-1", FLW_Payments_Controller::csv_cell( '-1' ) );
		$this->assertSame( "'@SUM(A1)", FLW_Payments_Controller::csv_cell( '@SUM(A1)' ) );
		$this->assertSame( 'Ada', FLW_Payments_Controller::csv_cell( 'Ada' ) );
		$this->assertSame( '', FLW_Payments_Controller::csv_cell( '' ) );
	}

	public function test_write_csv_outputs_filtered_rows() {
		$this->payment(
			'successful',
			'NGN',
			0,
			array(
				'fullname' => '=cmd|calc',
				'customer' => 'ada@example.com',
			)
		);
		$this->payment( 'failed', 'NGN' );

		$out  = fopen( 'php://memory', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$rows = FLW_Payments_Controller::write_csv(
			$out,
			FLW_Payments_Controller::normalize_filters( array( 'status' => 'successful' ) )
		);
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$this->assertSame( 1, $rows );
		$this->assertStringStartsWith( 'Date,Reference,Customer,Email,Amount', $csv );
		$this->assertStringContainsString( "'=cmd|calc", $csv );
		$this->assertStringContainsString( '5000.00,NGN,successful', $csv );
	}

	public function test_export_url_carries_a_nonce() {
		$this->login_admin();
		wp_parse_str( (string) wp_parse_url( FLW_Payments_Controller::export_url(), PHP_URL_QUERY ), $query );

		$this->assertSame( FLW_Payments_Controller::EXPORT_ACTION, $query['action'] );
		$this->assertSame( 1, wp_verify_nonce( $query['_wpnonce'], FLW_Payments_Controller::EXPORT_ACTION ) );
	}
}
