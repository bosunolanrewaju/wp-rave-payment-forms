<?php

  if ( ! defined( 'ABSPATH' ) ) { exit; }
  $form_id = bin2hex( openssl_random_pseudo_bytes( 2 ) );

?>

<div>
  <form id="<?php echo $form_id ?>" class="flw-simple-pay-now-form" <?php echo $data_attr; ?> >
    <div id="notice"></div>
    <?php if ( empty( $atts['email'] ) ) : ?>

      <label class="pay-now"><?php _e( 'Email', 'rave-pay' ) ?></label>
      <input class="flw-form-input-text" id="flw-customer-email" type="email" placeholder="<?php _e( 'Email', 'rave-pay' ) ?>" required /><br>

    <?php endif; ?>

    <?php if ( empty( $atts['amount'] ) ) : ?>

      <label class="pay-now"><? _e( 'Amount', 'rave-pay' ); ?></label>
      <input class="flw-form-input-text" id="flw-amount" type="text" placeholder="<? _e( 'Amount', 'rave-pay' ); ?>" required /><br>

    <?php endif; ?>
    <?php wp_nonce_field( 'flw-rave-pay-nonce', 'flw_sec_code' ); ?>
    <button value="submit" class='flw-pay-now-button' href='#'><?php _e( $btn_text, 'rave-pay' ) ?></button>
  </form>
</div>
