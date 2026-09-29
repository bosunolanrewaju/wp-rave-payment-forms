# Changelog
## Unreleased

### Added
- New onboarding flow for first-time setup (welcome, general details, API & webhook, payment methods, redirects), matching the Flutterwave WooCommerce plugin.
- Tabbed settings screen replacing the old settings form, with a generated webhook secret hash, copyable webhook URL and test/live key detection.
- Payment methods are chosen from a list (cards, stablecoin, bank transfer, mobile money, Apple Pay, Google Pay, Opay, plus advanced options) and enforced at checkout.
- Donation form heading and message are now shown on the form.
- Blocks: Payment Button, Payment Form, Donation Form, Pricing Card and Accepted Payment Methods, with per-block colours and corner radius.
- Payment form shortcode: `heading`, `description`, `layout="compact"`, `width="full"` and `show_secured="0"` attributes, `exclude="fullname"`, and `{amount}` in the button text.
- Donation form shortcode: `heading`, `message`, `currency`, `amounts` (suggested amounts) and `show_frequency="0"` attributes.
- Integration analytics through the Flutterwave SigNoz service (app.created, request.sent, app.transaction, app.error), ported from the WooCommerce plugin. Events are sent in the background and can be turned off with `FLW_DISABLE_TELEMETRY` or the `flw_signoz_enabled` filter.
- Payment Forms screen listing every Flutterwave block and shortcode in use, with the page it is on, its status and the payments it has taken, plus a builder that creates a draft page or copies the shortcode with a live preview.
- Payments now record the page they were made on.
- Guided tips: a short tour of each admin screen the first time a merchant opens it after onboarding, with a Help menu on every screen to replay a tour or turn tips on and off (saved per user).
- Integrations screen: install and activate the Flutterwave WooCommerce extension, switch on the new Easy Digital Downloads and GiveWP gateways (paid on Flutterwave's hosted checkout, confirmed on return and by webhook), and see what is coming soon.
- Custom CSV download of transactions for a date range, with spreadsheet formula injection neutralised.

### Removed
- The unused third-party integrations scaffolding (exchange-rate service sample and its admin page) is no longer loaded or shipped.

### Changed
- Payment and donation forms use the new Flutterwave design
- Transactions screen redesigned: customer, amount, date and status table with date-range, status and currency filters, pagination and a details drawer to re-verify or delete a record., with self-hosted fonts.
- The payment page title, description and logo settings are now sent to Flutterwave checkout.
- Form styles only load on pages that contain a form.

### Security
- [FIXED] Payment amount and currency are enforced server-side through a signed form configuration.
- [FIXED] Transaction verification checks the stored record's tx_ref, amount and currency instead of transaction metadata.
- [FIXED] Stored XSS through shortcode attributes and in the transactions list.
- [FIXED] Webhooks are rejected when no secret hash is configured; hashes are compared in constant time.
- [FIXED] `update-transaction` requires `manage_options` and a REST nonce.
- [ADDED] Easy Digital Downloads and GiveWP payments are only changed by a transaction verified with Flutterwave or the signed webhook, never by the return URL's query arguments, and completion is locked so a simultaneous return and webhook cannot complete an order twice.
- [ADDED] Checkout rate limiting (`flw_checkout_rate_limit` filter) and validated settings.
- [CHANGED] Secret key and secret hash fields are masked.

### Fixed
- Checkout showed nothing when Flutterwave rejected the payment request; it now shows an error.
- The plugin version constant said 1.0.6 while the plugin header said 1.0.7.
- Name fields were never rendered on the payment form (and `split_name` never showed first/last name).
- Editors saw the "API keys missing" notice instead of the form on configured sites.
- Translation loading was triggered too early on WordPress 6.7+.
- Checkout errors were not shown to customers.

### Development
- Dependencies upgraded; `npm audit` and `composer audit` report no vulnerabilities.
- New test suites: unit (PHPUnit + Brain Monkey), integration (WordPress test suite via wp-env) and end-to-end (Playwright).
- WordPress Coding Standards 3, ESLint flat config, Node.js 24, GitHub Actions pinned to commit SHAs.

## 1.0.7 | 29-08-2023
Update admin settings view for Transactions.

### Version Changes
- [FIXED] Missing WP_List_Table implementation.
- [CHANGED] Tested to WP version 6.3 (latest).

## 1.0.6 | 17-05-2023
Security updates and libraty enhancements. This release add the necessary enhancements for faster payment verifications, vulnerabilities fix and UX improvements.

### Version changes
- [ADDED] Webhook Handler to handle notification from Flutterwave.
- [ADDED] Transaction Endpoint to handle Payment Requests and Confirmation.
- [CHANGED] Payment Checkout to redirect users to a page hosted by Flutterwave.
- [CHANGED] Payment Shortcodes User Experience and Features.
