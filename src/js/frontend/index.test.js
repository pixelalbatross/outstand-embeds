/**
 * Internal dependencies
 */
import { createConsent } from '../consent/consent';

const mockFacebook = jest.fn();
const mockInstagram = jest.fn();

jest.mock( './providers/facebook', () => ( {
	__esModule: true,
	default: ( ...args ) => mockFacebook( ...args ),
} ) );

jest.mock( './providers/instagram', () => ( {
	__esModule: true,
	default: ( ...args ) => mockInstagram( ...args ),
} ) );

/**
 * Create a placeholder element.
 *
 * @param {Object} data The data attributes.
 * @return {HTMLElement} The placeholder.
 */
function createNode( data = {} ) {
	const node = document.createElement( 'div' );

	node.className = 'outstand-embed';
	node.dataset.provider = 'facebook';
	node.dataset.url = 'https://www.facebook.com/page/posts/1';
	node.dataset.consentCategory = 'advertisement';
	Object.assign( node.dataset, data );
	node.innerHTML =
		'<p class="outstand-embed__notice" hidden>Notice</p><p class="outstand-embed__link"><a href="https://example.com">Link</a></p>';

	return node;
}

/**
 * Set the CookieYes consent state.
 *
 * @param {boolean} accepted Whether the visitor accepts the advertisement category.
 */
function setConsent( accepted ) {
	window.getCkyConsent = () => ( {
		categories: { advertisement: accepted },
	} );
	document.dispatchEvent( new CustomEvent( 'cookieyes_consent_update' ) );
}

/**
 * Load the front-end script with the given consent settings.
 *
 * @param {Object} settings The consent settings.
 */
function start( settings = {} ) {
	const all = {
		requireConsent: false,
		sources: [ 'cookieyes' ],
		category: 'advertisement',
		categories: {},
		...settings,
	};

	window.outstandEmbeds = {
		settings: all,
		consent: createConsent( { win: window, settings: all } ),
	};

	jest.isolateModules( () => {
		require( './index' );
	} );
}

describe( 'embeds', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		mockFacebook.mockReset().mockReturnValue( true );
		mockInstagram.mockReset().mockReturnValue( true );
		window.getCkyConsent = () => ( { categories: {} } );
	} );

	afterEach( () => {
		delete window.getCkyConsent;
		delete window.outstandEmbeds;
	} );

	it( 'shows the notice and loads nothing before consent', () => {
		const node = createNode();

		document.body.append( node );
		start();

		expect( mockFacebook ).not.toHaveBeenCalled();
		expect( node.querySelector( '.outstand-embed__notice' ).hidden ).toBe(
			false
		);
	} );

	it( 'sets up several embeds of both providers', () => {
		setConsent( true );
		document.body.append(
			createNode(),
			createNode(),
			createNode( {
				provider: 'instagram',
				url: 'https://www.instagram.com/p/abc/',
			} )
		);
		start();

		expect( mockFacebook ).toHaveBeenCalledTimes( 2 );
		expect( mockInstagram ).toHaveBeenCalledTimes( 1 );
		expect( mockFacebook.mock.calls[ 0 ][ 1 ] ).toEqual( {
			'data-cookieyes': 'cookieyes-advertisement',
		} );
	} );

	it( 'loads after the visitor accepts', () => {
		document.body.append( createNode() );
		start();
		setConsent( true );

		expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'a placeholder without a consent category', () => {
		it( 'waits for the default category of the settings', () => {
			const node = createNode( {
				consentCategory: '',
				src: 'javascript:alert(document.domain)',
			} );

			document.body.append( node );
			start();

			expect( mockFacebook ).not.toHaveBeenCalled();
			expect(
				node.querySelector( '.outstand-embed__notice' ).hidden
			).toBe( false );

			setConsent( true );

			expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'loads at once when the server listed the embed with an empty category', () => {
			document.body.append( createNode( { consentCategory: '' } ) );
			start( {
				categories: {
					'facebook https://www.facebook.com/page/posts/1': '',
				},
			} );

			expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'waits when the listed URL belongs to another embed', () => {
			document.body.append( createNode( { consentCategory: '' } ) );
			start( {
				categories: {
					'facebook https://www.facebook.com/page/posts/2': '',
				},
			} );

			expect( mockFacebook ).not.toHaveBeenCalled();
		} );

		it( 'ignores a category written into crafted markup', () => {
			const node = createNode( {
				type: 'video',
				url: 'https://www.facebook.com/x/videos/1/',
				consentCategory: 'necessary',
			} );

			document.body.append( node );
			start();

			expect( mockFacebook ).not.toHaveBeenCalled();
			expect(
				node.querySelector( '.outstand-embed__notice' ).hidden
			).toBe( false );
		} );

		it( 'uses the category that the server listed for the embed', () => {
			document.body.append(
				createNode( { consentCategory: 'necessary' } )
			);
			start( {
				categories: {
					'facebook https://www.facebook.com/page/posts/1':
						'marketing',
				},
			} );

			expect( mockFacebook ).not.toHaveBeenCalled();
		} );

		it( 'loads at once when no consent tool runs and consent is not required', () => {
			delete window.getCkyConsent;
			document.body.append( createNode( { consentCategory: '' } ) );
			start( { sources: [] } );

			expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	it( 'keeps the notice when the loader refuses the URL', () => {
		const node = createNode();

		setConsent( true );
		mockFacebook.mockReturnValue( false );
		document.body.append( node );
		start();

		expect( node.querySelector( '.outstand-embed__notice' ).hidden ).toBe(
			false
		);
		expect( node.dataset.embedActive ).toBeUndefined();
	} );

	it( 'removes the embed and restores the notice and link when consent is withdrawn', () => {
		const node = createNode();

		setConsent( true );
		mockFacebook.mockImplementation( ( target ) => {
			target.replaceChildren( document.createElement( 'iframe' ) );
			target.classList.add( 'is-loaded' );
			return true;
		} );
		document.body.append( node );
		start();

		expect( node.querySelector( 'iframe' ) ).not.toBeNull();

		setConsent( false );

		expect( node.querySelector( 'iframe' ) ).toBeNull();
		expect( node.classList.contains( 'is-loaded' ) ).toBe( false );
		expect( node.querySelector( '.outstand-embed__notice' ).hidden ).toBe(
			false
		);
		expect( node.querySelector( '.outstand-embed__link a' ).href ).toBe(
			'https://example.com/'
		);
		expect( node.dataset.embedActive ).toBeUndefined();
	} );

	describe( 'init( root )', () => {
		it( 'sets up the embeds in a root once', () => {
			start();
			setConsent( true );

			const wrapper = document.createElement( 'div' );

			wrapper.append( createNode(), createNode() );
			document.body.append( wrapper );

			window.outstandEmbeds.init( wrapper );
			window.outstandEmbeds.init( wrapper );

			expect( mockFacebook ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'sets up a root that is a placeholder', () => {
			start();
			setConsent( true );

			const node = createNode();

			document.body.append( node );
			window.outstandEmbeds.init( node );

			expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'embeds added after load', () => {
		it( 'sets them up', async () => {
			start();
			setConsent( true );

			const wrapper = document.createElement( 'div' );

			wrapper.append( createNode() );
			document.body.append( wrapper );
			document.body.append( document.createElement( 'p' ) );

			await new Promise( ( resolve ) => setTimeout( resolve, 120 ) );

			expect( mockFacebook ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
