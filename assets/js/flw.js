'use strict';

var amount    = flw_payment_args.amount,
    button    = flw_payment_args.button,
    country   = flw_payment_args.country,
    currency  = flw_payment_args.currency,
    desc      = flw_payment_args.desc,
    email     = flw_payment_args.email,
    logo      = flw_payment_args.logo,
    prefix    = Math.random().toString(36).substr(2, 3),
    pbkey     = flw_payment_args.pbkey,
    form      = document.querySelector( '.flw-simple-pay-now-form' ),
    title     = flw_payment_args.title,
    txref     = prefix + '_' + new Date().valueOf(),
    url       = flw_payment_args.url;

if ( form ) {

  form.addEventListener( 'submit', function(evt) {
    evt.preventDefault();
    var thisForm = evt.target;
    amount  = amount || thisForm.querySelector( '#flw-amount' ).value;
    email   = email  || thisForm.querySelector( '#flw-customer-email' ).value;
    var config = buildConfigObj();

    processCheckout( config );

  } );

}

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
    redirect_url: url,
    txref: txref,
    onclose: function() {},
    callback: function(d) {
      handleResponse( d );
    }
  };

};

var processCheckout = function(opts) {
  getpaidSetup( opts );
};

var handleResponse = function(res) {

  console.log(res);
  var status       = 'failed';
  var responseCode = ( res.tx.paymentType === 'account' ) ? res.tx.acctvalrespcode : res.tx.vbvrespcode;
  var responseMsg  = ( res.tx.paymentType === 'account' ) ? res.tx.acctvalrespmsg  : res.tx.vbvrespmessage;

  if ( responseCode === '00' ) {
    status = 'successful';
  }

  var noticeDiv = document.getElementById( 'notice' );
  noticeDiv.className = status;

  if ( status === 'successful' ) {

    responseMsg = 'Payment completed successfully';
    form.style.display = 'none';

  }

  noticeDiv.innerHTML = responseMsg;

};
