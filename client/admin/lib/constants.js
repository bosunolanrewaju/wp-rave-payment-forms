/**
 * Shared copy and option lists for the admin app.
 */

import { __ } from '@wordpress/i18n';

export const STEPS = [
	{ key: 'general', label: __( 'General details', 'rave-payment-forms' ) },
	{ key: 'api', label: __( 'API & webhook', 'rave-payment-forms' ) },
	{ key: 'methods', label: __( 'Payment methods', 'rave-payment-forms' ) },
	{ key: 'redirects', label: __( 'Redirects', 'rave-payment-forms' ) },
];

/**
 * Field keys saved by each step or tab. Keep in step with FLW_Settings on the PHP side.
 */
export const STEP_FIELDS = {
	general: [
		'title',
		'description',
		'logoUrl',
		'buttonText',
		'currency',
		'country',
		'donationTitle',
		'donationDescription',
	],
	api: [ 'publicKey', 'secretKey', 'secretHash' ],
	methods: [ 'paymentMethods', 'advancedMethods', 'useThemeStyle' ],
	redirects: [ 'successUrl', 'failedUrl', 'pendingUrl' ],
};

export const CURRENCY_OPTIONS = [
	{
		value: 'any',
		label: __( 'Let customers choose', 'rave-payment-forms' ),
	},
	...[
		'NGN',
		'GHS',
		'KES',
		'USD',
		'GBP',
		'EUR',
		'ZAR',
		'TZS',
		'UGX',
		'RWF',
		'ZMW',
	].map( ( code ) => ( { value: code, label: code } ) ),
];

export const COUNTRY_OPTIONS = [
	{ value: 'NG', label: __( 'Nigeria', 'rave-payment-forms' ) },
	{ value: 'GH', label: __( 'Ghana', 'rave-payment-forms' ) },
	{ value: 'KE', label: __( 'Kenya', 'rave-payment-forms' ) },
	{ value: 'ZA', label: __( 'South Africa', 'rave-payment-forms' ) },
	{ value: 'TZ', label: __( 'Tanzania', 'rave-payment-forms' ) },
	{ value: 'UG', label: __( 'Uganda', 'rave-payment-forms' ) },
	{ value: 'RW', label: __( 'Rwanda', 'rave-payment-forms' ) },
	{ value: 'ZM', label: __( 'Zambia', 'rave-payment-forms' ) },
	{ value: 'US', label: __( 'Worldwide', 'rave-payment-forms' ) },
];

/**
 * The checkbox list on the "Payment methods" step. `key` maps to the groups in
 * FLW_Settings::METHOD_MAP on the PHP side.
 */
export const PAYMENT_METHODS = [
	{
		key: 'card',
		label: __( 'Cards (credit/debit cards)', 'rave-payment-forms' ),
		description: __(
			'Pay securely using your Visa, Mastercard, or American Express.',
			'rave-payment-forms'
		),
	},
	{
		key: 'stablecoin',
		label: __( 'Stablecoin', 'rave-payment-forms' ),
		description: __(
			'Pay with USDT or USDC. Fast, global, and low-fee.',
			'rave-payment-forms'
		),
	},
	{
		key: 'banktransfer',
		label: __( 'Bank Transfer', 'rave-payment-forms' ),
		description: __(
			"Transfer funds directly from your bank account using your bank's transfer service.",
			'rave-payment-forms'
		),
	},
	{
		key: 'mobilemoney',
		label: __( 'Mobile Money', 'rave-payment-forms' ),
		description: __(
			'Quick and secure payment directly from your mobile wallet.',
			'rave-payment-forms'
		),
	},
	{
		key: 'applepay',
		label: __( 'Apple Pay', 'rave-payment-forms' ),
		description: __(
			'Check out in seconds using your Apple Pay wallet.',
			'rave-payment-forms'
		),
	},
	{
		key: 'googlepay',
		label: __( 'Google Pay', 'rave-payment-forms' ),
		description: __(
			'Simple and fast checkout using the cards saved to your Google Account.',
			'rave-payment-forms'
		),
	},
	{
		key: 'opay',
		label: __( 'Opay', 'rave-payment-forms' ),
		description: __(
			'Pay directly from your OPay wallet or card.',
			'rave-payment-forms'
		),
	},
];

/**
 * Flutterwave payment options without a checkbox. Kept editable under
 * "Advanced" so upgrading merchants never silently lose them.
 */
export const ADVANCED_METHODS = [
	{ key: 'ussd', label: __( 'USSD', 'rave-payment-forms' ) },
	{ key: 'qr', label: __( 'QR', 'rave-payment-forms' ) },
	{ key: 'nqr', label: __( 'NQR', 'rave-payment-forms' ) },
	{ key: 'credit', label: __( 'Credit', 'rave-payment-forms' ) },
	{ key: 'barter', label: __( 'Barter', 'rave-payment-forms' ) },
];

export const TOOLTIPS = {
	title: __(
		'Shown at the top of the Flutterwave payment page. Defaults to your site name.',
		'rave-payment-forms'
	),
	currency: __(
		'Forms charge in this currency unless a shortcode sets its own.',
		'rave-payment-forms'
	),
	publicKey: __(
		'Your Flutterwave public key. Use a test key (FLWPUBK_TEST-…) to try payments without real money.',
		'rave-payment-forms'
	),
	secretKey: __(
		'Your Flutterwave secret key, used to verify payments. It must be from the same mode as the public key.',
		'rave-payment-forms'
	),
};
