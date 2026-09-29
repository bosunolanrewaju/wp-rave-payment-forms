/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { adminState, wp, seed } = require( './utils' );

const SETTINGS = '/wp-admin/admin.php?page=flutterwave-payments';

test.describe( 'Onboarding', () => {
	test.use( { storageState: adminState } );

	// These tests start from a fresh install, so they must not interleave.
	test.describe.configure( { mode: 'serial' } );

	// Tips are on, as for a new merchant, so the tour after onboarding is checked too.
	const setTips = ( enabled ) =>
		wp( [
			'user',
			'meta',
			'update',
			'admin',
			'flw_tour',
			JSON.stringify( { enabled, seen: [] } ),
			'--format=json',
		] );

	test.beforeAll( () => {
		wp( [ 'option', 'delete', 'flw_rave_options' ] );
		setTips( true );
	} );

	test.afterAll( () => {
		seed();
		setTips( false );
	} );

	test( 'a new merchant goes from welcome to a working setup', async ( {
		page,
	} ) => {
		const next = () =>
			page
				.getByRole( 'button', { name: /^Save( & continue)?$/ } )
				.click();

		await page.goto( SETTINGS );
		await expect(
			page.getByRole( 'heading', { name: /Welcome to Flutterwave/ } )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Activate Flutterwave' } )
			.click();

		// Step 1: the button stays disabled until required fields are filled.
		await expect( page.getByText( 'Step 1 of 4' ) ).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Save & continue' } )
		).toBeDisabled();
		await page.locator( '#flw-title' ).fill( 'Acme Studio' );
		await page.locator( '#flw-currency' ).selectOption( 'NGN' );
		await next();

		// Step 2: a live secret key with a test public key is explained inline.
		await expect( page.getByText( 'Step 2 of 4' ) ).toBeVisible();
		await expect( page.locator( '#flw-webhook' ) ).toHaveValue(
			/\/wp-json\/flutterwave\/v1\/webhook$/
		);
		await expect( page.locator( '#flw-secret-hash' ) ).toHaveValue(
			/^[a-f0-9]{64}$/
		);
		await page.locator( '#flw-publicKey' ).fill( 'FLWPUBK_TEST-e2e-X' );
		await page.locator( '#flw-secretKey' ).fill( 'FLWSECK-e2e-X' );
		await expect(
			page.getByText( /same mode \(test or live\)/ )
		).toBeVisible();
		await page.locator( '#flw-secretKey' ).fill( 'FLWSECK_TEST-e2e-X' );
		await expect(
			page.getByText( /Test keys: payments are simulated/ )
		).toBeVisible();
		await next();

		// Step 3: sensible defaults are already ticked.
		await expect( page.getByText( 'Step 3 of 4' ) ).toBeVisible();
		await expect( page.locator( '#flw-method-card' ) ).toBeChecked();
		await page.locator( 'label[for="flw-method-applepay"]' ).click();
		await next();

		// Step 4: redirects are prefilled with the home page.
		await expect( page.getByText( 'Step 4 of 4' ) ).toBeVisible();
		await expect( page.locator( '#flw-successUrl' ) ).toHaveValue(
			/^http/
		);
		await next();

		await expect(
			page.getByRole( 'heading', { name: 'Setup successful!' } )
		).toBeVisible();
		await expect( page.locator( '#flw-shortcode' ) ).toHaveValue(
			'[flw-pay-form amount="5000"]'
		);
		// No tips during setup: they start once the merchant reaches the dashboard.
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );

		const saved = JSON.parse(
			wp( [ 'option', 'get', 'flw_rave_options', '--format=json' ] )
		);
		expect( saved ).toMatchObject( {
			modal_title: 'Acme Studio',
			currency: 'NGN',
			public_key: 'FLWPUBK_TEST-e2e-X',
			secret_key: 'FLWSECK_TEST-e2e-X',
			onboarding_complete: 'yes',
		} );
		expect( saved.payment_options ).toContain( 'applepay' );

		await page.getByRole( 'button', { name: 'Go to settings' } ).click();
		await expect(
			page.getByRole( 'tab', { name: 'General' } )
		).toHaveAttribute( 'aria-selected', 'true' );

		// The guided tour starts straight after onboarding.
		const welcome = page.getByRole( 'dialog', {
			name: 'Welcome to your Flutterwave dashboard',
		} );
		await expect( welcome ).toBeVisible();
		await welcome.getByRole( 'button', { name: 'Skip tour' } ).click();
		await expect( page.locator( '.flw-tour__card' ) ).toHaveCount( 0 );
	} );

	test( 'returning merchants land on the settings tabs', async ( {
		page,
	} ) => {
		await page.goto( `${ SETTINGS }&tab=api` );

		await expect(
			page.getByRole( 'tab', { name: 'API & Webhook' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		await expect( page.locator( '#flw-secretKey' ) ).toHaveAttribute(
			'type',
			'password'
		);

		await page.getByRole( 'button', { name: 'Show' } ).click();
		await expect( page.locator( '#flw-secretKey' ) ).toHaveAttribute(
			'type',
			'text'
		);

		await page.getByRole( 'tab', { name: 'General' } ).click();
		await page.locator( '#flw-title' ).fill( 'Acme Studio Ltd' );
		await page.getByRole( 'button', { name: 'Save & continue' } ).click();
		await expect( page.getByText( 'Changes saved.' ) ).toBeVisible();
		await expect(
			page.getByRole( 'tab', { name: 'API & Webhook' } )
		).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );
