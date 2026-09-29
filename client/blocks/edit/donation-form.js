/**
 * Editor for flutterwave/donation-form.
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
	CurrencyControl,
	Preview,
	SetupNotice,
	StyleControls,
} from '../components';
import { parsePresetAmounts } from '../lib/format';

const Edit = ( { name, attributes, setAttributes } ) => {
	const presets = parsePresetAmounts( attributes.amounts );

	return (
		<>
			<InspectorControls>
				<SetupNotice />
				<PanelBody title={ __( 'Content', 'rave-payment-forms' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'rave-payment-forms' ) }
						help={ __(
							'Leave empty to use the heading from Flutterwave settings.',
							'rave-payment-forms'
						) }
						value={ attributes.heading }
						onChange={ ( heading ) => setAttributes( { heading } ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Message', 'rave-payment-forms' ) }
						rows={ 3 }
						value={ attributes.message }
						onChange={ ( message ) => setAttributes( { message } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Donation', 'rave-payment-forms' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Suggested amounts',
							'rave-payment-forms'
						) }
						help={
							presets.length
								? `${ __( 'Shown as', 'rave-payment-forms' ) }: ${ presets.join( ' · ' ) }`
								: __(
										'Comma separated, e.g. 1000, 5000, 10000. Up to six.',
										'rave-payment-forms'
									)
						}
						value={ attributes.amounts }
						onChange={ ( amounts ) => setAttributes( { amounts } ) }
					/>
					<CurrencyControl
						value={ attributes.currency }
						defaultLabel={ __(
							'Let donors choose',
							'rave-payment-forms'
						) }
						onChange={ ( currency ) =>
							setAttributes( { currency } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Offer monthly and yearly giving',
							'rave-payment-forms'
						) }
						checked={ attributes.showFrequency }
						onChange={ ( showFrequency ) =>
							setAttributes( { showFrequency } )
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
};

export default Edit;
