<?php
/**
 * Server render for flutterwave/pricing-card.
 *
 * @package Flutterwave_Payments
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$amount   = FLW_Blocks::amount( $attributes['amount'] ?? 0 );
$currency = FLW_Blocks::currency( $attributes['currency'] ?? '' );
$currency = '' !== $currency ? $currency : FLW_Blocks::default_currency();
$features = array_filter(
	array_map(
		static function ( $feature ) {
			return is_scalar( $feature ) ? trim( (string) $feature ) : '';
		},
		(array) ( $attributes['features'] ?? array() )
	)
);
$label    = trim( (string) ( $attributes['buttonText'] ?? '' ) );
$checkout = array(
	'currency'               => $currency,
	'exclude'                => 'fullname,phone',
	'layout'                 => 'compact',
	'width'                  => 'full',
	'show_secured'           => '1',
	'use_current_user_email' => 'yes',
);

if ( $amount > 0 ) {
	$checkout['amount'] = (string) $amount;
}

$classes = 'flw-pricing-card' . ( empty( $attributes['highlighted'] ) ? '' : ' is-highlighted' );
$check   = '<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="m3.5 8.4 3 3 6-6.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<div <?php echo FLW_Blocks::wrapper( $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes. ?>>
	<div class="<?php echo esc_attr( $classes ); ?>">
		<?php if ( '' !== (string) ( $attributes['badge'] ?? '' ) ) : ?>
			<span class="flw-pricing-card__badge"><?php echo esc_html( $attributes['badge'] ); ?></span>
		<?php endif; ?>
		<h3 class="flw-pricing-card__name"><?php echo esc_html( (string) ( $attributes['planName'] ?? '' ) ); ?></h3>
		<p class="flw-pricing-card__price">
			<span class="flw-pricing-card__amount"><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount, floor( $amount ) === $amount ? 0 : 2 ) ); ?></span>
			<?php if ( '' !== (string) ( $attributes['period'] ?? '' ) ) : ?>
				<span class="flw-pricing-card__period"><?php echo esc_html( $attributes['period'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( '' !== (string) ( $attributes['description'] ?? '' ) ) : ?>
			<p class="flw-pricing-card__description"><?php echo esc_html( $attributes['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( $features ) : ?>
			<ul class="flw-pricing-card__features">
				<?php foreach ( $features as $feature ) : ?>
					<li><?php echo $check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?><span><?php echo esc_html( $feature ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php echo FLW_Shortcodes::pay_button_shortcode( $checkout, '' !== $label ? $label : __( 'Get started', 'rave-payment-forms' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the form view. ?>
	</div>
</div>
