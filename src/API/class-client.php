<?php
/**
 * Flutterwave Client.
 *
 * @package Flutterwave\WordPress\API
 */

namespace Flutterwave\WordPress\API;

use Flutterwave\WordPress\Exception\ApiException;

/**
 *  Flutterwave Client class.
 */
final class Client {

	const BASE_URL = 'https://api.flutterwave.com/';
	const VERSION  = 'v3';
	/**
	 * Instance
	 *
	 * @var Client|null
	 */
	private static ?Client $instance = null;
	/**
	 * Secret Key.
	 *
	 * @var string
	 */
	private string $secret_key;
	/**
	 * Timeout.
	 *
	 * @var int
	 */
	private int $timeout;
	/**
	 * Request Headers.
	 *
	 * @var string[]
	 */
	private array $headers;


	/**
	 * Client Header controller.
	 *
	 * @param string $secret_key the api key.
	 */
	private function __construct( string $secret_key ) {
		$this->secret_key = $secret_key;
		$this->timeout    = 60;
		$this->headers    = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->secret_key,
		);
	}

	/**
	 * Get class instance.
	 *
	 * @param string $secret_key the api key.
	 *
	 * @return Client
	 */
	public static function get_instance( string $secret_key ): Client {

		if ( is_null( self::$instance ) ) {
			return new self( $secret_key );
		}

		return self::$instance;
	}

	/**
	 * Get base url.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		return self::BASE_URL . self::VERSION;
	}

	/**
	 * This is the main request method for the Flutterwave WordPress client
	 *
	 * @param string $url the endpoint.
	 * @param string $method http verb.
	 * @param array  $data  data to be sent.
	 *
	 * @return array|\WP_Error
	 */
	public function request( string $url, string $method = 'GET', array $data = array() ) {
		$_request_url = $this->get_base_url() . $url;
		// url should be prefixed with a "/" .
		$wp_args['method']  = $method;
		$wp_args['timeout'] = $this->timeout;
		$wp_args['body']    = \wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		$wp_args['headers'] = $this->headers;
		if ( empty( $data ) || 'GET' === $method ) {
			unset( $wp_args['body'] );
		}

		$response = \wp_safe_remote_request( $_request_url, $wp_args );

		try {
			Handler::handle_api_errors( $response );
		} catch ( ApiException $e ) {
			return $e->getError();
		}

		return $response;
	}
}
