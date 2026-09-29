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
		// Form Appearance settings.
		const shouldSplitName = readAttr( form, 'split_name' ) === '1';

		let fullname = '';
		if ( ! shouldSplitName ) {
			fullname =
				readAttr( form, 'fullname' ) ||
				$( form ).find( '#flw-full-name' ).val();
		} else {
			const firstname =
				readAttr( form, 'firstname' ) ||
				$( form ).find( '#flw-first-name' ).val();
			const lastname =
				readAttr( form, 'lastname' ) ||
				$( form ).find( '#flw-last-name' ).val();
			fullname = firstname + ' ' + lastname;
		}

		const phone =
			readAttr( form, 'phone' ) || $( form ).find( '#flw-phone' ).val();

		const amount = amountFor( form );
		const email =
			readAttr( form, 'email' ) ||
			$( form ).find( '#flw-customer-email' ).val();
		const specialCurrencyValue =
			readAttr( form, 'custom_currency' ) ||
			$( form ).find( '#flw-currency' ).val();
		const formCurrency =
			( readAttr( form, 'custom_currency' ) || '' ).length > 3
				? $( form ).find( '#flw-currency' ).val()
				: specialCurrencyValue;

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
				phone_number: phone ?? null,
				name: fullname,
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
	$( '.flw-simple-pay-now-form' ).each( function () {
		const form = $( this );

		form.on( 'submit', function ( event ) {
			event.preventDefault(); // Prevent the default form submission
			errorBox( form ).removeClass( 'is-visible' ).text( '' );
			const btn = form.find( 'button' );
			//gray the button.
			btn.prop( 'disabled', true );

			const inputs = form.find( 'input[type="*"]' );

			let isValid = true;

			inputs.each( function () {
				const inputValue = $( this ).val();
				if (
					typeof inputValue === 'string' &&
					inputValue.trim() === ''
				) {
					isValid = false;
					$( this ).attr( 'style', 'border-color: red' );
				}

				if (
					$( this ).attr( 'type' ) === 'number' &&
					Number.isNaN( parseInt( inputValue, 10 ) )
				) {
					isValid = false;
					$( this ).attr( 'style', 'border-color: red' );
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
