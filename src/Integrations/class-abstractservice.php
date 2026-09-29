<?php
/**
 * Abstract Flutterwave Service.
 *
 * @package Flutterwave_Payments
 * @version 1.0.6
 */

namespace Flutterwave\WordPress\Integrations;

/**
 * AbstractService.
 */
abstract class AbstractService {

	/**
	 * Base Url.
	 *
	 * @var string
	 */
	protected string $base_url;

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Api Key.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * Owner Name
	 *
	 * @var string
	 */
	protected string $owner;

	/**
	 * Error Array.
	 *
	 * @var array
	 */
	public array $error_log = array();

	const PUBLIC_KEY = 'public';

	const SECRET_KEY = 'secret';

	/**
	 * Init Settings
	 *
	 * @param string $key The API key.
	 *
	 * @return void
	 */
	abstract public function init( string $key ): void;

	/**
	 * Get Features.
	 *
	 * @return array
	 */
	abstract public function get_features(): array;

	/**
	 * The Services Assets.
	 *
	 * @return array
	 */
	abstract public function get_assets(): array;

	/**
	 * Get Service Info.
	 *
	 * @return array
	 */
	abstract public function get_info(): array;

	/**
	 * Get Header.
	 *
	 * @return array
	 */
	abstract protected function get_headers(): array;

	/**
	 * Service Constructor.
	 *
	 * @param string $key The api key.
	 */
	public function __construct( string $key ) {
		$this->_init( $key );
	}

	/**
	 * Api Key.
	 *
	 * @param string $key The api key.
	 *
	 * @return void
	 */
	public function set_key( string $key ): void {
		$this->api_key = $key;
	}

	/**
	 * Get Api Key.
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->api_key;
	}

	/**
	 * Get Service name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->owner . ' ' . $this->name;
	}

	/**
	 * Make a Request.
	 *
	 * @param string $url Any endpoint the service provides.
	 * @param string $method The Method or verb can be POST, PUT, PATCH, HEAD or GET.
	 * @param array  $data The Data for POST and PUT requests.
	 *
	 * @return object|mixed
	 */
	protected function request( string $url, string $method = 'GET', array $data = array() ): object {
		$url                = $this->base_url . $url;
		$wp_args['method']  = $method;
		$wp_args['timeout'] = 60;
		$wp_args['body']    = \wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		$wp_args['headers'] = $this->get_headers();
		if ( empty( $data ) || 'GET' === $method ) {
			unset( $wp_args['body'] );
		}

		$response = \wp_safe_remote_request( $url, $wp_args );

		if ( ! is_wp_error( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return \WP_Error(
			'flw-unavailable',
			/* translators: %s: owner's name, %s: service name */
			sprintf( '%s \'s  %s service is currently unavailable. please use another integration.', $this->owner, $this->name )
		);
	}
}
