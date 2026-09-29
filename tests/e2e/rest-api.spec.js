/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { adminState } = require( './utils' );

test.describe( 'REST routes', () => {
	test( 'webhook without a valid hash is rejected', async ( { request } ) => {
		const missing = await request.post( '/wp-json/flutterwave/v1/webhook', {
			data: { event: 'charge.completed', data: { id: 1 } },
		} );
		expect( missing.status() ).toBe( 401 );

		const wrong = await request.post( '/wp-json/flutterwave/v1/webhook', {
			headers: { 'verif-hash': 'guess' },
			data: { event: 'charge.completed', data: { id: 1 } },
		} );
		expect( wrong.status() ).toBe( 401 );
	} );

	test( 'update-transaction requires an administrator', async ( {
		request,
	} ) => {
		const response = await request.get(
			'/wp-json/flutterwave/v1/update-transaction?post_id=1',
			{ maxRedirects: 0 }
		);
		expect( response.status() ).toBe( 401 );
	} );
} );

test.describe( 'Admin pages', () => {
	test.use( { storageState: adminState } );

	test( 'transactions page loads for administrators', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=flutterwave-payments-transactions'
		);

		await expect(
			page.getByRole( 'heading', { name: /\d+ Transactions?/ } )
		).toBeVisible();
	} );

	test( 'settings page shows the admin app', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=flutterwave-payments' );

		await expect(
			page.getByRole( 'tab', { name: 'General' } )
		).toBeVisible();
		await expect( page.locator( '#flw-title' ) ).toBeVisible();
	} );
} );
