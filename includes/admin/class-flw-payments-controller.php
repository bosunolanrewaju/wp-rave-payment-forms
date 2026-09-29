<?php
/**
 * REST endpoints and CSV export behind the Transactions screen.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists, re-verifies, deletes and exports payment records.
 */
final class FLW_Payments_Controller {

	/**
	 * Status filters offered on the Transactions screen.
	 *
	 * @var string[]
	 */
	const STATUS_GROUPS = array( 'successful', 'pending', 'failed', 'cancelled', 'review' );

	/**
	 * Longest date range a filter or export may cover, in days.
	 */
	const MAX_RANGE_DAYS = 366;

	/**
	 * Admin-post action for CSV export.
	 */
	const EXPORT_ACTION = 'flw_export_payments';

	/**
	 * Meta key recording the page a payment was made from.
	 */
	const SOURCE_META = '_flw_rave_payment_source';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/payments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_payments' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/payments/(?P<id>\d+)/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'verify_payment' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/payments/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_payment' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Only administrators see payments.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	// Filters.

	/**
	 * Clean filter input: valid dates within the maximum range, known statuses and
	 * three-letter currency codes.
	 *
	 * @param array $raw Raw filter values.
	 *
	 * @return array{from: string, to: string, status: string[], currency: string[]}
	 */
	public static function normalize_filters( array $raw ): array {
		$from = self::date_or_empty( $raw['from'] ?? '' );
		$to   = self::date_or_empty( $raw['to'] ?? '' );

		if ( '' !== $from && '' !== $to && $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}

		// Keep the end date and pull the start in, as the screen does.
		if ( '' !== $to ) {
			$earliest = gmdate( 'Y-m-d', strtotime( $to . ' -' . self::MAX_RANGE_DAYS . ' days' ) );

			if ( '' === $from || $from < $earliest ) {
				$from = $earliest;
			}
		}

		$status = array_values( array_intersect( self::STATUS_GROUPS, self::list_param( $raw['status'] ?? array() ) ) );

		$currency = array_values(
			array_unique(
				array_filter(
					array_map( 'strtoupper', self::list_param( $raw['currency'] ?? array() ) ),
					static function ( string $code ): bool {
						return 1 === preg_match( '/^[A-Z]{3}$/', $code );
					}
				)
			)
		);

		return compact( 'from', 'to', 'status', 'currency' );
	}

	/**
	 * WP_Query arguments for a set of normalised filters.
	 *
	 * @param array $filters  Output of normalize_filters().
	 * @param int   $page     Page number.
	 * @param int   $per_page Items per page.
	 *
	 * @return array
	 */
	public static function query_args( array $filters, int $page = 1, int $per_page = 20 ): array {
		$args = array(
			'post_type'        => FLW_Payment_Record::POST_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => max( 1, min( 100, $per_page ) ),
			'paged'            => max( 1, $page ),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => true,
		);

		if ( '' !== $filters['from'] || '' !== $filters['to'] ) {
			$range = array(
				'inclusive' => true,
				'column'    => 'post_date',
			);

			if ( '' !== $filters['from'] ) {
				$range['after'] = $filters['from'] . ' 00:00:00';
			}

			if ( '' !== $filters['to'] ) {
				$range['before'] = $filters['to'] . ' 23:59:59';
			}

			$args['date_query'] = array( $range );
		}

		$meta = array();

		if ( $filters['status'] ) {
			$statuses = array( 'relation' => 'OR' );

			foreach ( $filters['status'] as $group ) {
				foreach ( self::status_clauses( $group ) as $clause ) {
					$statuses[] = $clause;
				}
			}

			$meta[] = $statuses;
		}

		if ( $filters['currency'] ) {
			$meta[] = array(
				'key'     => '_flw_rave_payment_currency',
				'value'   => $filters['currency'],
				'compare' => 'IN',
			);
		}

		if ( $meta ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		return $args;
	}

	/**
	 * Meta query clauses matching one status group.
	 *
	 * @param string $group Status group.
	 *
	 * @return array[]
	 */
	private static function status_clauses( string $group ): array {
		$key = '_flw_rave_payment_status';

		switch ( $group ) {
			case 'successful':
				return array( array( 'key' => $key, 'value' => 'successful' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			case 'pending':
				return array(
					array(
						'key'     => $key,
						'value'   => array( 'pending', 'processing' ),
						'compare' => 'IN',
					),
				);
			case 'failed':
				return array( array( 'key' => $key, 'value' => 'failed' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			case 'cancelled':
				return array( array( 'key' => $key, 'value' => 'cancelled' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			case 'review':
				return array(
					array(
						'key'     => $key,
						'value'   => 'paid less',
						'compare' => 'LIKE',
					),
					array(
						'key'     => $key,
						'value'   => 'paid more',
						'compare' => 'LIKE',
					),
					array(
						'key'   => $key,
						'value' => 'currency diff',
					),
				);
		}//end switch

		return array();
	}

	/**
	 * The status group a stored status belongs to.
	 *
	 * @param string $status Stored status.
	 *
	 * @return string
	 */
	public static function status_group( string $status ): string {
		if ( in_array( $status, array( 'successful', 'failed', 'cancelled' ), true ) ) {
			return $status;
		}

		if ( 0 === strpos( $status, 'paid less' ) || 0 === strpos( $status, 'paid more' ) || 'currency diff' === $status ) {
			return 'review';
		}

		return 'pending';
	}

	/**
	 * A Y-m-d date, or '' when the value is not one.
	 *
	 * @param mixed $value Candidate date.
	 *
	 * @return string
	 */
	private static function date_or_empty( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );

		return checkdate( $month, $day, $year ) ? $value : '';
	}

	/**
	 * A list parameter given as an array or a comma separated string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string[]
	 */
	private static function list_param( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		return is_array( $value ) ? array_values( array_filter( array_map( 'trim', array_map( 'strval', $value ) ) ) ) : array();
	}

	/**
	 * Filters from a request's parameters.
	 *
	 * @param array $params Request parameters.
	 *
	 * @return array
	 */
	private static function filters_from( array $params ): array {
		return self::normalize_filters(
			array(
				'from'     => $params['from'] ?? '',
				'to'       => $params['to'] ?? '',
				'status'   => $params['status'] ?? array(),
				'currency' => $params['currency'] ?? array(),
			)
		);
	}

	// Responses.

	/**
	 * A payment record shaped for the Transactions screen.
	 *
	 * @param WP_Post $post Payment record.
	 *
	 * @return array
	 */
	public static function to_item( WP_Post $post ): array {
		$meta   = static function ( string $key ) use ( $post ): string {
			return (string) get_post_meta( $post->ID, '_flw_rave_payment_' . $key, true );
		};
		$status = $meta( 'status' );
		$source = (int) get_post_meta( $post->ID, self::SOURCE_META, true );
		$page   = $source > 0 ? get_post( $source ) : null;
		$time   = get_post_timestamp( $post );

		return array(
			'id'            => $post->ID,
			'reference'     => '' !== $meta( 'tx_ref' ) ? $meta( 'tx_ref' ) : $post->post_title,
			'customerName'  => $meta( 'fullname' ),
			'customerEmail' => $meta( 'customer' ),
			'amount'        => (float) $meta( 'amount' ),
			'currency'      => $meta( 'currency' ),
			'status'        => $status,
			'statusGroup'   => self::status_group( $status ),
			'flutterwaveId' => $meta( 'id' ),
			'date'          => $time ? gmdate( 'c', $time ) : '',
			'dateDisplay'   => $time ? wp_date( get_option( 'date_format' ), $time ) : '',
			'timeDisplay'   => $time ? wp_date( get_option( 'time_format' ), $time ) : '',
			'source'        => $page instanceof WP_Post ? array(
				'id'    => $page->ID,
				'title' => get_the_title( $page ),
				'url'   => (string) get_permalink( $page ),
			) : null,
		);
	}

	/**
	 * Currencies that appear on payment records, for the currency filter.
	 *
	 * @return string[]
	 */
	public static function recorded_currencies(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small admin-only aggregate.
		$codes = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value <> '' ORDER BY pm.meta_value",
				FLW_Payment_Record::POST_TYPE,
				'_flw_rave_payment_currency'
			)
		);

		return array_values(
			array_filter(
				array_map( 'strval', (array) $codes ),
				static function ( string $code ): bool {
					return 1 === preg_match( '/^[A-Z]{3}$/', $code );
				}
			)
		);
	}

	/**
	 * GET /payments.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_payments( WP_REST_Request $request ): WP_REST_Response {
		$filters = self::filters_from( $request->get_params() );
		$query   = new WP_Query( self::query_args( $filters, (int) $request->get_param( 'page' ), (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		return rest_ensure_response(
			array(
				'items'      => array_map( array( __CLASS__, 'to_item' ), $query->posts ),
				'total'      => (int) $query->found_posts,
				'pages'      => (int) $query->max_num_pages,
				'filters'    => $filters,
				'currencies' => self::recorded_currencies(),
			)
		);
	}

	/**
	 * POST /payments/{id}/verify: ask Flutterwave for the latest status.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify_payment( WP_REST_Request $request ) {
		$record = FLW_Payment_Record::get( $request->get_param( 'id' ) );

		if ( null === $record ) {
			return new WP_Error( 'flw-not-found', __( 'Transaction not found.', 'rave-payment-forms' ), array( 'status' => 404 ) );
		}

		if ( 'successful' !== FLW_Payment_Record::get_status( $record->ID ) ) {
			$transaction = FLW_Payment_Record::fetch_verified_by_reference( (string) get_post_meta( $record->ID, '_flw_rave_payment_tx_ref', true ) );

			if ( is_wp_error( $transaction ) ) {
				return new WP_Error( 'flw-unverified', __( 'Flutterwave has no record of this payment yet.', 'rave-payment-forms' ), array( 'status' => 502 ) );
			}

			$result = FLW_Payment_Record::apply_verified_transaction( $transaction, $record->ID );

			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'flw-unverified', $result->get_error_message(), array( 'status' => 409 ) );
			}
		}

		return rest_ensure_response( self::to_item( get_post( $record->ID ) ) );
	}

	/**
	 * DELETE /payments/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_payment( WP_REST_Request $request ) {
		$record = FLW_Payment_Record::get( $request->get_param( 'id' ) );

		if ( null === $record ) {
			return new WP_Error( 'flw-not-found', __( 'Transaction not found.', 'rave-payment-forms' ), array( 'status' => 404 ) );
		}

		wp_delete_post( $record->ID, true );

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// Export.

	/**
	 * The URL that downloads a CSV for the given filters.
	 *
	 * @return string Base URL; the screen adds the filter parameters.
	 */
	public static function export_url(): string {
		return add_query_arg(
			array(
				'action'   => self::EXPORT_ACTION,
				'_wpnonce' => wp_create_nonce( self::EXPORT_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Stream matching payments as CSV. Runs on admin-post.php.
	 *
	 * @return void
	 */
	public static function export(): void {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export transactions.', 'rave-payment-forms' ), 403 );
		}

		check_admin_referer( self::EXPORT_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$filters = self::filters_from( wp_unslash( $_GET ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . self::export_filename( $filters ) . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		self::write_csv( $out, $filters );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Write the CSV rows for the filters to a stream.
	 *
	 * @param resource $out     Output stream.
	 * @param array    $filters Normalised filters.
	 *
	 * @return int Rows written, excluding the header.
	 */
	public static function write_csv( $out, array $filters ): int {
		fputcsv( $out, array( 'Date', 'Reference', 'Customer', 'Email', 'Amount', 'Currency', 'Status', 'Flutterwave ID', 'Page' ) );

		$rows = 0;
		$page = 1;

		do {
			$query = new WP_Query( self::query_args( $filters, $page, 100 ) );

			foreach ( $query->posts as $post ) {
				$item = self::to_item( $post );

				fputcsv(
					$out,
					array_map(
						array( __CLASS__, 'csv_cell' ),
						array(
							wp_date( 'Y-m-d H:i:s', (int) get_post_timestamp( $post ) ),
							$item['reference'],
							$item['customerName'],
							$item['customerEmail'],
							number_format( $item['amount'], 2, '.', '' ),
							$item['currency'],
							$item['status'],
							$item['flutterwaveId'],
							$item['source'] ? $item['source']['title'] : '',
						)
					)
				);
				++$rows;
			}//end foreach

			++$page;
		} while ( $page <= (int) $query->max_num_pages );

		return $rows;
	}

	/**
	 * Neutralise a value spreadsheet software would run as a formula.
	 *
	 * @param mixed $value Cell value.
	 *
	 * @return string
	 */
	public static function csv_cell( $value ): string {
		$value = (string) $value;

		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	/**
	 * Download file name, e.g. flutterwave-transactions-2026-08-25-to-2026-09-24.csv.
	 *
	 * @param array $filters Normalised filters.
	 *
	 * @return string
	 */
	private static function export_filename( array $filters ): string {
		$range = '' !== $filters['from'] ? '-' . $filters['from'] . '-to-' . ( '' !== $filters['to'] ? $filters['to'] : gmdate( 'Y-m-d' ) ) : '';

		return 'flutterwave-transactions' . $range . '.csv';
	}
}
