/**
 * "New form" builder: pick a type, fill in a few fields with a live preview,
 * then create a draft page with the block or copy the shortcode.
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CopyField,
	Field,
	Select,
	TextArea,
	TextInput,
} from '../components';
import { Modal } from '../components/list';
import { adminData, createFormPage, renderBlock } from '../lib/api';
import { toShortcode } from '../lib/shortcode';
import { FORM_TYPES } from './form-types';

const DEFAULTS = {
	'payment-button': { amount: 5000, buttonText: '', useUserEmail: false },
	'payment-form': { heading: '', description: '', amount: 0, buttonText: '' },
	'donation-form': { heading: '', message: '', amounts: '1000, 5000, 10000' },
	'pricing-card': {
		planName: 'Starter',
		amount: 25000,
		features: [],
		badge: '',
	},
};

const PAGE_TITLES = {
	'payment-button': __( 'Buy now', 'rave-payment-forms' ),
	'payment-form': __( 'Make a payment', 'rave-payment-forms' ),
	'donation-form': __( 'Donate', 'rave-payment-forms' ),
	'pricing-card': __( 'Pricing', 'rave-payment-forms' ),
};

/**
 * Amount input showing 0 as empty.
 *
 * @param {Object}   props
 * @param {string}   props.id       Control id.
 * @param {number}   props.value    Amount.
 * @param {Function} props.onChange Receives the number.
 * @return {Element} The input.
 */
const AmountInput = ( { id, value, onChange } ) => (
	<TextInput
		id={ id }
		type="number"
		min="0"
		step="0.01"
		value={ value > 0 ? String( value ) : '' }
		placeholder={ __( 'Customer enters the amount', 'rave-payment-forms' ) }
		onChange={ ( event ) =>
			onChange( Math.max( 0, parseFloat( event.target.value ) || 0 ) )
		}
	/>
);

/**
 * The fields for one form type.
 *
 * @param {Object}   props
 * @param {string}   props.type     Form type.
 * @param {Object}   props.values   Attributes.
 * @param {Function} props.setValue `( key, value ) => void`.
 * @return {Element} The fields.
 */
const TypeFields = ( { type, values, setValue } ) => {
	const { currencies = [], siteCurrency } = adminData();
	const currency = (
		<Field
			id="flw-builder-currency"
			label={ __( 'Currency', 'rave-payment-forms' ) }
		>
			<Select
				id="flw-builder-currency"
				value={ values.currency || '' }
				placeholder={ `${ __( 'Site default', 'rave-payment-forms' ) }${ siteCurrency ? ` (${ siteCurrency })` : '' }` }
				options={ currencies.map( ( code ) => ( {
					value: code,
					label: code,
				} ) ) }
				onChange={ ( event ) =>
					setValue( 'currency', event.target.value )
				}
			/>
		</Field>
	);
	const text = ( key, label, placeholder ) => (
		<Field id={ `flw-builder-${ key }` } label={ label }>
			<TextInput
				id={ `flw-builder-${ key }` }
				value={ values[ key ] || '' }
				placeholder={ placeholder }
				onChange={ ( event ) => setValue( key, event.target.value ) }
			/>
		</Field>
	);
	const amount = ( label ) => (
		<Field id="flw-builder-amount" label={ label }>
			<AmountInput
				id="flw-builder-amount"
				value={ values.amount }
				onChange={ ( next ) => setValue( 'amount', next ) }
			/>
		</Field>
	);

	switch ( type ) {
		case 'payment-button':
			return (
				<>
					<div className="flw-field-row">
						{ amount( __( 'Amount', 'rave-payment-forms' ) ) }
						{ currency }
					</div>
					{ text(
						'buttonText',
						__( 'Button text', 'rave-payment-forms' ),
						__( 'Pay {amount}', 'rave-payment-forms' )
					) }
				</>
			);
		case 'payment-form':
			return (
				<>
					{ text(
						'heading',
						__( 'Heading', 'rave-payment-forms' ),
						__( 'Pay an invoice', 'rave-payment-forms' )
					) }
					<div className="flw-field-row">
						{ amount(
							__(
								'Fixed amount (optional)',
								'rave-payment-forms'
							)
						) }
						{ currency }
					</div>
					{ text(
						'buttonText',
						__( 'Button text', 'rave-payment-forms' ),
						__( 'Proceed to Flutterwave', 'rave-payment-forms' )
					) }
				</>
			);
		case 'donation-form':
			return (
				<>
					{ text(
						'heading',
						__( 'Heading', 'rave-payment-forms' ),
						__( 'Support our work', 'rave-payment-forms' )
					) }
					<Field
						id="flw-builder-message"
						label={ __( 'Message', 'rave-payment-forms' ) }
					>
						<TextArea
							id="flw-builder-message"
							rows={ 3 }
							value={ values.message || '' }
							onChange={ ( event ) =>
								setValue( 'message', event.target.value )
							}
						/>
					</Field>
					<div className="flw-field-row">
						{ text(
							'amounts',
							__( 'Suggested amounts', 'rave-payment-forms' ),
							'1000, 5000, 10000'
						) }
						{ currency }
					</div>
				</>
			);
		case 'pricing-card':
			return (
				<>
					{ text(
						'planName',
						__( 'Plan name', 'rave-payment-forms' ),
						'Starter'
					) }
					<div className="flw-field-row">
						{ amount( __( 'Price', 'rave-payment-forms' ) ) }
						{ currency }
					</div>
					<Field
						id="flw-builder-features"
						label={ __(
							'Features (one per line)',
							'rave-payment-forms'
						) }
					>
						<TextArea
							id="flw-builder-features"
							rows={ 4 }
							value={ ( values.features || [] ).join( '\n' ) }
							onChange={ ( event ) =>
								setValue(
									'features',
									event.target.value
										.split( '\n' )
										.map( ( line ) => line.trimStart() )
										.filter(
											( line, index, all ) =>
												line || index === all.length - 1
										)
								)
							}
						/>
					</Field>
					{ text(
						'badge',
						__( 'Badge (optional)', 'rave-payment-forms' ),
						__( 'Popular', 'rave-payment-forms' )
					) }
				</>
			);
	}

	return null;
};

/**
 * Server-rendered preview of the block, refreshed shortly after each change.
 *
 * @param {Object} props
 * @param {string} props.type       Form type.
 * @param {Object} props.attributes Attributes.
 * @return {Element} The preview.
 */
const Preview = ( { type, attributes } ) => {
	const [ html, setHtml ] = useState( '' );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		const timer = setTimeout( () => {
			const clean = {
				...attributes,
				features: ( attributes.features || [] ).filter( Boolean ),
			};
			renderBlock( type, 'pricing-card' === type ? clean : attributes )
				.then( ( response ) => {
					if ( ! cancelled ) {
						setHtml( response?.rendered || '' );
						setFailed( false );
					}
				} )
				.catch( () => ! cancelled && setFailed( true ) );
		}, 350 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ type, attributes ] );

	if ( failed ) {
		return (
			<p className="flw-builder__preview-note">
				{ __( 'Preview unavailable.', 'rave-payment-forms' ) }
			</p>
		);
	}

	return (
		<div
			className="flw-builder__preview-frame"
			aria-hidden="true"
			// The HTML is this plugin's own server render of the block, fetched by an administrator.

			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
};

/**
 * @param {Object}   props
 * @param {Function} props.onClose  Close handler.
 * @param {Function} props.onNotice `( status, message ) => void`.
 * @return {Element} The builder modal.
 */
const FormBuilder = ( { onClose, onNotice } ) => {
	const [ type, setType ] = useState( '' );
	const [ values, setValues ] = useState( {} );
	const [ title, setTitle ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const choose = ( key ) => {
		setType( key );
		setValues( DEFAULTS[ key ] );
		setTitle( PAGE_TITLES[ key ] );
	};

	const setValue = ( key, value ) =>
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
	const shortcode = type ? toShortcode( type, values ) : null;

	const create = async () => {
		setBusy( true );
		try {
			const attributes = { ...values };
			if ( attributes.features ) {
				attributes.features = attributes.features.filter( Boolean );
			}
			const page = await createFormPage( {
				title,
				block: type,
				attributes,
			} );
			window.location.assign( page.editUrl );
		} catch ( error ) {
			setBusy( false );
			onNotice(
				'error',
				error?.message ||
					__( 'Could not create the page.', 'rave-payment-forms' )
			);
		}
	};

	if ( ! type ) {
		return (
			<Modal
				title={ __( 'New payment form', 'rave-payment-forms' ) }
				onClose={ onClose }
			>
				<p className="flw-builder__intro">
					{ __(
						'What would you like to add to your site?',
						'rave-payment-forms'
					) }
				</p>
				<div className="flw-type-grid">
					{ FORM_TYPES.filter( ( item ) => item.buildable ).map(
						( item ) => (
							<button
								key={ item.key }
								type="button"
								className="flw-type-card"
								onClick={ () => choose( item.key ) }
							>
								<span
									className="flw-type-card__icon"
									aria-hidden="true"
								>
									{ item.icon }
								</span>
								<span className="flw-type-card__title">
									{ item.label }
								</span>
								<span className="flw-type-card__desc">
									{ item.description }
								</span>
							</button>
						)
					) }
				</div>
			</Modal>
		);
	}

	const current = FORM_TYPES.find( ( item ) => item.key === type );

	return (
		<Modal
			title={ current.label }
			onClose={ onClose }
			footer={
				<>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () => setType( '' ) }
					>
						{ __( 'Back', 'rave-payment-forms' ) }
					</Button>
					<Button
						busy={ busy }
						disabled={ ! title.trim() }
						onClick={ create }
					>
						{ __( 'Create page', 'rave-payment-forms' ) }
					</Button>
				</>
			}
		>
			<div className="flw-builder">
				<div className="flw-builder__fields">
					<Field
						id="flw-builder-title"
						label={ __( 'Page title', 'rave-payment-forms' ) }
					>
						<TextInput
							id="flw-builder-title"
							value={ title }
							onChange={ ( event ) =>
								setTitle( event.target.value )
							}
						/>
					</Field>
					<TypeFields
						type={ type }
						values={ values }
						setValue={ setValue }
					/>
					<Field
						id="flw-builder-shortcode"
						label={ __( 'Shortcode', 'rave-payment-forms' ) }
						hint={
							shortcode
								? __(
										'Or paste this into any page, post or widget.',
										'rave-payment-forms'
									)
								: __(
										'Pricing cards are available as a block only.',
										'rave-payment-forms'
									)
						}
					>
						{ shortcode && (
							<CopyField
								id="flw-builder-shortcode"
								value={ shortcode }
							/>
						) }
					</Field>
				</div>
				<div className="flw-builder__preview">
					<span className="flw-builder__preview-label">
						{ __( 'Preview', 'rave-payment-forms' ) }
					</span>
					<Preview type={ type } attributes={ values } />
				</div>
			</div>
		</Modal>
	);
};

export default FormBuilder;
