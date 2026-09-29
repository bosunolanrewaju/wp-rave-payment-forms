/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { clampRange, earliestDate, presetRange } from '../dates';
import { formatMoney } from '../money';
import { toShortcode } from '../shortcode';

const NOW = new Date( 2026, 8, 24 ); // Sep 24, 2026.

describe( 'presetRange', () => {
	it.each( [
		[ 'today', '2026-09-24', '2026-09-24' ],
		[ '7d', '2026-09-17', '2026-09-24' ],
		[ '30d', '2026-08-25', '2026-09-24' ],
		[ '1y', '2025-09-24', '2026-09-24' ],
	] )( '%s', ( preset, from, to ) => {
		expect( presetRange( preset, NOW ) ).toEqual( { from, to } );
	} );
} );

describe( 'clampRange', () => {
	it( 'keeps ranges inside the last year and in order', () => {
		expect( clampRange( '2020-01-01', '2026-01-01', NOW ) ).toEqual( {
			from: earliestDate( NOW ),
			to: '2026-01-01',
		} );
		expect( clampRange( '2026-09-01', '2026-08-01', NOW ) ).toEqual( {
			from: '2026-08-01',
			to: '2026-09-01',
		} );
		expect( clampRange( '2026-09-01', '2027-01-01', NOW ).to ).toBe(
			'2026-09-24'
		);
		expect( clampRange( 'bad', '', NOW ) ).toEqual(
			presetRange( '1y', NOW )
		);
	} );
} );

describe( 'formatMoney', () => {
	it( 'formats like the Flutterwave dashboard', () => {
		expect( formatMoney( 98000, 'NGN' ) ).toBe( 'NGN 98,000.00' );
		expect( formatMoney( 602474.29, 'NGN' ) ).toBe( 'NGN 602,474.29' );
		expect( formatMoney( 5000, 'NGN', { trimZeros: true } ) ).toBe(
			'NGN 5,000'
		);
		expect( formatMoney( 12.5, '', { trimZeros: true } ) ).toBe( '12.50' );
	} );
} );

describe( 'toShortcode', () => {
	it( 'builds a compact payment button', () => {
		expect(
			toShortcode( 'payment-button', { amount: 5000, currency: 'NGN' } )
		).toBe(
			'[flw-pay-form amount="5000" currency="NGN" layout="compact" exclude="fullname,phone"]Pay {amount}[/flw-pay-form]'
		);
	} );

	it( 'drops empty values and characters that would break the tag', () => {
		expect(
			toShortcode( 'payment-form', {
				heading: 'Pay "now" [today]',
				amount: 0,
				currency: '',
			} )
		).toBe( '[flw-pay-form heading="Pay now today"]' );
	} );

	it( 'builds a donation form', () => {
		expect(
			toShortcode( 'donation-form', {
				amounts: '1000, 5000',
				currency: 'KES',
			} )
		).toBe( '[flw-donation-form amounts="1000, 5000" currency="KES"]' );
	} );

	it( 'has no shortcode for block-only types', () => {
		expect( toShortcode( 'pricing-card', {} ) ).toBeNull();
	} );
} );
