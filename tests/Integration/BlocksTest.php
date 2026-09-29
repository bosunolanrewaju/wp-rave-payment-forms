<?php
/**
 * The Flutterwave blocks, rendered on the server.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Blocks;
use FLW_Form_Config;
use WP_Block_Type_Registry;

/**
 * @covers FLW_Blocks
 */
class BlocksTest extends TestCase {

	/**
	 * Configure the plugin.
	 */
	public function set_up() {
		parent::set_up();
		$this->configure_plugin();
	}

	/**
	 * Render one block from its attributes.
	 *
	 * @param string $name       Block name without the namespace.
	 * @param array  $attributes Block attributes.
	 *
	 * @return string
	 */
	private function render( string $name, array $attributes = array() ): string {
		$json = $attributes ? ' ' . wp_json_encode( $attributes ) : '';

		return do_blocks( '<!-- wp:flutterwave/' . $name . $json . ' /-->' );
	}

	/**
	 * The signed form config in rendered HTML.
	 *
	 * @param string $html Rendered block.
	 *
	 * @return array
	 */
	private function signed_config( string $html ): array {
		preg_match( '/name="flw_form_config" value="([^"]+)"/', $html, $payload );
		preg_match( '/name="flw_form_sig" value="([^"]+)"/', $html, $signature );

		$config = FLW_Form_Config::verify( html_entity_decode( $payload[1] ?? '' ), $signature[1] ?? '' );
		$this->assertIsArray( $config, 'The block should carry a valid signed config.' );

		return $config;
	}

	public function test_blocks_are_registered_in_the_flutterwave_category() {
		foreach ( FLW_Blocks::BLOCKS as $block ) {
			$type = WP_Block_Type_Registry::get_instance()->get_registered( 'flutterwave/' . $block );

			$this->assertNotNull( $type, $block );
			$this->assertSame( 'flutterwave', $type->category );
			$this->assertTrue( $type->is_dynamic(), $block . ' is rendered on the server.' );
		}

		$slugs = wp_list_pluck( get_block_categories( get_post( self::factory()->post->create() ) ), 'slug' );
		$this->assertContains( 'flutterwave', $slugs );
	}

	public function test_payment_button_signs_its_amount_and_labels_the_button() {
		$html = $this->render(
			'payment-button',
			array(
				'amount'       => 5000,
				'currency'     => 'NGN',
				'useUserEmail' => false,
			)
		);

		$this->assertStringContainsString( 'flw-layout-compact', $html );
		$this->assertStringContainsString( '>Pay NGN 5,000</button>', $html );
		$this->assertStringContainsString( 'id="flw-customer-email"', $html );
		$this->assertStringNotContainsString( 'id="flw-full-name"', $html );
		$this->assertStringNotContainsString( 'id="flw-phone"', $html );

		$config = $this->signed_config( $html );
		$this->assertEquals( 5000, $config['amount'] );
		$this->assertSame( array( 'NGN' ), $config['currencies'] );
	}

	public function test_payment_button_uses_the_logged_in_users_email() {
		wp_set_current_user( self::factory()->user->create( array( 'user_email' => 'member@example.com' ) ) );

		$html = $this->render( 'payment-button', array( 'amount' => 10 ) );

		$this->assertStringNotContainsString( 'id="flw-customer-email"', $html );
		$this->assertStringContainsString( 'data-email="member@example.com"', $html );
	}

	public function test_payment_button_defaults_to_the_site_currency() {
		$this->configure_plugin( array( 'currency' => 'KES' ) );

		$this->assertSame( array( 'KES' ), $this->signed_config( $this->render( 'payment-button', array( 'amount' => 10 ) ) )['currencies'] );
	}

	public function test_style_settings_become_css_variables_and_bad_values_are_dropped() {
		$html = $this->render(
			'payment-button',
			array(
				'amount'          => 10,
				'accentColor'     => '#2a3362',
				'accentTextColor' => 'red;background:url(https://evil.test)',
				'borderRadius'    => 999,
			)
		);

		$this->assertStringContainsString( '--flw-accent:#2a3362', $html );
		$this->assertStringContainsString( '--flw-custom-radius:40px', $html );
		$this->assertStringNotContainsString( 'evil.test', $html );
		$this->assertStringNotContainsString( '--flw-accent-text', $html );
	}

	public function test_payment_form_options() {
		$html = $this->render(
			'payment-form',
			array(
				'heading'      => 'Pay <script>alert(1)</script>',
				'description'  => 'Invoice payments',
				'collectName'  => 'split',
				'collectPhone' => false,
				'buttonText'   => 'Pay {amount} now',
				'amount'       => 1500,
				'currency'     => 'NGN',
			)
		);

		$this->assertStringContainsString( '<h2 class="flw-form-title">Pay</h2>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'id="flw-first-name"', $html );
		$this->assertStringNotContainsString( 'id="flw-phone"', $html );
		$this->assertStringContainsString( '>Pay NGN 1,500 now</button>', $html );
		$this->assertEquals( 1500, $this->signed_config( $html )['amount'] );
	}

	public function test_payment_form_can_skip_the_name() {
		$html = $this->render( 'payment-form', array( 'collectName' => 'none' ) );

		$this->assertStringNotContainsString( 'id="flw-full-name"', $html );
		$this->assertStringNotContainsString( 'id="flw-first-name"', $html );
		$this->assertEquals( 0, $this->signed_config( $html )['amount'] );
	}

	public function test_donation_form_presets_currency_and_frequency() {
		$html = $this->render(
			'donation-form',
			array(
				'heading'       => 'Give',
				'amounts'       => '1000, abc, 5000, -1, 5000, 2, 3, 4, 5, 6',
				'currency'      => 'NGN',
				'showFrequency' => false,
			)
		);

		$this->assertSame( 6, substr_count( $html, 'class="flw-amount-preset"' ) );
		$this->assertStringContainsString( 'data-amount="1000"', $html );
		$this->assertStringNotContainsString( 'id="flw-payment-type"', $html );
		$this->assertStringNotContainsString( 'id="flw-currency"', $html );
		$this->assertStringContainsString( 'data-currency="NGN"', $html );
		$this->assertSame( array( 'NGN' ), $this->signed_config( $html )['currencies'] );
	}

	/**
	 * The data-amount attribute of the rendered <form>, or null.
	 *
	 * @param string $html Rendered form.
	 *
	 * @return string|null
	 */
	private function form_amount( string $html ): ?string {
		$tag = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tag->next_tag( 'form' ) );

		return $tag->get_attribute( 'data-amount' );
	}

	public function test_open_amount_forms_have_no_fixed_price_attribute() {
		$this->assertNull( $this->form_amount( $this->render( 'donation-form' ) ) );
		$this->assertNull( $this->form_amount( $this->render( 'payment-form' ) ) );
		$this->assertNull( $this->form_amount( do_shortcode( '[flw-donation-form]' ) ) );
		$this->assertNull( $this->form_amount( do_shortcode( '[flw-pay-form amount="0"]' ) ) );
		$this->assertSame( '10', $this->form_amount( do_shortcode( '[flw-pay-form amount="10"]' ) ) );
	}

	public function test_donation_form_falls_back_to_settings_heading() {
		$this->configure_plugin( array( 'donation_title' => 'From settings' ) );

		$this->assertStringContainsString( 'From settings', $this->render( 'donation-form' ) );
		$this->assertStringContainsString( 'Block heading', $this->render( 'donation-form', array( 'heading' => 'Block heading' ) ) );
	}

	public function test_pricing_card() {
		$html = $this->render(
			'pricing-card',
			array(
				'planName'    => 'Pro',
				'amount'      => 25000,
				'currency'    => 'NGN',
				'features'    => array( 'Support', '<img src=x onerror=alert(1)>' ),
				'badge'       => 'Popular',
				'highlighted' => true,
			)
		);

		$this->assertStringContainsString( 'flw-pricing-card is-highlighted', $html );
		$this->assertStringContainsString( 'NGN 25,000', $html );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $html );
		$this->assertStringContainsString( '>Get started</button>', $html );
		$this->assertEquals( 25000, $this->signed_config( $html )['amount'] );
	}

	public function test_payment_methods_follow_the_settings() {
		$this->configure_plugin( array( 'payment_options' => 'card,applepay,ussd' ) );

		$html = $this->render( 'payment-methods' );

		preg_match_all( '/<li class="flw-payment-methods__item">.*?<\/svg>([^<]+)<\/li>/', $html, $items );
		$this->assertSame( array( 'Cards', 'Apple Pay', 'USSD' ), $items[1] );
		$this->assertStringContainsString( 'Payments secured by', $html );
	}

	public function test_blocks_show_the_setup_notice_to_editors_when_not_configured() {
		$this->configure_plugin( array( 'public_key' => '' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertStringContainsString( 'flw-mssing-api-keys', $this->render( 'payment-button', array( 'amount' => 10 ) ) );
	}

	public function test_existing_shortcodes_are_unchanged() {
		$html = do_shortcode( '[flw-pay-form amount="10"]' );

		$this->assertStringContainsString( '<div class="flutterwave-payment-form">', $html );
		$this->assertStringNotContainsString( 'flw-form-header', $html );
		$this->assertStringContainsString( 'flw-secured', $html );
	}
}
