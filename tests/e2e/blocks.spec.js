/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { adminState, pageUrl } = require( './utils' );

test.describe( 'Flutterwave blocks on the front end', () => {
	test( 'payment button shows the price and uses the block colours', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'blocks' ) );

		const button = page
			.locator(
				'.wp-block-flutterwave-payment-button .flw-pay-now-button'
			)
			.first();
		await expect( button ).toHaveText( 'Pay NGN 5,000' );
		await expect( button ).toHaveCSS(
			'background-color',
			'rgb(42, 51, 98)'
		);
		await expect( button ).toHaveCSS( 'color', 'rgb(255, 255, 255)' );
	} );

	test( 'a tampered block price is rejected by the server', async ( {
		page,
	} ) => {
		await page.goto( pageUrl( 'blocks' ) );

		const block = page.locator( '.wp-block-flutterwave-payment-button' );
		await block
			.locator( 'input[name="flw_form_config"]' )
			.evaluate( ( input ) => {
				input.value = btoa(
					JSON.stringify( { amount: 1, currencies: [ 'NGN' ] } )
				);
			} );
		await block
			.locator( '#flw-customer-email' )
			.fill( 'buyer@example.com' );

		const response = page.waitForResponse( ( res ) =>
			res.url().includes( 'admin-ajax.php' )
		);
		await block.locator( '.flw-pay-now-button' ).click();

		expect( ( await response ).status() ).toBe( 400 );
		await expect( block.locator( '.flw-error' ) ).toBeVisible();
	} );

	test( 'the amount a donor types is the amount sent', async ( { page } ) => {
		let posted;
		await page.route( '**/admin-ajax.php', async ( route ) => {
			posted = new URLSearchParams( route.request().postData() );
			await route.fulfill( {
				status: 400,
				contentType: 'application/json',
				body: JSON.stringify( { status: 'error', message: 'stubbed' } ),
			} );
		} );

		await page.goto( pageUrl( 'donation' ) );
		const form = page.locator( '.flutterwave-donation-form' );
		await form.locator( '#flw-customer-email' ).fill( 'donor@example.com' );
		await form.locator( '#flw-first-name' ).fill( 'Ada' );
		await form.locator( '#flw-last-name' ).fill( 'Lovelace' );
		await form.locator( '#flw-payment-type' ).selectOption( 'yearly' );
		await form.locator( '#flw-amount' ).fill( '10000' );
		await form.locator( '#flw-pay-now-button' ).click();

		await expect( form.locator( '.flw-error' ) ).toHaveText( 'stubbed' );
		expect( posted.get( 'amount' ) ).toBe( '10000' );
		expect( posted.get( 'payment_type' ) ).toBe( 'yearly' );
	} );

	test( 'the amount a customer types is the amount sent', async ( {
		page,
	} ) => {
		let posted;
		await page.route( '**/admin-ajax.php', async ( route ) => {
			posted = new URLSearchParams( route.request().postData() );
			await route.fulfill( {
				status: 400,
				contentType: 'application/json',
				body: JSON.stringify( { status: 'error', message: 'stubbed' } ),
			} );
		} );

		await page.goto( pageUrl( 'open' ) );
		await page.locator( '#flw-customer-email' ).fill( 'buyer@example.com' );
		await page.locator( '#flw-full-name' ).fill( 'Ada Lovelace' );
		await page.locator( '#flw-amount' ).fill( '2500' );
		await page.locator( '#flw-pay-now-button' ).click();

		await expect( page.locator( '.flw-error' ) ).toHaveText( 'stubbed' );
		expect( posted.get( 'amount' ) ).toBe( '2500' );
	} );

	test( 'donation presets fill the amount', async ( { page } ) => {
		await page.goto( pageUrl( 'blocks' ) );

		const form = page.locator( '.wp-block-flutterwave-donation-form' );
		await expect( form.locator( '#flw-payment-type' ) ).toHaveCount( 0 );

		const preset = form.getByRole( 'button', { name: '5,000' } );
		await preset.click();

		await expect( preset ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( form.locator( '#flw-amount' ) ).toHaveValue( '5000' );

		await form.locator( '#flw-amount' ).fill( '7500' );
		await expect( preset ).toHaveAttribute( 'aria-pressed', 'false' );
	} );
} );

test.describe( 'Flutterwave blocks in the editor', () => {
	test.use( { storageState: adminState } );

	test( 'blocks are listed and preview the server output', async ( {
		page,
	} ) => {
		const errors = [];
		// Other plugins on the site can throw in the editor; only this plugin's errors count.
		page.on( 'pageerror', ( error ) => {
			if (
				! /\/wp-content\/plugins\/(?!rave-payment-forms\/)/.test(
					error.stack || ''
				)
			) {
				errors.push( error.message );
			}
		} );

		await page.goto( '/wp-admin/post-new.php' );
		await page.waitForFunction(
			() =>
				window.wp?.blocks &&
				window.wp?.data?.select( 'core/block-editor' )
		);

		const names = await page.evaluate( () => {
			window.wp.data
				.dispatch( 'core/preferences' )
				.set( 'core/edit-post', 'welcomeGuide', false );

			return window.wp.blocks
				.getBlockTypes()
				.filter( ( type ) => 'flutterwave' === type.category )
				.map( ( type ) => type.name )
				.sort();
		} );

		expect( names ).toEqual( [
			'flutterwave/donation-form',
			'flutterwave/payment-button',
			'flutterwave/payment-form',
			'flutterwave/payment-methods',
			'flutterwave/pricing-card',
		] );

		await page.evaluate( () => {
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( [
				createBlock( 'flutterwave/payment-button', {
					amount: 2500,
					currency: 'NGN',
					useUserEmail: false,
					buttonText: 'Buy – {amount}',
				} ),
				createBlock( 'flutterwave/pricing-card', {
					planName: 'Pro',
					amount: 9000,
					currency: 'NGN',
				} ),
			] );
		} );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect( canvas.getByText( 'Buy – NGN 2,500' ) ).toBeVisible();
		await expect(
			canvas.locator( '.flw-pricing-card__amount' )
		).toHaveText( 'NGN 9,000' );
		expect( errors ).toEqual( [] );
	} );
} );
