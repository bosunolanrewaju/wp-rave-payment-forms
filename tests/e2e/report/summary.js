/**
 * Turns Playwright's JSON report into a Markdown summary for the GitHub job page.
 *
 * Usage: node tests/e2e/report/summary.js test-reports/e2e-results.json [artifact-url]
 */

/**
 * External dependencies
 */
const { readFileSync } = require( 'node:fs' );

/**
 * Every test in a Playwright JSON report, with the file and describe blocks it is in.
 *
 * @param {Array}         suites Suites from the report.
 * @param {string[]|null} path   Titles of the enclosing describe blocks; null at the top.
 * @return {Array} `{ file, title, status, duration, error }` per test.
 */
const collect = ( suites = [], path = null ) =>
	suites.flatMap( ( suite ) => {
		// Top-level suites are spec files, not describe blocks, so their title is left out.
		const here = null === path ? [] : [ ...path, suite.title ];
		const own = ( suite.specs || [] ).flatMap( ( spec ) =>
			( spec.tests || [] ).map( ( test ) => {
				const results = test.results || [];
				const last = results[ results.length - 1 ] || {};
				const errorOf = ( result ) =>
					result.error || ( result.errors || [] )[ 0 ];
				const failed = results.find( errorOf );

				return {
					file: spec.file || suite.file,
					title: [ ...here, spec.title ]
						.filter( Boolean )
						.join( ' › ' ),
					status: test.status,
					duration: results.reduce(
						( total, result ) => total + ( result.duration || 0 ),
						0
					),
					error:
						'unexpected' === test.status
							? (
									errorOf( last ) ||
									( failed && errorOf( failed ) ) ||
									{}
								).message || ''
							: '',
				};
			} )
		);

		return [ ...own, ...collect( suite.suites, here ) ];
	} );

/**
 * Milliseconds as "1m 04s" or "12.3s".
 *
 * @param {number} ms Duration.
 * @return {string} Readable duration.
 */
const formatDuration = ( ms ) => {
	const seconds = ms / 1000;

	if ( seconds < 60 ) {
		return `${ seconds.toFixed( 1 ) }s`;
	}

	const minutes = Math.floor( seconds / 60 );
	return `${ minutes }m ${ String( Math.round( seconds % 60 ) ).padStart( 2, '0' ) }s`;
};

/**
 * First lines of an error, without terminal colour codes, safe inside a code block.
 *
 * @param {string} message Error message.
 * @return {string} Trimmed message.
 */
const cleanError = ( message ) =>
	message

		.replace( /\u001b\[[0-9;]*m/g, '' )
		.split( '\n' )
		.slice( 0, 6 )
		.join( '\n' )
		.replace( /```/g, "'''" )
		.trim();

/**
 * A table cell: pipes and line breaks would break the table.
 *
 * @param {string} text Cell text.
 * @return {string} Escaped text.
 */
const cell = ( text ) =>
	String( text ).replace( /\|/g, '\\|' ).replace( /\n/g, ' ' );

/**
 * The Markdown summary.
 *
 * @param {Object} report                Playwright JSON report.
 * @param {Object} [options]
 * @param {string} [options.artifactUrl] Link to the uploaded HTML report.
 * @return {string} Markdown.
 */
const summarize = ( report, { artifactUrl = '' } = {} ) => {
	const tests = collect( report.suites );
	const stats = report.stats || {};
	const count = ( status ) =>
		tests.filter( ( test ) => test.status === status ).length;

	const passed = count( 'expected' );
	const failed = count( 'unexpected' );
	const flaky = count( 'flaky' );
	const skipped = count( 'skipped' );
	const setupErrors = ( report.errors || [] ).map(
		( error ) => error.message || ''
	);
	const ok = 0 === failed && 0 === setupErrors.length;

	const lines = [
		`## End-to-end report: ${ ok ? 'passed' : 'failed' }`,
		'',
		stats.startTime
			? `Run on ${ new Date( stats.startTime ).toUTCString() } in ${ formatDuration( stats.duration || 0 ) }.`
			: '',
		'',
		'| Total | Passed | Failed | Flaky | Skipped |',
		'| ---: | ---: | ---: | ---: | ---: |',
		`| ${ tests.length } | ${ passed } | ${ failed } | ${ flaky } | ${ skipped } |`,
		'',
	];

	if ( setupErrors.length ) {
		lines.push( '### Errors outside tests', '' );
		setupErrors.forEach( ( message ) =>
			lines.push( '```', cleanError( message ), '```', '' )
		);
	}

	const failures = tests.filter( ( test ) => 'unexpected' === test.status );

	if ( failures.length ) {
		lines.push( '### Failed', '' );
		failures.forEach( ( test ) => {
			lines.push( `**${ test.file }** › ${ test.title }`, '' );
			if ( test.error ) {
				lines.push( '```', cleanError( test.error ), '```', '' );
			}
		} );
	}

	const flakyTests = tests.filter( ( test ) => 'flaky' === test.status );

	if ( flakyTests.length ) {
		lines.push(
			'### Flaky (passed on retry)',
			'',
			...flakyTests.map(
				( test ) => `- **${ test.file }** › ${ test.title }`
			),
			''
		);
	}

	const files = [ ...new Set( tests.map( ( test ) => test.file ) ) ].sort();

	lines.push(
		'### By spec',
		'',
		'| Spec | Passed | Failed | Flaky | Skipped | Time |',
		'| --- | ---: | ---: | ---: | ---: | ---: |',
		...files.map( ( file ) => {
			const inFile = tests.filter( ( test ) => test.file === file );
			const n = ( status ) =>
				inFile.filter( ( test ) => test.status === status ).length;
			const time = inFile.reduce(
				( total, test ) => total + test.duration,
				0
			);

			return `| ${ cell( file ) } | ${ n( 'expected' ) } | ${ n( 'unexpected' ) } | ${ n( 'flaky' ) } | ${ n( 'skipped' ) } | ${ formatDuration( time ) } |`;
		} ),
		''
	);

	if ( artifactUrl ) {
		lines.push(
			`[Download the full HTML report](${ artifactUrl }) with traces and screenshots of any failures.`,
			''
		);
	}

	return lines
		.filter(
			( line, index, all ) => ! ( '' === line && '' === all[ index - 1 ] )
		)
		.join( '\n' );
};

module.exports = { collect, summarize, formatDuration };

if ( require.main === module ) {
	const [ file, artifactUrl ] = process.argv.slice( 2 );

	try {
		process.stdout.write(
			summarize( JSON.parse( readFileSync( file, 'utf8' ) ), {
				artifactUrl,
			} ) + '\n'
		);
	} catch ( error ) {
		// No report means the run died before Playwright wrote one.
		process.stdout.write(
			`## End-to-end report: no results\n\nPlaywright did not write a report (${ error.message }). Check the job log.\n`
		);
	}
}
