/**
 * Create a placeholder in the document.
 *
 * @param {Object} data The data attributes.
 * @return {HTMLElement} The placeholder.
 */
function createNode( data ) {
	const node = document.createElement( 'div' );

	node.className = 'outstand-embed';
	node.dataset.embedActive = '1';
	Object.assign( node.dataset, data );
	node.innerHTML =
		'<p class="outstand-embed__notice" hidden>Notice</p><p class="outstand-embed__link"><a href="https://example.com">Link</a></p>';

	document.body.append( node );

	return node;
}

/**
 * Wait for the promise callbacks to run.
 */
async function flush() {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

describe( 'loadFacebook', () => {
	let loadFacebook;

	beforeEach( () => {
		jest.resetModules();
		jest.useRealTimers();
		document.body.innerHTML = '';
		delete window.FB;
		delete window.fbAsyncInit;
		loadFacebook = require( './facebook' ).default;
	} );

	describe( 'URL validation', () => {
		it.each( [
			[ 'javascript:alert(document.domain)' ],
			[ 'data:text/html,<script>alert(1)</script>' ],
			[ 'http://www.facebook.com/page/videos/123' ],
			[ 'https://www.facebook.com.example.com/page/videos/123' ],
			[ 'https://www.facebook.com@example.com/page/videos/123' ],
			[ 'https://www.facebook.com:8443/page/videos/123' ],
			[ '' ],
		] )( 'does not load a video from %s', ( url ) => {
			const node = createNode( {
				provider: 'facebook',
				type: 'video',
				url,
				src: 'javascript:alert(1)',
			} );

			expect( loadFacebook( node, {} ) ).toBe( false );
			expect( node.querySelector( 'iframe' ) ).toBeNull();
			expect(
				node.querySelector( '.outstand-embed__link' )
			).not.toBeNull();
		} );

		it( 'does not load a post from an invalid URL', () => {
			const node = createNode( {
				provider: 'facebook',
				type: 'post',
				url: 'javascript:alert(1)',
			} );

			expect( loadFacebook( node, {} ) ).toBe( false );
			expect( document.getElementById( 'facebook-jssdk' ) ).toBeNull();
		} );
	} );

	describe( 'video', () => {
		it( 'builds the iframe URL itself and ignores data-src', () => {
			const node = createNode( {
				provider: 'facebook',
				type: 'video',
				url: 'https://www.facebook.com/page/videos/123/',
				src: 'javascript:alert(document.domain)',
				title: 'Facebook video',
			} );

			expect(
				loadFacebook( node, { 'data-cookieyes': 'cookieyes-a' } )
			).toBe( true );

			const iframe = node.querySelector( 'iframe' );
			const src = new URL( iframe.src );

			expect( src.origin + src.pathname ).toBe(
				'https://www.facebook.com/plugins/video.php'
			);
			expect( src.searchParams.get( 'href' ) ).toBe(
				'https://www.facebook.com/page/videos/123/'
			);
			expect( iframe.getAttribute( 'data-cookieyes' ) ).toBe(
				'cookieyes-a'
			);
			expect( iframe.title ).toBe( 'Facebook video' );
			expect( node.classList.contains( 'is-loaded' ) ).toBe( true );
		} );
	} );

	describe( 'post and SDK', () => {
		const url = 'https://www.facebook.com/page/posts/123';

		it( 'loads the SDK once for several posts', async () => {
			const first = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );
			const second = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );
			const parse = jest.fn();

			loadFacebook( first, {} );
			loadFacebook( second, {} );

			expect(
				document.querySelectorAll( 'script#facebook-jssdk' )
			).toHaveLength( 1 );

			window.FB = { init: jest.fn(), XFBML: { parse } };
			window.fbAsyncInit();
			await flush();

			expect( window.FB.init ).toHaveBeenCalledTimes( 1 );
			expect( parse ).toHaveBeenCalledTimes( 2 );
			expect( first.querySelector( '.fb-post' ).dataset.href ).toBe(
				url
			);
			expect( second.querySelector( '.fb-post' ) ).not.toBeNull();
		} );

		it( 'waits for an SDK script that is already on the page', async () => {
			const script = document.createElement( 'script' );
			const previous = jest.fn();
			const parse = jest.fn();

			script.id = 'facebook-jssdk';
			document.body.append( script );
			window.fbAsyncInit = previous;

			const node = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );

			loadFacebook( node, {} );

			expect(
				document.querySelectorAll( 'script#facebook-jssdk' )
			).toHaveLength( 1 );
			expect( window.fbAsyncInit ).not.toBe( previous );

			window.FB = { init: jest.fn(), XFBML: { parse } };
			window.fbAsyncInit();
			await flush();

			expect( previous ).toHaveBeenCalledTimes( 1 );
			expect( window.FB.init ).not.toHaveBeenCalled();
			expect( parse ).toHaveBeenCalledTimes( 1 );
			expect( node.querySelector( '.fb-post' ) ).not.toBeNull();
		} );

		it( 'resolves from an existing SDK that has no init callback', async () => {
			jest.useFakeTimers();

			const script = document.createElement( 'script' );
			const parse = jest.fn();

			script.id = 'facebook-jssdk';
			document.body.append( script );

			const node = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );

			loadFacebook( node, {} );

			window.FB = { XFBML: { parse } };
			jest.advanceTimersByTime( 200 );
			await Promise.resolve();
			await Promise.resolve();

			expect( parse ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'gives up on an existing SDK that never loads', async () => {
			jest.useFakeTimers();

			const script = document.createElement( 'script' );

			script.id = 'facebook-jssdk';
			document.body.append( script );

			const node = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );

			loadFacebook( node, {} );
			jest.advanceTimersByTime( 11000 );
			await Promise.resolve();
			await Promise.resolve();

			expect( node.querySelector( '.fb-post' ) ).toBeNull();
			expect(
				node.querySelector( '.outstand-embed__link' )
			).not.toBeNull();
		} );

		it( 'does not render a post that lost consent while the SDK loaded', async () => {
			const node = createNode( {
				provider: 'facebook',
				type: 'post',
				url,
			} );

			loadFacebook( node, {} );
			delete node.dataset.embedActive;

			window.FB = { init: jest.fn(), XFBML: { parse: jest.fn() } };
			window.fbAsyncInit();
			await flush();

			expect( node.querySelector( '.fb-post' ) ).toBeNull();
		} );
	} );
} );
