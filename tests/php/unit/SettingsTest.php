<?php
/**
 * Embed consent settings tests.
 *
 * @package Outstand\WP\Embeds\Tests\Unit
 */

namespace Outstand\WP\Embeds\Tests\Unit;

use Outstand\WP\Embeds\Settings;

/**
 * The consent category defaults to advertisement and keeps only a slug; "require consent" is off
 * by default; both pass through their filters.
 *
 * @covers \Outstand\WP\Embeds\Settings
 */
class SettingsTest extends \WP_UnitTestCase {

	/**
	 * Without a stored value, embeds wait for the advertisement category.
	 *
	 * @return void
	 */
	public function test_category_defaults_to_advertisement(): void {

		delete_option( Settings::CATEGORY_OPTION );

		$this->assertSame( 'advertisement', Settings::get_category() );
	}

	/**
	 * A stored category replaces the default.
	 *
	 * @return void
	 */
	public function test_stored_category_is_used(): void {

		update_option( Settings::CATEGORY_OPTION, 'functional' );

		$this->assertSame( 'functional', Settings::get_category() );
	}

	/**
	 * An empty stored value falls back to the default.
	 *
	 * @return void
	 */
	public function test_empty_category_falls_back_to_default(): void {

		update_option( Settings::CATEGORY_OPTION, '' );

		$this->assertSame( 'advertisement', Settings::get_category() );
	}

	/**
	 * Saving the setting keeps a slug, and an empty value saves the default.
	 *
	 * @return void
	 */
	public function test_sanitize_keeps_a_slug(): void {

		$settings = new Settings();

		$this->assertSame( 'analytics', $settings->sanitize_category( 'Analytics' ) );
		$this->assertSame( 'scriptalertx', $settings->sanitize_category( '<script>alert("x")' ) );
		$this->assertSame( 'advertisement', $settings->sanitize_category( '' ) );
	}

	/**
	 * Saving the category through the options API runs the sanitize callback.
	 *
	 * @return void
	 */
	public function test_saving_the_option_sanitizes_it(): void {

		update_option( Settings::CATEGORY_OPTION, 'Functional!' );

		$this->assertSame( 'functional', get_option( Settings::CATEGORY_OPTION ) );
	}

	/**
	 * The category filter receives the provider and URL, and its result is sanitized.
	 *
	 * @return void
	 */
	public function test_category_filter(): void {

		$received = [];
		$filter   = function ( $category, $provider, $url ) use ( &$received ) {
			$received = [ $category, $provider, $url ];

			return 'instagram' === $provider ? 'Marketing' : $category;
		};

		add_filter( 'outstand_embeds_consent_category', $filter, 10, 3 );

		$this->assertSame( 'marketing', Settings::get_consent_category( 'instagram', 'https://www.instagram.com/p/AbC/' ) );
		$this->assertSame( [ 'advertisement', 'instagram', 'https://www.instagram.com/p/AbC/' ], $received );
		$this->assertSame( 'advertisement', Settings::get_consent_category( 'facebook', 'https://www.facebook.com/reel/1' ) );

		remove_filter( 'outstand_embeds_consent_category', $filter, 10 );
	}

	/**
	 * A filter result that is not a string falls back to the stored category.
	 *
	 * @return void
	 */
	public function test_category_filter_ignores_a_value_that_is_not_a_string(): void {

		update_option( Settings::CATEGORY_OPTION, 'functional' );

		$filter = static fn() => [ 'marketing' ];

		add_filter( 'outstand_embeds_consent_category', $filter );

		$this->assertSame( 'functional', Settings::get_consent_category( 'facebook', 'https://www.facebook.com/reel/1' ) );

		remove_filter( 'outstand_embeds_consent_category', $filter );
	}

	/**
	 * "Require consent" is off by default, follows the stored value and the filter.
	 *
	 * @return void
	 */
	public function test_require_consent(): void {

		delete_option( Settings::REQUIRE_CONSENT_OPTION );

		$this->assertFalse( Settings::is_consent_required() );

		update_option( Settings::REQUIRE_CONSENT_OPTION, '1' );

		$this->assertTrue( Settings::is_consent_required() );

		add_filter( 'outstand_embeds_require_consent', '__return_false' );

		$this->assertFalse( Settings::is_consent_required() );

		remove_filter( 'outstand_embeds_require_consent', '__return_false' );
	}

	/**
	 * Both options are registered on the Reading page.
	 *
	 * @return void
	 */
	public function test_options_are_registered(): void {

		$registered = get_registered_settings();

		$this->assertSame( 'reading', $registered[ Settings::CATEGORY_OPTION ]['group'] ?? null );
		$this->assertSame( 'reading', $registered[ Settings::REQUIRE_CONSENT_OPTION ]['group'] ?? null );
		$this->assertSame( 'boolean', $registered[ Settings::REQUIRE_CONSENT_OPTION ]['type'] ?? null );
	}
}
