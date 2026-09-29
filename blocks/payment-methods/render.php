<?php
/**
 * Server render for flutterwave/payment-methods: the methods enabled in settings.
 *
 * @package Flutterwave_Payments
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$icons = array(
	'card'   => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="1.5" y="3.5" width="13" height="9" rx="1.5" fill="none" stroke="currentColor"/><path d="M1.5 6.5h13" stroke="currentColor"/></svg>',
	'bank'   => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 1.5 14 5H2l6-3.5ZM3.5 6.5v5m3-5v5m3-5v5m3-5v5M2 13.5h12" fill="none" stroke="currentColor" stroke-linecap="round"/></svg>',
	'phone'  => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="4.5" y="1.5" width="7" height="13" rx="1.5" fill="none" stroke="currentColor"/><path d="M7 12.5h2" stroke="currentColor" stroke-linecap="round"/></svg>',
	'wallet' => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="1.5" y="3.5" width="13" height="10" rx="1.5" fill="none" stroke="currentColor"/><path d="M10.5 8.5h4" stroke="currentColor"/></svg>',
	'coin'   => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor"/><path d="M6 6h4M8 6v5" stroke="currentColor" stroke-linecap="round"/></svg>',
	'code'   => '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="2" y="2" width="5" height="5" fill="none" stroke="currentColor"/><rect x="9" y="2" width="5" height="5" fill="none" stroke="currentColor"/><rect x="2" y="9" width="5" height="5" fill="none" stroke="currentColor"/><path d="M9.5 9.5h4v4" fill="none" stroke="currentColor"/></svg>',
);

$methods = array(
	'card'         => array( __( 'Cards', 'rave-payment-forms' ), 'card' ),
	'banktransfer' => array( __( 'Bank transfer', 'rave-payment-forms' ), 'bank' ),
	'mobilemoney'  => array( __( 'Mobile money', 'rave-payment-forms' ), 'phone' ),
	'applepay'     => array( __( 'Apple Pay', 'rave-payment-forms' ), 'wallet' ),
	'googlepay'    => array( __( 'Google Pay', 'rave-payment-forms' ), 'wallet' ),
	'opay'         => array( __( 'OPay', 'rave-payment-forms' ), 'wallet' ),
	'stablecoin'   => array( __( 'Stablecoin', 'rave-payment-forms' ), 'coin' ),
	'ussd'         => array( __( 'USSD', 'rave-payment-forms' ), 'phone' ),
	'qr'           => array( __( 'QR', 'rave-payment-forms' ), 'code' ),
	'nqr'          => array( __( 'NQR', 'rave-payment-forms' ), 'code' ),
	'credit'       => array( __( 'Credit', 'rave-payment-forms' ), 'card' ),
	'barter'       => array( __( 'Barter', 'rave-payment-forms' ), 'wallet' ),
);

$options = FLW_Settings::payment_options();
$enabled = array_merge( FLW_Settings::methods_from_option( $options ), FLW_Settings::advanced_from_option( $options ) );
$label   = trim( (string) ( $attributes['label'] ?? '' ) );
?>
<div <?php echo FLW_Blocks::wrapper( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
	<div class="flw-payment-methods">
		<?php if ( '' !== $label ) : ?>
			<p class="flw-payment-methods__label"><?php echo esc_html( $label ); ?></p>
		<?php endif; ?>
		<ul class="flw-payment-methods__list">
			<?php foreach ( $enabled as $method ) : ?>
				<?php
				if ( ! isset( $methods[ $method ] ) ) {
					continue;
				}
				?>
				<li class="flw-payment-methods__item"><?php echo $icons[ $methods[ $method ][1] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><?php echo esc_html( $methods[ $method ][0] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! empty( $attributes['showSecured'] ) ) : ?>
			<p class="flw-secured">
				<svg width="12" height="12" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V6H4a1.5 1.5 0 0 0-1.5 1.5v6A1.5 1.5 0 0 0 4 15h8a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 12 6h-.5V4.5A3.5 3.5 0 0 0 8 1Zm2 5H6V4.5a2 2 0 1 1 4 0V6Z"/></svg>
				<?php
				printf(
					/* translators: %s: Flutterwave, in bold. */
					esc_html__( 'Payments secured by %s', 'rave-payment-forms' ),
					'<strong>Flutterwave</strong>'
				);
				?>
			</p>
		<?php endif; ?>
	</div>
</div>
