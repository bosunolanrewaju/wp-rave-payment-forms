/**
 * Money formatting shared by the admin screens.
 */

/**
 * "NGN 98,000.00" style amounts.
 *
 * @param {number}  amount              Amount.
 * @param {string}  currency            ISO currency code.
 * @param {Object}  [options]
 * @param {boolean} [options.trimZeros] Drop ".00" on whole amounts.
 * @return {string} The formatted amount.
 */
export const formatMoney = ( amount, currency, { trimZeros = false } = {} ) => {
	const value = Number( amount ) || 0;
	const whole = Number.isInteger( value );
	const digits = trimZeros && whole ? 0 : 2;
	const number = value.toLocaleString( 'en-US', {
		minimumFractionDigits: digits,
		maximumFractionDigits: digits,
	} );

	return currency ? `${ currency } ${ number }` : number;
};
