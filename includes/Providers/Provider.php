<?php

namespace Outstand\WP\Embeds\Providers;

defined( 'ABSPATH' ) || exit;

use Outstand\WP\Embeds\Assets;
use Outstand\WP\Embeds\BaseModule;
use Outstand\WP\Embeds\Settings;

/**
 * Base class of an embed provider. A provider registers an embed handler for its URLs. The handler
 * prints a placeholder with the embed data in data attributes, a notice that the front-end script
 * shows while consent is missing, and a link to the original URL. The front-end script loads the
 * embed after consent. Unrecognised URLs stay a plain link.
 */
abstract class Provider extends BaseModule {

	/**
	 * Class of the placeholder. The front-end script and the stylesheet use it.
	 *
	 * @var string
	 */
	public const CLASS_NAME = 'outstand-embed';

	/**
	 * The provider slug, for example "facebook".
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * The provider name, for example "Facebook".
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * The provider home page URL.
	 *
	 * @return string
	 */
	abstract public function get_provider_url(): string;

	/**
	 * The embed type of a URL, or an empty string when the URL does not embed.
	 *
	 * @param  string $url The URL.
	 * @return string
	 */
	abstract public function get_type( string $url ): string;

	/**
	 * The oEmbed type of an embed type: "video" or "rich".
	 *
	 * @param  string $type The embed type.
	 * @return string
	 */
	abstract public function get_oembed_type( string $type ): string;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_handler' ] );
	}

	/**
	 * Registers the embed handler.
	 *
	 * @return void
	 */
	public function register_handler(): void {
		wp_embed_register_handler( 'outstand_embeds_' . $this->get_slug(), $this->get_handler_regex(), [ $this, 'render' ] );
	}

	/**
	 * Embed handler callback.
	 *
	 * @param  array  $matches The regex matches.
	 * @param  array  $attr    The embed attributes.
	 * @param  string $url     The URL to embed.
	 * @param  array  $rawattr The embed attributes as given.
	 * @return string|false The embed markup, or false to leave the URL to the next handler.
	 */
	public function render( $matches, $attr, $url, $rawattr ) {

		$url  = (string) $url;
		$type = $this->get_type( $url );

		if ( '' === $type ) {
			return false;
		}

		Assets::enqueue();

		$category = Settings::get_consent_category( $this->get_slug(), $url );

		Assets::add_embed_category( $this->get_slug(), $this->get_embed_url( $url, $type ), $category );

		return $this->get_markup( $url, $type, $category );
	}

	/**
	 * The placeholder markup of an embed.
	 *
	 * @param  string $url      The URL to embed.
	 * @param  string $type     The embed type.
	 * @param  string $category The consent category slug. An empty string loads the embed without consent.
	 * @return string
	 */
	public function get_markup( string $url, string $type, string $category ): string {

		$name       = $this->get_name();
		$attributes = [
			'class'                 => sprintf( '%1$s is-provider-%2$s is-type-%3$s', self::CLASS_NAME, $this->get_slug(), $type ),
			'data-provider'         => $this->get_slug(),
			'data-type'             => $type,
			'data-url'              => $this->get_embed_url( $url, $type ),
			'data-consent-category' => $category,
			'data-title'            => $this->get_title( $type ),
		];

		$html = '';

		foreach ( $attributes as $attribute => $value ) {

			if ( '' === $value ) {
				continue;
			}

			$html .= sprintf( ' %1$s="%2$s"', $attribute, 'data-url' === $attribute ? esc_url( $value ) : esc_attr( $value ) );
		}

		return sprintf(
			'<div%1$s><p class="%2$s__notice" hidden>%3$s</p><p class="%2$s__link"><a href="%4$s">%5$s</a></p></div>',
			$html,
			esc_attr( self::CLASS_NAME ),
			esc_html(
				sprintf(
					/* translators: %s: The provider name, for example Facebook. */
					__( 'This %s content loads after you accept cookies.', 'outstand-embeds' ),
					$name
				)
			),
			esc_url( $url ),
			esc_html(
				sprintf(
					/* translators: %s: The provider name, for example Facebook. */
					__( 'View on %s', 'outstand-embeds' ),
					$name
				)
			)
		);
	}

	/**
	 * The canonical URL of the embedded content. The front-end script validates it and builds the
	 * embed from it.
	 *
	 * @param  string $url  The URL to embed.
	 * @param  string $type The embed type.
	 * @return string
	 */
	abstract protected function get_embed_url( string $url, string $type ): string;

	/**
	 * The accessible title of the embed.
	 *
	 * @param  string $type The embed type.
	 * @return string
	 */
	abstract protected function get_title( string $type ): string;

	/**
	 * The regex of the embed handler. get_type() decides which URLs embed.
	 *
	 * @return string
	 */
	abstract protected function get_handler_regex(): string;

	/**
	 * The path and parsed query of an HTTPS URL on one of the given hosts, without a port or
	 * credentials.
	 *
	 * @param  string   $url   The URL.
	 * @param  string[] $hosts The allowed hosts.
	 * @return array{path: string, query: array}|null Null when the URL is not an HTTPS URL on one of the hosts, or has a port or credentials.
	 */
	protected function parse_url( string $url, array $hosts ): ?array {

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return null;
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );

		if ( 'https' !== $scheme || ! in_array( $host, $hosts, true ) ) {
			return null;
		}

		if ( isset( $parts['port'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return null;
		}

		$query = [];

		parse_str( (string) ( $parts['query'] ?? '' ), $query );

		return [
			'path'  => (string) ( $parts['path'] ?? '/' ),
			'query' => $query,
		];
	}
}
