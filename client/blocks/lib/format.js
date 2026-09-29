/**
 * Pure helpers shared by the block editors.
 */

/**
 * Split a textarea value into trimmed, non-empty lines.
 *
 * @param {string} text One item per line.
 * @return {string[]} The items.
 */
export const linesToList = ( text ) =>
	String( text || '' )
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );

/**
 * Join items into a textarea value.
 *
 * @param {string[]} list Items.
 * @return {string} One item per line.
 */
export const listToLines = ( list ) =>
	( Array.isArray( list ) ? list : [] ).join( '\n' );

/**
 * Parse an amount typed into a number field. Empty or invalid input is 0.
 *
 * @param {string|number} value Raw value.
 * @return {number} A non-negative amount with at most two decimals.
 */
export const parseAmount = ( value ) => {
	const amount = parseFloat( value );

	if ( ! Number.isFinite( amount ) || amount <= 0 ) {
		return 0;
	}

	return Math.round( amount * 100 ) / 100;
};

/**
 * The valid suggested amounts in a comma separated list, as the server reads them.
 *
 * @param {string} text Comma separated amounts.
 * @return {number[]} Up to six distinct positive amounts.
 */
export const parsePresetAmounts = ( text ) => {
	const seen = [];

	String( text || '' )
		.split( ',' )
		.map( ( item ) => parseAmount( item.trim() ) )
		.filter( ( amount ) => amount > 0 )
		.forEach( ( amount ) => {
			if ( ! seen.includes( amount ) ) {
				seen.push( amount );
			}
		} );

	return seen.slice( 0, 6 );
};
