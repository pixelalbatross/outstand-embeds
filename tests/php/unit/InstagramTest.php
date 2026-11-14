<?php
/**
 * Instagram embed tests.
 *
 * @package Outstand\WP\Embeds\Tests\Unit
 */

namespace Outstand\WP\Embeds\Tests\Unit;

use Outstand\WP\Embeds\Providers\Instagram;

/**
 * Instagram post and reel URLs render consent-gated markup; other URLs are left alone.
 *
 * @covers \Outstand\WP\Embeds\Providers\Instagram
 */
class InstagramTest extends \WP_UnitTestCase {

	/**
	 * The provider under test.
	 *
	 * @var Instagram
	 */
	private Instagram $instagram;

	/**
	 * Creates the provider.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->instagram = new Instagram();
	}

	/**
	 * Post and reel URL shapes, with their type and canonical permalink.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function media_urls(): array {
		return [
			'post'                => [ 'https://www.instagram.com/p/AbC-12_xyZ/', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, no slash'      => [ 'https://www.instagram.com/p/AbC-12_xyZ', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, query'         => [ 'https://www.instagram.com/p/AbC-12_xyZ/?utm_source=ig_web_copy_link&igsh=abc', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, no www'        => [ 'https://instagram.com/p/AbC-12_xyZ/', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, username'      => [ 'https://www.instagram.com/example.user/p/AbC-12_xyZ/', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, instagr.am'    => [ 'https://instagr.am/p/AbC-12_xyZ/', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'reel'                => [ 'https://www.instagram.com/reel/AbC-12_xyZ/', Instagram::TYPE_REEL, 'https://www.instagram.com/reel/AbC-12_xyZ/' ],
			'reels'               => [ 'https://www.instagram.com/reels/AbC-12_xyZ/', Instagram::TYPE_REEL, 'https://www.instagram.com/reel/AbC-12_xyZ/' ],
			'reel, username'      => [ 'https://www.instagram.com/example_user/reel/AbC-12_xyZ/', Instagram::TYPE_REEL, 'https://www.instagram.com/reel/AbC-12_xyZ/' ],
			'reel, mobile host'   => [ 'https://m.instagram.com/reel/AbC-12_xyZ/', Instagram::TYPE_REEL, 'https://www.instagram.com/reel/AbC-12_xyZ/' ],
			'post, fragment'      => [ 'https://www.instagram.com/p/AbC-12_xyZ/#comments', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
			'post, uppercase www' => [ 'https://WWW.INSTAGRAM.COM/p/AbC-12_xyZ/', Instagram::TYPE_POST, 'https://www.instagram.com/p/AbC-12_xyZ/' ],
		];
	}

	/**
	 * URLs that must not embed.
	 *
	 * @return array<string, array{string}>
	 */
	public function rejected_urls(): array {
		return [
			'http scheme'    => [ 'http://www.instagram.com/p/AbC-12_xyZ/' ],
			'other host'     => [ 'https://www.instagram.com.example.com/p/AbC-12_xyZ/' ],
			'lookalike host' => [ 'https://evilinstagram.com/p/AbC-12_xyZ/' ],
			'profile'        => [ 'https://www.instagram.com/example.user/' ],
			'stories'        => [ 'https://www.instagram.com/stories/example.user/1234567890/' ],
			'explore'        => [ 'https://www.instagram.com/explore/tags/example/' ],
			'post, no code'  => [ 'https://www.instagram.com/p/' ],
			'embed url'      => [ 'https://www.instagram.com/p/AbC-12_xyZ/embed/' ],
			'tv'             => [ 'https://www.instagram.com/tv/AbC-12_xyZ/' ],
			'share link'     => [ 'https://www.instagram.com/share/AbC-12_xyZ/' ],
			'javascript'     => [ 'javascript:alert(1)//www.instagram.com/p/AbC/' ],
			'port'           => [ 'https://www.instagram.com:443/p/AbC-12_xyZ/' ],
			'other port'     => [ 'https://www.instagram.com:8443/p/AbC-12_xyZ/' ],
			'user'           => [ 'https://user@www.instagram.com/p/AbC-12_xyZ/' ],
			'user, password' => [ 'https://user:secret@www.instagram.com/p/AbC-12_xyZ/' ],
		];
	}

	/**
	 * Post and reel URLs map to their type and canonical permalink.
	 *
	 * @dataProvider media_urls
	 *
	 * @param  string $url       The URL.
	 * @param  string $type      The expected type.
	 * @param  string $permalink The expected permalink.
	 * @return void
	 */
	public function test_media_urls_embed( string $url, string $type, string $permalink ): void {

		$this->assertSame( $type, $this->instagram->get_type( $url ) );

		$markup = $this->instagram->get_markup( $url, $type, 'advertisement' );

		$this->assertStringContainsString( 'data-url="' . $permalink . '"', $markup );
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
		$this->assertSame( '', $this->instagram->get_type( $url ) );
	}

	/**
	 * The markup carries no iframe URL and names the provider.
	 *
	 * @return void
	 */
	public function test_markup(): void {

		$markup = $this->instagram->get_markup( 'https://www.instagram.com/reel/AbC-12_xyZ/', Instagram::TYPE_REEL, 'advertisement' );

		$this->assertStringStartsWith( '<div class="outstand-embed is-provider-instagram is-type-reel" data-provider="instagram" data-type="reel"', $markup );
		$this->assertStringNotContainsString( 'data-src=', $markup );
		$this->assertStringContainsString( 'data-title="Instagram reel"', $markup );
		$this->assertStringContainsString( 'This Instagram content loads after you accept cookies.', $markup );
		$this->assertStringContainsString( '>View on Instagram</a>', $markup );
	}

	/**
	 * Embedding a post URL in content runs the handler.
	 *
	 * @return void
	 */
	public function test_content_embed(): void {

		global $wp_embed;

		$html = $wp_embed->shortcode( [], 'https://www.instagram.com/p/AbC-12_xyZ/' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'class="outstand-embed is-provider-instagram is-type-post"', $html );
		$this->assertStringContainsString( 'data-consent-category="advertisement"', $html );
	}

	/**
	 * An unrecognised Instagram URL is left to the other handlers.
	 *
	 * @return void
	 */
	public function test_handler_skips_unrecognised_urls(): void {

		global $wp_embed;

		$this->assertFalse( $wp_embed->get_embed_handler_html( [], 'https://www.instagram.com/example.user/' ) );
	}
}
