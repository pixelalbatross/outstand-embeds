/**
 * Internal dependencies
 */
import { getFacebookUrl, getInstagramUrl, getFacebookVideoSrc } from './urls';

describe( 'getFacebookUrl', () => {
	it( 'accepts an HTTPS URL on www.facebook.com', () => {
		expect(
			getFacebookUrl( 'https://www.facebook.com/page/videos/123/?a=1' )
		).toBe( 'https://www.facebook.com/page/videos/123/?a=1' );
	} );

	it.each( [
		[ 'a javascript URL', 'javascript:alert(document.domain)' ],
		[ 'a data URL', 'data:text/html,<script>alert(1)</script>' ],
		[ 'an HTTP URL', 'http://www.facebook.com/page/videos/123' ],
		[ 'a protocol-relative URL', '//www.facebook.com/page/videos/123' ],
		[ 'a lookalike host', 'https://www.facebook.com.example.com/p/1' ],
		[ 'a host inside the path', 'https://example.com/www.facebook.com/' ],
		[ 'a credentials trick', 'https://www.facebook.com@example.com/p/1' ],
		[ 'a credentials URL', 'https://user:pass@www.facebook.com/p/1' ],
		[ 'a port', 'https://www.facebook.com:8443/p/1' ],
		[ 'another subdomain', 'https://m.facebook.com/p/1' ],
		[ 'a relative URL', '/page/videos/123' ],
		[ 'an empty value', '' ],
		[ 'a missing value', undefined ],
	] )( 'rejects %s', ( name, value ) => {
		expect( getFacebookUrl( value ) ).toBeNull();
	} );
} );

describe( 'getInstagramUrl', () => {
	it.each( [
		[ 'https://www.instagram.com/p/Abc_123-x/', '/p/Abc_123-x/' ],
		[ 'https://www.instagram.com/reel/Abc_123-x/', '/reel/Abc_123-x/' ],
	] )( 'accepts %s', ( value, path ) => {
		expect( getInstagramUrl( value ) ).toBe(
			`https://www.instagram.com${ path }`
		);
	} );

	it( 'drops the query', () => {
		expect(
			getInstagramUrl( 'https://www.instagram.com/p/abc/?igsh=1' )
		).toBe( 'https://www.instagram.com/p/abc/' );
	} );

	it.each( [
		[ 'a javascript URL', 'javascript:alert(1)' ],
		[ 'a data URL', 'data:text/html,x' ],
		[ 'an HTTP URL', 'http://www.instagram.com/p/abc/' ],
		[ 'a lookalike host', 'https://www.instagram.com.example.com/p/abc/' ],
		[ 'a credentials trick', 'https://www.instagram.com@example.com/p/a/' ],
		[ 'a port', 'https://www.instagram.com:444/p/abc/' ],
		[ 'a profile path', 'https://www.instagram.com/username/' ],
		[ 'a wrong path', 'https://www.instagram.com/stories/abc/' ],
		[ 'a path with a username', 'https://www.instagram.com/u/p/abc/' ],
		[ 'a path without a code', 'https://www.instagram.com/p/' ],
		[ 'a path with an extra segment', 'https://www.instagram.com/p/a/b/' ],
		[ 'another host', 'https://instagram.com/p/abc/' ],
		[ 'an empty value', '' ],
		[ 'a missing value', null ],
	] )( 'rejects %s', ( name, value ) => {
		expect( getInstagramUrl( value ) ).toBeNull();
	} );
} );

describe( 'getFacebookVideoSrc', () => {
	it( 'builds the video plugin URL from the video URL', () => {
		const src = new URL(
			getFacebookVideoSrc( 'https://www.facebook.com/watch/?v=1' )
		);

		expect( src.origin ).toBe( 'https://www.facebook.com' );
		expect( src.pathname ).toBe( '/plugins/video.php' );
		expect( src.searchParams.get( 'href' ) ).toBe(
			'https://www.facebook.com/watch/?v=1'
		);
		expect( src.searchParams.get( 'show_text' ) ).toBe( 'false' );
	} );
} );
