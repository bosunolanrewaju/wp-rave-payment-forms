/**
 * External dependencies
 */
const { readFileSync } = require( 'node:fs' );
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { adminState, wp } = require( './utils' );

const TRANSACTIONS =
	'/wp-admin/admin.php?page=flutterwave-payments-transactions';
const FORMS = '/wp-admin/admin.php?page=flutterwave-payment-forms';

test.use( { storageState: adminState } );

test.beforeAll( () => {
	wp( [ 'eval-file', 'tests/e2e/fixtures/payments.php' ] );
} );

test.describe( 'Transactions screen', () => {
	test( 'lists payments with their status', async ( { page } ) => {
		await page.goto( TRANSACTIONS );

		await expect(
			page.getByRole( 'button', { name: /Filter applied: 1/ } )
		).toBeVisible();

		const row = page.locator( '.flw-table tbody tr', {
			hasText: 'Ada Paid',
		} );
		await expect( row ).toContainText( 'NGN 5,000.00' );
		await expect( row.locator( '.flw-pill' ) ).toHaveText( 'Successful' );
	} );

	test( 'filters by status', async ( { page } ) => {
		await page.goto( TRANSACTIONS );
		await expect(
			page.locator( '.flw-table tbody tr' ).first()
		).toBeVisible();

		await page.getByRole( 'button', { name: /Filter applied/ } ).click();
		const status = page
			.locator( '.flw-filter-field', { hasText: 'Status' } )
			.locator( '.flw-multiselect__control' );
		await status.click();
		await page.getByLabel( 'Failed' ).check();
		await status.click();
		await page.getByRole( 'button', { name: 'Apply filter' } ).click();

		await expect(
			page.getByRole( 'button', { name: /Filter applied: 2/ } )
		).toBeVisible();
		await expect( page.locator( '.flw-table tbody tr' ) ).toHaveCount( 1 );
		await expect( page.locator( '.flw-table tbody' ) ).toContainText(
			'Bola Failed'
		);
	} );

	test( 'opens the details drawer', async ( { page } ) => {
		await page.goto( TRANSACTIONS );
		await page
			.locator( '.flw-table tbody tr', { hasText: 'Chen Pending' } )
			.click();

		const drawer = page.getByRole( 'dialog', {
			name: 'Transaction details',
		} );
		await expect( drawer ).toContainText( 'FLW-E2E-2' );
		await expect( drawer ).toContainText( 'flw-e2e-fixed' );
	} );

	test( 'downloads a CSV for the chosen range', async ( { page } ) => {
		await page.goto( TRANSACTIONS );
		await page.getByRole( 'button', { name: /Custom Download/i } ).click();
		await page.getByRole( 'button', { name: 'Last 7 days' } ).click();

		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			page
				.getByRole( 'button', { name: 'Download', exact: true } )
				.click(),
		] );

		expect( download.suggestedFilename() ).toMatch(
			/^flutterwave-transactions-\d{4}-\d{2}-\d{2}-to-\d{4}-\d{2}-\d{2}\.csv$/
		);
		const csv = readFileSync( await download.path(), 'utf8' );
		expect( csv ).toContain( 'Date,Reference,Customer' );
		expect( csv ).toContain( 'Ada Paid' );
	} );
} );

test.describe( 'Payment Forms screen', () => {
	test( 'lists forms with the page they are on', async ( { page } ) => {
		await page.goto( FORMS );

		const row = page.locator( '.flw-table tbody tr', {
			hasText: 'flw-e2e-fixed',
		} );
		await expect( row ).toContainText( 'NGN 5,000' );
		await expect( row ).toContainText( '1 payment' );
		await expect( row.locator( '.flw-pill' ) ).toHaveText( 'Published' );
	} );

	test( 'filters by form type', async ( { page } ) => {
		await page.goto( FORMS );
		await page.getByRole( 'tab', { name: /Donations/ } ).click();

		await expect(
			page.locator( '.flw-table tbody tr', { hasText: 'flw-e2e-fixed' } )
		).toHaveCount( 0 );
		await expect(
			page.locator( '.flw-table tbody tr', {
				hasText: 'flw-e2e-donation',
			} )
		).toHaveCount( 1 );
	} );

	test( 'builds a payment button page', async ( { page } ) => {
		await page.goto( FORMS );
		await page.getByRole( 'button', { name: 'New form' } ).first().click();
		await page
			.locator( '.flw-type-card', { hasText: 'Payment button' } )
			.click();

		await page.getByLabel( 'Page title' ).fill( 'E2E builder page' );
		await page.getByLabel( 'Amount' ).fill( '7500' );

		await expect( page.locator( '#flw-builder-shortcode' ) ).toHaveValue(
			/amount="7500"/
		);
		await expect(
			page.locator( '.flw-builder__preview-frame' )
		).toContainText( '7,500' );

		await page.getByRole( 'button', { name: 'Create page' } ).click();
		await page.waitForURL( /post\.php\?post=\d+&action=edit/ );

		const id = new URL( page.url() ).searchParams.get( 'post' );
		const content = wp( [ 'post', 'get', id, '--field=post_content' ] );
		expect( content ).toContain( '<!-- wp:flutterwave/payment-button' );
		expect( content ).toContain( '"amount":7500' );
	} );
} );

test.describe( 'Integrations screen', () => {
	const INTEGRATIONS =
		'/wp-admin/admin.php?page=flutterwave-payments-integrations';

	test( 'lists available and coming soon integrations', async ( {
		page,
	} ) => {
		await page.goto( INTEGRATIONS );

		await expect(
			page.getByRole( 'heading', { name: 'Integrations', level: 1 } )
		).toBeVisible();

		const woo = page.getByRole( 'article', { name: 'WooCommerce' } );
		await expect( woo ).toContainText(
			'Uses the free Flutterwave WooCommerce extension.'
		);
		await expect(
			page.getByRole( 'article', { name: 'Easy Digital Downloads' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'article', { name: 'GiveWP' } )
		).toBeVisible();

		const soon = page.locator( '.flw-integration.is-soon' );
		await expect( soon ).toHaveCount( 8 );
		await expect( soon.first().getByRole( 'button' ) ).toHaveCount( 0 );
	} );

	test( 'filters by category', async ( { page } ) => {
		await page.goto( INTEGRATIONS );
		await page.getByRole( 'button', { name: 'Donations' } ).click();

		await expect( page.locator( '.flw-integration' ) ).toHaveCount( 1 );
		await expect(
			page.getByRole( 'article', { name: 'GiveWP' } )
		).toBeVisible();
	} );
} );
