<?php
/**
 * Webhook Hook Helper.
 *
 * @package Flutterwave\WordPress\Helper
 */

namespace Flutterwave\WordPress\Helper;

/**
 * Webhook Helper Class.
 */
final class WebhookHelper {
	/**
	 * Compare hashes.
	 *
	 * Fails closed when either side is empty, so an unconfigured secret hash never authenticates a request.
	 *
	 * @param string $expected local hash.
	 * @param string $actual recieved hash.
	 *
	 * @return bool
	 */
	public static function compare_secret_hash( string $expected, string $actual ): bool {
		if ( '' === $expected || '' === $actual ) {
			return false;
		}

		return hash_equals( $expected, $actual );
	}

	/**
	 * Validate Hook Data.
	 *
	 * @param array $hook notification sent by flutterwave.
	 *
	 * @return bool
	 */
	public static function validate_hook_body( array $hook ): bool {
		return isset( $hook['event'], $hook['data'] ) && is_string( $hook['event'] ) && is_array( $hook['data'] );
	}
}
