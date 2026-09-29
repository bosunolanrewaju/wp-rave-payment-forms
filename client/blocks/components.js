/**
 * Controls shared by the Flutterwave blocks.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	Disabled,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { parseAmount } from './lib/format';

/**
 * Values passed in from PHP (FLW_Blocks::editor_data()).
 *
 * @return {Object} Editor data.
 */
export const editorData = () => window.flwBlocksData || {};

/**
 * The server-rendered block, made non-interactive inside the editor.
 *
 * @param {Object} props
 * @param {string} props.name       Block name.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} The preview.
 */
export const Preview = ( { name, attributes } ) => (
	<div { ...useBlockProps() }>
		<Disabled>
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				skipBlockSupportAttributes
				LoadingResponsePlaceholder={ () => (
					<div className="flw-block-loading">
						<Spinner />
					</div>
				) }
			/>
		</Disabled>
	</div>
);

/**
 * Sidebar notice while the plugin is not connected to Flutterwave.
 *
 * @return {Element|null} The notice.
 */
export const SetupNotice = () => {
	const { configured, settingsUrl } = editorData();

	if ( configured ) {
		return null;
	}

	return (
		<div className="flw-block-setup">
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Connect your Flutterwave account before publishing: visitors will not see this block until you do.',
					'rave-payment-forms'
				) }{ ' ' }
				{ settingsUrl && (
					<a href={ settingsUrl }>
						{ __( 'Set up Flutterwave', 'rave-payment-forms' ) }
					</a>
				) }
			</Notice>
		</div>
	);
};

/**
 * Amount input. 0 is shown empty.
 *
 * @param {Object}   props
 * @param {string}   props.label    Label.
 * @param {number}   props.value    Current amount.
 * @param {Function} props.onChange Receives the parsed amount.
 * @param {string}   [props.help]   Help text.
 * @return {Element} The control.
 */
export const AmountControl = ( { label, value, onChange, help } ) => (
	<TextControl
		__next40pxDefaultSize
		__nextHasNoMarginBottom
		type="number"
		min="0"
		step="0.01"
		label={ label }
		help={ help }
		value={ value > 0 ? String( value ) : '' }
		onChange={ ( next ) => onChange( parseAmount( next ) ) }
	/>
);

/**
 * Currency picker.
 *
 * @param {Object}   props
 * @param {string}   props.value        Current currency, or '' for the default.
 * @param {Function} props.onChange     Receives the new value.
 * @param {string}   props.defaultLabel Label for the empty option.
 * @return {Element} The control.
 */
export const CurrencyControl = ( { value, onChange, defaultLabel } ) => {
	const { currencies = [] } = editorData();

	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Currency', 'rave-payment-forms' ) }
			value={ value }
			options={ [
				{ value: '', label: defaultLabel },
				...currencies.map( ( code ) => ( {
					value: code,
					label: code,
				} ) ),
			] }
			onChange={ onChange }
		/>
	);
};

/**
 * Colour and corner radius controls, in the block's Styles tab.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The panels.
 */
export const StyleControls = ( { attributes, setAttributes } ) => (
	<InspectorControls group="styles">
		<PanelColorSettings
			title={ __( 'Flutterwave colours', 'rave-payment-forms' ) }
			colorSettings={ [
				{
					value: attributes.accentColor || undefined,
					onChange: ( color ) =>
						setAttributes( { accentColor: color || '' } ),
					label: __( 'Button & accent', 'rave-payment-forms' ),
				},
				{
					value: attributes.accentTextColor || undefined,
					onChange: ( color ) =>
						setAttributes( { accentTextColor: color || '' } ),
					label: __( 'Button text', 'rave-payment-forms' ),
				},
			] }
		/>
		<PanelBody title={ __( 'Corners', 'rave-payment-forms' ) }>
			<RangeControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Corner radius', 'rave-payment-forms' ) }
				value={ attributes.borderRadius }
				min={ 0 }
				max={ 40 }
				allowReset
				resetFallbackValue={ undefined }
				onChange={ ( borderRadius ) =>
					setAttributes( { borderRadius } )
				}
			/>
		</PanelBody>
	</InspectorControls>
);
