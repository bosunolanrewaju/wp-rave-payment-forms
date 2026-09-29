/**
 * External dependencies
 */
const { writeFileSync, mkdirSync } = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { wp, seed, STATE_DIR } = require( './utils' );

module.exports = async ( config ) => {
	const { baseURL } = config.projects[ 0 ].use;

	mkdirSync( STATE_DIR, { recursive: true } );

	wp( [ 'plugin', 'activate', 'rave-payment-forms' ] );
	const pages = seed();
	writeFileSync(
		path.join( STATE_DIR, 'pages.json' ),
		JSON.stringify( pages )
	);

	const browser = await chromium.launch();

	// The first login after the site starts can be slow while caches warm up, so
	// try a few times before failing the whole run.
	for ( let attempt = 1; ; attempt++ ) {
		const page = await browser.newPage( { baseURL } );

		try {
			await page.goto( '/wp-login.php' );
			await page
				.locator( '#user_login' )
				.fill( process.env.WP_USERNAME || 'admin' );
			await page
				.locator( '#user_pass' )
				.fill( process.env.WP_PASSWORD || 'password' );
			await Promise.all( [
				page.waitForURL( '**/wp-admin/**', { timeout: 60_000 } ),
				page.locator( '#wp-submit' ).click(),
			] );
			await page
				.context()
				.storageState( { path: path.join( STATE_DIR, 'admin.json' ) } );
			await page.close();
			break;
		} catch ( error ) {
			await page.close();

			if ( attempt >= 3 ) {
				await browser.close();
				throw error;
			}
		}
	}

	await browser.close();
};
