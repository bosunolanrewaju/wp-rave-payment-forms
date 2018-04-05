=== Rave Payment Forms ===
Contributors: bosunolanrewaju, Flutterwave, Flamez
Tags: rave, payment form, payment gateway, bank account, credit card, debit card, nigeria, kenya, international, mastercard, visa
Donate link: http://flutterwave.com/
Requires at least: 4.4
Tested up to: 4.8
Requires PHP: 5.4
Stable tag: 0.0.1
License: MIT
License URI: https://github.com/Flutterwave/rave-payment-forms/blob/master/LICENSE

Accept Credit card, Debit card and Bank account payment directly on your WordPress site with the Rave payment gateway.
Take donations and payments easily and directly on your site
Signup for a live rave account at https://rave.flutterwave.com and a sandbox account on https://ravesandbox.flutterwave.com
Please see our Terms of service here: http://bit.ly/2GH9oTy
For more information on the data we collect please view our privacy policy here: http://bit.ly/2HcQUv7
Rave is available in:
Nigeria
Ghana
Kenya

== Description ==

Pay Button Public Key - Enter your public key which can be retrieved from Settings > API on your Rave account dashboard.
Pay Button Secret Key - Enter your secret key which can be retrieved from Settings > API on your Rave account dashboard.
Go Live - Tick that section to turn your rave plugin live.
Modal Title - (Optional) customize the title of the Pay Modal. Default is FLW PAY.
Modal Description - (Optional) customize the description on the Pay Modal. Default is FLW PAY MODAL.
Modal Logo - (Optional) customize the logo on the Pay Modal. Enter a full url (with \'http\'). Default is Rave logo.
Success Redirect URL - (Optional) The URL the user should be redirected to after a successful payment. Enter a full url (with \'http\'). Default: \"\".
Failed Redirect URL - (Optional) The URL the user should be redirected to after a failed payment. Enter a full url (with \'http\'). Default: \"\".
Pay Button Text - (Optional) The text to display on the button. Default: \"PAY NOW\".
Charge Currency - (Optional) The currency the user is charged. Default: \"NGN\".
Charge Country - (Optional) The country the merchant is serving. Default: \"NG: Nigeria\".
Form Style - (Optional) Disable form default style and use the activated theme style instead.
Click Save Changes to save your changes.

Styling
You can enable default theme\'s style to override default form style from the Settings page. Or you can override the formclass .flw-simple-pay-now-form from your stylesheet.

Usage
####1. Shortcode
Insert the shortcode anywhere on your page or post that you want the form to be displayed to the user.
Basic: requires the user to enter amount and email to complete payment
[flw-pay-button]


With button text:
[flw-pay-button]Button Text[/flw-pay-button]


With attributes: email or use_current_user_email with value \"yes\", amount
[flw-pay-button amount=\"1290\" email=\"customer@email.com\" ]

or

[flw-pay-button amount=\"1290\" use_current_user_email=\"yes\" ]


With attributes and button text: email, amount
[flw-pay-button amount=\"1290\" email=\"customer@email.com\" ]Button Text[/flw-pay-button]



With currency
[flw-pay-button custom_currency=\'NGN,GBP,USD\']

With attributes: email or use_current_user_email with value \"yes\", amount and currency
[flw-pay-button amount=\"1290\" email=\"customer@email.com\" custom_currency= ‘NGN, GBP, USD’ ]

or

[flw-pay-button amount=\"1290\" use_current_user_email=\"yes\" custom_currency= ‘NGN, GBP, USD’ ]

With currency:
[flw-pay-button custom_currency=\'NGN,GBP,USD\']

With attributes: email or use_current_user_email with value \"yes\", amount and currency
[flw-pay-button amount=\"1290\" email=\"customer@email.com\" custom_currency= ‘NGN, GBP, USD’ ]

or

[flw-pay-button amount=\"1290\" use_current_user_email=\"yes\" custom_currency= ‘NGN, GBP, USD’ ]


####2. Visual Composer
The shortcode can be added via Visual Composer elements.
On Visual Composer Add Element dialog, click on \"Rave Forms\" and select the type of form you want to include on your page. 


On the \"Form Settings\" dialog, fill in the form attributes and click \"Save Changes\".

Payment Form successfully added to the page. 


Transaction List
All the payments made through the forms to Rave can be accessed on Rave > Transactions page.


TODO
Add advanced forms to include customization where user can choose what fields to add to the form.
Multiple Pay Button integrations.
More (that I can\'t think of ATM) - Suggestions and Feature request are highly welcome

Suggestions / Contributions
For issues, suggestions and feature request, click here. To contribute, fork the repo, add your changes and modifications, then create a pull request.

License
MIT License

== Installation ==
Automatic Installation
Login to your WordPress Dashboard.
Click on \"Plugins > Add New\" from the left menu.
In the search box type Rave Payment Forms.
Click on Install Now on Rave Payment Forms to install the plugin on your site.
Confirm the installation.
Activate the plugin.
Go to \"Rave > Settings\" from the left menu to configure the plugin.


Manual Installation
Download the plugin zip file.
Login to your WordPress Admin. Click on \"Plugins > Add New\" from the left menu.
Click on the \"Upload\" option, then click \"Choose File\" to select the zip file you downloaded. Click \"OK\" and \"Install Now\" to complete the installation.
Activate the plugin.
Go to \"Rave > Settings\" from the left menu to configure the plugin.
For FTP manual installation, check here.
Configure the plugin
To configure the plugin, go to Rave > Settings from the left menu.

== Frequently Asked Questions ==
How do I get my Test public and secret keys ?
To get your test public and secret key visit this page to see how: https://flutterwavedevelopers.readme.io/v2.0/docs/api-keys

How do I move from test to production on the plugin ?
You need to toggle the go live check box by clicking on it, you also need to make sure your live keys have been added to the rave configuration page on wordpress.

How do I charge my customers in multiple currencies ?
We allow you use shortcodes to append multiple currencies to the form shown to your customers simple embed with the currency shortcode style above.

== Screenshots ==
1. https://cloud.githubusercontent.com/assets/8383666/21610555/f1b32abc-d1c8-11e6-8d53-e77c9e35a6c7.png
2. https://cloud.githubusercontent.com/assets/8383666/21606192/20887a10-d1ae-11e6-85f7-6f8771cb8688.png
3. https://cloud.githubusercontent.com/assets/8383666/21606210/381994b6-d1ae-11e6-8731-810be5550f55.png

== Changelog ==
v 1.0.1

v 1.0.0

== Upgrade Notice ==
v1.0.1

This version allows you use multiple currencies on the Wordpress payment form.
