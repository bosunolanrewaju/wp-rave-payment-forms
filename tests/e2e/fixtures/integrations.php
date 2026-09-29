<?php
/**
 * Seeds Easy Digital Downloads and GiveWP for the integration tests and switches
 * on the Flutterwave mock. Run with `wp eval-file`; pass "off" to switch it off.
 *
 * @package Flutterwave\WordPress\Tests
 */

use Give\Campaigns\Models\Campaign;
use Give\Campaigns\ValueObjects\CampaignGoalType;
use Give\Campaigns\ValueObjects\CampaignStatus;
use Give\Campaigns\ValueObjects\CampaignType;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GiveWP models.

if ( 'off' === ( $args[0] ?? '' ) ) {
	delete_option( 'flw_e2e_mock_api' );
	echo wp_json_encode( array( 'mock' => false ) );
	return;
}

if ( ! FLW_EDD_Gateway::is_host_active() || ! FLW_GiveWP::is_host_active() ) {
	echo wp_json_encode( array( 'skip' => 'Easy Digital Downloads and GiveWP must be active (see .wp-env.json).' ) );
	return;
}

update_option( 'flw_e2e_mock_api', 1 );

// Both integrations on, and Flutterwave the only way to pay so the forms go straight to it.
update_option(
	FLW_Integrations::OPTION_KEY,
	array(
		'easy-digital-downloads' => true,
		'givewp'                 => true,
	)
);
edd_update_option( 'gateways', array( FLW_EDD_Gateway::ID => 1 ) );
edd_update_option( 'default_gateway', FLW_EDD_Gateway::ID );
edd_update_option( 'currency', 'USD' );
give_update_option( 'gateways', array( FLW_GiveWP::ID => '1' ) );
give_update_option( 'gateways_v3', array( FLW_GiveWP::ID => '1' ) );
give_update_option( 'default_gateway', FLW_GiveWP::ID );
give_update_option( 'currency', 'USD' );

$flw_download = get_page_by_path( 'flw-e2e-ebook', OBJECT, 'download' );
$flw_download = wp_insert_post(
	array(
		'ID'          => $flw_download ? $flw_download->ID : 0,
		'post_type'   => 'download',
		'post_status' => 'publish',
		'post_name'   => 'flw-e2e-ebook',
		'post_title'  => 'E2E Ebook',
	)
);
update_post_meta( $flw_download, 'edd_price', '25.00' );

$flw_campaign = get_option( 'flw_e2e_campaign' );
$flw_campaign = $flw_campaign ? Campaign::find( (int) $flw_campaign ) : null;

if ( ! $flw_campaign ) {
	$flw_campaign = Campaign::create(
		array(
			'type'             => CampaignType::CORE(),
			'title'            => 'E2E campaign',
			'shortDescription' => '',
			'longDescription'  => '',
			'logo'             => '',
			'image'            => '',
			'primaryColor'     => '#ff9b00',
			'secondaryColor'   => '#101828',
			'goal'             => 100000,
			'goalType'         => CampaignGoalType::AMOUNT(),
			'status'           => CampaignStatus::ACTIVE(),
		)
	);
	$flw_campaign = Campaign::find( $flw_campaign->id );
	update_option( 'flw_e2e_campaign', $flw_campaign->id );
}

$flw_donate = get_page_by_path( 'flw-e2e-donate' );
$flw_donate = wp_insert_post(
	array(
		'ID'           => $flw_donate ? $flw_donate->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'flw-e2e-donate',
		'post_title'   => 'flw-e2e-donate',
		'post_content' => '[give_form id="' . (int) $flw_campaign->defaultFormId . '"]',
	)
);

echo wp_json_encode(
	array(
		'download' => wp_make_link_relative( get_permalink( $flw_download ) ),
		'checkout' => wp_make_link_relative( edd_get_checkout_uri() ),
		'donate'   => wp_make_link_relative( get_permalink( $flw_donate ) ),
	)
);
