/**
 * Date helpers for the Transactions filters. Dates are YYYY-MM-DD strings in the
 * browser's local time, which is what <input type="date"> uses.
 */

/**
 * A Date as YYYY-MM-DD in local time.
 *
 * @param {Date} date The date.
 * @return {string} ISO date.
 */
export const toIsoDate = ( date ) => {
	const pad = ( value ) => String( value ).padStart( 2, '0' );
	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad(
		date.getDate()
	) }`;
};

/**
 * Parse YYYY-MM-DD as a local date.
 *
 * @param {string} value ISO date.
 * @return {Date|null} The date, or null when invalid.
 */
export const fromIsoDate = ( value ) => {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( String( value || '' ) );

	if ( ! match ) {
		return null;
	}

	const date = new Date(
		Number( match[ 1 ] ),
		Number( match[ 2 ] ) - 1,
		Number( match[ 3 ] )
	);

	return date.getMonth() === Number( match[ 2 ] ) - 1 ? date : null;
};

export const PRESETS = [ 'today', '7d', '30d', '1y' ];

/**
 * The date range for a preset, ending today.
 *
 * @param {string} preset One of PRESETS.
 * @param {Date}   [now]  Today; injectable for tests.
 * @return {{from: string, to: string}} The range.
 */
export const presetRange = ( preset, now = new Date() ) => {
	const to = new Date( now.getFullYear(), now.getMonth(), now.getDate() );
	const from = new Date( to );

	switch ( preset ) {
		case '7d':
			from.setDate( from.getDate() - 7 );
			break;
		case '30d':
			from.setDate( from.getDate() - 30 );
			break;
		case '1y':
			from.setFullYear( from.getFullYear() - 1 );
			break;
	}

	return { from: toIsoDate( from ), to: toIsoDate( to ) };
};

/**
 * The earliest date a filter may start: one year before today.
 *
 * @param {Date} [now] Today; injectable for tests.
 * @return {string} ISO date.
 */
export const earliestDate = ( now = new Date() ) =>
	presetRange( '1y', now ).from;

/**
 * Keep a custom range inside the last year and in order.
 *
 * @param {string} from  Start date.
 * @param {string} to    End date.
 * @param {Date}   [now] Today; injectable for tests.
 * @return {{from: string, to: string}} The corrected range.
 */
export const clampRange = ( from, to, now = new Date() ) => {
	const today = toIsoDate( now );
	const earliest = earliestDate( now );
	let start = fromIsoDate( from ) ? from : earliest;
	let end = fromIsoDate( to ) ? to : today;

	if ( end > today ) {
		end = today;
	}
	if ( start < earliest ) {
		start = earliest;
	}
	if ( start > end ) {
		[ start, end ] = [ end, start ];
	}

	return { from: start, to: end };
};

/**
 * Display a date like "Sep 24, 2026".
 *
 * @param {string} value ISO date.
 * @return {string} Formatted date.
 */
export const formatDate = ( value ) => {
	const date = fromIsoDate( value );

	return date
		? date.toLocaleDateString( undefined, {
				month: 'short',
				day: 'numeric',
				year: 'numeric',
			} )
		: '';
};
