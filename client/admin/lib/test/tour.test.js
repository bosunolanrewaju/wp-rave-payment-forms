/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	pickTourState,
	placeTooltip,
	shouldAutoStart,
	tourKey,
	tourSteps,
	TOUR_VERSION,
} from '../tour';

const VIEWPORT = { width: 1280, height: 800 };
const CARD = { width: 320, height: 180 };

describe( 'shouldAutoStart', () => {
	it( 'starts once per screen and version after onboarding', () => {
		const fresh = { enabled: true, seen: [] };

		expect( shouldAutoStart( fresh, 'forms', true ) ).toBe( true );
		expect( shouldAutoStart( fresh, 'forms', false ) ).toBe( false );
		expect(
			shouldAutoStart(
				{ enabled: true, seen: [ tourKey( 'forms' ) ] },
				'forms',
				true
			)
		).toBe( false );
		expect(
			shouldAutoStart(
				{ enabled: true, seen: [ 'forms@0' ] },
				'forms',
				true
			)
		).toBe( true );
	} );

	it( 'never starts when tips are off', () => {
		expect(
			shouldAutoStart( { enabled: false, seen: [] }, 'forms', true )
		).toBe( false );
		expect( shouldAutoStart( undefined, 'forms', true ) ).toBe( false );
	} );

	it( 'keys tours by version', () => {
		expect( tourKey( 'transactions' ) ).toBe(
			`transactions@${ TOUR_VERSION }`
		);
	} );
} );

describe( 'placeTooltip', () => {
	it( 'centres a step without a target', () => {
		expect( placeTooltip( null, CARD, VIEWPORT ) ).toEqual( {
			top: 310,
			left: 480,
			side: 'center',
			arrow: null,
		} );
	} );

	it( 'sits below the target with the arrow on its centre', () => {
		const target = { top: 100, left: 600, width: 80, height: 40 };

		expect( placeTooltip( target, CARD, VIEWPORT ) ).toEqual( {
			top: 154,
			left: 480,
			side: 'bottom',
			arrow: 160,
		} );
	} );

	it( 'flips above when there is no room below', () => {
		const target = { top: 700, left: 600, width: 80, height: 40 };

		expect( placeTooltip( target, CARD, VIEWPORT ).side ).toBe( 'top' );
		expect( placeTooltip( target, CARD, VIEWPORT ).top ).toBe( 506 );
	} );

	it( 'stays inside the viewport near an edge', () => {
		const target = { top: 100, left: 1240, width: 30, height: 30 };
		const placed = placeTooltip( target, CARD, VIEWPORT );

		expect( placed.left ).toBe( 1280 - 320 - 16 );
		expect( placed.arrow ).toBe( 304 );
	} );

	it( 'goes right of admin menu links, or below when it cannot', () => {
		const link = { top: 300, left: 0, width: 160, height: 30 };

		expect( placeTooltip( link, CARD, VIEWPORT, 'right' ) ).toMatchObject( {
			side: 'right',
			left: 174,
			arrow: 90,
		} );
		expect(
			placeTooltip( link, CARD, { width: 360, height: 800 }, 'right' )
				.side
		).toBe( 'bottom' );
	} );
} );

describe( 'tourSteps', () => {
	it( 'has a short tour for every screen', () => {
		const steps = tourSteps();

		for ( const screen of [
			'settings',
			'forms',
			'transactions',
			'integrations',
		] ) {
			expect( steps[ screen ].length ).toBeGreaterThan( 1 );
			expect( steps[ screen ].length ).toBeLessThanOrEqual( 6 );
			steps[ screen ].forEach( ( step ) => {
				expect( step.title ).toBeTruthy();
				expect( step.body ).toBeTruthy();
			} );
		}
	} );
} );

describe( 'pickTourState', () => {
	const server = { enabled: true, seen: [ 'forms@1' ], updated: 1000 };

	it( 'uses the server copy by default', () => {
		expect( pickTourState( server, null ) ).toEqual( {
			enabled: true,
			seen: [ 'forms@1' ],
		} );
		expect( pickTourState( undefined, null ) ).toEqual( {
			enabled: true,
			seen: [],
		} );
	} );

	it( 'prefers a newer browser copy that the server has not caught up with', () => {
		const local = {
			enabled: true,
			seen: [ 'forms@1', 'transactions@1' ],
			at: 1001000,
		};

		expect( pickTourState( server, local ).seen ).toContain(
			'transactions@1'
		);
	} );

	it( 'ignores an older or malformed browser copy', () => {
		expect(
			pickTourState( server, { enabled: false, seen: [], at: 999000 } )
		).toEqual( {
			enabled: true,
			seen: [ 'forms@1' ],
		} );
		expect(
			pickTourState( server, { enabled: false, at: 5000000 } ).enabled
		).toBe( true );
	} );
} );
