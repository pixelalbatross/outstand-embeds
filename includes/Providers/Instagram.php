<?php

namespace Outstand\WP\Embeds\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Instagram posts and reels. They load through Instagram's embed.js, which makes the embed iframe
 * and sets its height from the height that the iframe reports.
 */
class Instagram extends Provider {

	/**
	 * Embed type of posts.
	 *
	 * @var string
	 */
	public const TYPE_POST = 'post';

	/**
	 * Embed type of reels.
	 *
	 * @var string
	 */
	public const TYPE_REEL = 'reel';

	/**
	 * Hosts that an Instagram URL can use.
	 *
	 * @var string[]
	 */
	public const HOSTS = [ 'instagram.com', 'www.instagram.com', 'm.instagram.com', 'instagr.am', 'www.instagr.am' ];

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'instagram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Instagram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_url(): string {
		return 'https://www.instagram.com';
	}

	/**
	 * The embed type of an Instagram URL: a post, a reel, or an empty string when the URL does not
	 * embed (other hosts, other schemes, profiles, stories, share links).
	 *
	 * @param  string $url The URL.
	 * @return string One of the TYPE_* constants, or an empty string.
	 */
	public function get_type( string $url ): string {

		$media = $this->get_media( $url );

		if ( null === $media ) {
			return '';
		}

		return $media['type'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_oembed_type( string $type ): string {
		return 'rich';
	}

	/**
	 * The canonical permalink of the post or reel: `https://www.instagram.com/p/{code}/` or
	 * `https://www.instagram.com/reel/{code}/`.
	 *
	 * @param  string $url  The post or reel URL.
	 * @param  string $type The embed type.
	 * @return string
	 */
	protected function get_embed_url( string $url, string $type ): string {

		$media = $this->get_media( $url );

		if ( null === $media ) {
			return '';
		}

		$segment = self::TYPE_REEL === $media['type'] ? 'reel' : 'p';

		return sprintf( 'https://www.instagram.com/%1$s/%2$s/', $segment, $media['code'] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_title( string $type ): string {

		if ( self::TYPE_REEL === $type ) {
			return __( 'Instagram reel', 'outstand-embeds' );
		}

		return __( 'Instagram post', 'outstand-embeds' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_handler_regex(): string {
		return '#^https://(?:(?:www|m)\.)?(?:instagram\.com|instagr\.am)/\S+$#i';
	}

	/**
	 * The type and shortcode of a post or reel URL: `/p/{code}`, `/reel/{code}`, `/reels/{code}`,
	 * with or without a leading `/{username}`.
	 *
	 * @param  string $url The URL.
	 * @return array{type: string, code: string}|null Null when the URL is not a post or reel.
	 */
	private function get_media( string $url ): ?array {

		$parts = $this->parse_url( $url, self::HOSTS );

		if ( null === $parts ) {
			return null;
		}

		$matches  = [];
		$is_media = preg_match( '#^/(?:[A-Za-z0-9._]+/)?(p|reels?)/([A-Za-z0-9_-]+)/?$#', $parts['path'], $matches );

		if ( 1 !== $is_media ) {
			return null;
		}

		return [
			'type' => 'p' === $matches[1] ? self::TYPE_POST : self::TYPE_REEL,
			'code' => $matches[2],
		];
	}
}
