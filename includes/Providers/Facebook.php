<?php

namespace Outstand\WP\Embeds\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Facebook posts, photos, videos and reels. A video loads as Facebook's video plugin iframe. A post
 * loads through the Facebook JS SDK, which sets the height of the post. The front-end script builds
 * both from the validated canonical URL.
 */
class Facebook extends Provider {

	/**
	 * Embed type of videos, reels and live videos.
	 *
	 * @var string
	 */
	public const TYPE_VIDEO = 'video';

	/**
	 * Embed type of posts and photos.
	 *
	 * @var string
	 */
	public const TYPE_POST = 'post';

	/**
	 * Hosts that a Facebook URL can use.
	 *
	 * @var string[]
	 */
	public const HOSTS = [ 'facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com' ];

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'Facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_url(): string {
		return 'https://www.facebook.com';
	}

	/**
	 * The embed type of a Facebook URL: a video, a post, or an empty string when the URL does not
	 * embed (other hosts, other schemes, pages, profiles, events, share links).
	 *
	 * @param  string $url The URL.
	 * @return string One of the TYPE_* constants, or an empty string.
	 */
	public function get_type( string $url ): string {

		$parts = $this->parse_url( $url, self::HOSTS );

		if ( null === $parts ) {
			return '';
		}

		if ( $this->is_video( $parts['path'], $parts['query'] ) ) {
			return self::TYPE_VIDEO;
		}

		if ( $this->is_post( $parts['path'], $parts['query'] ) ) {
			return self::TYPE_POST;
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_oembed_type( string $type ): string {
		return self::TYPE_VIDEO === $type ? 'video' : 'rich';
	}

	/**
	 * The Facebook URL on the www host, with its query and without its fragment.
	 *
	 * @param  string $url  The post or video URL.
	 * @param  string $type The embed type.
	 * @return string
	 */
	protected function get_embed_url( string $url, string $type ): string {

		$parts = wp_parse_url( $url );
		$path  = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
		$query = is_array( $parts ) ? (string) ( $parts['query'] ?? '' ) : '';
		$href  = 'https://www.facebook.com' . $path;

		if ( '' !== $query ) {
			$href .= '?' . $query;
		}

		return $href;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_title( string $type ): string {

		if ( self::TYPE_VIDEO === $type ) {
			return __( 'Facebook video', 'outstand-embeds' );
		}

		return __( 'Facebook post', 'outstand-embeds' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_handler_regex(): string {
		return '#^https://(?:(?:www|m|web)\.)?facebook\.com/\S+$#i';
	}

	/**
	 * Whether a URL path and query name a video: `/{page}/videos/{id}`, `/watch/?v={id}`,
	 * `/watch/live/?v={id}`, `/reel/{id}` or `/video.php?v={id}`.
	 *
	 * @param  string $path  The URL path.
	 * @param  array  $query The parsed query string.
	 * @return bool
	 */
	private function is_video( string $path, array $query ): bool {

		$is_page_video = preg_match( '#^/(?:[^/]+/)?videos/(?:[^/]+/)?\d+/?$#', $path );

		if ( 1 === $is_page_video ) {
			return true;
		}

		$is_reel = preg_match( '#^/reel/\d+/?$#', $path );

		if ( 1 === $is_reel ) {
			return true;
		}

		$video_id = is_string( $query['v'] ?? null ) ? $query['v'] : '';

		if ( '' === $video_id ) {
			return false;
		}

		$is_numeric_id = preg_match( '#^\d+$#', $video_id );

		if ( 1 !== $is_numeric_id ) {
			return false;
		}

		$is_video_path = preg_match( '#^/(?:watch(?:/live)?/?|video\.php)$#', $path );

		return 1 === $is_video_path;
	}

	/**
	 * Whether a URL path and query name a post: `/{page}/posts/{id}`, `/{page}/photos/…`,
	 * `/permalink.php?story_fbid={id}`, `/story.php?story_fbid={id}&id={page id}`,
	 * `/photo.php?fbid={id}`, `/photo/?fbid={id}` or `/photo?fbid={id}`.
	 *
	 * @param  string $path  The URL path.
	 * @param  array  $query The parsed query string.
	 * @return bool
	 */
	private function is_post( string $path, array $query ): bool {

		$is_post = preg_match( '#^/[^/]+/posts/[\w:.-]+/?$#', $path );

		if ( 1 === $is_post ) {
			return true;
		}

		$is_photo = preg_match( '#^/[^/]+/photos/[^/]+(?:/[^/]+)*/?$#', $path );

		if ( 1 === $is_photo ) {
			return true;
		}

		$story_id = is_string( $query['story_fbid'] ?? null ) ? $query['story_fbid'] : '';
		$owner_id = is_string( $query['id'] ?? null ) ? $query['id'] : '';

		if ( '/permalink.php' === $path && '' !== $story_id ) {
			return true;
		}

		if ( '/story.php' === $path && '' !== $story_id && '' !== $owner_id ) {
			return true;
		}

		$photo_id = is_string( $query['fbid'] ?? null ) ? $query['fbid'] : '';

		if ( '' === $photo_id ) {
			return false;
		}

		$is_photo_path = preg_match( '#^/photo(?:\.php|/)?$#', $path );

		return 1 === $is_photo_path;
	}
}
