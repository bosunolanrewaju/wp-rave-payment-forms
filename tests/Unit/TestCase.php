<?php
/**
 * Base unit test case.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Sets up Brain Monkey and stubs the WordPress helpers the plugin relies on.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * In-memory post meta, keyed by post id.
	 *
	 * @var array
	 */
	protected $meta = array();

	/**
	 * In-memory posts, keyed by post id.
	 *
	 * @var array
	 */
	protected $posts = array();

	/**
	 * In-memory options, keyed by name.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * In-memory transients, keyed by name.
	 *
	 * @var array
	 */
	protected $transients = array();

	/**
	 * Events scheduled with wp_schedule_single_event(), as [ hook, args ].
	 *
	 * @var array
	 */
	protected $scheduled = array();

	/**
	 * Set up Brain Monkey.
	 */
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\stubs(
			array(
				'wp_json_encode'           => static function ( $data ) {
					return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				},
				'wp_salt'                  => 'unit-test-salt',
				'absint'                   => static function ( $value ) {
					return abs( (int) $value );
				},
				'sanitize_key'             => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'sanitize_text_field'      => static function ( $value ) {
					return trim( preg_replace( '/<[^>]*>/', '', (string) $value ) );
				},
				'sanitize_email'           => static function ( $value ) {
					return (string) filter_var( $value, FILTER_SANITIZE_EMAIL );
				},
				'esc_url_raw'              => static function ( $url ) {
					return preg_match( '#^https?://#i', (string) $url ) ? (string) $url : '';
				},
				'get_post'                 => function ( $id ) {
					return $this->posts[ $id ] ?? null;
				},
				'get_post_meta'            => function ( $id, $key = '' ) {
					return $this->meta[ $id ][ $key ] ?? '';
				},
				'update_post_meta'         => function ( $id, $key, $value ) {
					$this->meta[ $id ][ $key ] = $value;
					return true;
				},
				'get_option'               => function ( $name, $fallback = false ) {
					return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $fallback;
				},
				'update_option'            => function ( $name, $value ) {
					$this->options[ $name ] = $value;
					return true;
				},
				'get_transient'            => function ( $name ) {
					return $this->transients[ $name ] ?? false;
				},
				'set_transient'            => function ( $name, $value ) {
					$this->transients[ $name ] = $value;
					return true;
				},
				'delete_transient'         => function ( $name ) {
					unset( $this->transients[ $name ] );
					return true;
				},
				'wp_schedule_single_event' => function ( $timestamp, $hook, $args = array() ) {
					$this->scheduled[] = array( $hook, $args );
					return true;
				},
				'wp_next_scheduled'        => function ( $hook, $args = array() ) {
					foreach ( $this->scheduled as $event ) {
						if ( $event[0] === $hook && $event[1] === $args ) {
							return time();
						}
					}
					return false;
				},
				'plugin_basename'          => static function ( $file ) {
					return basename( dirname( $file ) ) . '/' . basename( $file );
				},
			)
		);
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Add an in-memory post.
	 *
	 * @param int    $id        Post id.
	 * @param string $post_type Post type.
	 * @param array  $meta      Post meta.
	 */
	protected function add_post( int $id, string $post_type, array $meta = array() ): void {
		$this->posts[ $id ] = new \WP_Post( $id, $post_type );
		$this->meta[ $id ]  = $meta;
	}
}
