/**
 * Per-step validation. Each function returns a map of field key to message;
 * an empty object means the step is complete.
 */

import { __ } from '@wordpress/i18n';

const blank = ( value ) => ! String( value || '' ).trim();

/**
 * Whether a value is an absolute http(s) URL.
 *
 * @param {string} value Candidate URL.
 * @return {boolean} True when valid.
 */
export const isHttpUrl = ( value ) => {
	try {
		const url = new URL( String( value || '' ).trim() );
		return 'http:' === url.protocol || 'https:' === url.protocol;
	} catch {
		return false;
	}
};

/**
 * The mode a Flutterwave key belongs to.
 *
 * @param {string} key    The key.
 * @param {string} prefix `FLWPUBK` or `FLWSECK`.
 * @return {string} `test`, `live`, or an empty string when the key is not recognised.
 */
export const keyMode = ( key, prefix ) => {
	const value = String( key || '' ).trim();

	if ( value.startsWith( `${ prefix }_TEST-` ) ) {
		return 'test';
	}

	return value.startsWith( `${ prefix }-` ) ? 'live' : '';
};

/**
 * The errors worth showing before the merchant has tried to continue: format
 * problems in fields they have filled in, not "required" errors for empty ones.
 *
 * @param {Object} found  Errors from a validator.
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const formatErrors = ( found, values ) =>
	Object.fromEntries(
		Object.entries( found ).filter(
			( [ key ] ) => ! blank( values[ key ] )
		)
	);

/**
 * Step 1 needs a title and a default currency.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateGeneral = ( values ) => {
	const errors = {};

	if ( blank( values.title ) ) {
		errors.title = __(
			'Enter a title customers will see when they pay.',
			'rave-payment-forms'
		);
	}

	if ( blank( values.currency ) ) {
		errors.currency = __(
			'Choose the currency your forms charge in.',
			'rave-payment-forms'
		);
	}

	if ( ! blank( values.logoUrl ) && ! isHttpUrl( values.logoUrl ) ) {
		errors.logoUrl = __(
			'Enter a full URL, starting with https://.',
			'rave-payment-forms'
		);
	}

	return errors;
};

/**
 * Step 2 needs a matching public and secret key pair.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateApi = ( values ) => {
	const errors = {};
	const publicMode = keyMode( values.publicKey, 'FLWPUBK' );
	const secretMode = keyMode( values.secretKey, 'FLWSECK' );

	if ( blank( values.publicKey ) ) {
		errors.publicKey = __( 'Enter your public key.', 'rave-payment-forms' );
	} else if ( ! publicMode ) {
		errors.publicKey = __(
			'Public keys start with FLWPUBK_TEST- or FLWPUBK-.',
			'rave-payment-forms'
		);
	}

	if ( blank( values.secretKey ) ) {
		errors.secretKey = __( 'Enter your secret key.', 'rave-payment-forms' );
	} else if ( ! secretMode ) {
		errors.secretKey = __(
			'Secret keys start with FLWSECK_TEST- or FLWSECK-.',
			'rave-payment-forms'
		);
	} else if ( publicMode && publicMode !== secretMode ) {
		errors.secretKey = __(
			'Use a secret key from the same mode (test or live) as your public key.',
			'rave-payment-forms'
		);
	}

	return errors;
};

/**
 * Step 3 needs at least one method, otherwise checkout would offer nothing.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateMethods = ( values ) => {
	const errors = {};
	const selected =
		( values.paymentMethods || [] ).length +
		( values.advancedMethods || [] ).length;

	if ( 0 === selected ) {
		errors.paymentMethods = __(
			'Select at least one payment method.',
			'rave-payment-forms'
		);
	}

	return errors;
};

/**
 * Step 4 needs somewhere to send customers after each outcome.
 *
 * @param {Object} values Form values.
 * @return {Object} Field errors.
 */
export const validateRedirects = ( values ) => {
	const errors = {};

	[ 'successUrl', 'failedUrl', 'pendingUrl' ].forEach( ( key ) => {
		if ( ! isHttpUrl( values[ key ] ) ) {
			errors[ key ] = __(
				'Enter a full URL, starting with https://.',
				'rave-payment-forms'
			);
		}
	} );

	return errors;
};
