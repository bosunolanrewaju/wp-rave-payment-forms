<?php
/**
 * Server render for flutterwave/donation-form.
 *
 * @package Flutterwave_Payments
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$currency = FLW_Blocks::currency( $attributes['currency'] ?? '' );

$shortcode_atts = array(
	'heading'        => sanitize_text_field( (string) ( $attributes['heading'] ?? '' ) ),
	'message'        => sanitize_textarea_field( (string) ( $attributes['message'] ?? '' ) ),
	'amounts'        => sanitize_text_field( (string) ( $attributes['amounts'] ?? '' ) ),
	'show_frequency' => empty( $attributes['showFrequency'] ) ? '0' : '1',
);

if ( '' !== $currency ) {
	$shortcode_atts['currency'] = $currency;
}

printf(
	'<div %1$s>%2$s</div>',
	FLW_Blocks::wrapper( $attributes ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes.
	FLW_Shortcodes::donation_page_shortcode( $shortcode_atts, '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the form view.
);
