/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { pageUrl } = require( './utils' );

test.describe( 'Payment forms', () => {
	test( 'fixed amount form shows the amount and carries a signed config', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'fixed' ) );

		const form = page.locator( 'form.flw-simple-pay-now-form' );
		await expect( form ).toHaveAttribute( 'data-amount', '5000' );
		await expect( form.locator( '#flw-full-name' ) ).toBeVisible();
		await expect( form.locator( '#flw-amount' ) ).toHaveCount( 0 );
		await expect(
			form.locator( 'input[name="flw_form_sig"]' )
		).toHaveAttribute( 'value', /^[a-f0-9]{64}$/ );
	} );

	test( 'forms use the Flutterwave design', async ( { page } ) => {
		await page.goto( pageUrl( 'fixed' ) );

		await expect( page.locator( '#flw-pay-now-button' ) ).toHaveCSS(
			'background-color',
			'rgb(255, 155, 0)'
		);
		await expect( page.locator( '.flw_payment_overview' ) ).toContainText(
			'NGN 5,000.00'
		);
		await expect( page.locator( '.flw-secured' ) ).toContainText(
			'Secured by Flutterwave'
		);
		await page.evaluate( () => document.fonts.ready );
		expect(
			await page.evaluate( () => document.fonts.check( '16px Moderat' ) )
		).toBe( true );
	} );

	test( 'open amount form asks for the amount', async ( { page } ) => {
		await page.goto( pageUrl( 'open' ) );

		await expect( page.locator( '#flw-amount' ) ).toBeVisible();
	} );

	test( 'server rejects a forged form config before contacting Flutterwave', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'fixed' ) );

		await page.evaluate( () => {
			const payload = btoa(
				JSON.stringify( { amount: 1, currencies: [ 'NGN' ] } )
			);
			document.querySelector( 'input[name="flw_form_config"]' ).value =
				payload;
		} );

		await page
			.locator( '#flw-customer-email' )
			.fill( 'customer@example.com' );
		await page.locator( '#flw-full-name' ).fill( 'Test Customer' );

		const response = page.waitForResponse( ( res ) =>
			res.url().includes( 'admin-ajax.php' )
		);
		await page.locator( '#flw-pay-now-button' ).click();

		expect( ( await response ).status() ).toBe( 400 );
		await expect( page.locator( '.flw-error' ) ).toContainText(
			'invalid or has expired'
		);
		await expect( page.locator( '#flw-pay-now-button' ) ).toBeEnabled();
	} );

	test( 'donation form renders payment types and a signed config', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'donation' ) );

		await expect( page.locator( '#flw-payment-type option' ) ).toHaveText( [
			'Give once',
			'Give monthly',
			'Give yearly',
		] );
		await expect(
			page.locator( 'input[name="flw_form_sig"]' )
		).toHaveCount( 1 );
	} );

	test( 'shortcode attributes cannot inject event handlers', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'xss' ) );

		const form = page.locator( 'form.flw-simple-pay-now-form' );
		await form.hover();

		expect( await form.getAttribute( 'onmouseover' ) ).toBeNull();
		expect( await page.evaluate( () => window.flwPwned ) ).toBeUndefined();
	} );
} );
