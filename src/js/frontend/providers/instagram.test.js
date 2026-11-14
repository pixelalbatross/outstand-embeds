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
		'<p class="outstand-embed__link"><a href="https://example.com">Link</a></p>';

	document.body.append( node );

	return node;
}

/**
 * Wait for the promise callbacks to run.
 */
async function flush() {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

describe( 'loadInstagram', () => {
	const url = 'https://www.instagram.com/p/abc123/';
	let loadInstagram;

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '';
		document.head.innerHTML = '';
		delete window.instgrm;
		loadInstagram = require( './instagram' ).default;
	} );

	it.each( [
		[ 'javascript:alert(1)' ],
		[ 'http://www.instagram.com/p/abc123/' ],
		[ 'https://www.instagram.com.example.com/p/abc123/' ],
		[ 'https://www.instagram.com/username/' ],
		[ 'https://www.instagram.com/stories/abc123/' ],
		[ '' ],
	] )( 'does not load from %s', ( value ) => {
		const node = createNode( { provider: 'instagram', url: value } );

		expect( loadInstagram( node, {} ) ).toBe( false );
		expect( document.querySelector( 'script' ) ).toBeNull();
		expect( node.querySelector( 'blockquote' ) ).toBeNull();
	} );

	it( 'loads embed.js once for several embeds and processes each', async () => {
		const first = createNode( { provider: 'instagram', url } );
		const second = createNode( { provider: 'instagram', url } );

		loadInstagram( first, { 'data-cookieyes': 'cookieyes-a' } );
		loadInstagram( second, {} );

		const scripts = document.querySelectorAll( 'script' );

		expect( scripts ).toHaveLength( 1 );
		expect( scripts[ 0 ].src ).toBe( 'https://www.instagram.com/embed.js' );
		expect( scripts[ 0 ].getAttribute( 'data-cookieyes' ) ).toBe(
			'cookieyes-a'
		);

		window.instgrm = { Embeds: { process: jest.fn() } };
		scripts[ 0 ].dispatchEvent( new Event( 'load' ) );
		await flush();

		expect( window.instgrm.Embeds.process ).toHaveBeenCalledTimes( 2 );
		expect(
			first
				.querySelector( 'blockquote' )
				.getAttribute( 'data-instgrm-permalink' )
		).toBe( url );
		expect( second.classList.contains( 'is-loaded' ) ).toBe( true );
	} );

	it( 'reuses an embed.js script that is already loading', async () => {
		const script = document.createElement( 'script' );

		script.src = 'https://www.instagram.com/embed.js';
		document.head.append( script );

		const node = createNode( { provider: 'instagram', url } );

		loadInstagram( node, {} );

		expect( document.querySelectorAll( 'script' ) ).toHaveLength( 1 );

		window.instgrm = { Embeds: { process: jest.fn() } };
		script.dispatchEvent( new Event( 'load' ) );
		await flush();

		expect( window.instgrm.Embeds.process ).toHaveBeenCalledTimes( 1 );
		expect( node.querySelector( 'blockquote' ) ).not.toBeNull();
	} );

	it( 'keeps the link when embed.js fails to load', async () => {
		const node = createNode( { provider: 'instagram', url } );

		loadInstagram( node, {} );
		document
			.querySelector( 'script' )
			.dispatchEvent( new Event( 'error' ) );
		await flush();

		expect( node.querySelector( 'blockquote' ) ).toBeNull();
		expect( node.querySelector( '.outstand-embed__link' ) ).not.toBeNull();
	} );

	it( 'does not render an embed that lost consent while the script loaded', async () => {
		const node = createNode( { provider: 'instagram', url } );

		loadInstagram( node, {} );
		delete node.dataset.embedActive;

		window.instgrm = { Embeds: { process: jest.fn() } };
		document.querySelector( 'script' ).dispatchEvent( new Event( 'load' ) );
		await flush();

		expect( node.querySelector( 'blockquote' ) ).toBeNull();
	} );
} );
