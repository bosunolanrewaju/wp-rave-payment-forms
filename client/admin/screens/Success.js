/**
 * Terminal screen of the wizard: confirms setup and hands the merchant the
 * shortcode they need next, since a form only appears once it is on a page.
 */

import { __ } from '@wordpress/i18n';
import { SuccessIllustration } from '../icons';
import { Button, CopyField } from '../components';
import { adminData } from '../lib/api';

const SHORTCODE = '[flw-pay-form amount="5000"]';

/**
 * @param {Object}   props
 * @param {Function} props.onDone Opens the settings screen.
 * @return {Element} The success screen.
 */
const Success = ( { onDone } ) => {
	const { newPageUrl } = adminData();

	return (
		<div className="flw-success">
			<SuccessIllustration />
			<h1>{ __( 'Setup successful!', 'rave-payment-forms' ) }</h1>
			<p>
				{ __(
					'Your Flutterwave account is connected. Add a payment form to any page with this shortcode:',
					'rave-payment-forms'
				) }
			</p>

			<div className="flw-success__shortcode">
				<CopyField id="flw-shortcode" value={ SHORTCODE } />
				<p className="flw-field__hint">
					{ __(
						'Leave out amount to let customers enter their own, or use [flw-donation-form] for donations.',
						'rave-payment-forms'
					) }
				</p>
			</div>

			<div className="flw-actions">
				{ newPageUrl && (
					<Button
						onClick={ () => window.location.assign( newPageUrl ) }
					>
						{ __( 'Create a page', 'rave-payment-forms' ) }
					</Button>
				) }
				<Button variant="secondary" onClick={ onDone }>
					{ __( 'Go to settings', 'rave-payment-forms' ) }
				</Button>
			</div>
		</div>
	);
};

export default Success;
