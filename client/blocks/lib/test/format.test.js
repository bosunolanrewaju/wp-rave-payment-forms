/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	linesToList,
	listToLines,
	parseAmount,
	parsePresetAmounts,
} from '../format';

describe( 'linesToList / listToLines', () => {
	it( 'drops blank lines and trims', () => {
		expect( linesToList( ' One \n\n Two\r\n ' ) ).toEqual( [
			'One',
			'Two',
		] );
	} );

	it( 'round-trips', () => {
		expect( linesToList( listToLines( [ 'A', 'B' ] ) ) ).toEqual( [
			'A',
			'B',
		] );
		expect( listToLines( undefined ) ).toBe( '' );
	} );
} );

describe( 'parseAmount', () => {
	it.each( [
		[ '5000', 5000 ],
		[ '12.345', 12.35 ],
		[ '', 0 ],
		[ '-3', 0 ],
		[ 'abc', 0 ],
	] )( '%s → %s', ( value, expected ) => {
		expect( parseAmount( value ) ).toBe( expected );
	} );
} );

describe( 'parsePresetAmounts', () => {
	it( 'keeps up to six distinct positive amounts, like the server', () => {
		expect(
			parsePresetAmounts( '1000, 5000, x, -1, 5000, 1, 2, 3, 4, 5' )
		).toEqual( [ 1000, 5000, 1, 2, 3, 4 ] );
	} );
} );
