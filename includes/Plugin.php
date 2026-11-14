<?php

namespace Outstand\WP\Embeds;

defined( 'ABSPATH' ) || exit;

use Outstand\WP\Embeds\Providers\Facebook;
use Outstand\WP\Embeds\Providers\Instagram;

class Plugin {

	/**
	 * Singleton instance of the Plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns singleton instance.
	 *
	 * @return Plugin The singleton instance.
	 */
	public static function get_instance(): Plugin {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Enable plugin functionality.
	 *
	 * @return void
	 */
	public function enable(): void {

		$providers = [
			new Facebook(),
			new Instagram(),
		];

		$modules = [
			new Settings(),
			new Assets(),
			new Proxy( $providers ),
			...$providers,
		];

		foreach ( $modules as $module ) {
			if ( $module instanceof BaseModule && $module->can_register() ) {
				$module->register();
			}
		}

		add_action( 'init', [ $this, 'load_textdomain' ], 1 );
		add_filter( 'plugin_action_links_' . OUTSTAND_EMBEDS_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Loads the bundled translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'outstand-embeds', false, dirname( OUTSTAND_EMBEDS_BASENAME ) . '/languages' );
	}

	/**
	 * Adds a link to the settings on the Reading page.
	 *
	 * @param  array $links The plugin action links.
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'options-reading.php#' . Settings::SECTION_ID ) ),
			esc_html__( 'Settings', 'outstand-embeds' )
		);

		return array_merge( [ $settings_link ], $links );
	}
}
