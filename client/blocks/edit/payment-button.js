/**
 * Editor for flutterwave/payment-button.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import {
	AmountControl,
	CurrencyControl,
	Preview,
	SetupNotice,
	StyleControls,
	editorData,
} from '../components';

const Edit = ( { name, attributes, setAttributes } ) => (
	<>
		<InspectorControls>
			<SetupNotice />
			<PanelBody title={ __( 'Payment', 'rave-payment-forms' ) }>
				<AmountControl
					label={ __( 'Amount', 'rave-payment-forms' ) }
					help={ __(
						'Leave empty to let customers enter the amount.',
						'rave-payment-forms'
					) }
					value={ attributes.amount }
					onChange={ ( amount ) => setAttributes( { amount } ) }
				/>
				<CurrencyControl
					value={ attributes.currency }
					defaultLabel={ `${ __( 'Site default', 'rave-payment-forms' ) } (${ editorData().defaultCurrency || 'NGN' })` }
					onChange={ ( currency ) => setAttributes( { currency } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Button text', 'rave-payment-forms' ) }
					placeholder={ __( 'Pay {amount}', 'rave-payment-forms' ) }
					help={ __(
						'{amount} is replaced with the price, e.g. "Pay NGN 5,000".',
						'rave-payment-forms'
					) }
					value={ attributes.buttonText }
					onChange={ ( buttonText ) =>
						setAttributes( { buttonText } )
					}
				/>
			</PanelBody>
			<PanelBody title={ __( 'Checkout', 'rave-payment-forms' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Ask for the customer’s name',
						'rave-payment-forms'
					) }
					checked={ attributes.collectName }
					onChange={ ( collectName ) =>
						setAttributes( { collectName } )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Use the logged-in user’s email',
						'rave-payment-forms'
					) }
					help={ __(
						'Logged-in visitors only see the button.',
						'rave-payment-forms'
					) }
					checked={ attributes.useUserEmail }
					onChange={ ( useUserEmail ) =>
						setAttributes( { useUserEmail } )
					}
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Width', 'rave-payment-forms' ) }
					value={ attributes.width }
					options={ [
						{
							value: 'auto',
							label: __( 'Fit content', 'rave-payment-forms' ),
						},
						{
							value: 'full',
							label: __( 'Full width', 'rave-payment-forms' ),
						},
					] }
					onChange={ ( width ) => setAttributes( { width } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Show “Secured by Flutterwave”',
						'rave-payment-forms'
					) }
					checked={ attributes.showSecured }
					onChange={ ( showSecured ) =>
						setAttributes( { showSecured } )
					}
				/>
			</PanelBody>
		</InspectorControls>
		<StyleControls
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
		<Preview name={ name } attributes={ attributes } />
	</>
);

export default Edit;
