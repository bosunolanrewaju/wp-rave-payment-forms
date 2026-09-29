/**
 * Editor for flutterwave/pricing-card.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
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
	editorData,
} from '../components';
import { linesToList, listToLines } from '../lib/format';

const Edit = ( { name, attributes, setAttributes } ) => (
	<>
		<InspectorControls>
			<SetupNotice />
			<PanelBody title={ __( 'Plan', 'rave-payment-forms' ) }>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Name', 'rave-payment-forms' ) }
					value={ attributes.planName }
					onChange={ ( planName ) => setAttributes( { planName } ) }
				/>
				<AmountControl
					label={ __( 'Price', 'rave-payment-forms' ) }
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
					label={ __( 'Price note', 'rave-payment-forms' ) }
					placeholder={ __( 'one-off', 'rave-payment-forms' ) }
					help={ __(
						'Shown next to the price. Customers pay once.',
						'rave-payment-forms'
					) }
					value={ attributes.period }
					onChange={ ( period ) => setAttributes( { period } ) }
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Description', 'rave-payment-forms' ) }
					rows={ 2 }
					value={ attributes.description }
					onChange={ ( description ) =>
						setAttributes( { description } )
					}
				/>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Features', 'rave-payment-forms' ) }
					help={ __( 'One per line.', 'rave-payment-forms' ) }
					rows={ 5 }
					value={ listToLines( attributes.features ) }
					onChange={ ( text ) =>
						setAttributes( { features: linesToList( text ) } )
					}
				/>
			</PanelBody>
			<PanelBody title={ __( 'Highlight', 'rave-payment-forms' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Highlight this plan', 'rave-payment-forms' ) }
					checked={ attributes.highlighted }
					onChange={ ( highlighted ) =>
						setAttributes( { highlighted } )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Badge', 'rave-payment-forms' ) }
					placeholder={ __( 'Popular', 'rave-payment-forms' ) }
					value={ attributes.badge }
					onChange={ ( badge ) => setAttributes( { badge } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Button text', 'rave-payment-forms' ) }
					placeholder={ __( 'Get started', 'rave-payment-forms' ) }
					value={ attributes.buttonText }
					onChange={ ( buttonText ) =>
						setAttributes( { buttonText } )
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
