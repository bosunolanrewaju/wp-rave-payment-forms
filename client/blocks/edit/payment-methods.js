/**
 * Editor for flutterwave/payment-methods.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { Preview, SetupNotice, editorData } from '../components';

const Edit = ( { name, attributes, setAttributes } ) => {
	const { settingsUrl } = editorData();

	return (
		<>
			<InspectorControls>
				<SetupNotice />
				<PanelBody title={ __( 'Settings', 'rave-payment-forms' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Label', 'rave-payment-forms' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show “Payments secured by Flutterwave”',
							'rave-payment-forms'
						) }
						checked={ attributes.showSecured }
						onChange={ ( showSecured ) =>
							setAttributes( { showSecured } )
						}
					/>
					<p className="flw-block-help">
						{ __(
							'The methods shown are the ones enabled in your Flutterwave settings, so this badge stays in step with checkout.',
							'rave-payment-forms'
						) }{ ' ' }
						{ settingsUrl && (
							<a href={ `${ settingsUrl }&tab=methods` }>
								{ __(
									'Change payment methods',
									'rave-payment-forms'
								) }
							</a>
						) }
					</p>
				</PanelBody>
			</InspectorControls>
			<Preview name={ name } attributes={ attributes } />
		</>
	);
};

export default Edit;
