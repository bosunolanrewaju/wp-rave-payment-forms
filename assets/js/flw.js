'use strict';

let { email, amount } = flw_payment_args;
const {
  button,
  country,
  currency,
  desc,
  pbkey,
  title,
  url
} = flw_payment_args;
const prefix    = Math.random().toString(36).substr(2, 3);
const txref     = prefix + '_' + new Date().valueOf();
const simplePayNowForm = document.querySelector( '.flw-simple-pay-now-form' );

if ( simplePayNowForm ) {

  simplePayNowForm.addEventListener( 'submit', ( evt ) => {
    evt.preventDefault();
    const form = evt.target;
    email = email || form.querySelector( '#flw-customer-email' ).value;
    amount = amount || form.querySelector( '#flw-amount' ).value;

    let opts = buildConfigObj();

    processCheckout( opts );

  } );

}

const buildConfigObj = () => {
  return {
    customer_email: email,
    amount: amount,
    txref: txref,
    PBFPubKey: pbkey,
    custom_title: title,
    custom_description: desc,
    redirect_url: url,
    pay_button_text: button,
    currency: currency,
    country: country,
    onclose: () => {
      console.log(this);
    },
    callback: (d) => {
      console.log(d);
    }
  };
};

const processCheckout = (opts) => {
  getpaidSetup( opts );
};
