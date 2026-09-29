<?php
/**
 * Flutterwave Payments Settings Page
 *
 * @package Flutterwave\Payments\Views
 * @version 1.0.6
 */

defined( 'ABSPATH' ) || exit;
$admin_settings = FLW_Admin_Settings::get_instance();
$form_id        = Flutterwave_Payments::gen_rand_string();

if ( ! empty( $atts['custom_currency'] ) ) {
	if ( preg_match( '/^[a-z\d]* [a-z\d]*$/', $atts['custom_currency'] ) ) {
		$currencies = explode( ', ', $atts['custom_currency'] );
	} else {
		$currencies = explode( ',', $atts['custom_currency'] );
	}
}

$donation_heading = '' !== (string) $atts['heading'] ? $atts['heading'] : $admin_settings->get_option_value( 'donation_title' );
$donation_details = '' !== (string) $atts['message'] ? $atts['message'] : $admin_settings->get_option_value( 'donation_desc' );
?>

<div class="flutterwave-donation-form">
	<?php if ( '' !== (string) $donation_heading || '' !== (string) $donation_details ) : ?>
		<div class="flw-form-header">
			<?php if ( '' !== (string) $donation_heading ) : ?>
				<h2 class="flw-form-title"><?php echo esc_html( $donation_heading ); ?></h2>
			<?php endif; ?>
			<?php if ( '' !== (string) $donation_details ) : ?>
				<p class="flw-form-description"><?php echo esc_html( $donation_details ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<p class="flw-error" role="alert" aria-live="polite"></p>
	<form id="<?php echo esc_attr( $form_id ); ?>" class="flw-donation-form" <?php echo $data_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each key and value is escaped when built. ?>>
		<div id="notice"></div>
		<?php if ( empty( $atts['email'] ) ) : ?>

			<label class="pay-now"><?php esc_attr_e( 'Email', 'rave-payment-forms' ); ?></label>
			<input class="flw-form-input-text" id="flw-customer-email" type="email" placeholder="<?php esc_attr_e( 'Email', 'rave-payment-forms' ); ?>" required />

		<?php endif; ?>

		<?php if ( empty( $atts['firstname'] ) ) : ?>

			<label class="pay-now"><?php esc_attr_e( 'First name', 'rave-payment-forms' ); ?></label>
			<input class="flw-form-input-text" id="flw-first-name" type="text" placeholder="<?php esc_attr_e( 'First name', 'rave-payment-forms' ); ?>" />

		<?php endif; ?>

		<?php if ( empty( $atts['lastname'] ) ) : ?>

			<label class="pay-now"><?php esc_attr_e( 'Last name', 'rave-payment-forms' ); ?></label>
			<input class="flw-form-input-text" id="flw-last-name" type="text" placeholder="<?php esc_attr_e( 'Last name', 'rave-payment-forms' ); ?>" />

		<?php endif; ?>

		<?php if ( '0' !== (string) $atts['show_frequency'] ) : ?>
		<label class="pay-now" for="flw-payment-type"><?php esc_attr_e( 'Payment type', 'rave-payment-forms' ); ?></label>
		<select class="flw-form-select" id="flw-payment-type">
			<option value="once"><?php esc_html_e( 'Give once', 'rave-payment-forms' ); ?></option>
			<option value="monthly"><?php esc_html_e( 'Give monthly', 'rave-payment-forms' ); ?></option>
			<option value="yearly"><?php esc_html_e( 'Give yearly', 'rave-payment-forms' ); ?></option>
		</select>
		<?php endif; ?>

		<?php if ( empty( $atts['amount'] ) ) : ?>

			<label class="pay-now" for="flw-amount"><?php esc_attr_e( 'Amount', 'rave-payment-forms' ); ?></label>
			<?php if ( ! empty( $presets ) ) : ?>
				<div class="flw-amount-presets" role="group" aria-label="<?php esc_attr_e( 'Suggested amounts', 'rave-payment-forms' ); ?>">
					<?php foreach ( $presets as $preset ) : ?>
						<button type="button" class="flw-amount-preset" data-amount="<?php echo esc_attr( (string) $preset ); ?>" aria-pressed="false"><?php echo esc_html( number_format_i18n( $preset, floor( $preset ) === $preset ? 0 : 2 ) ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<input class="flw-form-input-text" id="flw-amount" type="text" placeholder="<?php esc_attr_e( 'Amount', 'rave-payment-forms' ); ?>" required />

		<?php endif; ?>

		<?php if ( empty( $atts['currency'] ) ) : ?>
			<label class="pay-now"><?php esc_attr_e( 'Currency', 'rave-payment-forms' ); ?></label>
			<?php if ( ! empty( $atts['custom_currency'] ) ) { ?>

				<select class="flw-form-select" id="flw-currency" required>
					<?php foreach ( $currencies as $currency ) : ?>
						<option value="<?php echo esc_attr( $currency ); ?>"><?php echo esc_attr( $currency ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php } else { ?>


				<?php if ( 'NG' === $atts['country'] ) : ?>
					<select class="flw-form-select" id="flw-currency" required>
						<option value="NGN">NGN</option>
						<option value="USD">USD</option>
						<option value="KES">KES</option>
						<option value="EUR">EUR</option>
						<option value="GBP">GBP</option>
					</select>
				<?php endif; ?>

				<?php if ( 'KE' === $atts['country'] ) : ?>
					<select class="flw-form-select" id="flw-currency" required>
						<option value="KES">KES</option>
					</select>
				<?php endif; ?>

				<?php if ( 'GH' === $atts['country'] ) : ?>
					<select class="flw-form-select" id="flw-currency" required>
						<option value="GHS">GHS</option>
						<option value="USD">USD</option>
					</select>
				<?php endif; ?>

				<?php if ( 'ZA' === $atts['country'] ) : ?>
					<select class="flw-form-select" id="flw-currency" required>
						<option value="ZAR">ZAR</option>
					</select>
				<?php endif; ?>

				<?php if ( 'US' === $atts['country'] ) : ?>
					<select class="flw-form-select" id="flw-currency" required>
						<option value="NGN">NGN</option>
						<option value="USD">USD</option>
						<option value="KES">KES</option>
						<option value="GHS">GHS</option>
						<option value="EUR">EUR</option>
						<option value="ZAR">ZAR</option>
						<option value="GBP">GBP</option>
					</select>
				<?php endif; ?>

				<?php
			}//end if
			?>

		<?php endif; ?>

		<?php wp_nonce_field( 'flw-rave-pay-nonce', 'flw_sec_code' ); ?>
		<?php echo isset( $signed_config ) ? $signed_config : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values are escaped when built. ?>
		<button value="submit" id="flw-pay-now-button" class='flw-pay-now-button' href='#'><?php echo esc_html( $btn_text ); ?></button>
	</form>
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
</div>
<div id="flutterwave-overlay" style="display:none">
	<div id="flw-overlay-text">
		<span class="flw-overlay-spinner" aria-hidden="true"></span>
		<?php esc_html_e( 'Taking you to Flutterwave to pay. Please don’t close this page.', 'rave-payment-forms' ); ?>
	</div>
</div>
