/**
 * The form bodies, shared by the onboarding wizard and the tabbed settings
 * screen so both surfaces stay in step with one another.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Field,
	TextInput,
	TextArea,
	Select,
	Checkbox,
	CopyField,
	SecretInput,
} from '../components';
import { METHOD_MARKS } from '../icons';
import {
	CURRENCY_OPTIONS,
	COUNTRY_OPTIONS,
	PAYMENT_METHODS,
	ADVANCED_METHODS,
	TOOLTIPS,
} from '../lib/constants';
import { keyMode } from '../lib/validate';

/**
 * A random 64-character hex string, the same shape the server generates.
 *
 * @return {string} The new secret hash.
 */
const generateSecretHash = () => {
	const bytes = new Uint8Array( 32 );
	window.crypto.getRandomValues( bytes );

	return Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
};

/**
 * Step 1: how the forms and the Flutterwave payment page present the business.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const GeneralForm = ( { values, setValue, errors = {} } ) => (
	<>
		<Field
			id="flw-title"
			label={ __( 'Payment page title', 'rave-payment-forms' ) }
			tooltip={ TOOLTIPS.title }
			error={ errors.title }
		>
			<TextInput
				id="flw-title"
				value={ values.title }
				placeholder={ __(
					'Enter your business name',
					'rave-payment-forms'
				) }
				onChange={ ( event ) =>
					setValue( 'title', event.target.value )
				}
			/>
		</Field>

		<Field
			id="flw-description"
			label={ __( 'Description', 'rave-payment-forms' ) }
		>
			<TextArea
				id="flw-description"
				value={ values.description }
				placeholder={ __(
					'What customers are paying for',
					'rave-payment-forms'
				) }
				onChange={ ( event ) =>
					setValue( 'description', event.target.value )
				}
			/>
		</Field>

		<Field
			id="flw-logo"
			label={ __( 'Logo URL', 'rave-payment-forms' ) }
			hint={ __(
				'Optional. A square image shown on the payment page.',
				'rave-payment-forms'
			) }
			error={ errors.logoUrl }
		>
			<TextInput
				id="flw-logo"
				type="url"
				value={ values.logoUrl }
				placeholder="https://"
				onChange={ ( event ) =>
					setValue( 'logoUrl', event.target.value )
				}
			/>
		</Field>

		<div className="flw-field-row">
			<Field
				id="flw-currency"
				label={ __( 'Default currency', 'rave-payment-forms' ) }
				tooltip={ TOOLTIPS.currency }
				error={ errors.currency }
			>
				<Select
					id="flw-currency"
					value={ values.currency }
					options={ CURRENCY_OPTIONS }
					placeholder={ __(
						'Select currency',
						'rave-payment-forms'
					) }
					onChange={ ( event ) =>
						setValue( 'currency', event.target.value )
					}
				/>
			</Field>

			<Field
				id="flw-country"
				label={ __( 'Country', 'rave-payment-forms' ) }
			>
				<Select
					id="flw-country"
					value={ values.country }
					options={ COUNTRY_OPTIONS }
					placeholder={ __( 'Select country', 'rave-payment-forms' ) }
					onChange={ ( event ) =>
						setValue( 'country', event.target.value )
					}
				/>
			</Field>
		</div>

		<Field
			id="flw-button-text"
			label={ __( 'Pay button text', 'rave-payment-forms' ) }
		>
			<TextInput
				id="flw-button-text"
				value={ values.buttonText }
				placeholder={ __(
					'Proceed to Flutterwave',
					'rave-payment-forms'
				) }
				onChange={ ( event ) =>
					setValue( 'buttonText', event.target.value )
				}
			/>
		</Field>

		<h2 className="flw-section-title">
			{ __( 'Donation form', 'rave-payment-forms' ) }
		</h2>
		<p className="flw-section-note">
			{ __(
				'Optional. Shown above the [flw-donation-form] shortcode.',
				'rave-payment-forms'
			) }
		</p>

		<Field
			id="flw-donation-title"
			label={ __( 'Heading', 'rave-payment-forms' ) }
		>
			<TextInput
				id="flw-donation-title"
				value={ values.donationTitle }
				placeholder={ __( 'Support our work', 'rave-payment-forms' ) }
				onChange={ ( event ) =>
					setValue( 'donationTitle', event.target.value )
				}
			/>
		</Field>

		<Field
			id="flw-donation-description"
			label={ __( 'Message', 'rave-payment-forms' ) }
		>
			<TextArea
				id="flw-donation-description"
				rows={ 3 }
				value={ values.donationDescription }
				onChange={ ( event ) =>
					setValue( 'donationDescription', event.target.value )
				}
			/>
		</Field>
	</>
);

/**
 * Step 2: API keys, plus the webhook URL and secret hash to copy into the
 * Flutterwave dashboard.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const ApiForm = ( { values, setValue, errors = {} } ) => {
	const mode = keyMode( values.publicKey, 'FLWPUBK' );

	return (
		<>
			{ mode && (
				<p className={ `flw-mode flw-mode--${ mode }` }>
					{ 'live' === mode
						? __(
								'Live keys: customers will be charged real money.',
								'rave-payment-forms'
							)
						: __(
								'Test keys: payments are simulated, no real money moves.',
								'rave-payment-forms'
							) }
				</p>
			) }

			<Field
				id="flw-publicKey"
				label={ __( 'Public key', 'rave-payment-forms' ) }
				tooltip={ TOOLTIPS.publicKey }
				error={ errors.publicKey }
			>
				<TextInput
					id="flw-publicKey"
					value={ values.publicKey }
					placeholder="FLWPUBK_TEST-…"
					spellCheck="false"
					autoComplete="off"
					onChange={ ( event ) =>
						setValue( 'publicKey', event.target.value.trim() )
					}
				/>
			</Field>

			<Field
				id="flw-secretKey"
				label={ __( 'Secret key', 'rave-payment-forms' ) }
				tooltip={ TOOLTIPS.secretKey }
				error={ errors.secretKey }
			>
				<SecretInput
					id="flw-secretKey"
					value={ values.secretKey }
					placeholder="FLWSECK_TEST-…"
					onChange={ ( event ) =>
						setValue( 'secretKey', event.target.value.trim() )
					}
				/>
			</Field>

			<Field
				id="flw-webhook"
				label={ __( 'Webhook URL', 'rave-payment-forms' ) }
				hint={ __(
					'Add this URL to the webhook settings in your Flutterwave dashboard.',
					'rave-payment-forms'
				) }
			>
				<CopyField id="flw-webhook" value={ values.webhookUrl } />
			</Field>

			<Field
				id="flw-secret-hash"
				label={ __( 'Secret hash', 'rave-payment-forms' ) }
				hint={ __(
					'Copy this into the secret hash field of your Flutterwave webhook settings. Webhooks are rejected unless the two match.',
					'rave-payment-forms'
				) }
			>
				<div className="flw-copyfield">
					<TextInput
						id="flw-secret-hash"
						value={ values.secretHash }
						spellCheck="false"
						autoComplete="off"
						onChange={ ( event ) =>
							setValue( 'secretHash', event.target.value )
						}
					/>
					<button
						type="button"
						className="flw-copyfield__btn"
						onClick={ () =>
							setValue( 'secretHash', generateSecretHash() )
						}
					>
						{ __( 'Generate', 'rave-payment-forms' ) }
					</button>
				</div>
			</Field>
		</>
	);
};

/**
 * Step 3: the payment methods offered at checkout, plus the advanced options
 * existing merchants may already rely on.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const MethodsForm = ( { values, setValue, errors = {} } ) => {
	const [ advancedOpen, setAdvancedOpen ] = useState( false );

	const toggle = ( listKey, key, checked ) => {
		const current = values[ listKey ] || [];
		setValue(
			listKey,
			checked
				? [ ...new Set( [ ...current, key ] ) ]
				: current.filter( ( item ) => item !== key )
		);
	};

	return (
		<>
			<div className="flw-methods">
				{ PAYMENT_METHODS.map( ( method ) => {
					const Mark = METHOD_MARKS[ method.key ];
					const checked = ( values.paymentMethods || [] ).includes(
						method.key
					);

					return (
						<label
							key={ method.key }
							className="flw-method"
							htmlFor={ `flw-method-${ method.key }` }
						>
							<input
								id={ `flw-method-${ method.key }` }
								type="checkbox"
								checked={ checked }
								onChange={ ( event ) =>
									toggle(
										'paymentMethods',
										method.key,
										event.target.checked
									)
								}
							/>
							<span
								className="flw-checkbox__box"
								aria-hidden="true"
							>
								<svg
									viewBox="0 0 16 16"
									width="12"
									height="12"
									aria-hidden="true"
								>
									<path
										d="m3.5 8.4 3 3 6-6.8"
										fill="none"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</span>
							<span className="flw-method__mark">
								{ Mark && <Mark /> }
							</span>
							<span className="flw-method__text">
								<span className="flw-method__title">
									{ method.label }
								</span>
								<span className="flw-method__desc">
									{ method.description }
								</span>
							</span>
						</label>
					);
				} ) }
			</div>

			{ errors.paymentMethods && (
				<p className="flw-field__error">{ errors.paymentMethods }</p>
			) }

			<div className="flw-advanced">
				<button
					type="button"
					className="flw-advanced__toggle"
					aria-expanded={ advancedOpen }
					onClick={ () => setAdvancedOpen( ! advancedOpen ) }
				>
					{ __( 'Advanced', 'rave-payment-forms' ) }
					<span
						className={ `flw-advanced__caret ${
							advancedOpen ? 'is-open' : ''
						}` }
						aria-hidden="true"
					/>
				</button>

				{ advancedOpen && (
					<div className="flw-advanced__body">
						<p className="flw-advanced__note">
							{ __(
								'Additional Flutterwave payment options. These are not offered unless selected.',
								'rave-payment-forms'
							) }
						</p>

						<div className="flw-advanced__grid">
							{ ADVANCED_METHODS.map( ( method ) => (
								<Checkbox
									key={ method.key }
									id={ `flw-advanced-${ method.key }` }
									checked={ (
										values.advancedMethods || []
									).includes( method.key ) }
									onChange={ ( checked ) =>
										toggle(
											'advancedMethods',
											method.key,
											checked
										)
									}
									label={ method.label }
								/>
							) ) }
						</div>

						<Checkbox
							id="flw-theme-style"
							checked={ values.useThemeStyle }
							onChange={ ( checked ) =>
								setValue( 'useThemeStyle', checked )
							}
							label={ __(
								"Style payment forms with my theme instead of Flutterwave's design",
								'rave-payment-forms'
							) }
						/>
					</div>
				) }
			</div>
		</>
	);
};

/**
 * Step 4: where customers land after paying.
 *
 * @param {Object}   props
 * @param {Object}   props.values   Current form values.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @param {Object}   [props.errors] Per-field validation messages.
 * @return {Element} The form body.
 */
export const RedirectsForm = ( { values, setValue, errors = {} } ) => {
	const fields = [
		{
			key: 'successUrl',
			label: __( 'After a successful payment', 'rave-payment-forms' ),
		},
		{
			key: 'failedUrl',
			label: __( 'After a failed payment', 'rave-payment-forms' ),
		},
		{
			key: 'pendingUrl',
			label: __(
				'While a payment is still processing',
				'rave-payment-forms'
			),
		},
	];

	return fields.map( ( field ) => (
		<Field
			key={ field.key }
			id={ `flw-${ field.key }` }
			label={ field.label }
			error={ errors[ field.key ] }
		>
			<TextInput
				id={ `flw-${ field.key }` }
				type="url"
				value={ values[ field.key ] }
				placeholder="https://"
				onChange={ ( event ) =>
					setValue( field.key, event.target.value )
				}
			/>
		</Field>
	) );
};
