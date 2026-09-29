<?php
/**
 * Plugin Name: Flutterwave E2E mock
 * Description: Test-only stand-in for the Flutterwave API and hosted checkout.
 *
 * Mapped into wp-content/mu-plugins of the wp-env development site only (see
 * .wp-env.json). It does nothing unless the end-to-end tests switch it on with
 * the flw_e2e_mock_api option, so browsing the development site by hand still
 * talks to the real API.
 *
 * - POST /v3/payments returns a payment link and remembers the charge.
 * - GET /v3/transactions/{id}/verify returns that charge as successful.
 * - The payment link opens a local "Flutterwave" page with Pay and Cancel,
 *   which sends the customer back the way Flutterwave does.
 *
 * @package Flutterwave\WordPress\Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! get_option( 'flw_e2e_mock_api' ) ) {
	return;
}

/**
 * The transaction id the mock gives a reference.
 *
 * @param string $tx_ref Transaction reference.
 *
 * @return int
 */
function flw_e2e_transaction_id( string $tx_ref ): int {
	return abs( crc32( $tx_ref ) ) % 900000000 + 1000;
}

/**
 * The local stand-in for the hosted checkout.
 *
 * @param array $args Query arguments.
 *
 * @return string
 */
function flw_e2e_checkout_url( array $args ): string {
	return add_query_arg( array_map( 'rawurlencode', array_merge( array( 'flw-e2e-checkout' => '1' ), $args ) ), home_url( '/' ) );
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.flutterwave.com/' ) ) {
			return $pre;
		}

		$reply = static function ( array $body, int $code = 200 ): array {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $body ),
				'response' => array(
					'code'    => $code,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		if ( false !== strpos( $url, '/v3/payments' ) ) {
			$payment = json_decode( (string) $args['body'], true );
			$id      = flw_e2e_transaction_id( (string) $payment['tx_ref'] );

			update_option(
				'flw_e2e_tx_' . $id,
				array(
					'id'       => $id,
					'tx_ref'   => $payment['tx_ref'],
					'amount'   => $payment['amount'],
					'currency' => $payment['currency'],
					'status'   => 'successful',
				),
				false
			);

			// The real link is on checkout.flutterwave.com; the path marks it as the mock's.
			return $reply(
				array(
					'status' => 'success',
					'data'   => array(
						'link' => add_query_arg(
							array(
								'tx_ref'   => rawurlencode( $payment['tx_ref'] ),
								'id'       => $id,
								'redirect' => rawurlencode( $payment['redirect_url'] ),
							),
							'https://checkout.flutterwave.com/v3/hosted/pay/flw-e2e'
						),
					),
				)
			);
		}

		if ( preg_match( '#/v3/transactions/(\d+)/verify#', $url, $matches ) ) {
			$transaction = get_option( 'flw_e2e_tx_' . $matches[1] );

			return $transaction
				? $reply(
					array(
						'status' => 'success',
						'data'   => $transaction,
					)
				)
				: $reply(
					array(
						'status'  => 'error',
						'message' => 'No transaction was found for this id',
					),
					404
				);
		}

		return $pre;
	},
	10,
	3
);

// Server-side redirects to the mock link (Easy Digital Downloads) go to the local page instead.
add_filter(
	'wp_redirect',
	static function ( $location ) {
		if ( ! is_string( $location ) || 0 !== strpos( $location, 'https://checkout.flutterwave.com/v3/hosted/pay/flw-e2e' ) ) {
			return $location;
		}

		wp_parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );

		return flw_e2e_checkout_url( $query );
	},
	1
);

// The stand-in hosted checkout: Pay or Cancel, then back to the site.
add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- test-only page with no side effects.
		if ( empty( $_GET['flw-e2e-checkout'] ) ) {
			return;
		}

		$tx_ref   = sanitize_text_field( wp_unslash( $_GET['tx_ref'] ?? '' ) );
		$id       = absint( $_GET['id'] ?? 0 );
		$redirect = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect'] ?? '' ) ), home_url( '/' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$transaction = get_option( 'flw_e2e_tx_' . $id );
		$back        = static function ( array $args ) use ( $redirect, $tx_ref ): string {
			return add_query_arg( array_merge( array( 'tx_ref' => $tx_ref ), $args ), $redirect );
		};

		status_header( 200 );
		nocache_headers();
		printf(
			'<!doctype html><html><head><meta charset="utf-8"><title>Flutterwave checkout (test)</title></head><body>'
			. '<h1>Flutterwave checkout (test)</h1><p class="amount">%1$s %2$s</p>'
			. '<a class="pay" href="%3$s">Pay now</a> <a class="cancel" href="%4$s">Cancel payment</a>'
			. '</body></html>',
			esc_html( (string) ( $transaction['currency'] ?? '' ) ),
			esc_html( number_format( (float) ( $transaction['amount'] ?? 0 ), 2 ) ),
			esc_url(
				$back(
					array(
						'status'         => 'successful',
						'transaction_id' => $id,
					)
				)
			),
			esc_url( $back( array( 'status' => 'cancelled' ) ) )
		);
		exit;
	}
);
