<?php
/**
 * Flutterwave blocks for the block editor.
 *
 * Every block is rendered on the server through the same code as the shortcodes,
 * so signed amounts, escaping and rate limiting apply to blocks unchanged. The
 * editor previews the real server output.
 *
 * @package Flutterwave_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the blocks, their category, scripts and styles.
 */
final class FLW_Blocks {

	const CATEGORY = 'flutterwave';

	const EDITOR_SCRIPT = 'flw-blocks-editor';

	const EDITOR_STYLE = 'flw-blocks-editor-style';

	/**
	 * Block folder names under blocks/.
	 *
	 * @var string[]
	 */
	const BLOCKS = array( 'payment-button', 'payment-form', 'donation-form', 'pricing-card', 'payment-methods' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'register_category' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Register the shared styles and the editor script.
	 *
	 * @return void
	 */
	public static function register_assets(): void {
		wp_register_style( 'flw-fonts', FLW_DIR_URL . 'assets/css/flw-fonts.css', array(), FLW_PAY_VERSION );
		wp_register_style( 'flw_css', FLW_DIR_URL . 'assets/css/flw.css', array( 'flw-fonts' ), FLW_PAY_VERSION );

		$asset_path = FLW_DIR_PATH . 'build/blocks.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_register_script( self::EDITOR_SCRIPT, FLW_DIR_URL . 'build/blocks.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::EDITOR_SCRIPT, 'rave-payment-forms', FLW_DIR_PATH . 'i18n/languages' );

		if ( file_exists( FLW_DIR_PATH . 'build/blocks.css' ) ) {
			wp_register_style( self::EDITOR_STYLE, FLW_DIR_URL . 'build/blocks.css', array( 'flw_css' ), $asset['version'] );
		}
	}

	/**
	 * Register each block from its block.json.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		foreach ( self::BLOCKS as $block ) {
			register_block_type( FLW_DIR_PATH . 'blocks/' . $block );
		}
	}

	/**
	 * Add the Flutterwave category to the inserter.
	 *
	 * @param array $categories Registered categories.
	 *
	 * @return array
	 */
	public static function register_category( $categories ): array {
		$categories = is_array( $categories ) ? $categories : array();

		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Flutterwave', 'rave-payment-forms' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Settings the editor needs: whether the plugin is set up, and the currencies.
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		if ( ! wp_script_is( self::EDITOR_SCRIPT, 'registered' ) ) {
			return;
		}

		wp_localize_script(
			self::EDITOR_SCRIPT,
			'flwBlocksData',
			array(
				'configured'      => '' !== FLW_Settings::get( 'public_key' ),
				'settingsUrl'     => current_user_can( 'manage_options' ) ? FLW_Admin_Settings::get_url() : '',
				'currencies'      => FLW_Settings::CURRENCIES,
				'defaultCurrency' => self::default_currency(),
			)
		);
	}

	/**
	 * The site's default currency, or NGN when customers may choose.
	 *
	 * @return string
	 */
	public static function default_currency(): string {
		$currency = FLW_Settings::get( 'currency' );

		return in_array( $currency, FLW_Settings::CURRENCIES, true ) ? $currency : 'NGN';
	}

	/**
	 * A supported currency code from a block attribute, or '' when not set or unknown.
	 *
	 * @param mixed $currency Currency attribute.
	 *
	 * @return string
	 */
	public static function currency( $currency ): string {
		$currency = strtoupper( trim( (string) $currency ) );

		return in_array( $currency, FLW_Settings::CURRENCIES, true ) ? $currency : '';
	}

	/**
	 * CSS custom properties for a block's colour and radius settings. Only valid
	 * hex colours and a bounded radius get through.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function style_vars( array $attributes ): string {
		$vars = array();

		$accent = sanitize_hex_color( (string) ( $attributes['accentColor'] ?? '' ) );
		if ( $accent ) {
			$vars[] = '--flw-accent:' . $accent;
		}

		$text = sanitize_hex_color( (string) ( $attributes['accentTextColor'] ?? '' ) );
		if ( $text ) {
			$vars[] = '--flw-accent-text:' . $text;
		}

		if ( isset( $attributes['borderRadius'] ) && is_numeric( $attributes['borderRadius'] ) ) {
			$vars[] = '--flw-custom-radius:' . max( 0, min( 40, (int) $attributes['borderRadius'] ) ) . 'px';
		}

		return implode( ';', $vars );
	}

	/**
	 * The block wrapper attributes, including its style variables.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $extra      Extra wrapper attributes.
	 *
	 * @return string
	 */
	public static function wrapper( array $attributes, array $extra = array() ): string {
		$style = self::style_vars( $attributes );

		if ( '' !== $style ) {
			$extra['style'] = $style;
		}

		return get_block_wrapper_attributes( $extra );
	}

	/**
	 * A positive amount from a block attribute, or 0.
	 *
	 * @param mixed $amount Amount attribute.
	 *
	 * @return float
	 */
	public static function amount( $amount ): float {
		return is_numeric( $amount ) && (float) $amount > 0 ? round( (float) $amount, 2 ) : 0.0;
	}
}
