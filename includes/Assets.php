<?php

namespace Outstand\WP\Embeds;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the consent helper, the front-end embed script and the embed stylesheet. The consent
 * helper is a public handle that other scripts can depend on. The embed script and stylesheet are
 * enqueued only when a page renders an embed.
 */
class Assets extends BaseModule {
	use GetAssetInfo;

	/**
	 * Handle of the consent helper script.
	 *
	 * @var string
	 */
	public const CONSENT_HANDLE = 'outstand-embeds-consent';

	/**
	 * Handle of the front-end embed script.
	 *
	 * @var string
	 */
	public const FRONTEND_HANDLE = 'outstand-embeds-frontend';

	/**
	 * Handle of the embed stylesheet.
	 *
	 * @var string
	 */
	public const STYLE_HANDLE = 'outstand-embeds';

	/**
	 * Consent source of the CookieYes plugin.
	 *
	 * @var string
	 */
	public const SOURCE_COOKIEYES = 'cookieyes';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {

		$this->setup_asset_vars(
			dist_path: OUTSTAND_EMBEDS_DIST_PATH,
			fallback_version: OUTSTAND_EMBEDS_VERSION
		);

		add_action( 'init', [ $this, 'register_assets' ] );
	}

	/**
	 * Registers the scripts and the stylesheet, and passes the consent settings to the helper.
	 *
	 * @return void
	 */
	public function register_assets(): void {

		wp_register_script(
			self::CONSENT_HANDLE,
			OUTSTAND_EMBEDS_DIST_URL . 'js/consent.js',
			$this->get_asset_info( 'consent', 'dependencies' ),
			$this->get_asset_info( 'consent', 'version' ),
			[ 'in_footer' => true ]
		);

		wp_add_inline_script(
			self::CONSENT_HANDLE,
			sprintf(
				'window.outstandEmbeds = Object.assign( window.outstandEmbeds || {}, { settings: %s } );',
				wp_json_encode( self::get_consent_settings() )
			),
			'before'
		);

		wp_register_script(
			self::FRONTEND_HANDLE,
			OUTSTAND_EMBEDS_DIST_URL . 'js/frontend.js',
			array_merge( $this->get_asset_info( 'frontend', 'dependencies' ), [ self::CONSENT_HANDLE ] ),
			$this->get_asset_info( 'frontend', 'version' ),
			[ 'in_footer' => true ]
		);

		wp_register_style(
			self::STYLE_HANDLE,
			OUTSTAND_EMBEDS_DIST_URL . 'js/frontend.css',
			[],
			$this->get_asset_info( 'frontend', 'version' )
		);

		wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Enqueues the embed script and stylesheet. Embeds call it when they render.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		wp_enqueue_script( self::FRONTEND_HANDLE );
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * Records the consent category that the server chose for an embed. The front-end script takes
	 * the category of an embed from this list and ignores the category in the markup. An empty
	 * category means that the embed needs no consent.
	 *
	 * @param  string $provider The provider slug.
	 * @param  string $url      The canonical embed URL.
	 * @param  string $category The consent category slug.
	 * @return void
	 */
	public static function add_embed_category( string $provider, string $url, string $category ): void {

		wp_add_inline_script(
			self::CONSENT_HANDLE,
			sprintf(
				'window.outstandEmbeds.settings.categories[ %1$s ] = %2$s;',
				wp_json_encode( $provider . ' ' . $url, JSON_HEX_TAG | JSON_HEX_AMP ),
				wp_json_encode( $category, JSON_HEX_TAG | JSON_HEX_AMP )
			),
			'before'
		);
	}

	/**
	 * The settings the consent helper reads: whether consent is required without a consent tool,
	 * the consent tools that run on the page, the default consent category, and the category of each
	 * rendered embed.
	 *
	 * @return array{requireConsent: bool, sources: string[], category: string, categories: object}
	 */
	public static function get_consent_settings(): array {

		$sources = [];

		if ( self::is_cookieyes_running() ) {
			$sources[] = self::SOURCE_COOKIEYES;
		}

		return [
			'requireConsent' => Settings::is_consent_required(),
			'sources'        => $sources,
			'category'       => Settings::get_category(),
			'categories'     => new \stdClass(),
		];
	}

	/**
	 * Whether the CookieYes front end runs on the server side: the plugin is active, runs its
	 * current (not legacy) version, and does not disable its banner for this request. Only then
	 * does the page get `window.getCkyConsent`.
	 *
	 * @return bool
	 */
	private static function is_cookieyes_running(): bool {

		if ( ! defined( 'CLI_VERSION' ) || ! function_exists( 'cky_is_legacy' ) ) {
			return false;
		}

		$is_legacy = cky_is_legacy();

		if ( $is_legacy ) {
			return false;
		}

		if ( ! function_exists( 'cky_disable_banner' ) ) {
			return true;
		}

		$is_banner_disabled = cky_disable_banner();

		return true !== $is_banner_disabled;
	}
}
