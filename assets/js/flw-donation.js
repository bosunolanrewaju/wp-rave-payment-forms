jQuery( function ( $ ) {
	const options = window.flw_pay_options;

	/**
	 * Reads a data-* attribute as a raw string.
	 *
	 * @param {jQuery} form The form element.
	 * @param {string} name Attribute name without the data- prefix.
	 * @return {string|undefined} The attribute value.
	 */
	const readAttr = function ( form, name ) {
		const value = $( form ).attr( 'data-' + name );
		return value === undefined || value === '' ? undefined : value;
	};

	/**
	 * The fixed amount from data-amount, or the amount the customer typed.
	 *
	 * @param {jQuery} form The form element.
	 * @return {string|undefined} The amount to charge.
	 */
	const amountFor = function ( form ) {
		const fixed = readAttr( form, 'amount' );

		// "0" or an empty attribute means the customer chooses the amount.
		if ( fixed !== undefined && parseFloat( fixed ) > 0 ) {
			return fixed;
		}

		return $( form ).find( '#flw-amount' ).val();
	};

	/**
	 * Shows a checkout error.
	 *
	 * @param {jQuery} form    The form element.
	 * @param {string} message The message to show.
	 */
	const showError = function ( form, message ) {
		errorBox( form ).text( message ).addClass( 'is-visible' );
		$( form ).find( 'button' ).prop( 'disabled', false );
	};

	/**
	 * The error box belonging to a form.
	 *
	 * @param {jQuery} form The form element.
	 * @return {jQuery} The error box.
	 */
	const errorBox = function ( form ) {
		return $( form )
			.closest( '.flutterwave-payment-form, .flutterwave-donation-form' )
			.find( '.flw-error' );
	};

	/**
	 * Builds the checkout request.
	 *
	 * @param {jQuery} form The form element.
	 * @return {Object} The checkout request.
	 */
	const buildConfigObj = function ( form ) {
		const amount = amountFor( form );
		const email =
			readAttr( form, 'email' ) ||
			$( form ).find( '#flw-customer-email' ).val();
		const firstname =
			readAttr( form, 'firstname' ) ||
			$( form ).find( '#flw-first-name' ).val();
		const lastname =
			readAttr( form, 'lastname' ) ||
			$( form ).find( '#flw-last-name' ).val();
		const formCurrency =
			readAttr( form, 'currency' ) ||
			$( form ).find( '#flw-currency' ).val();
		const formId = form.attr( 'id' );
		const txref = 'WP_' + formId.toUpperCase() + '_' + new Date().valueOf();
		// Switch the country with the form currency provided.
		const country = options.countries[ formCurrency ]
			? options.countries[ formCurrency ]
			: options.countries.NGN;

		return {
			amount,
			country,
			currency: formCurrency ?? options.currency,
			customer: {
				email,
				phone_number: null,
				name: firstname + ' ' + lastname,
			},
			payment_options: options.method,
			public_key: options.public_key,
			tx_ref: txref,
			customizations: {
				title: options.title,
				description: options.desc,
				logo: options.logo,
			},
			form_id: formId,
		};
	};

	const processCheckout = function ( opts, form ) {
		const args = {
			action: 'get_payment_url',
			flw_sec_code: $( form ).find( '#flw_sec_code' ).val(),
			payment_type: $( form ).find( '#flw-payment-type' ).val(),
			flw_form_config: $( form )
				.find( 'input[name="flw_form_config"]' )
				.val(),
			flw_form_sig: $( form ).find( 'input[name="flw_form_sig"]' ).val(),
		};

		const dataObj = Object.assign( {}, args, opts );
		$.post( options.cb_url, dataObj )
			.done( function ( data ) {
				const response = data;

				if ( response.status === 'error' ) {
					showError( form, response.message );
				} else {
					const overlay = $( '#flutterwave-overlay' );
					overlay.addClass( 'flutterwave-overlay' );
					$( '#flw-overlay-text' ).addClass( 'flw-overlay-text' );
					overlay.show();
					redirectTo( response.url );
				}
			} )
			.fail( function ( xhr ) {
				showError(
					form,
					( xhr.responseJSON && xhr.responseJSON.message ) ||
						'Unable to start the payment. Please try again.'
				);
			} );
	};

	/**
	 * Redirect to a url.
	 *
	 * @param {string} url The link to redirect to.
	 */
	const redirectTo = function ( url ) {
		if ( url ) {
			location.href = url;
		}
	};

	// for each form process payments
	$( '.flw-donation-form' ).each( function () {
		const form = $( this );

		form.find( '#flw-payment-type' ).on( 'change', function () {
			const option = $( this ).val();
			form.find( '#flw-pay-now-button' ).text( 'Donate ' + option );
		} );

		// Suggested amounts fill the amount field; typing clears the selection.
		const presets = form.find( '.flw-amount-preset' );
		presets.on( 'click', function () {
			presets
				.removeClass( 'is-selected' )
				.attr( 'aria-pressed', 'false' );
			$( this ).addClass( 'is-selected' ).attr( 'aria-pressed', 'true' );
			form.find( '#flw-amount' ).val( $( this ).data( 'amount' ) );
		} );
		form.find( '#flw-amount' ).on( 'input', function () {
			presets
				.removeClass( 'is-selected' )
				.attr( 'aria-pressed', 'false' );
		} );

		form.on( 'submit', function ( event ) {
			event.preventDefault(); // Prevent the default form submission
			errorBox( form ).removeClass( 'is-visible' ).text( '' );
			const btn = form.find( 'button' );
			btn.prop( 'disabled', true );

			const inputs = form.find( 'input[type="text"]' );
			let isValid = true;

			inputs.each( function () {
				const inputValue = $( this ).val();
				if (
					typeof inputValue === 'string' &&
					inputValue.trim() === ''
				) {
					isValid = false;
					$( this ).attr( 'style', 'border-color: red' );
				} else {
					$( this ).attr( 'style', 'border-color: green' );
				}
			} );

			if ( isValid ) {
				const config = buildConfigObj( form );
				processCheckout( config, form );
			} else {
				//unblur button.
				btn.effect( 'shake', { times: 2 }, 300 );
				btn.prop( 'disabled', false );
			}
		} );
	} );
} );
