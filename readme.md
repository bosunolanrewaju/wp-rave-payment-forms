# Rave Payment Forms

 - **Contributors:** bosunolanrewaju
 - **Tags:** rave, payment form, payment gateway, bank account, credit card, debit card, nigeria, kenya, international, mastercard, visa
 - **Requires at least:** 4.4
 - **Tested up to:** 4.6
 - **Stable tag:** 0.1.2
 - **License:** [MIT](https://github.com/bosunolanrewaju/rave-payment-forms/blob/master/LICENSE)

Take donations and payments for services on your WordPress site using Rave.



## Description


Accept Credit card, Debit card and Bank account payment directly on your WordPress site with the Rave payment gateway.

#### Take donations and payments easily and directly on your site

Signup for an account [here](https://ravepay.co)

Rave is available in:

* __Nigeria__
* __Ghana__
* __Kenya__



## Installation


### Automatic Installation
*   Login to your WordPress Dashboard.
*   Click on "Plugins > Add New" from the left menu.
*   In the search box type __Rave Payment Forms__.
*   Click on __Install Now__ on __Rave Payment Forms__ to install the plugin on your site.
*   Confirm the installation.
*   Activate the plugin.
*   Go to "Rave > Settings" from the left menu to configure the plugin.


### Manual Installation
*  Download the plugin zip file.
*  Login to your WordPress Admin. Click on "Plugins > Add New" from the left menu.
*  Click on the "Upload" option, then click "Choose File" to select the zip file you downloaded. Click "OK" and "Install Now" to complete the installation.
*  Activate the plugin.
*  Go to "Rave > Settings" from the left menu to configure the plugin.

For FTP manual installation, [check here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).



### Configure the plugin
To configure the plugin, go to __Rave > Settings__ from the left menu.

###
![Rave Settings Screenshot](https://cloud.githubusercontent.com/assets/8383666/21610555/f1b32abc-d1c8-11e6-8d53-e77c9e35a6c7.png)

* __Pay Button Public Key__ - Enter your public key which can be retrieved from "Pay Buttons" page on your Rave account dashboard.
* __Modal Title__ - (Optional) customize the title of the Pay Modal. Default is FLW PAY.
* __Modal Description__ - (Optional) customize the description on the Pay Modal. Default is FLW PAY MODAL.
* __Modal Logo__ - (Optional) customize the logo on the Pay Modal. Enter a full url (with 'http'). Default is Rave logo.
* __Success Redirect URL__ - (Optional) The URL the user should be redirected to after a successful payment. Enter a full url (with 'http'). Default: "".
* __Failed Redirect URL__ - (Optional) The URL the user should be redirected to after a failed payment. Enter a full url (with 'http'). Default: "".
* __Pay Button Text__ - (Optional) The text to display on the button. Default: "PAY NOW".
* __Charge Currency__ - (Optional) The currency the user is charged. Default: "NGN".
* __Charge Country__ - (Optional) The country the merchant is serving. Default: "NG: Nigeria".
* __Form Style__ - (Optional) Disable form default style and use the activated theme style instead.
* Click __Save Changes__ to save your changes.

### Styling
You can enable default theme's style to override default form style from the __Settings__ page.
Or you can override the _form_ class `.flw-simple-pay-now-form` from your stylesheet.


## Usage ##

####1. Shortcode

Insert the shortcode anywhere on your page or post that you want the form to be displayed to the user.

Basic: _requires the user to enter amount and email to complete payment_
```
[flw-pay-button]
```

With button text:
```
[flw-pay-button]Button Text[/flw-pay-button]
```

With attributes: _email_ or _use_current_user_email_ with value "yes", _amount_
```
[flw-pay-button amount="1290" email="customer@email.com" ]

or

[flw-pay-button amount="1290" use_current_user_email="yes" ]
```

With attributes and button text: _email_, _amount_
```
[flw-pay-button amount="1290" email="customer@email.com" ]Button Text[/flw-pay-button]
```

####2. Visual Composer

The shortcode can be added via Visual Composer elements.

* On Visual Composer __Add Element__ dialog, click on "__Rave Forms__" and select the type of form you want to include on your page.
![Visual Composer Screenshot 1](https://cloud.githubusercontent.com/assets/8383666/21606192/20887a10-d1ae-11e6-85f7-6f8771cb8688.png)
###

* On the "Form Settings" dialog, fill in the form attributes and click "__Save Changes__".
![Visual Composer Screenshot 2](https://cloud.githubusercontent.com/assets/8383666/21606210/381994b6-d1ae-11e6-8731-810be5550f55.png)
###

* Payment Form successfully added to the page.
![Visual Composer Screenshot 3](https://cloud.githubusercontent.com/assets/8383666/21606217/46200ed2-d1ae-11e6-812b-7d5a2c1f6b43.png)
###


## Transaction List ##

All the payments made through the forms to Rave can be accessed on __Rave > Transactions__ page.

![Rave Transactions Screenshot](https://cloud.githubusercontent.com/assets/8383666/21606454/01022040-d1b0-11e6-8c61-755cea93ea14.png)

##
### TODO
* Add advanced forms to include customization where user can choose what fields to add to the form.
* Multiple Pay Button integrations.
* More (that I can't think of ATM) - Suggestions and Feature request are highly welcome

### Suggestions / Contributions

For issues, suggestions and feature request, [click here](https://github.com/bosunolanrewaju/rave-payment-forms/issues).
To contribute, fork the repo, add your changes and modifications, then create a pull request.


### License

##### [MIT License](https://github.com/bosunolanrewaju/rave-payment-forms/blob/master/LICENSE)
