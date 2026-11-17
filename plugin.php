<?php // phpcs:ignore Generic.Commenting.DocComment.MissingShort
/**
 * @wordpress-plugin
 * Plugin Name:       Outstand Embeds
 * Description:       Embed third-party content in the Embed block, and load it only after cookie consent. Supports Facebook and Instagram.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-embeds&utm_campaign=plugin-uri
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Version:           1.0.0
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-embeds&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-embeds
 * Text Domain:       outstand-embeds
 * Domain Path:       /languages
 */

namespace Outstand\WP\Embeds;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_EMBEDS_VERSION', '1.0.0' );
define( 'OUTSTAND_EMBEDS_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_EMBEDS_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_EMBEDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_EMBEDS_DIST_URL', OUTSTAND_EMBEDS_URL . 'build/' );
define( 'OUTSTAND_EMBEDS_DIST_PATH', OUTSTAND_EMBEDS_PATH . 'build/' );

if ( file_exists( OUTSTAND_EMBEDS_PATH . 'vendor/autoload.php' ) ) {
	require_once OUTSTAND_EMBEDS_PATH . 'vendor/autoload.php';
}

if ( class_exists( PucFactory::class ) ) {
	PucFactory::buildUpdateChecker(
		'https://github.com/pixelalbatross/outstand-embeds/',
		__FILE__,
		'outstand-embeds'
	)->setBranch( 'main' );
}

add_action(
	'plugins_loaded',
	function () {
		Plugin::get_instance()->enable();
	}
);
