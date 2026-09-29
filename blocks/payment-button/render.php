<?php
/**
 * Server render for flutterwave/payment-button.
 *
 * @package Flutterwave_Payments
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$amount   = FLW_Blocks::amount( $attributes['amount'] ?? 0 );
$currency = FLW_Blocks::currency( $attributes['currency'] ?? '' );
$currency = '' !== $currency ? $currency : FLW_Blocks::default_currency();
$exclude  = array( 'phone' );

if ( empty( $attributes['collectName'] ) ) {
	$exclude[] = 'fullname';
}

$shortcode_atts = array(
	'currency'               => $currency,
	'exclude'                => implode( ',', $exclude ),
	'layout'                 => 'compact',
	'width'                  => 'full' === ( $attributes['width'] ?? '' ) ? 'full' : 'auto',
	'show_secured'           => empty( $attributes['showSecured'] ) ? '0' : '1',
	'use_current_user_email' => empty( $attributes['useUserEmail'] ) ? 'no' : 'yes',
);

if ( $amount > 0 ) {
	$shortcode_atts['amount'] = (string) $amount;
}

$label = trim( (string) ( $attributes['buttonText'] ?? '' ) );
$label = '' !== $label ? $label : __( 'Pay {amount}', 'rave-payment-forms' );

printf(
	'<div %1$s>%2$s</div>',
	FLW_Blocks::wrapper( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.
	FLW_Shortcodes::pay_button_shortcode( $shortcode_atts, $label ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the form view.
);
