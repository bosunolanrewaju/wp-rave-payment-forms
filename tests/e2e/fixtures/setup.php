<?php
/**
 * Seeds the wp-env development site for the end-to-end tests. Run with `wp eval-file`.
 *
 * @package Flutterwave\WordPress\Tests
 */

// Guided tips would cover the screens the other tests click through; the tour spec turns them on itself.
$flw_admin = get_user_by( 'login', 'admin' );
if ( $flw_admin ) {
	update_user_meta(
		$flw_admin->ID,
		'flw_tour',
		array(
			'enabled' => false,
			'seen'    => array(),
		)
	);
}

update_option(
	'flw_rave_options',
	array(
		'public_key'           => 'FLWPUBK_TEST-e2e',
		'secret_key'           => 'FLWSECK_TEST-e2e',
		'secret_hash'          => 'e2e-webhook-secret',
		'success_redirect_url' => home_url( '/?flw=success' ),
		'failed_redirect_url'  => home_url( '/?flw=failed' ),
		'pending_redirect_url' => home_url( '/?flw=pending' ),
		'currency'             => 'NGN',
		'country'              => 'NG',
	)
);

$flw_pages = array(
	'fixed'    => '[flw-pay-form amount="5000" currency="NGN"]',
	'open'     => '[flw-pay-form]',
	'donation' => '[flw-donation-form]',
	'xss'      => '[flw-pay-form amount="1 onmouseover=window.flwPwned=1//"]',
	'blocks'   => '<!-- wp:flutterwave/payment-button {"amount":5000,"currency":"NGN","useUserEmail":false,"accentColor":"#2a3362","accentTextColor":"#ffffff"} /-->'
		. '<!-- wp:flutterwave/donation-form {"currency":"NGN","amounts":"1000, 5000","showFrequency":false} /-->'
		. '<!-- wp:flutterwave/payment-methods /-->',
);

$flw_urls = array();

foreach ( $flw_pages as $flw_slug => $flw_content ) {
	$flw_slug = 'flw-e2e-' . $flw_slug;
	$flw_page = get_page_by_path( $flw_slug );
	$flw_id   = $flw_page ? $flw_page->ID : 0;

	$flw_id = wp_insert_post(
		array(
			'ID'           => $flw_id,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $flw_slug,
			'post_title'   => $flw_slug,
			'post_content' => $flw_content,
		)
	);

	$flw_urls[ $flw_slug ] = wp_make_link_relative( get_permalink( $flw_id ) );
}

echo wp_json_encode( $flw_urls );
