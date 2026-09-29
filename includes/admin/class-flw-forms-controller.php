<?php
/**
 * REST endpoints behind the Payment Forms screen.
 *
 * Forms are not stored separately: they are the Flutterwave blocks and shortcodes
 * in your content, so this screen finds them where they are used.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists the payment forms in use and creates pages for new ones.
 */
final class FLW_Forms_Controller {

	/**
	 * Shortcodes that render a form, and the block type each corresponds to.
	 *
	 * @var array<string, string>
	 */
	const SHORTCODES = array(
		'flw-pay-form'      => 'payment-form',
		'flw-pay-button'    => 'payment-form',
		'flw-donation-form' => 'donation-form',
	);

	/**
	 * Most posts scanned for forms.
	 */
	const SCAN_LIMIT = 200;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/forms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_forms' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			FLW_Settings::REST_NAMESPACE,
			'/forms/page',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_page' ),
				'permission_callback' => array( __CLASS__, 'can_create_pages' ),
			)
		);
	}

	/**
	 * Only administrators see the forms screen.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Creating a page also needs the right to edit pages.
	 *
	 * @return bool
	 */
	public static function can_create_pages(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'edit_pages' );
	}

	/**
	 * The Flutterwave forms in a piece of content.
	 *
	 * @param string $content Post content.
	 *
	 * @return array[] Each with kind ("block" or "shortcode"), type and attributes.
	 */
	public static function parse_content( string $content ): array {
		$forms = array();

		if ( false !== strpos( $content, '<!-- wp:flutterwave/' ) ) {
			self::collect_blocks( parse_blocks( $content ), $forms );
		}

		$pattern = get_shortcode_regex( array_keys( self::SHORTCODES ) );

		if ( preg_match_all( '/' . $pattern . '/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				// Escaped shortcodes ([[flw-pay-form]]) are not rendered.
				if ( '[' === $match[1] && ']' === $match[6] ) {
					continue;
				}

				$atts = shortcode_parse_atts( $match[3] );
				$atts = is_array( $atts ) ? $atts : array();
				$type = self::SHORTCODES[ $match[2] ];

				if ( 'payment-form' === $type && 'compact' === ( $atts['layout'] ?? '' ) ) {
					$type = 'payment-button';
				}

				if ( '' !== trim( (string) $match[5] ) ) {
					$atts['buttonText'] = trim( wp_strip_all_tags( $match[5] ) );
				}

				$forms[] = array(
					'kind'       => 'shortcode',
					'type'       => $type,
					'attributes' => $atts,
				);
			}//end foreach
		}//end if

		return $forms;
	}

	/**
	 * Walk parsed blocks, including nested ones, collecting Flutterwave blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $forms  Collected forms.
	 *
	 * @return void
	 */
	private static function collect_blocks( array $blocks, array &$forms ): void {
		foreach ( $blocks as $block ) {
			$name = (string) ( $block['blockName'] ?? '' );

			if ( 0 === strpos( $name, 'flutterwave/' ) ) {
				$forms[] = array(
					'kind'       => 'block',
					'type'       => substr( $name, strlen( 'flutterwave/' ) ),
					'attributes' => is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array(),
				);
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect_blocks( $block['innerBlocks'], $forms );
			}
		}
	}

	/**
	 * Posts that contain a Flutterwave block or shortcode, most recently changed first.
	 *
	 * @return WP_Post[]
	 */
	public static function posts_with_forms(): array {
		global $wpdb;

		$types    = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$types[]  = 'wp_block';
		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );

		$type_in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built above.
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only scan.
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($type_in) AND post_status IN ($status_in) AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) ORDER BY post_modified DESC LIMIT %d",
				array_merge(
					$types,
					$statuses,
					array(
						'%' . $wpdb->esc_like( '<!-- wp:flutterwave/' ) . '%',
						'%' . $wpdb->esc_like( '[flw-pay-' ) . '%',
						'%' . $wpdb->esc_like( '[flw-donation-form' ) . '%',
						self::SCAN_LIMIT,
					)
				)
			)
		);
		// phpcs:enable

		return array_values( array_filter( array_map( 'get_post', array_map( 'intval', (array) $ids ) ) ) );
	}

	/**
	 * Successful payments per source page: count and totals per currency.
	 *
	 * @param int[] $page_ids Page ids.
	 *
	 * @return array<int, array{count: int, totals: array<string, float>}>
	 */
	public static function payment_stats( array $page_ids ): array {
		global $wpdb;

		$page_ids = array_values( array_filter( array_map( 'intval', $page_ids ) ) );

		if ( ! $page_ids ) {
			return array();
		}

		$in = implode( ',', array_fill( 0, count( $page_ids ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are built above.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only aggregate.
			$wpdb->prepare(
				"SELECT s.meta_value AS source, c.meta_value AS currency, COUNT(*) AS payments, SUM(CAST(a.meta_value AS DECIMAL(20,2))) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				INNER JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_flw_rave_payment_status' AND st.meta_value = 'successful'
				LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_flw_rave_payment_currency'
				LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_flw_rave_payment_amount'
				WHERE p.post_type = %s AND s.meta_value IN ($in)
				GROUP BY s.meta_value, c.meta_value",
				array_merge( array( FLW_Payments_Controller::SOURCE_META, FLW_Payment_Record::POST_TYPE ), array_map( 'strval', $page_ids ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$stats = array();

		foreach ( (array) $rows as $row ) {
			$id = (int) $row['source'];

			if ( ! isset( $stats[ $id ] ) ) {
				$stats[ $id ] = array(
					'count'  => 0,
					'totals' => array(),
				);
			}

			$stats[ $id ]['count'] += (int) $row['payments'];

			if ( '' !== (string) $row['currency'] ) {
				$stats[ $id ]['totals'][ (string) $row['currency'] ] = (float) $row['total'];
			}
		}

		return $stats;
	}

	/**
	 * A form shaped for the Payment Forms screen.
	 *
	 * @param array   $form  Output of parse_content().
	 * @param WP_Post $post  Where it is used.
	 * @param int     $index Position within the post.
	 * @param array   $stats Payment stats for the post.
	 *
	 * @return array
	 */
	public static function to_item( array $form, WP_Post $post, int $index, array $stats ): array {
		$atts   = $form['attributes'];
		$amount = FLW_Blocks::amount( $atts['amount'] ?? 0 );
		$label  = '';

		foreach ( array( 'heading', 'planName', 'label' ) as $key ) {
			if ( isset( $atts[ $key ] ) && is_scalar( $atts[ $key ] ) && '' !== trim( (string) $atts[ $key ] ) ) {
				$label = trim( (string) $atts[ $key ] );
				break;
			}
		}

		$totals = array();
		foreach ( $stats['totals'] ?? array() as $currency => $total ) {
			$totals[] = array(
				'currency' => $currency,
				'amount'   => $total,
			);
		}

		$status = get_post_status( $post );

		return array(
			'id'         => $post->ID . '-' . $index,
			'kind'       => $form['kind'],
			'type'       => $form['type'],
			'label'      => $label,
			'amount'     => $amount,
			'currency'   => FLW_Blocks::currency( $atts['currency'] ?? '' ),
			'buttonText' => isset( $atts['buttonText'] ) && is_scalar( $atts['buttonText'] ) ? (string) $atts['buttonText'] : '',
			'page'       => array(
				'id'       => $post->ID,
				'title'    => '' !== get_the_title( $post ) ? get_the_title( $post ) : __( '(no title)', 'rave-payment-forms' ),
				'type'     => $post->post_type,
				'status'   => $status,
				'editUrl'  => (string) get_edit_post_link( $post, 'raw' ),
				'viewUrl'  => 'publish' === $status && 'wp_block' !== $post->post_type ? (string) get_permalink( $post ) : '',
				'modified' => wp_date( get_option( 'date_format' ), (int) get_post_timestamp( $post, 'modified' ) ),
			),
			'payments'   => array(
				'count'  => (int) ( $stats['count'] ?? 0 ),
				'totals' => $totals,
			),
		);
	}

	/**
	 * GET /forms.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_forms(): WP_REST_Response {
		$posts = self::posts_with_forms();
		$stats = self::payment_stats( wp_list_pluck( $posts, 'ID' ) );
		$items = array();

		foreach ( $posts as $post ) {
			foreach ( self::parse_content( (string) $post->post_content ) as $index => $form ) {
				$items[] = self::to_item( $form, $post, $index, $stats[ $post->ID ] ?? array() );
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * POST /forms/page: create a draft page containing one Flutterwave block.
	 *
	 * @param WP_REST_Request $request Request with title, block and attributes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_page( WP_REST_Request $request ) {
		$block = sanitize_key( (string) $request->get_param( 'block' ) );

		if ( ! in_array( $block, FLW_Blocks::BLOCKS, true ) ) {
			return new WP_Error( 'flw-invalid-block', __( 'Unknown form type.', 'rave-payment-forms' ), array( 'status' => 400 ) );
		}

		$type       = WP_Block_Type_Registry::get_instance()->get_registered( 'flutterwave/' . $block );
		$attributes = $request->get_param( 'attributes' );
		$attributes = is_array( $attributes ) && $type ? array_intersect_key( $attributes, (array) $type->attributes ) : array();
		$title      = sanitize_text_field( (string) $request->get_param( 'title' ) );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => '' !== $title ? $title : __( 'Payment', 'rave-payment-forms' ),
				'post_content' => serialize_block(
					array(
						'blockName'    => 'flutterwave/' . $block,
						'attrs'        => $attributes,
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					)
				),
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		return rest_ensure_response(
			array(
				'id'      => $page_id,
				'editUrl' => (string) get_edit_post_link( $page_id, 'raw' ),
			)
		);
	}
}
