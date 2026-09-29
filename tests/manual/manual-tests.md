# Manual tests

The automated suites (`npm test`) cover the plugin against a mocked Flutterwave API. Run these
checks by hand before a release: they use the real Flutterwave sandbox, real webhooks and real
emails, which the automated tests cannot.

## Before you start

- A site you can reach from the internet, so Flutterwave can send webhooks: a staging site, or
  `npm run env:start` exposed with a tunnel such as `ngrok http 8888`.
- A Flutterwave account in **test mode**, with its test public key, secret key and a webhook URL
  set to `https://<your-site>/wp-json/flutterwave/v1/webhook`.
- Flutterwave's [test cards](https://developer.flutterwave.com/docs/test-cards) for successful,
  failed and OTP payments.
- On the development site, make sure the end-to-end mock is off (it is unless a test run was
  interrupted): `npx wp-env run cli wp option delete flw_e2e_mock_api`.

## 1. Onboarding

Start from a fresh install: `npx wp-env run cli wp option delete flw_rave_options`.

1. Open **Flutterwave** in the admin menu. The welcome screen shows **Activate Flutterwave**.
2. Go through the four steps: business details, API keys and webhook, payment methods, redirects.
   - [ ] Keys from different modes (a live secret key with a test public key) are flagged.
   - [ ] The webhook URL and secret hash can be copied. Paste both into the Flutterwave dashboard.
3. Finish. You see **Setup successful!** and a sample shortcode.
4. Click **Go to settings**.
   - [ ] The guided tour starts with **Welcome to your Flutterwave dashboard** and points at the
     settings tabs, then Payment Forms, Transactions and Integrations in the menu, then **Help**.

## 2. Taking payments

Create a page for each of these and pay with a test card.

| Form | Content |
| --- | --- |
| Fixed amount | `[flw-pay-form amount="5000" currency="NGN"]` |
| Customer enters the amount | `[flw-pay-form]` |
| Payment button | **Payment Button** block, amount 2500 |
| Donation | `[flw-donation-form]` and the **Donation Form** block |
| Pricing | **Pricing Card** block |

For each:

- [ ] The form uses the Flutterwave design and the amount shown is the amount charged.
- [ ] A successful card payment returns to the success redirect and appears in **Transactions**
  as **Successful**.
- [ ] A failed card payment returns to the failed redirect and shows as **Failed**.
- [ ] Closing the Flutterwave checkout without paying leaves the payment **Pending** or
  **Cancelled**, not successful.
- [ ] A monthly donation creates a payment plan in the Flutterwave dashboard.

## 3. Webhooks

- [ ] Pay by bank transfer and close the tab before it confirms. When Flutterwave sends the
  webhook, the transaction becomes **Successful** without the customer returning.
- [ ] Resend a webhook from the Flutterwave dashboard: the transaction is not duplicated.

## 4. Admin screens

- [ ] **Payment Forms** lists every page with a Flutterwave block or shortcode, with its payments.
  **New form** previews a form, copies its shortcode and creates a draft page.
- [ ] **Transactions**: filter by date, status and currency; open a transaction; re-check a pending
  one; **Custom Download** a CSV and open it in a spreadsheet.
- [ ] **Help** on each screen replays its tour, and **Show tips** turns tips off and back on.

## 5. Integrations

Install and activate each plugin from **Flutterwave → Integrations**.

### WooCommerce

- [ ] **Install WooCommerce**, then install and activate **Flutterwave WooCommerce**.
- [ ] **Manage settings** opens the WooCommerce payment settings for Flutterwave.

### Easy Digital Downloads

- [ ] **Enable**, then buy a download with a test card. The order is **Completed** with the
  Flutterwave transaction ID, and the purchase receipt email arrives.
- [ ] Cancel on the Flutterwave page: you see the failed-transaction page and can try again.
- [ ] With the store currency set to one Flutterwave does not support, the card warns about it.

### GiveWP

- [ ] **Enable**, then donate on both a visual builder form and a classic form. The donation is
  **Complete** with the transaction ID, and the donor receipt arrives.

## 6. Other environments

- [ ] Safari and Firefox: onboarding, one payment, and the Transactions filters.
- [ ] A phone-sized screen: the admin screens, the tour and a payment form.
- [ ] PHP 8.4 with `WP_DEBUG` on: no notices from this plugin in `wp-content/debug.log`.
