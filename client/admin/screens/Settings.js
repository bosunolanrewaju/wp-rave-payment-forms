/**
 * Post-onboarding screen: the same groups of fields as the wizard, now as tabs.
 *
 * Saving from any tab persists that tab's fields only, matching the wizard so
 * an unsaved edit on one tab can never overwrite another.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Page, Button } from '../components';
import { HelpMenu, Tour, useTour } from '../components/tour';
import { GeneralForm, ApiForm, MethodsForm, RedirectsForm } from './forms';
import { STEP_FIELDS } from '../lib/constants';
import { saveSettings } from '../lib/api';
import {
	validateGeneral,
	validateApi,
	validateMethods,
	validateRedirects,
	formatErrors,
} from '../lib/validate';

const SAVE_AND_CONTINUE = __( 'Save & continue', 'rave-payment-forms' );

const TABS = [
	{
		key: 'general',
		label: __( 'General', 'rave-payment-forms' ),
		crumb: __( 'Flutterwave Payments', 'rave-payment-forms' ),
		fields: STEP_FIELDS.general,
		validate: validateGeneral,
		Form: GeneralForm,
		cta: SAVE_AND_CONTINUE,
	},
	{
		key: 'api',
		label: __( 'API & Webhook', 'rave-payment-forms' ),
		crumb: __( 'General', 'rave-payment-forms' ),
		fields: STEP_FIELDS.api,
		validate: validateApi,
		Form: ApiForm,
		cta: SAVE_AND_CONTINUE,
	},
	{
		key: 'methods',
		label: __( 'Payment Methods', 'rave-payment-forms' ),
		crumb: __( 'API & Webhook', 'rave-payment-forms' ),
		fields: STEP_FIELDS.methods,
		validate: validateMethods,
		Form: MethodsForm,
		cta: SAVE_AND_CONTINUE,
	},
	{
		key: 'redirects',
		label: __( 'Redirects', 'rave-payment-forms' ),
		crumb: __( 'Payment Methods', 'rave-payment-forms' ),
		fields: STEP_FIELDS.redirects,
		validate: validateRedirects,
		Form: RedirectsForm,
		cta: __( 'Save', 'rave-payment-forms' ),
	},
];

/**
 * The tab named by `?tab=` in the URL, falling back to the first.
 *
 * @return {number} Tab index.
 */
const initialTab = () => {
	const key = new URLSearchParams( window.location.search ).get( 'tab' );
	const index = TABS.findIndex( ( item ) => item.key === key );

	return index > -1 ? index : 0;
};

/**
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Function} props.onSaved  Receives the payload returned by the API.
 * @param {Function} props.onNotice `( status, message ) => void`.
 * @return {Element} The settings screen.
 */
const Settings = ( { values, setValue, onSaved, onNotice } ) => {
	const [ active, setActive ] = useState( initialTab );
	// This screen only shows once onboarding is done, including straight after the wizard.
	const tour = useTour( 'settings', { onboarded: true } );
	const [ busy, setBusy ] = useState( false );
	const [ attempted, setAttempted ] = useState( false );

	const tab = TABS[ active ];
	const { Form } = tab;
	const found = tab.validate( values );
	const errors = attempted ? found : formatErrors( found, values );

	const goTo = ( next ) => {
		setAttempted( false );
		setActive( next );
	};

	const save = async () => {
		if ( Object.keys( found ).length ) {
			setAttempted( true );
			return;
		}

		setBusy( true );

		try {
			const patch = tab.fields.reduce(
				( acc, key ) => ( { ...acc, [ key ]: values[ key ] } ),
				{}
			);
			const payload = await saveSettings( patch );
			onSaved( payload );
			onNotice( 'success', __( 'Changes saved.', 'rave-payment-forms' ) );

			if ( active < TABS.length - 1 ) {
				goTo( active + 1 );
			}
		} catch ( error ) {
			onNotice(
				'error',
				error?.message ||
					__(
						'Could not save your settings. Please try again.',
						'rave-payment-forms'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<Page
			wide
			backLabel={ tab.crumb }
			onBack={ active > 0 ? () => goTo( active - 1 ) : undefined }
			actions={ <HelpMenu tour={ tour } /> }
		>
			<Tour tour={ tour } />
			<div className="flw-tabs" role="tablist" data-tour="settings-tabs">
				{ TABS.map( ( item, index ) => (
					<button
						key={ item.key }
						type="button"
						role="tab"
						aria-selected={ index === active }
						className={ `flw-tab ${ index === active ? 'is-active' : '' }` }
						onClick={ () => {
							goTo( index );
						} }
					>
						{ item.label }
					</button>
				) ) }
			</div>

			<div className="flw-tabpanel" role="tabpanel">
				<Form
					values={ values }
					setValue={ setValue }
					errors={ errors }
				/>

				<div className="flw-actions">
					<Button onClick={ save } busy={ busy }>
						{ tab.cta }
					</Button>
				</div>
			</div>
		</Page>
	);
};

export default Settings;
