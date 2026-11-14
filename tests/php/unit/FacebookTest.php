<?php
/**
 * Facebook embed tests.
 *
 * @package Outstand\WP\Embeds\Tests\Unit
 */

namespace Outstand\WP\Embeds\Tests\Unit;

use Outstand\WP\Embeds\Providers\Facebook;
use Outstand\WP\Embeds\Settings;

/**
 * Facebook post and video URLs render consent-gated markup; other URLs are left alone.
 *
 * @covers \Outstand\WP\Embeds\Providers\Facebook
 * @covers \Outstand\WP\Embeds\Providers\Provider
 */
class FacebookTest extends \WP_UnitTestCase {

	/**
	 * The provider under test.
	 *
	 * @var Facebook
	 */
	private Facebook $facebook;

	/**
	 * Creates the provider.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->facebook = new Facebook();
	}

	/**
	 * Video URL shapes.
	 *
	 * @return array<string, array{string}>
	 */
	public function video_urls(): array {
		return [
			'page video'                 => [ 'https://www.facebook.com/examplepage/videos/1234567890/' ],
			'page video, no host prefix' => [ 'https://facebook.com/examplepage/videos/1234567890' ],
			'titled page video'          => [ 'https://www.facebook.com/examplepage/videos/example-title/1234567890/' ],
			'watch'                      => [ 'https://www.facebook.com/watch/?v=1234567890' ],
			'watch, no slash'            => [ 'https://www.facebook.com/watch?v=1234567890' ],
			'watch live'                 => [ 'https://www.facebook.com/watch/live/?v=1234567890' ],
			'watch live, no slash'       => [ 'https://www.facebook.com/watch/live?v=1234567890&ref=watch_permalink' ],
			'reel'                       => [ 'https://www.facebook.com/reel/1234567890' ],
			'video.php'                  => [ 'https://www.facebook.com/video.php?v=1234567890' ],
			'mobile host'                => [ 'https://m.facebook.com/examplepage/videos/1234567890/' ],
		];
	}

	/**
	 * Post URL shapes.
	 *
	 * @return array<string, array{string}>
	 */
	public function post_urls(): array {
		return [
			'page post'     => [ 'https://www.facebook.com/examplepage/posts/1234567890' ],
			'pfbid post'    => [ 'https://www.facebook.com/examplepage/posts/pfbid0AbCdEf123/' ],
			'post with :0'  => [ 'https://www.facebook.com/examplepage/posts/1234567890:0' ],
			'permalink.php' => [ 'https://www.facebook.com/permalink.php?story_fbid=1234567890&id=987654321' ],
			'photo.php'     => [ 'https://www.facebook.com/photo.php?fbid=1234567890&set=a.111' ],
			'page photo'    => [ 'https://www.facebook.com/examplepage/photos/a.111/1234567890/' ],
			'photo/'        => [ 'https://www.facebook.com/photo/?fbid=1234567890&set=a.111' ],
			'photo, no /'   => [ 'https://www.facebook.com/photo?fbid=1234567890' ],
			'story.php'     => [ 'https://www.facebook.com/story.php?story_fbid=1234567890&id=987654321' ],
			'mobile story'  => [ 'https://m.facebook.com/story.php?story_fbid=1234567890&id=987654321' ],
		];
	}

	/**
	 * URLs that must not embed.
	 *
	 * @return array<string, array{string}>
	 */
	public function rejected_urls(): array {
		return [
			'http scheme'        => [ 'http://www.facebook.com/examplepage/videos/1234567890/' ],
			'other host'         => [ 'https://www.facebook.com.example.com/examplepage/videos/1234567890/' ],
			'lookalike host'     => [ 'https://evilfacebook.com/examplepage/videos/1234567890/' ],
			'page'               => [ 'https://www.facebook.com/examplepage' ],
			'event'              => [ 'https://www.facebook.com/events/1234567890/' ],
			'share link'         => [ 'https://www.facebook.com/share/v/AbCdEf123/' ],
			'link shim'          => [ 'https://l.facebook.com/l.php?u=https%3A%2F%2Fexample.com' ],
			'watch, no id'       => [ 'https://www.facebook.com/watch/' ],
			'watch, bad id'      => [ 'https://www.facebook.com/watch/?v=abc' ],
			'plugin url'         => [ 'https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fexample.com' ],
			'short link'         => [ 'https://fb.watch/AbCdEf123/' ],
			'javascript'         => [ 'javascript:alert(1)//www.facebook.com/examplepage/videos/1/' ],
			'port'               => [ 'https://www.facebook.com:443/examplepage/videos/1234567890/' ],
			'other port'         => [ 'https://www.facebook.com:8443/examplepage/videos/1234567890/' ],
			'user'               => [ 'https://user@www.facebook.com/examplepage/videos/1234567890/' ],
			'user, password'     => [ 'https://user:secret@www.facebook.com/examplepage/videos/1234567890/' ],
			'story, no page'     => [ 'https://www.facebook.com/story.php?story_fbid=1234567890' ],
			'photo, no id'       => [ 'https://www.facebook.com/photo/' ],
			'watch live, bad id' => [ 'https://www.facebook.com/watch/live/?v=abc' ],
		];
	}

	/**
	 * Video URLs map to the video type.
	 *
	 * @dataProvider video_urls
	 *
	 * @param  string $url The URL.
	 * @return void
	 */
	public function test_video_urls_are_videos( string $url ): void {
		$this->assertSame( Facebook::TYPE_VIDEO, $this->facebook->get_type( $url ) );
	}

	/**
	 * Post URLs map to the post type.
	 *
	 * @dataProvider post_urls
	 *
	 * @param  string $url The URL.
	 * @return void
	 */
	public function test_post_urls_are_posts( string $url ): void {
		$this->assertSame( Facebook::TYPE_POST, $this->facebook->get_type( $url ) );
	}

	/**
	 * Other URLs do not embed.
	 *
	 * @dataProvider rejected_urls
	 *
	 * @param  string $url The URL.
	 * @return void
	 */
	public function test_rejected_urls_do_not_embed( string $url ): void {
		$this->assertSame( '', $this->facebook->get_type( $url ) );
	}

	/**
	 * A video placeholder carries the normalised video URL and no iframe URL: the front-end script
	 * builds the iframe URL from the video URL.
	 *
	 * @return void
	 */
	public function test_video_markup_has_the_video_url(): void {

		$markup = $this->facebook->get_markup( 'https://m.facebook.com/watch/?v=1234567890#comments', Facebook::TYPE_VIDEO, 'advertisement' );

		$this->assertStringNotContainsString( 'data-src=', $markup );
		$this->assertStringContainsString( 'data-url="https://www.facebook.com/watch/?v=1234567890"', $markup );
		$this->assertStringContainsString( 'data-title="Facebook video"', $markup );
	}

	/**
	 * A post placeholder carries the normalised post URL.
	 *
	 * @return void
	 */
	public function test_post_markup_has_the_post_url(): void {

		$markup = $this->facebook->get_markup( 'https://web.facebook.com/examplepage/posts/1234567890', Facebook::TYPE_POST, 'advertisement' );

		$this->assertStringContainsString( 'data-url="https://www.facebook.com/examplepage/posts/1234567890"', $markup );
		$this->assertStringNotContainsString( 'data-src=', $markup );
	}

	/**
	 * The markup holds the embed data in data attributes, a hidden notice and a link to the
	 * original URL, with every value escaped.
	 *
	 * @return void
	 */
	public function test_markup(): void {

		$url    = 'https://www.facebook.com/permalink.php?story_fbid=1234567890&id=987654321&x="><script>';
		$markup = $this->facebook->get_markup( $url, Facebook::TYPE_POST, 'advertisement' );

		$this->assertStringStartsWith( '<div class="outstand-embed is-provider-facebook is-type-post" data-provider="facebook" data-type="post" data-url="https://www.facebook.com/permalink.php?story_fbid=', $markup );
		$this->assertStringContainsString( 'data-consent-category="advertisement"', $markup );
		$this->assertStringContainsString( 'data-title="Facebook post"', $markup );
		$this->assertStringContainsString( '<p class="outstand-embed__notice" hidden>This Facebook content loads after you accept cookies.</p>', $markup );
		$this->assertStringContainsString( '>View on Facebook</a>', $markup );
		$this->assertStringContainsString( 'href="https://www.facebook.com/permalink.php?story_fbid=1234567890&#038;id=987654321', $markup );
		$this->assertStringNotContainsString( '<script>', $markup );
		$this->assertStringNotContainsString( '"><', str_replace( [ '"><p', '"><a' ], '', $markup ) );
		$this->assertStringNotContainsString( '<iframe', $markup );
	}

	/**
	 * A category with markup is escaped in its attribute, and an empty category is left out.
	 *
	 * @return void
	 */
	public function test_markup_escapes_the_category(): void {

		$markup = $this->facebook->get_markup( 'https://www.facebook.com/reel/1234567890', Facebook::TYPE_VIDEO, 'a"b' );

		$this->assertStringContainsString( 'data-consent-category="a&quot;b"', $markup );
		$this->assertStringContainsString( 'class="outstand-embed is-provider-facebook is-type-video"', $markup );

		$markup = $this->facebook->get_markup( 'https://www.facebook.com/reel/1234567890', Facebook::TYPE_VIDEO, '' );

		$this->assertStringNotContainsString( 'data-consent-category', $markup );
	}

	/**
	 * Embedding a video URL in content runs the handler with the configured category and
	 * enqueues the embed script and stylesheet.
	 *
	 * @return void
	 */
	public function test_content_embed_uses_the_configured_category(): void {

		global $wp_embed;

		update_option( Settings::CATEGORY_OPTION, 'functional' );

		$html = $wp_embed->shortcode( [], 'https://www.facebook.com/examplepage/videos/1234567890/' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="outstand-embed is-provider-facebook is-type-video"', $html );
		$this->assertStringContainsString( 'data-consent-category="functional"', $html );
		$this->assertStringContainsString( 'href="https://www.facebook.com/examplepage/videos/1234567890/"', $html );
		$this->assertTrue( wp_script_is( 'outstand-embeds-frontend', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'outstand-embeds', 'enqueued' ) );
	}

	/**
	 * The category that the filter chose for an embed, here an empty one, is listed for the front-end
	 * script. The script ignores the category in the markup.
	 *
	 * @return void
	 */
	public function test_embed_category_is_listed_for_the_front_end(): void {

		global $wp_embed;

		add_filter( 'outstand_embeds_consent_category', '__return_empty_string' );

		$html = $wp_embed->shortcode( [], 'https://www.facebook.com/examplepage/videos/1234567890/' );

		remove_filter( 'outstand_embeds_consent_category', '__return_empty_string' );

		$inline = implode( '', (array) wp_scripts()->get_data( 'outstand-embeds-consent', 'before' ) );

		$this->assertIsString( $html );
		$this->assertStringNotContainsString( 'data-consent-category', $html );
		$this->assertStringContainsString( 'settings.categories[ "facebook https:\\/\\/www.facebook.com\\/examplepage\\/videos\\/1234567890\\/" ] = "";', $inline );
	}

	/**
	 * An unrecognised Facebook URL is left to the other handlers.
	 *
	 * @return void
	 */
	public function test_handler_skips_unrecognised_urls(): void {

		global $wp_embed;

		$html = $wp_embed->get_embed_handler_html( [], 'https://www.facebook.com/examplepage' );

		$this->assertFalse( $html );
	}
}
