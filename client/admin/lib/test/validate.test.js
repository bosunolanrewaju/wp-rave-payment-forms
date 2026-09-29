/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	formatErrors,
	isHttpUrl,
	keyMode,
	validateApi,
	validateGeneral,
	validateMethods,
	validateRedirects,
} from '../validate';

describe( 'validateGeneral', () => {
	it( 'requires a title and a currency', () => {
		expect( Object.keys( validateGeneral( {} ) ) ).toEqual( [
			'title',
			'currency',
		] );
	} );

	it( 'passes with a title and currency', () => {
		expect( validateGeneral( { title: 'Acme', currency: 'NGN' } ) ).toEqual(
			{}
		);
	} );

	it( 'rejects a logo that is not an http(s) URL', () => {
		expect(
			validateGeneral( {
				title: 'Acme',
				currency: 'NGN',
				logoUrl: 'javascript:alert(1)',
			} )
		).toHaveProperty( 'logoUrl' );
	} );
} );

describe( 'keyMode', () => {
	it.each( [
		[ 'FLWPUBK_TEST-abc-X', 'FLWPUBK', 'test' ],
		[ 'FLWPUBK-abc-X', 'FLWPUBK', 'live' ],
		[ 'FLWSECK_TEST-abc-X', 'FLWSECK', 'test' ],
		[ 'FLWSECK_TEST-abc-X', 'FLWPUBK', '' ],
		[ 'pk_live_123', 'FLWPUBK', '' ],
		[ undefined, 'FLWPUBK', '' ],
	] )( '%s with prefix %s is %s', ( key, prefix, expected ) => {
		expect( keyMode( key, prefix ) ).toBe( expected );
	} );
} );

describe( 'validateApi', () => {
	it( 'requires both keys', () => {
		expect( Object.keys( validateApi( {} ) ) ).toEqual( [
			'publicKey',
			'secretKey',
		] );
	} );

	it( 'rejects keys that are not Flutterwave keys', () => {
		const errors = validateApi( {
			publicKey: 'pk_test_1',
			secretKey: 'sk_test_1',
		} );

		expect( errors.publicKey ).toMatch( /FLWPUBK/ );
		expect( errors.secretKey ).toMatch( /FLWSECK/ );
	} );

	it( 'rejects a test public key with a live secret key', () => {
		expect(
			validateApi( {
				publicKey: 'FLWPUBK_TEST-a-X',
				secretKey: 'FLWSECK-a-X',
			} )
		).toEqual( { secretKey: expect.stringMatching( /same mode/ ) } );
	} );

	it( 'passes with a matching pair', () => {
		expect(
			validateApi( {
				publicKey: 'FLWPUBK-a-X',
				secretKey: 'FLWSECK-a-X',
			} )
		).toEqual( {} );
	} );
} );

describe( 'validateMethods', () => {
	it( 'requires at least one method', () => {
		expect( validateMethods( {} ) ).toHaveProperty( 'paymentMethods' );
	} );

	it( 'accepts an advanced method on its own', () => {
		expect( validateMethods( { advancedMethods: [ 'ussd' ] } ) ).toEqual(
			{}
		);
	} );
} );

describe( 'validateRedirects', () => {
	it( 'requires three http(s) URLs', () => {
		expect(
			validateRedirects( {
				successUrl: 'https://shop.test/thanks',
				failedUrl: '/relative',
				pendingUrl: '',
			} )
		).toEqual( {
			failedUrl: expect.any( String ),
			pendingUrl: expect.any( String ),
		} );
	} );
} );

describe( 'isHttpUrl', () => {
	it.each( [
		[ 'https://shop.test', true ],
		[ 'http://localhost:8888/', true ],
		[ 'ftp://shop.test', false ],
		[ 'shop.test', false ],
		[ '', false ],
	] )( '%s → %s', ( value, expected ) => {
		expect( isHttpUrl( value ) ).toBe( expected );
	} );
} );

describe( 'formatErrors', () => {
	it( 'keeps errors only for fields that have a value', () => {
		const values = { publicKey: 'nope', secretKey: '' };
		const found = { publicKey: 'bad format', secretKey: 'required' };

		expect( formatErrors( found, values ) ).toEqual( {
			publicKey: 'bad format',
		} );
	} );
} );
