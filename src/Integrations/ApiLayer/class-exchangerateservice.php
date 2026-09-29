<?php
/**
 * Exchange Rate API Service.
 *
 * @package Flutterwave_Payments
 * @version 1.0.6
 */

namespace Flutterwave\WordPress\Integrations\ApiLayer;

use Flutterwave\WordPress\Integrations\AbstractService;

/**
 * Exchange Rate Service.
 */
final class ExchangeRateService extends AbstractService {
	/**
	 * Exchange Rate Constructor.
	 *
	 * @param string $key Api key.
	 */
	public function __construct( string $key = '' ) {
		parent::__construct( $key );

		$this->owner = 'apilayer';
		$this->name  = 'exchange_rate';
	}

	/**
	 * Init Service
	 *
	 * @param string $key Api key.
	 *
	 * @return void
	 */
	public function init( string $key ): void {
		$this->set_key( $key );
	}

	/**
	 * Get Header.
	 *
	 * @return array
	 */
	protected function get_headers(): array {
		return array(
			'Content-Type' => 'text/plain',
			'apikey'       => $this->get_key(),
		);
	}

	/**
	 * The Services Assets.
	 *
	 * @return array
	 */
	public function get_assets(): array {
		return array(
			'logo' => 'https://assets.apilayer.com/logo/logo.png',
		);
	}

	/**
	 * Get Service Info.
	 *
	 * @return array
	 */
	public function get_info(): array {
		return array(
			'owner' => $this->owner,
			'name'  => $this->name,
		);
	}

	/**
	 * Get Features.
	 *
	 * @return array
	 */
	public function get_features(): array {
		return array(
			'exchange rate',
		);
	}
}
