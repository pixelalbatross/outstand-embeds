<?php

namespace Outstand\WP\Embeds;

defined( 'ABSPATH' ) || exit;

use Outstand\WP\Embeds\Providers\Provider;

/**
 * Answers the editor's oEmbed proxy request for a supported URL, so the Embed block shows a
 * preview. The proxy tries oEmbed discovery before the embed handlers, and core drops the HTML of
 * the endpoints that Facebook and Instagram pages advertise, so the Embed block would report that
 * the URL cannot be embedded.
 */
class Proxy extends BaseModule {

	/**
	 * The proxy route.
	 *
	 * @var string
	 */
	public const ROUTE = '/oembed/1.0/proxy';

	/**
	 * The embed providers.
	 *
	 * @var Provider[]
	 */
	private array $providers;

	/**
	 * Sets the embed providers.
	 *
	 * @param Provider[] $providers The embed providers.
	 */
	public function __construct( array $providers ) {
		$this->providers = $providers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'rest_dispatch_request', [ $this, 'proxy_response' ], 10, 3 );
	}

	/**
	 * Answers the proxy request for a supported URL. The preview markup has no consent category,
	 * so the embed loads in the editor at once. It links the embed stylesheet and lists the
	 * scripts that the editor loads into the preview.
	 *
	 * @param  mixed  $result  The response so far; null to let the route callback run.
	 * @param  mixed  $request The REST request.
	 * @param  string $route   The matched route.
	 * @return mixed The proxy response for a supported URL, else the given result.
	 */
	public function proxy_response( $result, $request, $route ) {

		if ( null !== $result || self::ROUTE !== $route || ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		$url = (string) $request->get_param( 'url' );

		foreach ( $this->providers as $provider ) {
			$type = $provider->get_type( $url );

			if ( '' === $type ) {
				continue;
			}

			return [
				'provider_name' => $provider->get_name(),
				'provider_url'  => $provider->get_provider_url(),
				'type'          => $provider->get_oembed_type( $type ),
				'version'       => '1.0',
				'html'          => $this->get_stylesheet_link() . $provider->get_markup( $url, $type, '' ),
				'scripts'       => $this->get_script_urls( Assets::FRONTEND_HANDLE ),
			];
		}

		return $result;
	}

	/**
	 * The link element of the embed stylesheet, or an empty string when it is not registered.
	 *
	 * @return string
	 */
	private function get_stylesheet_link(): string {

		$style = wp_styles()->registered[ Assets::STYLE_HANDLE ] ?? null;

		if ( ! $style instanceof \_WP_Dependency || ! is_string( $style->src ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- The editor preview has no style queue.
		return sprintf( '<link rel="stylesheet" href="%s" />', esc_url( $this->get_versioned_src( $style ) ) );
	}

	/**
	 * The URLs of a registered script and of its dependencies, dependencies first.
	 *
	 * @param  string   $handle The script handle.
	 * @param  string[] $urls   The URLs found so far.
	 * @return string[]
	 */
	private function get_script_urls( string $handle, array $urls = [] ): array {

		$script = wp_scripts()->registered[ $handle ] ?? null;

		if ( ! $script instanceof \_WP_Dependency ) {
			return $urls;
		}

		foreach ( $script->deps as $dependency ) {
			$urls = $this->get_script_urls( $dependency, $urls );
		}

		if ( ! is_string( $script->src ) || '' === $script->src ) {
			return $urls;
		}

		$src = $this->get_versioned_src( $script );

		if ( ! in_array( $src, $urls, true ) ) {
			$urls[] = $src;
		}

		return $urls;
	}

	/**
	 * The source URL of a registered asset, with its version.
	 *
	 * @param  \_WP_Dependency $asset The registered asset.
	 * @return string
	 */
	private function get_versioned_src( \_WP_Dependency $asset ): string {

		if ( ! is_string( $asset->ver ) || '' === $asset->ver ) {
			return (string) $asset->src;
		}

		return add_query_arg( 'ver', $asset->ver, (string) $asset->src );
	}
}
