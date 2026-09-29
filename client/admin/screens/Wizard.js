/**
 * The four-step onboarding wizard.
 *
 * Each step saves on "Save & continue" so a merchant who drops out halfway
 * keeps what they entered, and the final step flips the onboarding flag.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Page, StepBadge, Stepper, Button, LoadingScreen } from '../components';
import { GeneralForm, ApiForm, MethodsForm, RedirectsForm } from './forms';
import { STEPS, STEP_FIELDS } from '../lib/constants';
import { saveSettings, completeOnboarding } from '../lib/api';
import {
	validateGeneral,
	validateApi,
	validateMethods,
	validateRedirects,
	formatErrors,
} from '../lib/validate';

const SUBTITLE = __(
	'Please fill in your correct details to set up your payment forms',
	'rave-payment-forms'
);

const STEP_META = [
	{
		title: __( 'General details', 'rave-payment-forms' ),
		subtitle: SUBTITLE,
		crumb: __( 'Flutterwave Payments', 'rave-payment-forms' ),
		fields: STEP_FIELDS.general,
		validate: validateGeneral,
		Form: GeneralForm,
	},
	{
		title: __( 'API & webhook', 'rave-payment-forms' ),
		subtitle: __(
			'Find your keys under Settings → API keys in your Flutterwave dashboard.',
			'rave-payment-forms'
		),
		crumb: __( 'General', 'rave-payment-forms' ),
		fields: STEP_FIELDS.api,
		validate: validateApi,
		Form: ApiForm,
	},
	{
		title: __( 'Payment methods', 'rave-payment-forms' ),
		subtitle: __(
			'Choose how customers can pay through your forms.',
			'rave-payment-forms'
		),
		crumb: __( 'API & Webhook', 'rave-payment-forms' ),
		fields: STEP_FIELDS.methods,
		validate: validateMethods,
		Form: MethodsForm,
	},
	{
		title: __( 'Redirects', 'rave-payment-forms' ),
		subtitle: __(
			'Where customers land after paying. Your home page works to start with.',
			'rave-payment-forms'
		),
		crumb: __( 'Payment methods', 'rave-payment-forms' ),
		fields: STEP_FIELDS.redirects,
		validate: validateRedirects,
		Form: RedirectsForm,
	},
];

/**
 * @param {Object}   props
 * @param {Object}   props.values     Current form values.
 * @param {Function} props.setValue   `( key, value ) => void`.
 * @param {Function} props.onSaved    Receives the payload returned by the API.
 * @param {Function} props.onFinished Called after the final step saves.
 * @param {Function} props.onError    Receives an error message.
 * @return {Element} The wizard.
 */
const Wizard = ( { values, setValue, onSaved, onFinished, onError } ) => {
	const [ index, setIndex ] = useState( 0 );
	const [ busy, setBusy ] = useState( false );
	const [ submitting, setSubmitting ] = useState( false );
	const [ attempted, setAttempted ] = useState( false );

	const meta = STEP_META[ index ];
	const { Form } = meta;
	const isLast = index === STEP_META.length - 1;
	const found = meta.validate( values );

	// Mirrors the designs: the primary button stays faded until the step is
	// actually completable. Format problems show straight away so a faded
	// button always has a visible reason; "required" errors wait for a click.
	const complete = Object.keys( found ).length === 0;
	const errors = attempted ? found : formatErrors( found, values );

	const goTo = ( next ) => {
		setAttempted( false );
		setIndex( next );
	};

	const patchFor = ( step ) =>
		step.fields.reduce(
			( patch, key ) => ( { ...patch, [ key ]: values[ key ] } ),
			{}
		);

	const advance = async () => {
		if ( ! complete ) {
			setAttempted( true );
			return;
		}

		setBusy( true );

		try {
			if ( isLast ) {
				setSubmitting( true );
				const payload = await completeOnboarding( patchFor( meta ) );
				onSaved( payload );
				onFinished();
			} else {
				const payload = await saveSettings( patchFor( meta ) );
				onSaved( payload );
				goTo( index + 1 );
			}
		} catch ( error ) {
			setSubmitting( false );
			onError(
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

	if ( submitting ) {
		return <LoadingScreen />;
	}

	return (
		<Page
			backLabel={ meta.crumb }
			onBack={ index > 0 ? () => goTo( index - 1 ) : undefined }
			aside={ <Stepper steps={ STEPS } activeIndex={ index } /> }
		>
			<StepBadge current={ index + 1 } total={ STEP_META.length } />

			<h1 className="flw-heading">{ meta.title }</h1>
			<p className="flw-subheading">{ meta.subtitle }</p>
			<hr className="flw-rule" />

			<Form values={ values } setValue={ setValue } errors={ errors } />

			<div className={ `flw-actions ${ index > 0 ? 'has-back' : '' }` }>
				{ index > 0 && (
					<Button
						variant="secondary"
						onClick={ () => goTo( index - 1 ) }
						disabled={ busy }
					>
						{ __( 'Go back', 'rave-payment-forms' ) }
					</Button>
				) }
				<Button
					onClick={ advance }
					busy={ busy }
					disabled={ ! complete }
				>
					{ isLast
						? __( 'Save', 'rave-payment-forms' )
						: __( 'Save & continue', 'rave-payment-forms' ) }
				</Button>
			</div>
		</Page>
	);
};

export default Wizard;
