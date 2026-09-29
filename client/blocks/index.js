/**
 * Registers the Flutterwave blocks in the editor. Rendering happens on the
 * server (blocks/<name>/render.php), so there is no save().
 */

import { registerBlockType } from '@wordpress/blocks';
import './editor.scss';

import paymentButton from '../../blocks/payment-button/block.json';
import paymentForm from '../../blocks/payment-form/block.json';
import donationForm from '../../blocks/donation-form/block.json';
import pricingCard from '../../blocks/pricing-card/block.json';
import paymentMethods from '../../blocks/payment-methods/block.json';

import PaymentButtonEdit from './edit/payment-button';
import PaymentFormEdit from './edit/payment-form';
import DonationFormEdit from './edit/donation-form';
import PricingCardEdit from './edit/pricing-card';
import PaymentMethodsEdit from './edit/payment-methods';

import {
	buttonIcon,
	formIcon,
	donationIcon,
	pricingIcon,
	methodsIcon,
} from './icons';

[
	[ paymentButton, PaymentButtonEdit, buttonIcon ],
	[ paymentForm, PaymentFormEdit, formIcon ],
	[ donationForm, DonationFormEdit, donationIcon ],
	[ pricingCard, PricingCardEdit, pricingIcon ],
	[ paymentMethods, PaymentMethodsEdit, methodsIcon ],
].forEach( ( [ metadata, edit, icon ] ) => {
	registerBlockType( metadata.name, {
		...metadata,
		icon,
		edit,
		save: () => null,
	} );
} );
