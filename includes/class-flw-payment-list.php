<?php
/**
 * Flutterwave Payment List
 *
 * @package Flutterwave Payment
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/template.php';

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'FLW_Payment_List' ) ) {

	/**
	 * Payment List Class to add list payments made.
	 * via the payment buttons.
	 */
	class FLW_Payment_List extends WP_List_Table {


		/**
		 * Class Instance
		 *
		 * @var null
		 */
		protected static $instance = null;

		/**
		 * Admin settings instance variable.
		 *
		 * @var FLW_Admin_Settings|null $admin_settings
		 */
		private ?FLW_Admin_Settings $admin_settings;

		/**
		 * Class construct
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => __( 'Payment List', 'rave-payment-forms' ),
					'plural'   => __( 'Payment Lists', 'rave-payment-forms' ),
					'ajax'     => false,
				)
			);

			$this->admin_settings = FLW_Admin_Settings::get_instance();

			add_filter( 'set-screen-option', array( $this, 'set_screen' ), 10, 3 );
			add_action( 'init', array( $this, 'add_payment_list_post_type' ) );
		}

		/**
		 * The text to display when no payment is made.
		 *
		 * @return void
		 */
		public function no_items() {
			esc_html_e( 'No payments have been made yet.', 'rave-payment-forms' );
		}

		/**
		 * Method for name column.
		 *
		 * @param array $item an array of DB data.
		 *
		 * @return string
		 */
		public function column_tx_ref( $item ) {
			$tx_ref                  = get_post_meta( $item->ID, '_flw_rave_payment_tx_ref', true );
			$title                   = '<strong>' . esc_html( $tx_ref ) . '</strong>';
			$update_transaction_link = add_query_arg(
				array(
					'post_id'  => absint( $item->ID ),
					'_wpnonce' => wp_create_nonce( 'wp_rest' ),
				),
				rest_url( 'flutterwave/v1/update-transaction' )
			);

			$actions = array(
				'delete' => sprintf( '<a href="%s">Delete</a>', esc_url( get_delete_post_link( absint( $item->ID ) ) ) ),
				'update' => sprintf( '<a href="%s">Update</a>', esc_url( $update_transaction_link ) ),
			);

			return $title . $this->row_actions( $actions );
		}

		/**
		 * Method for name column.
		 *
		 * @param array $item an array of DB data.
		 *
		 * @return string
		 */
		public function column_amount( $item ) {
			$amount = get_post_meta( $item->ID, '_flw_rave_payment_amount', true );
			return esc_html( number_format( (float) $amount, 2 ) );
		}

		/**
		 * Method for name column.
		 *
		 * @param array $item an array of DB data.
		 *
		 * @return string
		 */
		public function column_currency( $item ) {
			return esc_html( get_post_meta( $item->ID, '_flw_rave_payment_currency', true ) );
		}

		/**
		 * Renders a column when no column specific method exists.
		 *
		 * @param array  $item item in column.
		 * @param string $column_name column name.
		 *
		 * @return mixed
		 */
		public function column_default( $item, $column_name ) {

			switch ( $column_name ) {
				case 'customer':
				case 'fullname':
				case 'status':
					return esc_html( get_post_meta( $item->ID, '_flw_rave_payment_' . $column_name, true ) );
				case 'date':
					return esc_html( $item->post_date );
				default:
					return null;
			}
		}

		/**
		 *  Associative array of columns.
		 *
		 * @return array
		 */
		public function get_columns(): array {
			$columns = array(
				'cb'       => '<input type="checkbox" />',
				'tx_ref'   => __( 'Transaction Ref', 'rave-payment-forms' ),
				'customer' => __( 'Customer', 'rave-payment-forms' ),
				'fullname' => __( 'Customer Fullname', 'rave-payment-forms' ),
				'amount'   => __( 'Amount', 'rave-payment-forms' ),
				'currency' => __( 'Currency', 'rave-payment-forms' ),
				'status'   => __( 'Status', 'rave-payment-forms' ),
				'date'     => __( 'Date', 'rave-payment-forms' ),
			);

			return $columns;
		}

		/**
		 * Render the bulk edit checkbox.
		 *
		 * @param array $item item in the column.
		 *
		 * @return string
		 */
		public function column_cb( $item ): string {

			return sprintf(
				'<input type="checkbox" name="bulk-delete[]" value="%d" />',
				absint( $item->ID )
			);
		}

		/**
		 * Handles data query and filter, sorting, and pagination.
		 */
		public function prepare_items() {
			$per_page     = $this->get_items_per_page( 'payments_per_page' );
			$current_page = $this->get_pagenum();
			$total_items  = self::record_count();

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);

			$this->items = self::get_payments( $per_page, $current_page );
		}

		/**
		 * Set screen
		 *
		 * @param [string] $status status of screen.
		 * @param [string] $option option on screen.
		 * @param [string] $value  value of option.
		 *
		 * @return string
		 */
		public function set_screen( $status, $option, $value ) {
			return $value;
		}

		/**
		 * Fetches the payments made through Fluttewave Pay.
		 *
		 * @param integer $post_per_page No of posts to show.
		 * @param integer $page_number   The current page number.
		 *
		 * @return mixed                  The list of all the payment records.
		 */
		public static function get_payments( $post_per_page = 20, $page_number = 1 ) {
			$request = wp_unslash( $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification
			$args    = array(
				'posts_per_page'   => $post_per_page,
				'offset'           => ( $page_number - 1 ) * $post_per_page,
				'orderby'          => ! empty( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
				'order'            => ! empty( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
				'post_type'        => 'payment_list',
				'post_status'      => 'publish',
				'suppress_filters' => true,
			);

			$payment_list = get_posts( $args );

			return $payment_list;
		}

		/**
		 * Deletes a payment.
		 *
		 * @param int $payment_id The id of the payment to delete.
		 *
		 * @return void
		 */
		public static function delete_payment( $payment_id ) {

			wp_delete_post( $payment_id );
		}

		/**
		 * Gets the total payments made through Rave.
		 *
		 * @return int The total number of payments.
		 */
		public static function record_count(): int {
			$total_records = wp_count_posts( 'payment_list' );

			return $total_records->publish;
		}

		/**
		 * Add post types for payment lists.
		 */
		public function add_payment_list_post_type() {
			$args = array(
				'label'               => __( 'Payment Lists', 'rave-payment-forms' ),
				'description'         => __( 'Flutterwave payment lists', 'rave-payment-forms' ),
				'supports'            => array( 'title', 'author', 'custom-fields' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
			);

			register_post_type( 'payment_list', $args );
		}

		/**
		 * Returns the singleton instance of this class.
		 *
		 * @return object - the instance of the class.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {

				self::$instance = new self();
			}

			return self::$instance;
		}
	}
}//end if
