=== Flutterwave Payments ===
Contributors: flutterwave
Tags: rave, payment form, payment gateway, bank account, credit card, debit card, nigeria, kenya, international, mastercard, visa, flutterwave
Donate link: http://rave.flutterwave.com/
Requires at least: 4.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.7
License: MIT
License URI: https://github.com/Flutterwave/rave-payment-forms/blob/master/LICENSE

Accept Credit card, Debit card and Bank account payment directly on your WordPress site with the Flutterwave Payments Plugin.

== Description ==
Accept Credit card, Debit card and Bank account payment directly on your store with the official Flutterwave Plugin for WordPress.

= Plugin Features =

* Blocks for the block editor: Payment Button, Payment Form, Donation Form, Pricing Card and Accepted Payment Methods, each with its own colours and settings.
* Shortcodes: [flw-pay-form] and [flw-donation-form].
* Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, 1voucher.
* Recurring payments: Tokenization and Subscriptions.
* Split payments: Split payments between multiple recipients.

= Requirements =

1. Flutterwave for business [API Keys](https://developer.flutterwave.com/docs/integration-guides/authentication)
2. PHP 7.4 or later

== Installation ==

= Automatic Installation =

* Login to your WordPress Dashboard.
* Click on "Plugins > Add New" from the left menu.
* In the search box type Flutterwave Payments.
* Click on Install Now on Flutterwave Payments to install the plugin on your site.
* Confirm the installation.
* Activate the plugin.
* Go to "Rave > Settings" from the left menu to configure the plugin.


= Manual Installation =

* Download the plugin zip file.
* Login to your WordPress Admin. Click on \"Plugins > Add New\" from the left menu.
* Click on the \"Upload\" option, then click \"Choose File\" to select the zip file you downloaded. Click \"OK\" and \"Install Now\" to complete the installation.
* Activate the plugin.
* Go to \"Rave > Settings\" from the left menu to configure the plugin.
* For FTP manual installation, check here.

For FTP manual installation, [check here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Configuration options =

* Pay Button Public Key (live/Test) - Enter your public key which can be retrieved from Settings > API on your Rave account dashboard.
* Pay Button Secret Key (live/Test) - Enter your secret key which can be retrieved from Settings > API on your Rave account dashboard.
* Go Live - Tick that section to turn your rave plugin live.
* Modal Title - (Optional) customize the title of the Pay Modal. Default is FLW PAY.
* Modal Description - (Optional) customize the description on the Pay Modal. Default is FLW PAY MODAL.
* Modal Logo - (Optional) customize the logo on the Pay Modal. Enter a full url (with \'http\'). Default is Rave logo.
* Success Redirect URL - (Optional) The URL the user should be redirected to after a successful payment. Enter a full url (with 'http:\\'). Default: '\'.
* Failed Redirect URL - (Optional) The URL the user should be redirected to after a failed payment. Enter a full url (with 'http:\\'). Default: '\'.
* Pay Button Text - (Optional) The text to display on the button. Default: "PAY NOW".
* Charge Currency - (Optional) The currency the user is charged. Default: "NGN".
* Charge Country - (Optional) The country the merchant is serving. Default: "NG: Nigeria".
* Form Style - (Optional) Disable form default style and use the activated theme style instead.
* Click Save Changes to save your changes.

= Styling =

You can enable default theme's style to override default form style from the Settings page. Or you can override the formclass .flw-simple-pay-now-form from your stylesheet.

= Usage =

* a. Shortcode
Insert the shortcode anywhere on your page or post that you want the form to be displayed to the user.
Basic: requires the user to enter amount and email to complete payment
[flw-pay-button]


With button text:
[flw-pay-button]Button Text[/flw-pay-button]


With attributes: email or use_current_user_email with value "yes", amount
[flw-pay-button amount="1290" email="customer@email.com" ]

or

[flw-pay-button amount="1290" use_current_user_email="yes" ]


With attributes and button text: email, amount
[flw-pay-button amount="1290" email="customer@email.com" ]Button Text[/flw-pay-button]



With currency

[flw-pay-button custom_currency="NGN,GBP,USD"]

With attributes: email or use_current_user_email with value "yes", amount and currency
[flw-pay-button amount="1290" email="customer@email.com" custom_currency= "NGN, GBP, USD" ]

or

[flw-pay-button amount="1290" use_current_user_email="yes" custom_currency= "NGN, GBP, USD" ]

With currency:
[flw-pay-button custom_currency="NGN,GBP,USD"]

With attributes: email or use_current_user_email with value "yes", amount and currency
[flw-pay-button amount="1290" email="customer@email.com" custom_currency= "NGN, GBP, USD" ]

or

[flw-pay-button amount="1290" use_current_user_email="yes" custom_currency= "NGN, GBP, USD" ]


* b. Visual Composer
The shortcode can be added via Visual Composer elements.
On Visual Composer Add Element dialog, click on "Rave Forms" and select the type of form you want to include on your page.


On the "Form Settings" dialog, fill in the form attributes and click "Save Changes".

Payment Form successfully added to the page.

= Best Practices =
1. When in doubt about a transaction, always check the Flutterwave Dashboard to confirm the status of a transaction.
2. Always ensure you keep your API keys securely and privately. Do not share with anyone
3. Ensure you change from the default secret hash on the Wordpress admin and apply same on the Flutterwave Dashboard
4. Always ensure you install the most recent version of the Flutterwave Wordpress plugin

= Debugging Errors =

We understand that you may run into some errors while integrating our plugin. You can read more about our error messages [here](https://developer.flutterwave.com/docs/integration-guides/errors).

For `authorization` and `validation` error responses, double-check your API keys and request. If you get a `server` error, kindly engage the team for support.


= Support =

For additional assistance using this library, contact the developer experience (DX) team via [email](mailto:developers@flutterwavego.com) or on [slack](https://bit.ly/34Vkzcg).

You can also follow us [@FlutterwaveEng](https://twitter.com/FlutterwaveEng) and let us know what you think ðŸ˜Š.

= Contribution guidelines =

We love to get your input. Read more about our community contribution guidelines [here](/CONTRIBUTING.md)

= License =

By contributing to the Flutterwave WooCommerce, you agree that your contributions will be licensed under its [MIT license](/LICENSE).

== External services ==

This plugin connects to services run by Flutterwave Technology Solutions.

**Flutterwave payments API** (api.flutterwave.com) processes payments made through your forms. When a customer submits a form, the plugin sends the amount, currency, the customer's email, name and phone number, and a transaction reference to create the payment, then asks the API to verify it when the customer returns or a webhook arrives. The plugin also reads your account's transactions for the Transactions screen.

**Flutterwave integration analytics** (signozservice-prod.f4b-flutterwave.com) helps Flutterwave see whether integrations are working. The plugin sends:

* when your site is first set up: your public API key, the plugin name and version;
* when a payment starts: the transaction reference, whether you use test or live keys, and the plugin version;
* when a live payment succeeds: the transaction reference, amount, currency, fee and payment method;
* when something goes wrong: an error code and message, with the transaction reference where there is one.

No customer names, email addresses, phone numbers or card details are sent to the analytics service. To turn it off, add `define( 'FLW_DISABLE_TELEMETRY', true );` to wp-config.php, or return false from the `flw_signoz_enabled` filter.

Both services are covered by Flutterwave's [Terms of Service](https://flutterwave.com/us/terms) and [Privacy Notice](https://flutterwave.com/us/privacy-notice).

== Screenshots ==

1. Go to Flutterwave > Settings and click "Activate Flutterwave" to start the guided setup.
2. Add your Flutterwave API keys, then copy the webhook URL and secret hash into your Flutterwave dashboard.
3. Choose the payment methods customers can use: cards, bank transfer, mobile money, Apple Pay, Google Pay and more.
4. Change your settings at any time from the General, API & Webhook, Payment Methods and Redirects tabs.
5. See every payment made through your forms under Flutterwave > Transactions.
6. Add a payment form to any page with the [flw-pay-form] shortcode.
7. Collect one-off or recurring donations with the [flw-donation-form] shortcode.



== Changelog ==
v1.0.3
* This version allows you to add additional fields to the form.
* This version allow you to set default values to fields.
* This version allows you to hid fields "By appending '-h' to the name of the field".
* This version now allow for mobile money option in UGx,TZS,GHS respectively
v1.0.2
*Recuring payment section add under "Payment Plan"
v 1.0.1
* Recurring payments now enabled.

v 1.0.0

== Upgrade Notice ==
v1.0.1 - 12-02-2018
* This version doesn't redirect after failure, it allows the customer try payment again.
* This version allows you use multiple currencies on the Wordpress payment form.
* This version now has recurring payments.
