<?php
/**
 * The settings page that hosts the admin app.
 *
 * @package Flutterwave\WordPress\Tests
 */

namespace Flutterwave\WordPress\Tests\Integration;

use FLW_Admin_Settings;

/**
 * @covers FLW_Admin_Settings
 */
class AdminPageTest extends TestCase {

	/**
	 * Whether the admin bundle has been built.
	 *
	 * @return bool
	 */
	private function is_built(): bool {
		return file_exists( FLW_DIR_PATH . 'build/admin.asset.php' );
	}

	public function test_page_renders_app_root_or_build_notice() {
		ob_start();
		FLW_Admin_Settings::flw_rave_admin_setting_page();
		$html = ob_get_clean();

		if ( $this->is_built() ) {
			$this->assertStringContainsString( 'id="flutterwave-admin-root"', $html );
		} else {
			$this->assertStringContainsString( 'npm run build:admin', $html );
		}
	}

	/**
	 * @dataProvider screens
	 *
	 * @param string $method Render method.
	 * @param string $screen Expected data-screen value.
	 */
	public function test_list_screens_render_their_root( string $method, string $screen ) {
		if ( ! $this->is_built() ) {
			$this->markTestSkipped( 'Run `npm run build:admin` to build the admin app.' );
		}

		ob_start();
		call_user_func( array( FLW_Admin_Settings::class, $method ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="flutterwave-admin-root"', $html );
		$this->assertStringContainsString( 'data-screen="' . $screen . '"', $html );
	}

	/**
	 * Screen render methods.
	 *
	 * @return array
	 */
	public static function screens(): array {
		return array(
			'payment forms' => array( 'render_forms_page', 'forms' ),
			'transactions'  => array( 'render_transactions_page', 'transactions' ),
			'integrations'  => array( 'render_integrations_page', 'integrations' ),
		);
	}

	public function test_app_is_enqueued_only_on_the_settings_page() {
		if ( ! $this->is_built() ) {
			$this->markTestSkipped( 'Run `npm run build:admin` to build the admin app.' );
		}

		$settings = FLW_Admin_Settings::get_instance();

		$settings->enqueue_admin_app( 'index.php' );
		$this->assertFalse( wp_script_is( 'flw-admin', 'enqueued' ) );

		$settings->enqueue_admin_app( FLW_Admin_Settings::HOOK_SUFFIX );
		$this->assertTrue( wp_script_is( 'flw-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'flw-fonts', 'enqueued' ) );
		$this->assertStringContainsString( 'flutterwaveAdminData', (string) wp_scripts()->get_data( 'flw-admin', 'data' ) );
	}

	public function test_every_screen_is_registered_and_loads_the_app() {
		if ( ! $this->is_built() ) {
			$this->markTestSkipped( 'Run `npm run build:admin` to build the admin app.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		FLW_Admin_Settings::get_instance()->flw_rave_add_admin_menu();

		$pages = array(
			FLW_Admin_Settings::PAGE_SLUG         => 'settings',
			FLW_Admin_Settings::FORMS_SLUG        => 'forms',
			FLW_Admin_Settings::TRANSACTIONS_SLUG => 'transactions',
			FLW_Admin_Settings::INTEGRATIONS_SLUG => 'integrations',
		);

		foreach ( $pages as $slug => $screen ) {
			$hook = get_plugin_page_hookname( $slug, FLW_Admin_Settings::PAGE_SLUG );

			$this->assertSame( $screen, FLW_Admin_Settings::screen_for( $hook ), $slug );

			wp_dequeue_script( 'flw-admin' );
			FLW_Admin_Settings::get_instance()->enqueue_admin_app( $hook );
			$this->assertTrue( wp_script_is( 'flw-admin', 'enqueued' ), $slug );
		}

		$data = (string) wp_scripts()->get_data( 'flw-admin', 'data' );

		foreach ( array( 'exportUrl', 'formsUrl', 'onboarded', 'tour', 'userId', 'supportForum' ) as $key ) {
			$this->assertStringContainsString( '"' . $key . '"', $data );
		}
	}

	public function test_setup_notice_links_to_onboarding() {
		delete_option( 'flw_rave_options' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		ob_start();
		\Flutterwave_Payments::get_instance()->admin_notices();
		$html = ob_get_clean();

		$this->assertStringContainsString( esc_url( FLW_Admin_Settings::get_url() ), $html );
	}

	public function test_setup_notice_is_hidden_on_the_settings_page() {
		delete_option( 'flw_rave_options' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( FLW_Admin_Settings::HOOK_SUFFIX );

		ob_start();
		\Flutterwave_Payments::get_instance()->admin_notices();

		$this->assertSame( '', ob_get_clean() );
	}
}
