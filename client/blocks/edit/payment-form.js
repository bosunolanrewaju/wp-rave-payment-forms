/**
 * Editor for flutterwave/payment-form.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import {
	AmountControl,
	CurrencyControl,
	Preview,
	SetupNotice,
	StyleControls,
} from '../components';

const Edit = ( { name, attributes, setAttributes } ) => (
	<>
		<InspectorControls>
			<SetupNotice />
			<PanelBody title={ __( 'Content', 'rave-payment-forms' ) }>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Heading', 'rave-payment-forms' ) }
					value={ attributes.heading }
					onChange={ ( heading ) => setAttributes( { heading } ) }
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Description', 'rave-payment-forms' ) }
					rows={ 3 }
					value={ attributes.description }
					onChange={ ( description ) =>
						setAttributes( { description } )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Button text', 'rave-payment-forms' ) }
					placeholder={ __(
						'Proceed to Flutterwave',
						'rave-payment-forms'
					) }
					help={ __(
						'Use {amount} to show the price.',
						'rave-payment-forms'
					) }
					value={ attributes.buttonText }
					onChange={ ( buttonText ) =>
						setAttributes( { buttonText } )
					}
				/>
			</PanelBody>
			<PanelBody title={ __( 'Amount', 'rave-payment-forms' ) }>
				<AmountControl
					label={ __( 'Fixed amount', 'rave-payment-forms' ) }
					help={ __(
						'Leave empty to let customers enter the amount.',
						'rave-payment-forms'
					) }
					value={ attributes.amount }
					onChange={ ( amount ) => setAttributes( { amount } ) }
				/>
				<CurrencyControl
					value={ attributes.currency }
					defaultLabel={ __( 'Site default', 'rave-payment-forms' ) }
					onChange={ ( currency ) => setAttributes( { currency } ) }
				/>
			</PanelBody>
			<PanelBody title={ __( 'Fields', 'rave-payment-forms' ) }>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Customer name', 'rave-payment-forms' ) }
					value={ attributes.collectName }
					options={ [
						{
							value: 'full',
							label: __( 'Full name', 'rave-payment-forms' ),
						},
						{
							value: 'split',
							label: __(
								'First and last name',
								'rave-payment-forms'
							),
						},
						{
							value: 'none',
							label: __( 'Don’t ask', 'rave-payment-forms' ),
						},
					] }
					onChange={ ( collectName ) =>
						setAttributes( { collectName } )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Ask for a phone number',
						'rave-payment-forms'
					) }
					checked={ attributes.collectPhone }
					onChange={ ( collectPhone ) =>
						setAttributes( { collectPhone } )
					}
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
