/**
 * Registers the Flutterwave gateway on GiveWP visual builder forms. Donors pay
 * on Flutterwave's hosted checkout, so the gateway has no fields of its own.
 */
( function () {
	if ( ! window.givewp || ! window.givewp.gateways ) {
		return;
	}

	const gateway = {
		id: 'flutterwave',
		Fields() {
			return ( gateway.settings && gateway.settings.message ) || '';
		},
	};

	window.givewp.gateways.register( gateway );
} )();
