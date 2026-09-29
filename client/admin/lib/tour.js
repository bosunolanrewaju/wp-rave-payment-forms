/**
 * Guided tips: the steps for each screen, and where to place the tooltip.
 *
 * Bump TOUR_VERSION when the screens change enough to be worth showing again:
 * tours are remembered as "screen@version", so a new version reappears once.
 */

import { __ } from '@wordpress/i18n';

export const TOUR_VERSION = 1;

/**
 * A link in the Flutterwave admin menu.
 *
 * @param {string} page Page slug.
 * @return {string} Selector.
 */
const menuLink = ( page ) => `#adminmenu a[href$="page=${ page }"]`;

/**
 * Steps per screen. A step without a target is shown in the middle of the page;
 * a step whose target is missing or hidden (for example the admin menu on a
 * phone, or a table with no rows yet) is skipped.
 *
 * @return {Object<string, Array>} Steps keyed by screen.
 */
export const tourSteps = () => ( {
	settings: [
		{
			title: __(
				'Welcome to your Flutterwave dashboard',
				'rave-payment-forms'
			),
			body: __(
				'You’re set up to take payments. Here’s a one-minute look at what you can do next.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="settings-tabs"]',
			title: __( 'Your settings', 'rave-payment-forms' ),
			body: __(
				'Update your business details, API keys, payment methods and redirects here, one tab at a time.',
				'rave-payment-forms'
			),
		},
		{
			target: menuLink( 'flutterwave-payment-forms' ),
			placement: 'right',
			title: __( 'Payment Forms', 'rave-payment-forms' ),
			body: __(
				'Create payment buttons, forms, donation forms and pricing cards, and see the pages they’re on.',
				'rave-payment-forms'
			),
		},
		{
			target: menuLink( 'flutterwave-payments-transactions' ),
			placement: 'right',
			title: __( 'Transactions', 'rave-payment-forms' ),
			body: __(
				'Every payment in one place. Filter by date, status and currency, and download a CSV.',
				'rave-payment-forms'
			),
		},
		{
			target: menuLink( 'flutterwave-payments-integrations' ),
			placement: 'right',
			title: __( 'Integrations', 'rave-payment-forms' ),
			body: __(
				'Take Flutterwave payments in WooCommerce, Easy Digital Downloads and GiveWP.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="help"]',
			title: __( 'Help is always here', 'rave-payment-forms' ),
			body: __(
				'Replay a tour or turn tips off from this menu at any time.',
				'rave-payment-forms'
			),
		},
	],
	forms: [
		{
			target: '[data-tour="new-form"]',
			title: __( 'Create a form', 'rave-payment-forms' ),
			body: __(
				'Pick a payment button, form, donation form or pricing card, preview it, and we’ll put it on a new page for you.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="form-types"]',
			title: __( 'Filter by type', 'rave-payment-forms' ),
			body: __(
				'Every block and shortcode on your site is listed here, whichever way you added it.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="form-row"]',
			title: __( 'See what each form earns', 'rave-payment-forms' ),
			body: __(
				'Each row shows the page a form is on, whether it’s live, and the payments it has taken.',
				'rave-payment-forms'
			),
		},
	],
	transactions: [
		{
			target: '[data-tour="filter"]',
			title: __( 'Find a payment', 'rave-payment-forms' ),
			body: __(
				'Narrow the list by date, status or currency. The last year is shown to start with.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="download"]',
			title: __( 'Download for your records', 'rave-payment-forms' ),
			body: __(
				'Export any date range as a CSV file for your spreadsheet or accountant.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="transaction-row"]',
			title: __( 'Open a transaction', 'rave-payment-forms' ),
			body: __(
				'Click a row for the full details. You can re-check a pending payment with Flutterwave from there.',
				'rave-payment-forms'
			),
		},
	],
	integrations: [
		{
			target: '[data-tour="integrations-available"]',
			title: __( 'Connect your plugins', 'rave-payment-forms' ),
			body: __(
				'Use Flutterwave in the store or donation plugin you already run. Install, activate or switch on each one from its card.',
				'rave-payment-forms'
			),
		},
		{
			target: '[data-tour="integrations-soon"]',
			title: __( 'More on the way', 'rave-payment-forms' ),
			body: __(
				'These are coming next. Tell us which plugin you’d like us to add from the link at the bottom of the page.',
				'rave-payment-forms'
			),
		},
	],
} );

/**
 * The key a finished tour is remembered by.
 *
 * @param {string} screen Screen name.
 * @return {string} e.g. "transactions@1".
 */
export const tourKey = ( screen ) => `${ screen }@${ TOUR_VERSION }`;

/**
 * Whether a screen's tour should start by itself.
 *
 * @param {Object}  state     `{ enabled, seen }` for the current user.
 * @param {string}  screen    Screen name.
 * @param {boolean} onboarded Whether onboarding is finished.
 * @return {boolean} True the first time a screen is opened with tips on.
 */
export const shouldAutoStart = ( state, screen, onboarded ) =>
	Boolean(
		onboarded &&
		state?.enabled &&
		! state.seen?.includes( tourKey( screen ) )
	);

/**
 * The newer of the server's tour state and the copy kept in this browser.
 *
 * Saving happens in the background, so a merchant who closes a tip and moves
 * straight to another page can load it before the save lands. The browser copy
 * covers that gap; the server's copy wins when it is newer (another browser).
 *
 * @param {Object}      server `{ enabled, seen, updated }`, updated in seconds.
 * @param {Object|null} local  `{ enabled, seen, at }`, at in milliseconds.
 * @return {Object} `{ enabled, seen }`.
 */
export const pickTourState = ( server, local ) => {
	const fromServer = {
		enabled: server?.enabled !== false,
		seen: Array.isArray( server?.seen ) ? server.seen : [],
	};

	if (
		! local ||
		! Array.isArray( local.seen ) ||
		! ( local.at / 1000 > ( server?.updated || 0 ) )
	) {
		return fromServer;
	}

	return { enabled: local.enabled !== false, seen: local.seen };
};

const GAP = 14;
const MARGIN = 16;

/**
 * Keep a value inside a range.
 *
 * @param {number} value Value.
 * @param {number} min   Lower bound.
 * @param {number} max   Upper bound.
 * @return {number} Clamped value.
 */
const clamp = ( value, min, max ) =>
	Math.min( Math.max( value, min ), Math.max( min, max ) );

/**
 * Where to put the tooltip next to its target.
 *
 * Prefers the requested side and flips when there is not enough room, then
 * keeps the card inside the viewport. The arrow points at the target's centre.
 *
 * @param {Object} target    Target rect `{ top, left, width, height }`, or null to centre.
 * @param {Object} card      Card size `{ width, height }`.
 * @param {Object} viewport  Viewport size `{ width, height }`.
 * @param {string} placement "bottom" (default), "top" or "right".
 * @return {Object} `{ top, left, side, arrow }`, where arrow is the offset along the card edge.
 */
export const placeTooltip = (
	target,
	card,
	viewport,
	placement = 'bottom'
) => {
	if ( ! target ) {
		return {
			top: Math.max( MARGIN, ( viewport.height - card.height ) / 2 ),
			left: Math.max( MARGIN, ( viewport.width - card.width ) / 2 ),
			side: 'center',
			arrow: null,
		};
	}

	const centreX = target.left + target.width / 2;
	const centreY = target.top + target.height / 2;
	const fitsBelow =
		target.top + target.height + GAP + card.height <=
		viewport.height - MARGIN;
	const fitsAbove = target.top - GAP - card.height >= MARGIN;
	const fitsRight =
		target.left + target.width + GAP + card.width <=
		viewport.width - MARGIN;

	let side = placement;

	if ( 'right' === side && ! fitsRight ) {
		side = 'bottom';
	}

	if ( 'bottom' === side && ! fitsBelow && fitsAbove ) {
		side = 'top';
	} else if ( 'top' === side && ! fitsAbove && fitsBelow ) {
		side = 'bottom';
	}

	if ( 'right' === side ) {
		const top = clamp(
			centreY - card.height / 2,
			MARGIN,
			viewport.height - card.height - MARGIN
		);

		return {
			top,
			left: target.left + target.width + GAP,
			side,
			arrow: clamp( centreY - top, 16, card.height - 16 ),
		};
	}

	const left = clamp(
		centreX - card.width / 2,
		MARGIN,
		viewport.width - card.width - MARGIN
	);

	return {
		top:
			'top' === side
				? target.top - GAP - card.height
				: target.top + target.height + GAP,
		left,
		side,
		arrow: clamp( centreX - left, 16, card.width - 16 ),
	};
};
