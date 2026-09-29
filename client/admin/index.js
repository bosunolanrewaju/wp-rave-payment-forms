/**
 * Mounts the Flutterwave admin app.
 */

import { createRoot } from '@wordpress/element';
import App from './app';
import PaymentForms from './screens/PaymentForms';
import Transactions from './screens/Transactions';
import Integrations from './screens/Integrations';
import './admin.scss';

const mount = () => {
	const root = document.getElementById( 'flutterwave-admin-root' );

	if ( ! root ) {
		return;
	}

	const screens = {
		forms: PaymentForms,
		transactions: Transactions,
		integrations: Integrations,
	};
	const Screen = screens[ root.dataset.screen ] || App;

	createRoot( root ).render( <Screen /> );
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
