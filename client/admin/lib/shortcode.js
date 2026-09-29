/**
 * Turn form builder settings into a shortcode, for merchants who do not use
 * the block editor. Pricing cards and payment method badges are blocks only.
 */

/**
 * Remove characters that would end a shortcode attribute or tag.
 *
 * @param {*} value Attribute value.
 * @return {string} Safe value.
 */
const clean = ( value ) =>
	String( value ?? '' )
		.replace( /["[\]]/g, '' )
		.trim();

/**
 * Build `name="value"` pairs, skipping empty values.
 *
 * @param {Object} attributes Attributes.
 * @return {string} Attribute string with a leading space, or ''.
 */
const attrs = ( attributes ) =>
	Object.entries( attributes )
		.map( ( [ key, value ] ) => [ key, clean( value ) ] )
		.filter( ( [ , value ] ) => '' !== value && '0' !== value )
		.map( ( [ key, value ] ) => ` ${ key }="${ value }"` )
		.join( '' );

/**
 * The shortcode for a form, or null when the type has no shortcode.
 *
 * @param {string} type       Block type (e.g. "payment-button").
 * @param {Object} attributes Builder attributes.
 * @return {string|null} The shortcode.
 */
export const toShortcode = ( type, attributes ) => {
	const a = attributes || {};

	switch ( type ) {
		case 'payment-button': {
			const text = clean( a.buttonText ) || 'Pay {amount}';
			return `[flw-pay-form${ attrs( {
				amount: a.amount,
				currency: a.currency,
				layout: 'compact',
				exclude: 'fullname,phone',
			} ) }]${ text }[/flw-pay-form]`;
		}
		case 'payment-form': {
			const open = `[flw-pay-form${ attrs( {
				heading: a.heading,
				description: a.description,
				amount: a.amount,
				currency: a.currency,
			} ) }]`;
			const text = clean( a.buttonText );
			return text ? `${ open }${ text }[/flw-pay-form]` : open;
		}
		case 'donation-form':
			return `[flw-donation-form${ attrs( {
				heading: a.heading,
				message: a.message,
				amounts: a.amounts,
				currency: a.currency,
			} ) }]`;
	}

	return null;
};
