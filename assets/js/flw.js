'use strict';

var amount    = flw_payment_args.amount,
    button    = flw_payment_args.button,
    cbUrl     = flw_payment_args.cb_url,
    country   = flw_payment_args.country,
    currency  = flw_payment_args.currency,
    desc      = flw_payment_args.desc,
    email     = flw_payment_args.email,
    logo      = flw_payment_args.logo,
    prefix    = Math.random().toString(36).substr(2, 3).toUpperCase(),
    pbkey     = flw_payment_args.pbkey,
    form      = jQuery( '.flw-simple-pay-now-form' ),
    title     = flw_payment_args.title,
    txref, redirectUrl;

if ( form ) {

  form.on( 'submit', function(evt) {
    evt.preventDefault();
    var thisForm = evt.target;
    amount  = amount || thisForm.querySelector( '#flw-amount' ).value;
    email   = email  || thisForm.querySelector( '#flw-customer-email' ).value;
    txref   = 'WP_' + prefix + '_' + new Date().valueOf();
    var config = buildConfigObj();

    processCheckout( config );

  } );

}

/**
 * Builds config object to be sent to GetPaid
 *
 * @return object - The config object
 */
var buildConfigObj = function() {

  return {
    amount: amount,
    country: country,
    currency: currency,
    custom_description: desc,
    custom_logo: logo,
    custom_title: title,
    customer_email: email,
    pay_button_text: button,
    PBFPubKey: pbkey,
    txref: txref,
    onclose: function() {
      redirectTo( redirectUrl );
    },
    callback: function(d) {
      sendPaymentRequestResponse( d );
    }
  };

};

var processCheckout = function(opts) {
  getpaidSetup( opts );
};

/**
 * Sends payment response from GetPaid to the process payment endpoint
 *
 * @param object Response object from GetPaid
 *
 * @return void
 */
var sendPaymentRequestResponse = function( res ) {
  var args = {
    action: 'process_payment',
    flw_sec_code: jQuery( '#flw_sec_code' ).val(),
  };

  var dataObj = Object.assign( {}, args, res.tx );

  jQuery
    .post( cbUrl, dataObj )
    .success( function(data) {
      var response  = JSON.parse( data );
      redirectUrl   = response.redirect_url;

      if ( redirectUrl === '' ) {

        var responseMsg  = ( res.tx.paymentType === 'account' ) ? res.tx.acctvalrespmsg  : res.tx.vbvrespmessage;
        jQuery( '#notice' ).text( responseMsg ).removeClass( existingClasses ).addClass( response.status );

      } else {

        setTimeout( redirectTo, 5000, redirectUrl );

      }

    } );
};

/**
 * Redirect to set url
 *
 * @param string url - The link to redirect to
 *
 * @return void
 */
var redirectTo = function( url ) {

  if ( url !== '' ) {

    location.href = url;

  }

};

/**
 * Returns existing classes on the #notice element
 *
 * @return string - Space separated lists of classes
 */
var existingClasses = function() {

  return jQuery( '#notice' ).attr( 'class' );

};
