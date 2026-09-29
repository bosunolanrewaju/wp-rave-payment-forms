<?php
/**
 * Seeds payment records for the Transactions and Payment Forms tests, replacing
 * any from an earlier run. Run with `wp eval-file`.
 *
 * @package Flutterwave\WordPress\Tests
 */

$stale = get_posts(
	array(
		'post_type'   => array( 'payment_list', 'page' ),
		'post_status' => 'any',
		'meta_key'    => '_flw_e2e', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
$stale = array_merge(
	$stale,
	wp_list_pluck(
		get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'title'       => 'E2E builder page',
				'numberposts' => -1,
			)
		),
		'ID'
	)
);

foreach ( $stale as $stale_id ) {
	wp_delete_post( $stale_id, true );
}

$source = get_page_by_path( 'flw-e2e-fixed' );
$rows   = array(
	array( 'Ada Paid', 'successful', 'NGN', 5000 ),
	array( 'Bola Failed', 'failed', 'NGN', 5000 ),
	array( 'Chen Pending', 'pending', 'USD', 20 ),
);

foreach ( $rows as $index => $row ) {
	$record_id = wp_insert_post(
		array(
			'post_type'   => 'payment_list',
			'post_status' => 'publish',
			'post_title'  => 'FLW-E2E-' . $index,
			'post_date'   => gmdate( 'Y-m-d H:i:s', time() - $index * HOUR_IN_SECONDS ),
		)
	);

	$meta = array(
		'tx_ref'   => 'FLW-E2E-' . $index,
		'fullname' => $row[0],
		'customer' => strtolower( strtok( $row[0], ' ' ) ) . '@example.com',
		'status'   => $row[1],
		'currency' => $row[2],
		'amount'   => $row[3],
		'source'   => $source ? $source->ID : 0,
	);

	foreach ( $meta as $key => $value ) {
		update_post_meta( $record_id, '_flw_rave_payment_' . $key, $value );
	}

	update_post_meta( $record_id, '_flw_e2e', 1 );
}

echo 'ok';
