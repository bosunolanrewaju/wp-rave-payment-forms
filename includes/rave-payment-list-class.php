<?php
  /**
   * Rave Payment List
   */
  if ( ! defined( 'ABSPATH' ) ) { exit; }

  // require_once( ABSPATH . 'wp-admin/includes/screen.php');
  require_once( ABSPATH . 'wp-admin/includes/template.php');
  require_once( ABSPATH . 'wp-admin/includes/class-wp-screen.php');
  if( ! class_exists( 'FLW_WP_List_Table' ) ) {
    require_once( FLW_DIR_PATH . 'includes/wp-classes/class-wp-list-table.php' );
  }

  if ( ! class_exists( 'FLW_Rave_Payment_List' ) ) {

    /**
     * Payment List Class to add list payments made
     * via the payment buttons
     */
    class FLW_Rave_Payment_List extends FLW_WP_List_Table {

      /**
       * Class Instance
       * @var null
       */
      protected static $instance = null;

      /**
       * Class construct
       */
      public function __construct() {

        parent::__construct( array(
          'singular' => __( 'Payment List', 'rave-pay' ),
          'plural'   => __( 'Payment Lists', 'rave-pay' ),
          'ajax'     => false
        ) );

        add_filter( 'set-screen-option', array( $this, 'set_screen' ), 10, 3 );
        add_action( 'init', array( $this, 'add_payment_list_post_type' ) );
        add_action( 'admin_menu', array( $this, 'add_to_menu' ) );

      }

      /**
       * The text to display when no payment is made
       *
       * @return void
       *
       */
      public function no_items() {
        _e( 'No payments have been made yet.', 'rave-pay' );
      }

      /**
       * Method for name column
       *
       * @param array $item an array of DB data
       *
       * @return string
       */
      public function column_tx_ref( $item ) {

        $title = '<strong>' . get_post_meta( $item->ID, '_flw_rave_payment_tx_ref', true ) . '</strong>';

        $actions = array(
          'delete' => sprintf( '<a href="%s">Delete</a>', get_delete_post_link( absint( $item->ID ) ) )
        );

        return $title . $this->row_actions( $actions );
      }

      public function column_amount( $item ) {
        $amount = get_post_meta( $item->ID, '_flw_rave_payment_amount', true );
        return number_format( $amount, 2 );
      }

      /**
       * Renders a column when no column specific method exists.
       *
       * @param array $item
       * @param string $column_name
       *
       * @return mixed
       */
      public function column_default( $item, $column_name ) {

        switch ( $column_name ) {
          // case 'tx_ref':
          //   return $column_name;
          case 'customer':
          case 'status':
            return get_post_meta( $item->ID, '_flw_rave_payment_' . $column_name, true );
          case 'date':
            return $item->post_date;
          default:
            return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }

      }

      /**
       *  Associative array of columns
       *
       * @return array
       */
      function get_columns() {

        global $admin_settings;

        $columns = array(
          'cb'      => '<input type="checkbox" />',
          'tx_ref'  => __( 'Transaction Ref', 'rave-pay' ),
          'customer' => __( 'Customer', 'rave-pay' ),
          'amount'  => __( 'Amount (' . $admin_settings->get_option_value( 'currency' ) . ')', 'rave-pay' ),
          'status'  => __( 'Status', 'rave-pay' ),
          'date'    => __( 'Date', 'rave-pay' ),
        );

        return $columns;

      }

      /**
       * Render the bulk edit checkbox
       *
       * @param array $item
       *
       * @return string
       */
      public function column_cb( $item ) {

        return sprintf(
          '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->ID
        );

      }

      /**
       * Handles data query and filter, sorting, and pagination.
       */
      public function prepare_items() {

        // $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        // $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'payments_per_page' );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( array(
          'total_items' => $total_items,
          'per_page'    => $per_page
        ) );


        $this->items = self::get_payments( $per_page, $current_page );

      }

      public function set_screen( $status, $option, $value ) {

        return $value;

      }


      public function add_to_menu() {

        $hook = add_submenu_page(
          'rave-payment-forms',
          __( 'Rave Transaction List', 'rave-pay' ),
          __( 'Transactions', 'rave-pay' ),
          'manage_options',
          'flw_payment_list',
          array( $this, 'payment_list_table')
        );

        // add_action( "load-$hook", array( $this, 'screen_option' ) );

      }

      public function payment_list_table() {

        require_once( FLW_DIR_PATH . 'views/payment-list-table.php' );

      }

      /**
       * Fetches the payments made through Fluttewave Pay
       *
       * @param  integer $post_per_page No of posts to show
       * @param  integer $page_number   The current page number
       *
       * @return mixed                  The list of all the payment records
       *
       */
      public static function get_payments( $post_per_page = 20, $page_number = 1 ) {

        $args = array(
          'posts_per_page'   => $post_per_page,
          'offset'           => ( $page_number - 1 ) * $post_per_page,
          'orderby'          => ! empty( $_REQUEST['orderby'] ) ? $_REQUEST['orderby']  : 'date',
          'order'            => ! empty( $_REQUEST['order'] )   ? $_REQUEST['order']    : 'DESC',
          'post_type'        => 'payment_list',
          'post_status'      => 'publish',
          'suppress_filters' => true
        );

        $payment_list = get_posts( $args );

        return $payment_list;

      }

      /**
       * Deletes a payment
       *
       * @param  int $payment_id The id of the payment to delete
       *
       * @return void
       *
       */
      public static function delete_payment( $payment_id ) {

        wp_delete_post( $payment_id );

      }

      /**
       * Gets the total payments made through Rave
       *
       * @return int The total number of payments
       *
       */
      public static function record_count() {

        $total_records = wp_count_posts( 'payment_list' );

        return $total_records->publish;

      }

      /**
       * Add post types for payment lists
       */
      public function add_payment_list_post_type() {

        $args = array(
          'label'               => __( 'Payment Lists', 'rave-pay' ),
          'description'         => __( 'Rave payment lists', 'rave-pay' ),
          'supports'            => array( 'title', 'author', 'custom-fields', ),
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
       * Returns the singleton instance of this class
       *
       * @return object - the instance of the class
       */
      public static function get_instance() {

        if ( self::$instance == null ) {

          self::$instance = new self;

        }

        return self::$instance;

      }

    }

  }
?>
