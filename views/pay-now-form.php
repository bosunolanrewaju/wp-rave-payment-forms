<form class="flw-simple-pay-now-form">
  <?php if ( empty( $atts['email'] ) ) : ?>

    <label class="pay-now"><?php _e( 'Email', 'flw-flutterwave-pay' ) ?></label>
    <input id="flw-customer-email" type="email" placeholder="<?php _e( 'Email', 'flw-flutterwave-pay' ) ?>" required /><br>

  <?php endif; ?>

  <?php if ( empty( $atts['amount'] ) ) : ?>

    <label class="pay-now"><? _e( 'Amount', 'flw-flutterwave-pay' ); ?></label>
    <input id="flw-amount" type="text" placeholder="<? _e( 'Amount', 'flw-flutterwave-pay' ); ?>" required /><br>

  <?php endif; ?>

  <button value="submit" class='flw-pay-now-button' href='#'><?php _e( $btn_text, 'flw-flutterwave-pay' ) ?></button>
</form>
