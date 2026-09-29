const planName = jQuery( '#plan-name' );
const planAmount = jQuery( '#plan-amount' );
const planInterval = jQuery( '#plan-interval' );
const planDuration = jQuery( '#plan-duration' );

jQuery( document ).ready( function () {
	jQuery( '#wpfooter' ).hide();

	jQuery( '#createPlan-btn' ).on( 'click', ( event ) => {
		event.preventDefault();

		jQuery
			.post( '#', {
				amount: planAmount.val(),
				name: planName.val(),
				interval: planInterval.val(),
				duration: parseInt( planDuration.val() ),
			} )
			.done( function () {
				location.reload();
			} );
	} );
} );
