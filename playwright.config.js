/**
 * External dependencies
 */
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * End-to-end tests run against the wp-env development site (`npm run env:start`).
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	// Only specs: tests/e2e/report holds unit tests for the report script, run by Vitest.
	testMatch: '**/*.spec.js',
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	timeout: 60_000,
	fullyParallel: false,
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	// Every run writes an HTML report (playwright-report/) plus JSON and JUnit
	// results (test-reports/) that the scheduled report job summarises.
	reporter: [
		process.env.CI ? [ 'github' ] : [ 'list' ],
		[ 'html', { open: 'never', outputFolder: 'playwright-report' } ],
		[ 'json', { outputFile: 'test-reports/e2e-results.json' } ],
		[ 'junit', { outputFile: 'test-reports/e2e-junit.xml' } ],
	],
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
