<form>
  <?php if ( empty( $atts['email'] ) ) : ?>

    <label class="pay-now"><?php _e( 'Email', 'flw-flutterwave-pay' ) ?></label>
    <input id="flw-customer-email" type="text" placeholder="<?php _e( 'Email', 'flw-flutterwave-pay' ) ?>" required /><br>

  <?php endif; ?>

  <?php if ( empty( $atts['amount'] ) ) : ?>

    <label class="pay-now"><? _e( 'Amount', 'flw-flutterwave-pay' ); ?></label>
    <input id="flw-amount" type="number" placeholder="<? _e( 'Amount', 'flw-flutterwave-pay' ); ?>" required /><br>

  <?php endif; ?>

  <button id='flw-pay-now-button' href='#'><?php _e( $btn_text, 'flw-flutterwave-pay' ) ?></button>
</form>
