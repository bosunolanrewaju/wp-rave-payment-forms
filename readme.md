<p align="center">
    <img title="Flutterwave" height="200" src="https://flutterwave.com/images/logo/full.svg" width="50%"/>
</p>

# Flutterwave Payments

## Introduction

The WordPress Plugin makes it very easy and quick to add Flutterwave Payment options on your eCommerce site, Donation Page or a list of Payment Subscriptions you want your clients to subscribe to.

Take donations and payments for services on your WordPress site using Flutterwave.


## Description


Available features include:

- Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, NQR.
- Recurring payments: Tokenization and Subscriptions.
- Split payments: Split payments between multiple recipients.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Initialization](#initialization)
4. [Best Practices](#best-practices)
5. [Debugging Errors](#debugging-errors)
6. [Support](#support)
7. [Contribution guidelines](#contribution-guidelines)
9. [License](#)
10. [Changelog](#)


## Requirements

1. Flutterwave for business [API Keys](https://developer.flutterwave.com/docs/integration-guides/authentication)
2. Supported PHP version: 7.4 or higher
3. For Elementor: Elementor version: 2.8.0 or higher

## Installation

### Automatic Installation
*   Login to your WordPress Dashboard.
*   Click on "Plugins > Add New" from the left menu.
*   In the search box type __Flutterwave Payments__.
*   Click on __Install Now__ on __Flutterwave Payments__ to install the plugin on your site.
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

## Best Practices

- When in doubt about a transaction, always check the Flutterwave Dashboard to confirm the status of a transaction.
- Always ensure you keep your API keys securely and privately. Do not share with anyone.
- Ensure you change from the default secret hash on the Wordpress admin and apply same on the Flutterwave Dashboard.
- Always ensure you install the most recent version of the Flutterwave WooCommerce plugin.

## Debugging Errors

We understand that you may run into some errors while integrating our plugin. You can read more about our error messages [here](https://developer.flutterwave.com/docs/integration-guides/errors).

For `authorization` and `validation` error responses, double-check your API keys and request. If you get a `server` error, kindly engage the team for support.

## Support

For additional assistance using this library, contact the developer experience (DX) team via [email](mailto:developers@flutterwavego.com) or on [slack](https://bit.ly/34Vkzcg).

You can also follow us [@FlutterwaveEng](https://twitter.com/FlutterwaveEng) and let us know what you think ðŸ˜Š.

### Configure the plugin
To configure the plugin, go to __Rave > Settings__ from the left menu.

###
![Rave Settings Screenshot](https://cloud.githubusercontent.com/assets/8383666/21610555/f1b32abc-d1c8-11e6-8d53-e77c9e35a6c7.png)

* __Pay Button Public Key__ - Enter your public key which can be retrieved from "Pay Buttons" page on your Rave account dashboard.
* __Recurring Payments__ - To enable recurring payments/subscription for your users, click the Enable Recurring Payment 'checkbox' to enable it. Go to your Rave Dashboard, navigate to 'Payment Plans' and click the 'Create Payment Plan' button to create a payment plan with an interval. The intervals accepted for this plugin are WEEKLY, MONTHLy, QUARTERLY, ANNUALLY/YEARLY.
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

#### Blocks

In the block editor, open the inserter and choose the **Flutterwave** category:

| Block | What it does |
| --- | --- |
| Payment Button | One-line checkout for a fixed amount: an email field and a button such as "Pay NGN 5,000". Logged-in users can skip the email. |
| Payment Form | The full form, with an optional heading and description, a fixed or customer-entered amount, and optional name and phone fields. |
| Donation Form | One-off or monthly/yearly donations with suggested amounts. |
| Pricing Card | A plan or product card with price, features, an optional badge and a checkout. |
| Accepted Payment Methods | A badge listing the methods enabled in Flutterwave > Settings. |

Each block has its own accent colour, button text colour and corner radius under the block's Styles tab. Button text can include `{amount}`, which is replaced with the formatted price. Blocks are rendered on the server with the same code as the shortcodes.

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

or

[flw-pay-button amount="1290" email="customer@email.com" split_name=1 ]Button Text[/flw-pay-button]


```

With custom and excluded fields.
```
[flw-pay-form amount=1234 fullname="Abraham Olaobaju" currency="USD,UGX,NGN" custom_fields='age:number,color:select|black:#000|white:#fff' exclude="phone"]

```

With order rearranged.
```
[flw-pay-form amount=1234 fullname="Abraham Olaobaju" currency="USD,UGX,NGN" order="currency,fullname,amount,phone,email"]
```

Donation Form.
```
[flw-donation-form]
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

## Development

Requirements: PHP 7.4+, Composer, Node.js 24 (see `.nvmrc`) and Docker (for `wp-env`).

```bash
npm ci                 # installs npm and Composer dependencies
npm run env:start      # WordPress at http://localhost:8888 (admin / password)
```

| Command | What it runs |
| --- | --- |
| `npm run start` | Rebuild the admin app (`client/admin`) on every change |
| `npm run build:admin` | Build the admin app into `build/` once |
| `npm run test:js` | Vitest tests for the admin app (`client/**/test`) |
| `npm run test:unit` | Fast PHPUnit + Brain Monkey tests in `tests/Unit` (no WordPress needed) |
| `npm run test:integration` | PHPUnit against the WordPress test suite inside wp-env (`tests/Integration`) |
| `npm run test:e2e` | Playwright browser tests against the wp-env site (`tests/e2e`); run `npm run test:e2e:install` once first |
| `npm test` | All of the above |
| `npm run lint` | PHPCS (WordPress Coding Standards 3), ESLint and Stylelint |
| `npm run audit` | `npm audit` and `composer audit` |
| `npm run build` | Minified JS, translation template and `rave-payment-forms.zip` |

The onboarding wizard and settings screen are a React app in `client/admin`, built with `@wordpress/scripts` and mounted on **Flutterwave → Settings**. It reads and writes settings through the `flutterwave/v1/settings` REST route (`includes/admin`). `build/` is not committed, so run `npm run build:admin` before opening the settings page in a checkout of the repository.

CI (`.github/workflows/ci.yml`) runs linting, dependency audits, unit tests on PHP 7.4–8.5, integration tests on WordPress 6.4 and latest, and the end-to-end suite on every push and pull request.

## Contribution guidelines

We love to get your input. Read more about our community contribution guidelines [here](/CONTRIBUTING.md)

## License

By contributing to the Rave WooCommerce Plugin, you agree that your contributions will be licensed under its [MIT license](/LICENSE).

Copyright (c) Flutterwave Inc. 