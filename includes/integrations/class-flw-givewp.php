<?php
/**
 * Flutterwave gateway for GiveWP.
 *
 * The gateway class itself extends GiveWP's PaymentGateway, so it is only
 * loaded once GiveWP asks for its gateways. This class holds everything else:
 * creating the payment link, and completing the donation on the donor's return
 * or from the webhook.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;

// GiveWP's models expose camelCase properties.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * GiveWP integration.
 */
class FLW_GiveWP {

	/**
	 * Transaction reference prefix.
	 */
	const PREFIX = 'GIVE';

	/**
	 * Gateway id in GiveWP.
	 */
	const ID = 'flutterwave';

	/**
	 * Donation meta holding the transaction reference.
	 */
	const REF_META = '_flw_tx_ref';

	/**
	 * Donation meta holding where to send the donor afterwards.
	 */
	const URLS_META = '_flw_return_urls';

	/**
	 * Whether GiveWP 3 or later is active.
	 *
	 * @return bool
	 */
	public static function is_host_active(): bool {
		return class_exists( Donation::class ) && class_exists( 'Give\Framework\PaymentGateways\PaymentGateway' );
	}

	/**
	 * The GiveWP currency.
	 *
	 * @return string
	 */
	public static function host_currency(): string {
		return function_exists( 'give_get_currency' ) ? strtoupper( (string) give_get_currency() ) : '';
	}

	/**
	 * Register the gateway with GiveWP.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'givewp_register_payment_gateway',
			static function ( $registrar ) {
				require_once __DIR__ . '/givewp/class-flw-givewp-gateway.php';
				$registrar->registerGateway( FLW_GiveWP_Gateway::class );
			}
		);
	}

	/**
	 * Turn the gateway on or off for GiveWP's classic and visual builder forms.
	 *
	 * @param bool $enabled Whether donors can choose it.
	 *
	 * @return void
	 */
	public static function set_host_enabled( bool $enabled ): void {
		foreach ( array( 'gateways', 'gateways_v3' ) as $key ) {
			$gateways = (array) give_get_option( $key, array() );

			if ( $enabled ) {
				$gateways[ self::ID ] = '1';
			} else {
				unset( $gateways[ self::ID ] );
			}

			give_update_option( $key, $gateways );
		}
	}

	/**
	 * GiveWP passes its return URLs raw to classic forms and URL encoded to visual builder forms.
	 *
	 * @param mixed $url URL from the gateway data.
	 *
	 * @return string
	 */
	private static function url( $url ): string {
		$url = is_string( $url ) ? $url : '';

		return false === strpos( $url, '://' ) ? rawurldecode( $url ) : $url;
	}

	/**
	 * Create the Flutterwave payment link for a new donation.
	 *
	 * @param Donation $donation     The pending donation.
	 * @param array    $gateway_data Data GiveWP passes to the gateway.
	 *
	 * @return string|WP_Error
	 */
	public static function create_payment( Donation $donation, array $gateway_data ) {
		$tx_ref = FLW_Hosted_Checkout::reference( self::PREFIX, (int) $donation->id );
		$failed = self::url( $gateway_data['failedUrl'] ?? $gateway_data['cancelUrl'] ?? '' );

		give_update_meta( $donation->id, self::REF_META, $tx_ref );
		give_update_meta(
			$donation->id,
			self::URLS_META,
			array(
				'success' => self::url( $gateway_data['successUrl'] ?? '' ),
				'failed'  => '' !== $failed ? $failed : give_get_failed_transaction_uri(),
			)
		);

		return FLW_Hosted_Checkout::create_link(
			array(
				'tx_ref'       => $tx_ref,
				'amount'       => (float) $donation->amount->formatToDecimal(),
				'currency'     => $donation->amount->getCurrency()->getCode(),
				'redirect_url' => rest_url( FLW_Settings::REST_NAMESPACE . '/return/givewp' ),
				'email'        => (string) $donation->email,
				'name'         => trim( $donation->firstName . ' ' . $donation->lastName ),
				'description'  => (string) $donation->formTitle,
				'meta'         => array(
					'donation_id' => $donation->id,
					'source'      => 'givewp',
				),
			)
		);
	}

	/**
	 * The donor is back from Flutterwave: confirm the donation and pick the page to show.
	 *
	 * @param array $returned Parameters Flutterwave appended.
	 *
	 * @return string URL.
	 */
	public static function handle_return( array $returned ): string {
		$donation = self::donation( $returned['tx_ref'] );

		if ( null === $donation ) {
			return home_url( '/' );
		}

		$urls = (array) give_get_meta( $donation->id, self::URLS_META, true );

		// Only a transaction verified with Flutterwave changes the donation; the query
		// arguments on this URL are not trusted, so a "cancelled" status here only
		// decides which page to show. The webhook closes cancelled donations.
		if ( ! $donation->status->isComplete() && $returned['transaction_id'] > 0 ) {
			$transaction = FLW_Payment_Record::fetch_verified( $returned['transaction_id'] );

			if ( ! is_wp_error( $transaction ) ) {
				self::complete( $transaction );
			}
		}

		if ( DonationStatus::COMPLETE === self::current_status( $donation->id ) || 'pending' === $returned['status'] ) {
			return ! empty( $urls['success'] ) ? $urls['success'] : give_get_success_page_uri();
		}

		return ! empty( $urls['failed'] ) ? $urls['failed'] : give_get_failed_transaction_uri();
	}

	/**
	 * Complete the donation a verified transaction paid for.
	 *
	 * @param array $transaction Verified transaction.
	 *
	 * @return bool Whether the donation is now complete.
	 */
	public static function complete( array $transaction ): bool {
		$tx_ref   = (string) ( $transaction['tx_ref'] ?? '' );
		$donation = self::donation( $tx_ref );

		if ( null === $donation ) {
			return false;
		}

		$id   = (int) $donation->id;
		$done = FLW_Hosted_Checkout::with_lock(
			$tx_ref,
			static function () use ( $transaction, $tx_ref, $id ): bool {
				// Re-read under the lock: the webhook or the return may have just completed it.
				if ( DonationStatus::COMPLETE === self::current_status( $id ) ) {
					return true;
				}

				$donation = Donation::find( $id );
				$checked  = FLW_Hosted_Checkout::check(
					$transaction,
					$tx_ref,
					(float) $donation->amount->formatToDecimal(),
					$donation->amount->getCurrency()->getCode()
				);

				if ( is_wp_error( $checked ) ) {
					self::note(
						$donation,
						/* translators: %s: reason. */
						sprintf( __( 'Flutterwave payment not accepted: %s', 'rave-payment-forms' ), $checked->get_error_message() )
					);
					return false;
				}

				$donation->status               = DonationStatus::COMPLETE();
				$donation->gatewayTransactionId = (string) $transaction['id'];
				$donation->save();

				/* translators: %s: Flutterwave transaction id. */
				self::note( $donation, sprintf( __( 'Paid with Flutterwave. Transaction ID: %s', 'rave-payment-forms' ), $transaction['id'] ) );

				return true;
			}
		);

		return true === $done;
	}

	/**
	 * The donor cancelled on Flutterwave.
	 *
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return bool Whether a pending donation was marked cancelled.
	 */
	public static function cancel( string $tx_ref ): bool {
		$donation = self::donation( $tx_ref );

		if ( null === $donation ) {
			return false;
		}

		$id = (int) $donation->id;

		return true === FLW_Hosted_Checkout::with_lock(
			$tx_ref,
			static function () use ( $id ): bool {
				if ( DonationStatus::PENDING !== self::current_status( $id ) ) {
					return false;
				}

				$donation         = Donation::find( $id );
				$donation->status = DonationStatus::CANCELLED();
				$donation->save();

				return true;
			}
		);
	}

	/**
	 * A donation's status straight from the database, bypassing the object cache
	 * so a change made by a concurrent request is seen.
	 *
	 * @param int $id Donation id.
	 *
	 * @return string
	 */
	private static function current_status( int $id ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must not come from the cache.
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'give_payment'", $id ) );
	}

	/**
	 * The Flutterwave donation a reference belongs to, checked against the stored reference.
	 *
	 * @param string $tx_ref Transaction reference.
	 *
	 * @return Donation|null
	 */
	private static function donation( string $tx_ref ): ?Donation {
		$id       = FLW_Hosted_Checkout::id_from_reference( self::PREFIX, $tx_ref );
		$donation = $id ? Donation::find( $id ) : null;

		if ( ! $donation instanceof Donation || self::ID !== $donation->gatewayId ) {
			return null;
		}

		return hash_equals( (string) give_get_meta( $id, self::REF_META, true ), $tx_ref ) ? $donation : null;
	}

	/**
	 * Add a note to a donation.
	 *
	 * @param Donation $donation Donation.
	 * @param string   $content  Note.
	 *
	 * @return void
	 */
	private static function note( Donation $donation, string $content ): void {
		DonationNote::create(
			array(
				'donationId' => $donation->id,
				'content'    => $content,
			)
		);
	}
}
