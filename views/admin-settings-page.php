<?php

  if ( ! defined( 'ABSPATH' ) ) { exit; }

?>
<?php global $admin_settings; ?>

  <div class="wrap">
    <h1>Flutterwave Pay Settings</h1>
    <form id="flutterwave-pay" action="options.php" method="post" enctype="multipart/form-data">
      <?php settings_fields( 'flw-flutterwave-pay-settings-group' ); ?>
      <?php do_settings_sections( 'flw-flutterwave-pay-settings-group' ); ?>
      <table class="form-table">
        <tbody>

          <!-- Public Key -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[public_key]"><?php _e( 'Public Key', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[public_key]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'public_key' ) ); ?>" />
              <p class="description">Your Flutterwave integration public key</p>
            </td>
          </tr>
          <!-- Modal title -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[modal_title]"><?php _e( 'Modal Title', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[modal_title]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'modal_title' ) ); ?>" />
              <p class="description">(Optional) default: FLW PAY</p>
            </td>
          </tr>
          <!-- Modal Description -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[modal_desc]"><?php _e( 'Modal Description', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[modal_desc]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'modal_desc' ) ); ?>" />
              <p class="description">(Optional) default: FLW PAY MODAL</p>
            </td>
          </tr>
          <!-- Modal Logo -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[modal_logo]"><?php _e( 'Modal Logo', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[modal_logo]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'modal_logo' ) ); ?>" />
              <p class="description">(Optional) - Full URL (with 'http') to the custom logo. default: Flutterwave logo</p>
            </td>
          </tr>
          <!-- Successful Redirect URL -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[success_redirect_url]"><?php _e( 'Success Redirect URL', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[success_redirect_url]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'success_redirect_url' ) ); ?>" />
              <p class="description">(Optional) Full URL (with 'http') to redirect to for successful transactions. default: ""</p>
            </td>
          </tr>
          <!-- Failed Redirect URL -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[failed_redirect_url]"><?php _e( 'Failed Redirect URL', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[failed_redirect_url]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'failed_redirect_url' ) ); ?>" />
              <p class="description">(Optional) Full URL (with 'http') to redirect to for failed transactions. default: ""</p>
            </td>
          </tr>
          <!-- Pay Button Text -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[btn_text]"><?php _e( 'Pay Button Text', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <input class="regular-text code" type="text" name="flw_flutterwave_options[btn_text]" value="<?php echo esc_attr( $admin_settings->get_option_value( 'btn_text' ) ); ?>" />
              <p class="description">(Optional) default: PAY NOW</p>
            </td>
          </tr>
          <!-- Currency -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[currency]"><?php _e( 'Charge Currency', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <select class="regular-text code" name="flw_flutterwave_options[currency]">
                <?php $currency = esc_attr( $admin_settings->get_option_value( 'currency' ) ); ?>
                <option value="NGN" <?php selected( $currency, 'NGN' ) ?>>NGN</option>
                <option value="USD" <?php selected( $currency, 'USD' ) ?>>USD</option>
                <option value="GBP" <?php selected( $currency, 'GBP' ) ?>>GBP</option>
                <option value="EUR" <?php selected( $currency, 'EUR' ) ?>>EUR</option>
              </select>
              <p class="description">(Optional) default: NGN</p>
            </td>
          </tr>
          <!-- Country -->
          <tr valign="top">
            <th scope="row">
              <label for="flw_flutterwave_options[country]"><?php _e( 'Charge Country', 'flw-flutterwave-pay' ); ?></label>
            </th>
            <td class="forminp forminp-text">
              <select class="regular-text code" name="flw_flutterwave_options[country]">
                <?php $country = esc_attr( $admin_settings->get_option_value( 'country' ) ); ?>
                <option value="NG" <?php selected( $country, 'NG' ) ?>>NG: Nigeria</option>
                <option value="GH" <?php selected( $country, 'GH' ) ?>>GH: Ghana</option>
                <option value="KE" <?php selected( $country, 'KE' ) ?>>KE: Kenya</option>
                <option value="US" <?php selected( $country, 'US' ) ?>>All (Worldwide)</option>
              </select>
              <p class="description">(Optional) default: NG</p>
            </td>
          </tr>

        </tbody>
      </table>
      <?php submit_button(); ?>
    </form>

  </div>
