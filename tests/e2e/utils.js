/**
 * External dependencies
 */
const { execFileSync } = require( 'node:child_process' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );

const STATE_DIR = path.join( __dirname, '.state' );

/**
 * Run a WP-CLI command on the wp-env development site.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Command output.
 */
function wp( args ) {
	return execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'cli',
			'--env-cwd=wp-content/plugins/rave-payment-forms',
			'wp',
			...args,
		],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] }
	);
}

/**
 * Seed settings and pages for the tests, returning the page URLs.
 *
 * @return {Object} Page URLs keyed by slug.
 */
function seed() {
	const output = wp( [ 'eval-file', 'tests/e2e/fixtures/setup.php' ] );
	return JSON.parse( output.slice( output.indexOf( '{' ) ) );
}

/**
 * Run PHP on the development site and parse the JSON it echoes.
 *
 * @param {string}   file Fixture under tests/e2e/fixtures.
 * @param {string[]} args Arguments passed to the fixture.
 * @return {Object} Decoded output.
 */
function fixture( file, args = [] ) {
	const output = wp( [
		'eval-file',
		`tests/e2e/fixtures/${ file }`,
		...args,
	] );
	return JSON.parse( output.slice( output.indexOf( '{' ) ) );
}

/**
 * Evaluate PHP on the development site and parse the JSON it echoes.
 *
 * @param {string} code PHP code that echoes JSON.
 * @return {*} Decoded output.
 */
function evalJson( code ) {
	const output = wp( [ 'eval', code ] );
	return JSON.parse( output.slice( output.search( /[[{"]/ ) ) );
}

/**
 * URL of a page seeded by fixtures/setup.php.
 *
 * @param {string} name Page key, e.g. "fixed".
 * @return {string} Relative URL.
 */
function pageUrl( name ) {
	const pages = JSON.parse(
		readFileSync( path.join( STATE_DIR, 'pages.json' ), 'utf8' )
	);
	return pages[ `flw-e2e-${ name }` ];
}

module.exports = {
	wp,
	seed,
	fixture,
	evalJson,
	pageUrl,
	STATE_DIR,
	adminState: path.join( STATE_DIR, 'admin.json' ),
};
