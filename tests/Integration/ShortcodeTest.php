<?php
/**
 * Payment and donation shortcodes.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Form_Config;

/**
 * @covers FLW_Shortcodes
 * @covers FLW_Shortcode_Payment_Form
 * @covers FLW_Shortcode_Donation_Form
 */
class ShortcodeTest extends TestCase {

	/**
	 * Configure the plugin.
	 */
	public function set_up() {
		parent::set_up();
		$this->configure_plugin();
	}

	/**
	 * Decode the signed config embedded in rendered form HTML.
	 *
	 * @param string $html Rendered form.
	 *
	 * @return array
	 */
	private function signed_config( string $html ): array {
		$this->assertSame( 1, preg_match( '/name="flw_form_config" value="([^"]+)"/', $html, $payload ) );
		$this->assertSame( 1, preg_match( '/name="flw_form_sig" value="([^"]+)"/', $html, $signature ) );

		$config = FLW_Form_Config::verify( html_entity_decode( $payload[1] ), $signature[1] );
		$this->assertIsArray( $config );

		return $config;
	}

	/**
	 * Parse the rendered <form> tag.
	 *
	 * @param string $html Rendered shortcode.
	 *
	 * @return \WP_HTML_Tag_Processor Positioned on the form tag.
	 */
	private function form_tag( string $html ): \WP_HTML_Tag_Processor {
		$tag = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tag->next_tag( 'form' ), 'No form rendered.' );
		return $tag;
	}

	public function test_payment_form_signs_fixed_amount() {
		$config = $this->signed_config( do_shortcode( '[flw-pay-form amount="5000" currency="NGN"]' ) );

		$this->assertEquals( 5000, $config['amount'] );
		$this->assertSame( array( 'NGN' ), $config['currencies'] );
	}

	public function test_payment_form_attributes_cannot_inject_handlers() {
		$form = $this->form_tag( do_shortcode( '[flw-pay-form country="NG onmouseover=alert(document.domain)//" amount="1 onmouseover=alert(1)//"]' ) );

		$this->assertSame( 'NG onmouseover=alert(document.domain)//', $form->get_attribute( 'data-country' ) );
		$this->assertNull( $form->get_attribute( 'data-amount' ), 'A non-numeric amount is not a fixed price.' );
		$this->assertNull( $form->get_attribute( 'onmouseover' ) );
	}

	public function test_donation_form_attributes_cannot_inject_handlers() {
		$html = do_shortcode( '[flw-donation-form country="NG onfocus=alert(1) autofocus x=" amount="1 onfocus=alert(1) autofocus x="]' );
		$form = $this->form_tag( $html );

		$this->assertSame( 'NG onfocus=alert(1) autofocus x=', $form->get_attribute( 'data-country' ) );
		$this->assertNull( $form->get_attribute( 'data-amount' ) );
		$this->assertNull( $form->get_attribute( 'onfocus' ) );
		$this->assertNull( $form->get_attribute( 'autofocus' ) );
		$this->assertEquals( 0, $this->signed_config( $html )['amount'] );
	}

	public function test_editor_sees_form_when_plugin_is_configured() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertStringContainsString( 'flw-simple-pay-now-form', do_shortcode( '[flw-pay-form amount="10"]' ) );
	}

	public function test_editor_sees_setup_notice_when_keys_are_missing() {
		$this->configure_plugin( array( 'public_key' => '' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertStringContainsString( 'flw-mssing-api-keys', do_shortcode( '[flw-pay-form amount="10"]' ) );
	}

	public function test_open_amount_form_collects_name_and_amount() {
		$html = do_shortcode( '[flw-pay-form]' );

		$this->assertStringContainsString( 'id="flw-amount"', $html );
		$this->assertStringContainsString( 'id="flw-full-name"', $html );
		$this->assertSame( '0', $this->form_tag( $html )->get_attribute( 'data-split_name' ) );
	}

	public function test_fixed_amount_summary_is_formatted_and_styled() {
		$html = do_shortcode( '[flw-pay-form amount="5000" currency="NGN"]' );

		$this->assertStringContainsString( '<div class="flw_payment_overview">', $html );
		$this->assertStringContainsString( 'NGN 5,000.00', $html );
	}

	public function test_fields_have_readable_labels() {
		$html = do_shortcode( '[flw-pay-form]' );

		$this->assertStringContainsString( '<label class="pay-now" for="flw-full-name">Full name</label>', $html );
		$this->assertStringContainsString( 'placeholder="Phone number"', $html );
	}

	public function test_styles_load_with_a_form_unless_the_theme_styles_it() {
		wp_dequeue_style( 'flw_css' );
		do_shortcode( '[flw-pay-form amount="10"]' );
		$this->assertTrue( wp_style_is( 'flw_css', 'enqueued' ) );
		$this->assertTrue( in_array( 'flw-fonts', wp_styles()->registered['flw_css']->deps, true ) );

		wp_dequeue_style( 'flw_css' );
		$this->configure_plugin( array( 'theme_style' => 'yes' ) );
		do_shortcode( '[flw-pay-form amount="10"]' );
		$this->assertFalse( wp_style_is( 'flw_css', 'enqueued' ) );
	}

	public function test_donation_form_shows_the_configured_heading() {
		$this->configure_plugin(
			array(
				'donation_title' => 'Support <em>us</em>',
				'donation_desc'  => 'Every gift helps.',
			)
		);

		$html = do_shortcode( '[flw-donation-form]' );

		$this->assertStringContainsString( '<h2 class="flw-form-title">Support &lt;em&gt;us&lt;/em&gt;</h2>', $html );
		$this->assertStringContainsString( 'Every gift helps.', $html );
	}

	public function test_setup_notice_links_editors_to_the_settings_page() {
		$this->configure_plugin( array( 'public_key' => '' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$html = do_shortcode( '[flw-pay-form amount="10"]' );

		$this->assertStringContainsString( esc_url( \FLW_Admin_Settings::get_url() ), $html );
	}

	public function test_split_name_renders_first_and_last_name() {
		$html = do_shortcode( '[flw-pay-form split_name="1"]' );

		$this->assertStringContainsString( 'id="flw-first-name"', $html );
		$this->assertStringContainsString( 'id="flw-last-name"', $html );
		$this->assertStringNotContainsString( 'id="flw-full-name"', $html );
	}

	public function test_preset_fullname_is_sent_without_a_field() {
		$html = do_shortcode( '[flw-pay-form fullname="Ada Lovelace" split_name="1"]' );

		$this->assertSame( 'Ada Lovelace', $this->form_tag( $html )->get_attribute( 'data-fullname' ) );
		$this->assertStringNotContainsString( 'id="flw-full-name"', $html );
		$this->assertStringNotContainsString( 'id="flw-first-name"', $html );
	}
}
