/**
 * The form types shared by the Payment Forms screen and the builder.
 */

import { __ } from '@wordpress/i18n';
import {
	buttonIcon,
	formIcon,
	donationIcon,
	pricingIcon,
	methodsIcon,
} from '../../blocks/icons';

export const FORM_TYPES = [
	{
		key: 'payment-button',
		label: __( 'Payment button', 'rave-payment-forms' ),
		plural: __( 'Buttons', 'rave-payment-forms' ),
		description: __(
			'A fixed price with a one-line checkout.',
			'rave-payment-forms'
		),
		icon: buttonIcon,
		buildable: true,
	},
	{
		key: 'payment-form',
		label: __( 'Payment form', 'rave-payment-forms' ),
		plural: __( 'Forms', 'rave-payment-forms' ),
		description: __( 'Invoices and custom amounts.', 'rave-payment-forms' ),
		icon: formIcon,
		buildable: true,
	},
	{
		key: 'donation-form',
		label: __( 'Donation form', 'rave-payment-forms' ),
		plural: __( 'Donations', 'rave-payment-forms' ),
		description: __( 'One-off or recurring gifts.', 'rave-payment-forms' ),
		icon: donationIcon,
		buildable: true,
	},
	{
		key: 'pricing-card',
		label: __( 'Pricing card', 'rave-payment-forms' ),
		plural: __( 'Pricing', 'rave-payment-forms' ),
		description: __(
			'A plan or product with its features.',
			'rave-payment-forms'
		),
		icon: pricingIcon,
		buildable: true,
	},
	{
		key: 'payment-methods',
		label: __( 'Payment methods badge', 'rave-payment-forms' ),
		plural: __( 'Badges', 'rave-payment-forms' ),
		description: __(
			'Shows the payment methods you accept.',
			'rave-payment-forms'
		),
		icon: methodsIcon,
		buildable: false,
	},
];

/**
 * The human name of a form type.
 *
 * @param {string} key Type key.
 * @return {string} Label.
 */
export const typeLabel = ( key ) =>
	FORM_TYPES.find( ( type ) => type.key === key )?.label || key;
