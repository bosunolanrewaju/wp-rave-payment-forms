<?php
/**
 * Minimal stand-ins for WordPress core classes used by the code under test.
 *
 * @package Flutterwave\WordPress\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WP_Error stand-in.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Get the error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Get the error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * WP_Post stand-in.
	 */
	class WP_Post {
		/**
		 * Post id.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Constructor.
		 *
		 * @param int    $id        Post id.
		 * @param string $post_type Post type.
		 */
		public function __construct( int $id = 0, string $post_type = 'post' ) {
			$this->ID        = $id;
			$this->post_type = $post_type;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Check whether a value is a WP_Error.
	 *
	 * @param mixed $thing Value to check.
	 *
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
