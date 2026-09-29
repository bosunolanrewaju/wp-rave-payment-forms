/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { cardStatus, nextStep, unsupportedCurrency } from '../integrations';

const ALL = { canInstall: true, canActivate: true };
const NONE = { canInstall: false, canActivate: false };

const plugin = ( state, name = 'Host' ) => ( {
	name,
	slug: name.toLowerCase(),
	rest: `${ name.toLowerCase() }/${ name.toLowerCase() }`,
	state,
} );

const builtIn = ( state, enabled = false ) => ( {
	kind: 'built-in',
	host: plugin( state ),
	enabled,
} );

const extension = ( hostState, extensionState ) => ( {
	kind: 'extension',
	host: plugin( hostState, 'WooCommerce' ),
	extension: plugin( extensionState, 'Extension' ),
	enabled: 'active' === hostState && 'active' === extensionState,
} );

describe( 'nextStep', () => {
	it( 'installs a missing host plugin', () => {
		expect( nextStep( builtIn( 'missing' ), ALL ) ).toEqual( {
			action: 'install',
			target: 'host',
			label: 'Install Host',
		} );
	} );

	it( 'activates an inactive host plugin', () => {
		expect( nextStep( builtIn( 'inactive' ), ALL ) ).toMatchObject( {
			action: 'activate',
			target: 'host/host',
		} );
	} );

	it( 'is blocked without the capabilities', () => {
		expect( nextStep( builtIn( 'missing' ), NONE ).action ).toBe(
			'blocked'
		);
		expect( nextStep( builtIn( 'inactive' ), NONE ).action ).toBe(
			'blocked'
		);
	} );

	it( 'enables a built-in integration once the host is active', () => {
		expect( nextStep( builtIn( 'active' ), ALL ).action ).toBe( 'enable' );
		expect( nextStep( builtIn( 'active', true ), ALL ).action ).toBe(
			'manage'
		);
	} );

	it( 'needs WooCommerce before the extension', () => {
		expect(
			nextStep( extension( 'missing', 'missing' ), ALL )
		).toMatchObject( {
			action: 'install',
			target: 'woocommerce',
		} );
		expect(
			nextStep( extension( 'active', 'missing' ), ALL )
		).toMatchObject( {
			action: 'install',
			target: 'extension',
		} );
		expect(
			nextStep( extension( 'active', 'inactive' ), ALL )
		).toMatchObject( {
			action: 'activate',
			target: 'extension/extension',
		} );
		expect( nextStep( extension( 'active', 'active' ), ALL ).action ).toBe(
			'manage'
		);
	} );

	it( 'marks catalogue entries that are not ready', () => {
		expect( nextStep( { kind: 'coming-soon' }, ALL ).action ).toBe(
			'soon'
		);
	} );
} );

describe( 'cardStatus', () => {
	it( 'describes each state', () => {
		expect( cardStatus( { kind: 'coming-soon' } ).tone ).toBe( 'soon' );
		expect( cardStatus( builtIn( 'active', true ) ).tone ).toBe( 'active' );
		expect( cardStatus( builtIn( 'missing' ) ).tone ).toBe( 'idle' );
		expect( cardStatus( builtIn( 'inactive' ) ).tone ).toBe( 'ready' );
	} );
} );

describe( 'unsupportedCurrency', () => {
	it( 'flags a currency Flutterwave does not charge', () => {
		expect(
			unsupportedCurrency( { currency: 'JPY' }, [ 'NGN', 'USD' ] )
		).toBe( true );
		expect(
			unsupportedCurrency( { currency: 'USD' }, [ 'NGN', 'USD' ] )
		).toBe( false );
		expect( unsupportedCurrency( { currency: '' }, [ 'NGN' ] ) ).toBe(
			false
		);
	} );
} );
