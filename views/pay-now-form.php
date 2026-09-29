<?php
/**
 * Flutterwave Payments Form Page
 *
 * @package Flutterwave\Payments\Views
 * @version 1.0.6
 */

defined( 'ABSPATH' ) || exit;

$form_id = Flutterwave_Payments::gen_rand_string();

if ( ! empty( $atts['custom_currency'] ) ) {
	if ( preg_match( '/^[a-z\d]* [a-z\d]*$/', $atts['custom_currency'] ) ) {
		$currencies = explode( ', ', $atts['custom_currency'] );
	} else {
		$currencies = explode( ',', $atts['custom_currency'] );
	}
}

?>

<div class="<?php echo esc_attr( implode( ' ', $form_classes ) ); ?>">
	<?php if ( '' !== (string) $atts['heading'] || '' !== (string) $atts['description'] ) : ?>
		<div class="flw-form-header">
			<?php if ( '' !== (string) $atts['heading'] ) : ?>
				<h2 class="flw-form-title"><?php echo esc_html( $atts['heading'] ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== (string) $atts['description'] ) : ?>
				<p class="flw-form-description"><?php echo esc_html( $atts['description'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<p class="flw-error" role="alert" aria-live="polite"></p>
	<form id="<?php echo esc_attr( $form_id ); ?>" class="flw-simple-pay-now-form" <?php echo $data_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each key and value is escaped when built. ?>>
		<div id="notice"></div>
		<?php echo wp_kses( $input_fields_html, $allowed_html_elements ); ?>
		<?php wp_nonce_field( 'flw-rave-pay-nonce', 'flw_sec_code' ); ?>
		<?php echo isset( $signed_config ) ? $signed_config : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values are escaped when built. ?>
		<button value="submit" id="flw-pay-now-button" class='flw-pay-now-button'><?php echo esc_html( $btn_text ); ?></button>
	</form>
	<?php if ( '0' !== (string) $atts['show_secured'] ) : ?>
	<p class="flw-secured">
		<svg width="12" height="12" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 1a3.5 3.5 0 0 0-3.5 3.5V6H4a1.5 1.5 0 0 0-1.5 1.5v6A1.5 1.5 0 0 0 4 15h8a1.5 1.5 0 0 0 1.5-1.5v-6A1.5 1.5 0 0 0 12 6h-.5V4.5A3.5 3.5 0 0 0 8 1Zm2 5H6V4.5a2 2 0 1 1 4 0V6Z"/></svg>
		<?php
		printf(
			/* translators: %s: Flutterwave, in bold. */
			esc_html__( 'Secured by %s', 'rave-payment-forms' ),
			'<strong>Flutterwave</strong>'
		);
		?>
	</p>
	<?php endif; ?>
</div>
<div id="flutterwave-overlay" style="display:none">
	<div id="flw-overlay-text">
		<span class="flw-overlay-spinner" aria-hidden="true"></span>
		<?php esc_html_e( 'Taking you to Flutterwave to pay. Please don’t close this page.', 'rave-payment-forms' ); ?>
	</div>
</div>
