/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { adminState, wp } = require( './utils' );

const TRANSACTIONS =
	'/wp-admin/admin.php?page=flutterwave-payments-transactions';
const SETTINGS = '/wp-admin/admin.php?page=flutterwave-payments';

/**
 * Set the admin user's guided tips.
 *
 * @param {Object} state `{ enabled, seen }`.
 */
const setTips = ( state ) =>
	wp( [
		'user',
		'meta',
		'update',
		'admin',
		'flw_tour',
		JSON.stringify( state ),
		'--format=json',
	] );

test.use( { storageState: adminState } );

// Each test changes the same user's tips, so run them one after another.
test.describe.configure( { mode: 'serial' } );

test.describe( 'Guided tips', () => {
	test.afterAll( () => setTips( { enabled: false, seen: [] } ) );

	test( 'a page tour runs once and is remembered', async ( { page } ) => {
		setTips( { enabled: true, seen: [] } );
		await page.goto( TRANSACTIONS );

		const tip = page.getByRole( 'dialog', { name: 'Find a payment' } );
		await expect( tip ).toBeVisible();
		await expect( tip ).toContainText( '1 of' );

		await tip.getByRole( 'button', { name: 'Next' } ).click();
		const download = page.getByRole( 'dialog', {
			name: 'Download for your records',
		} );
		await expect( download ).toBeVisible();

		await download.getByRole( 'button', { name: 'Back' } ).click();
		await expect( tip ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );

		await page.reload();
		await expect(
			page.getByRole( 'button', { name: /Filter applied/ } )
		).toBeVisible();
		await page.waitForTimeout( 1000 );
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );
	} );

	test( 'the settings tour points at the new pages', async ( { page } ) => {
		setTips( { enabled: true, seen: [] } );
		await page.goto( SETTINGS );

		const welcome = page.getByRole( 'dialog', {
			name: 'Welcome to your Flutterwave dashboard',
		} );
		await expect( welcome ).toBeVisible();
		await welcome.getByRole( 'button', { name: 'Next' } ).click();
		await page
			.getByRole( 'dialog', { name: 'Your settings' } )
			.getByRole( 'button', { name: 'Next' } )
			.click();

		const forms = page.getByRole( 'dialog', { name: 'Payment Forms' } );
		await expect( forms ).toBeVisible();
		await expect( page.locator( '.flw-tour__card' ) ).toHaveClass(
			/is-right/
		);

		for ( const name of [
			'Payment Forms',
			'Transactions',
			'Integrations',
		] ) {
			await page
				.getByRole( 'dialog', { name } )
				.getByRole( 'button', { name: 'Next' } )
				.click();
		}

		const last = page.getByRole( 'dialog', {
			name: 'Help is always here',
		} );
		await expect( last ).toBeVisible();
		await expect(
			last.getByRole( 'button', { name: 'Skip tour' } )
		).toHaveCount( 0 );
		await last.getByRole( 'button', { name: 'Done' } ).click();
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );
	} );

	test( 'tips can be turned off and on from the Help menu', async ( {
		page,
	} ) => {
		setTips( { enabled: true, seen: [] } );
		await page.goto( TRANSACTIONS );
		await page
			.getByRole( 'dialog', { name: 'Find a payment' } )
			.getByRole( 'button', { name: 'Skip tour' } )
			.click();

		await page.getByRole( 'button', { name: 'Help' } ).click();
		await page.getByRole( 'switch', { name: 'Show tips' } ).click();

		// Off: a page not seen yet does not start its tour.
		await page.goto( '/wp-admin/admin.php?page=flutterwave-payment-forms' );
		await page.waitForTimeout( 1200 );
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );

		// Replaying still works while tips are off.
		await page.getByRole( 'button', { name: 'Help' } ).click();
		await page.getByRole( 'button', { name: /Take the tour/ } ).click();
		await expect(
			page.getByRole( 'dialog', { name: 'Create a form' } )
		).toBeVisible();
		await page.keyboard.press( 'Escape' );

		// On again: this page's tour starts straight away.
		await page.getByRole( 'button', { name: 'Help' } ).click();
		await page.getByRole( 'switch', { name: 'Show tips' } ).click();
		await expect(
			page.getByRole( 'dialog', { name: 'Create a form' } )
		).toBeVisible();
	} );
} );
