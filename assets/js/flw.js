'use strict';

let email     = '';
const {
  amount,
  button,
  country,
  currency,
  desc,
  pbkey,
  title,
  url
} = flw_payment_args;
const payNowBtn = document.getElementById( 'flw-pay-now-button' );
const prefix    = Math.random().toString(36).substr(2, 3);
const txref     = prefix + '_' + new Date().valueOf();

if ( payNowBtn ) {

  payNowBtn.addEventListener( 'click', ( evt ) => {

    evt.preventDefault();

    email = document.getElementById( 'flw-customer-email' ).value;

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
