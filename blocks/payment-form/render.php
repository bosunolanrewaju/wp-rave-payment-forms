<?php
/**
 * Server render for flutterwave/payment-form.
 *
 * @package Flutterwave_Payments
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$amount      = FLW_Blocks::amount( $attributes['amount'] ?? 0 );
$currency    = FLW_Blocks::currency( $attributes['currency'] ?? '' );
$name_fields = $attributes['collectName'] ?? 'full';
$exclude     = array();

if ( empty( $attributes['collectPhone'] ) ) {
	$exclude[] = 'phone';
}

if ( 'none' === $name_fields ) {
	$exclude[] = 'fullname';
}

$shortcode_atts = array(
	'heading'      => sanitize_text_field( (string) ( $attributes['heading'] ?? '' ) ),
	'description'  => sanitize_textarea_field( (string) ( $attributes['description'] ?? '' ) ),
	'split_name'   => 'split' === $name_fields ? '1' : '0',
	'show_secured' => empty( $attributes['showSecured'] ) ? '0' : '1',
);

if ( $exclude ) {
	$shortcode_atts['exclude'] = implode( ',', $exclude );
}

if ( $amount > 0 ) {
	$shortcode_atts['amount'] = (string) $amount;
}

if ( '' !== $currency ) {
	$shortcode_atts['currency'] = $currency;
}

printf(
	'<div %1$s>%2$s</div>',
	FLW_Blocks::wrapper( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.
	FLW_Shortcodes::pay_button_shortcode( $shortcode_atts, trim( (string) ( $attributes['buttonText'] ?? '' ) ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the form view.
);
