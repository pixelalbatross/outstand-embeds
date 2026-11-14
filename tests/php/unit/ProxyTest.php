<?php
/**
 * Editor oEmbed proxy tests.
 *
 * @package Outstand\WP\Embeds\Tests\Unit
 */

namespace Outstand\WP\Embeds\Tests\Unit;

use Outstand\WP\Embeds\Providers\Facebook;
use Outstand\WP\Embeds\Providers\Instagram;
use Outstand\WP\Embeds\Proxy;

/**
 * The editor's oEmbed proxy answers supported URLs with the placeholder markup and the scripts
 * that load the embed.
 *
 * @covers \Outstand\WP\Embeds\Proxy
 */
class ProxyTest extends \WP_UnitTestCase {

	/**
	 * Supported URLs with their provider and oEmbed type.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function supported_urls(): array {
		return [
			'facebook video' => [ 'https://www.facebook.com/examplepage/videos/1234567890/', 'Facebook', 'video' ],
			'facebook post'  => [ 'https://www.facebook.com/examplepage/posts/1234567890', 'Facebook', 'rich' ],
			'instagram post' => [ 'https://www.instagram.com/p/AbC-12_xyZ/', 'Instagram', 'rich' ],
			'instagram reel' => [ 'https://www.instagram.com/reel/AbC-12_xyZ/', 'Instagram', 'rich' ],
		];
	}

	/**
	 * The proxy answers a supported URL with the markup, without a consent category, and with
	 * the consent helper before the embed script.
	 *
	 * @dataProvider supported_urls
	 *
	 * @param  string $url      The URL.
	 * @param  string $provider The expected provider name.
	 * @param  string $type     The expected oEmbed type.
	 * @return void
	 */
	public function test_proxy_returns_the_markup( string $url, string $provider, string $type ): void {

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$request = new \WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', $url );

		$response = rest_do_request( $request );
		$data     = (array) $response->get_data();
		$html     = (string) ( $data['html'] ?? '' );
		$scripts  = array_map( fn( $src ) => wp_parse_url( $src, PHP_URL_PATH ), (array) ( $data['scripts'] ?? [] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $provider, $data['provider_name'] ?? null );
		$this->assertSame( $type, $data['type'] ?? null );
		$this->assertStringContainsString( 'class="outstand-embed is-provider-' . strtolower( $provider ), $html );
		$this->assertStringNotContainsString( 'data-consent-category', $html );
		$this->assertStringContainsString( 'build/js/frontend.css', $html );
		$this->assertCount( 2, $scripts );
		$this->assertStringEndsWith( 'build/js/consent.js', (string) $scripts[0] );
		$this->assertStringEndsWith( 'build/js/frontend.js', (string) $scripts[1] );
	}

	/**
	 * A visitor without the edit_posts capability gets no proxy response.
	 *
	 * @return void
	 */
	public function test_proxy_needs_permission(): void {

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$request = new \WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_param( 'url', 'https://www.facebook.com/examplepage/videos/1234567890/' );

		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	/**
	 * The proxy filter leaves other URLs, other routes and earlier results alone.
	 *
	 * @return void
	 */
	public function test_proxy_skips_other_requests(): void {

		$proxy   = new Proxy( [ new Facebook(), new Instagram() ] );
		$request = new \WP_REST_Request( 'GET', '/oembed/1.0/proxy' );

		$request->set_param( 'url', 'https://www.youtube.com/watch?v=abc' );
		$this->assertNull( $proxy->proxy_response( null, $request, '/oembed/1.0/proxy' ) );

		$request->set_param( 'url', 'https://www.instagram.com/example.user/' );
		$this->assertNull( $proxy->proxy_response( null, $request, '/oembed/1.0/proxy' ) );

		$request->set_param( 'url', 'https://www.facebook.com/examplepage/videos/1234567890/' );
		$this->assertNull( $proxy->proxy_response( null, $request, '/wp/v2/posts' ) );
		$this->assertSame( 'earlier', $proxy->proxy_response( 'earlier', $request, '/oembed/1.0/proxy' ) );
	}
}
