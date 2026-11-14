<?php
/**
 * Asset registration tests.
 *
 * @package Outstand\WP\Embeds\Tests\Unit
 */

namespace Outstand\WP\Embeds\Tests\Unit;

use Outstand\WP\Embeds\Assets;
use Outstand\WP\Embeds\Settings;

/**
 * The consent helper is a public handle with its settings, and the embed script depends on it.
 *
 * @covers \Outstand\WP\Embeds\Assets
 */
class AssetsTest extends \WP_UnitTestCase {

	/**
	 * The consent helper and the embed assets are registered, and nothing is enqueued before an
	 * embed renders.
	 *
	 * @return void
	 */
	public function test_handles_are_registered(): void {

		$this->assertTrue( wp_script_is( Assets::CONSENT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( Assets::FRONTEND_HANDLE, 'registered' ) );
		$this->assertTrue( wp_style_is( Assets::STYLE_HANDLE, 'registered' ) );
		$this->assertContains( Assets::CONSENT_HANDLE, wp_scripts()->registered[ Assets::FRONTEND_HANDLE ]->deps );
		$this->assertFalse( wp_script_is( Assets::FRONTEND_HANDLE, 'enqueued' ) );
	}

	/**
	 * The consent helper prints its settings before it runs.
	 *
	 * @return void
	 */
	public function test_consent_helper_has_settings(): void {

		$inline = (array) wp_scripts()->get_data( Assets::CONSENT_HANDLE, 'before' );

		$this->assertStringContainsString( 'window.outstandEmbeds = Object.assign( window.outstandEmbeds || {}, { settings: {"requireConsent":', implode( '', $inline ) );
	}

	/**
	 * The settings follow "require consent" and carry the default category.
	 *
	 * @return void
	 */
	public function test_consent_settings(): void {

		update_option( Settings::REQUIRE_CONSENT_OPTION, '1' );
		update_option( Settings::CATEGORY_OPTION, 'functional' );

		$settings = Assets::get_consent_settings();

		$this->assertTrue( $settings['requireConsent'] );
		$this->assertSame( 'functional', $settings['category'] );
		$this->assertEquals( new \stdClass(), $settings['categories'] );
	}

	/**
	 * CookieYes is a source when its current front end runs, not in legacy mode.
	 *
	 * @return void
	 */
	public function test_cookieyes_is_a_source_when_its_front_end_runs(): void {

		$this->load_cookieyes();

		update_option( 'cky_cookie_consent_lite_db_version', '3.0.0' );
		update_option( CLI_SETTINGS_FIELD, [ 'example' => 'value' ] );

		$this->assertSame( [ Assets::SOURCE_COOKIEYES ], Assets::get_consent_settings()['sources'] );
	}

	/**
	 * CookieYes in legacy mode has no consent API, so it is not a source.
	 *
	 * @return void
	 */
	public function test_cookieyes_in_legacy_mode_is_not_a_source(): void {

		$this->load_cookieyes();

		update_option( 'cky_cookie_consent_lite_db_version', '2.0' );
		update_option( CLI_SETTINGS_FIELD, [ 'example' => 'value' ] );

		$this->assertTrue( cky_is_legacy() );
		$this->assertSame( [], Assets::get_consent_settings()['sources'] );
	}

	/**
	 * The server category of an embed is listed for the front-end script.
	 *
	 * @return void
	 */
	public function test_embed_category_is_listed(): void {

		Assets::add_embed_category( 'facebook', 'https://www.facebook.com/reel/1', 'marketing' );

		$inline = implode( '', (array) wp_scripts()->get_data( Assets::CONSENT_HANDLE, 'before' ) );

		$this->assertStringContainsString( 'window.outstandEmbeds.settings.categories[ "facebook https:\/\/www.facebook.com\/reel\/1" ] = "marketing";', $inline );
	}

	/**
	 * Loads the CookieYes plugin when it is installed and not loaded yet.
	 *
	 * @return void
	 */
	private function load_cookieyes(): void {

		$file = WP_PLUGIN_DIR . '/cookie-law-info/cookie-law-info.php';

		if ( ! defined( 'CLI_VERSION' ) && file_exists( $file ) ) {
			require_once $file;
		}

		if ( ! function_exists( 'cky_is_legacy' ) ) {
			$this->markTestSkipped( 'The cookie-law-info plugin is not installed.' );
		}
	}
}
