/**
 * Easy Digital Downloads and GiveWP paying through Flutterwave, end to end.
 *
 * Flutterwave is replaced by fixtures/mock-flutterwave.php: the API calls are
 * answered locally and the hosted checkout is a local page with "Pay now" and
 * "Cancel payment", which sends the customer back the way Flutterwave does.
 */

/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

/**
 * Internal dependencies
 */
const { evalJson, fixture } = require( './utils' );

let urls = {};

/**
 * Send navigations to the mock's checkout.flutterwave.com link to the local page.
 * GiveWP opens the link from the browser; Easy Digital Downloads' server-side
 * redirect is rewritten by the mock itself.
 *
 * @param {import('@playwright/test').BrowserContext} context Browser context.
 * @param {string}                                    baseURL Site URL.
 */
const routeHostedCheckout = ( context, baseURL ) =>
	context.route(
		( url ) =>
			'checkout.flutterwave.com' === url.hostname &&
			url.pathname.endsWith( '/hosted/pay/flw-e2e' ),
		( route ) => {
			const from = new URL( route.request().url() );
			const to = new URL( '/', baseURL );
			to.searchParams.set( 'flw-e2e-checkout', '1' );
			for ( const key of [ 'tx_ref', 'id', 'redirect' ] ) {
				to.searchParams.set( key, from.searchParams.get( key ) );
			}
			return route.fulfill( {
				status: 302,
				headers: { location: to.toString() },
			} );
		}
	);

/**
 * The newest Easy Digital Downloads order for an email address.
 *
 * @param {string} email Customer email.
 * @return {Object} `{ id, status, gateway, total, transaction }`.
 */
const eddOrder = ( email ) =>
	evalJson( `
		$o = edd_get_orders( array( 'email' => '${ email }', 'number' => 1, 'orderby' => 'id', 'order' => 'DESC' ) )[0] ?? null;
		echo wp_json_encode( $o ? array( 'id' => (int) $o->id, 'status' => $o->status, 'gateway' => $o->gateway, 'total' => (float) $o->total, 'transaction' => edd_get_payment_transaction_id( $o->id ) ) : null );
	` );

/**
 * Put the E2E download in the cart and fill in the checkout.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {string}                          email Customer email.
 */
const checkOut = async ( page, email ) => {
	await page.goto( urls.download );
	await page.locator( '.edd-add-to-cart' ).first().click();
	await expect( page.locator( '.edd_go_to_checkout' ).first() ).toBeVisible();

	await page.goto( urls.checkout );
	await page.locator( '#edd-email' ).fill( email );
	await page.locator( '#edd-first' ).fill( 'Ada' );
	await page.locator( '#edd-last' ).fill( 'Lovelace' );
	await expect( page.locator( '.flw-edd-note' ) ).toContainText(
		'You will be taken to Flutterwave'
	);
	await page.locator( '#edd-purchase-button' ).click();

	await expect(
		page.getByRole( 'heading', { name: 'Flutterwave checkout (test)' } )
	).toBeVisible();
	await expect( page.locator( '.amount' ) ).toHaveText( 'USD 25.00' );
};

const uniqueEmail = ( name ) =>
	`${ name }+${ Date.now() }-${ Math.floor( Math.random() * 1e6 ) }@example.com`;

test.beforeAll( () => {
	urls = fixture( 'integrations.php' );
} );

test.afterAll( () => {
	fixture( 'integrations.php', [ 'off' ] );
} );

test.beforeEach( async ( { context, baseURL } ) => {
	test.skip( Boolean( urls.skip ), urls.skip );
	await routeHostedCheckout( context, baseURL );
} );

test.describe( 'Easy Digital Downloads', () => {
	test( 'a customer pays with Flutterwave and the order completes', async ( {
		page,
	} ) => {
		const email = uniqueEmail( 'edd-paid' );
		await checkOut( page, email );

		expect( eddOrder( email ) ).toMatchObject( {
			status: 'pending',
			gateway: 'flutterwave',
			total: 25,
		} );

		await page.getByRole( 'link', { name: 'Pay now' } ).click();

		await expect( page.locator( 'body' ) ).toContainText( 'Completed' );
		await expect( page.locator( 'body' ) ).toContainText( 'Flutterwave' );

		const order = eddOrder( email );
		expect( order.status ).toBe( 'complete' );
		expect( order.transaction ).not.toBe( '' );
	} );

	test( 'a customer who cancels is told and can try again', async ( {
		page,
	} ) => {
		const email = uniqueEmail( 'edd-cancelled' );
		await checkOut( page, email );

		await page.getByRole( 'link', { name: 'Cancel payment' } ).click();

		await expect( page ).toHaveURL( /transaction-failed/ );
		await expect( page ).not.toHaveURL( /payment-id/ );
		// Easy Digital Downloads fails the order from the customer's own session.
		expect( eddOrder( email ).status ).toBe( 'failed' );
	} );

	test( 'someone else cannot change an order with the return link', async ( {
		page,
		playwright,
		baseURL,
	} ) => {
		const email = uniqueEmail( 'edd-forged' );
		await checkOut( page, email );
		const txRef = new URL( page.url() ).searchParams.get( 'tx_ref' );

		// A stranger with the reference, but not the customer's session.
		const stranger = await playwright.request.newContext( { baseURL } );
		for ( const params of [
			{ status: 'cancelled', tx_ref: txRef },
			{ status: 'successful', tx_ref: txRef, transaction_id: '12345' },
		] ) {
			const response = await stranger.get(
				'/wp-json/flutterwave/v1/return/easy-digital-downloads',
				{ params }
			);
			expect( response.ok() ).toBe( true );
			expect( response.url() ).not.toContain( 'payment-id' );
		}
		await stranger.dispose();

		expect( eddOrder( email ).status ).toBe( 'pending' );
	} );

	test( 'the webhook completes an order the customer never returned to', async ( {
		page,
		request,
	} ) => {
		const email = uniqueEmail( 'edd-webhook' );
		await checkOut( page, email );

		const checkout = new URL( page.url() );
		const hook = {
			event: 'charge.completed',
			data: {
				id: Number( checkout.searchParams.get( 'id' ) ),
				tx_ref: checkout.searchParams.get( 'tx_ref' ),
				status: 'successful',
			},
		};

		const forged = await request.post( '/wp-json/flutterwave/v1/webhook', {
			headers: { 'verif-hash': 'wrong-secret' },
			data: hook,
		} );
		expect( forged.status() ).toBe( 401 );
		expect( eddOrder( email ).status ).toBe( 'pending' );

		const signed = await request.post( '/wp-json/flutterwave/v1/webhook', {
			headers: { 'verif-hash': 'e2e-webhook-secret' },
			data: hook,
		} );
		expect( signed.ok() ).toBe( true );
		expect( eddOrder( email ).status ).toBe( 'complete' );
	} );
} );

test.describe( 'GiveWP', () => {
	test( 'a donor gives with Flutterwave and the donation completes', async ( {
		page,
	} ) => {
		const email = uniqueEmail( 'give' );
		await page.goto( urls.donate );

		const form = page.frameLocator( 'iframe' ).first();
		await form.locator( 'button', { hasText: '$25.00' } ).click();
		await form.getByRole( 'button', { name: /Donate now/ } ).click();
		await form.getByLabel( /First name/i ).fill( 'Grace' );
		await form.getByLabel( /Last name/i ).fill( 'Wanjiru' );
		await form.getByLabel( /Email/i ).fill( email );
		await form
			.getByRole( 'button', { name: /Continue|Next/ } )
			.first()
			.click();

		await expect(
			form.getByText( 'Donate with Flutterwave' )
		).toBeVisible();
		await form.getByText( 'Donate with Flutterwave' ).click();
		await expect(
			form.getByText( 'You will be taken to Flutterwave' )
		).toBeVisible();
		await form
			.getByRole( 'button', { name: /Donate/ } )
			.last()
			.click();

		await expect(
			page.getByRole( 'heading', { name: 'Flutterwave checkout (test)' } )
		).toBeVisible();
		await expect( page.locator( '.amount' ) ).toHaveText( 'USD 25.00' );
		await page.getByRole( 'link', { name: 'Pay now' } ).click();

		const receipt = page.frameLocator( 'iframe' ).first();
		await expect( receipt.getByText( 'Completed' ) ).toBeVisible();

		const donation = evalJson( `
			$d = give()->donations->prepareQuery()->where( 'give_donationmeta_attach_meta_email.meta_value', '${ email }' )->orderBy( 'id', 'DESC' )->get();
			echo wp_json_encode( $d ? array( 'status' => $d->status->getValue(), 'gateway' => $d->gatewayId, 'transaction' => $d->gatewayTransactionId ) : null );
		` );
		expect( donation ).toMatchObject( {
			status: 'publish',
			gateway: 'flutterwave',
		} );
		expect( donation.transaction ).not.toBe( '' );
	} );
} );
