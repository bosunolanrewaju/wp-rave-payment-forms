/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { collect, formatDuration, summarize } from './summary';

/**
 * A test result as Playwright writes it.
 *
 * @param {string} status  Final test status.
 * @param {Array}  results Attempts.
 * @return {Object} Test.
 */
const run = ( status, results ) => ( { status, results } );

// Shaped like Playwright's JSON reporter output: file suite > describe > specs.
const report = {
	stats: { startTime: '2026-09-25T12:00:00.000Z', duration: 95000 },
	errors: [],
	suites: [
		{
			title: 'checkout.spec.js',
			file: 'checkout.spec.js',
			specs: [
				{
					title: 'loads',
					file: 'checkout.spec.js',
					tests: [
						run( 'expected', [
							{ status: 'passed', duration: 1200 },
						] ),
					],
				},
			],
			suites: [
				{
					title: 'Payment forms',
					file: 'checkout.spec.js',
					specs: [
						{
							title: 'rejects | forged config',
							file: 'checkout.spec.js',
							tests: [
								run( 'unexpected', [
									{
										status: 'failed',
										duration: 3000,
										errors: [
											{
												message:
													'\u001b[31mError: expect(received).toBe(expected)\u001b[39m\n\nExpected: 400\nReceived: 200\n\nat line 1\nat line 2\nat line 3',
											},
										],
									},
								] ),
							],
						},
						{
							title: 'retries',
							file: 'checkout.spec.js',
							tests: [
								run( 'flaky', [
									{
										status: 'failed',
										duration: 500,
										error: { message: 'Timeout' },
									},
									{ status: 'passed', duration: 400 },
								] ),
							],
						},
					],
				},
			],
		},
		{
			title: 'tour.spec.js',
			file: 'tour.spec.js',
			specs: [
				{
					title: 'later',
					file: 'tour.spec.js',
					tests: [ run( 'skipped', [] ) ],
				},
			],
		},
	],
};

describe( 'collect', () => {
	it( 'flattens specs with their describe blocks but not the file suite', () => {
		expect(
			collect( report.suites ).map( ( test ) => test.title )
		).toEqual( [
			'loads',
			'Payment forms › rejects | forged config',
			'Payment forms › retries',
			'later',
		] );
	} );

	it( 'keeps the error of failed tests only, and adds up retries', () => {
		const [ passed, failed, flaky ] = collect( report.suites );

		expect( passed.error ).toBe( '' );
		expect( failed.error ).toContain( 'Expected: 400' );
		expect( flaky.error ).toBe( '' );
		expect( flaky.duration ).toBe( 900 );
	} );
} );

describe( 'summarize', () => {
	const markdown = summarize( report, {
		artifactUrl: 'https://example.com/a/1',
	} );

	it( 'reports the overall result and totals', () => {
		expect( markdown ).toContain( '## End-to-end report: failed' );
		expect( markdown ).toContain( 'in 1m 35s' );
		expect( markdown ).toContain( '| 4 | 1 | 1 | 1 | 1 |' );
	} );

	it( 'lists failures with a clean, short error', () => {
		expect( markdown ).toContain(
			'**checkout.spec.js** › Payment forms › rejects | forged config'
		);
		expect( markdown ).toContain(
			'Error: expect(received).toBe(expected)'
		);
		expect( markdown ).not.toContain( '\u001b[' );
		expect( markdown ).not.toContain( 'at line 3' );
	} );

	it( 'lists flaky tests and a row per spec', () => {
		expect( markdown ).toContain( '### Flaky (passed on retry)' );
		expect( markdown ).toContain(
			'| checkout.spec.js | 1 | 1 | 1 | 0 | 5.1s |'
		);
		expect( markdown ).toContain(
			'| tour.spec.js | 0 | 0 | 0 | 1 | 0.0s |'
		);
		expect( markdown ).toContain( '(https://example.com/a/1)' );
	} );

	it( 'passes when nothing failed, and reports errors outside tests', () => {
		const clean = {
			...report,
			suites: [ report.suites[ 1 ] ],
		};

		expect( summarize( clean ) ).toContain(
			'## End-to-end report: passed'
		);
		expect(
			summarize( {
				...clean,
				errors: [ { message: 'globalSetup timed out' } ],
			} )
		).toContain( '### Errors outside tests' );
	} );
} );

describe( 'formatDuration', () => {
	it( 'formats seconds and minutes', () => {
		expect( formatDuration( 12345 ) ).toBe( '12.3s' );
		expect( formatDuration( 64000 ) ).toBe( '1m 04s' );
	} );
} );
