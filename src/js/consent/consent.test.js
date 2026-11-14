/**
 * Internal dependencies
 */
import { createConsent } from './consent';

/**
 * Install a fake CookieYes consent function on the window.
 *
 * @param {Object} categories The consent state of each category.
 */
function setCookieYesConsent( categories ) {
	window.getCkyConsent = () => ( { categories } );
}

/**
 * Fire the CookieYes consent update event.
 */
function fireConsentUpdate() {
	document.dispatchEvent( new CustomEvent( 'cookieyes_consent_update' ) );
}

describe( 'createConsent', () => {
	afterEach( () => {
		delete window.getCkyConsent;
	} );

	describe( 'without a consent tool', () => {
		it( 'loads at once when consent is not required', () => {
			const consent = createConsent( {
				settings: { requireConsent: false },
			} );
			const callback = jest.fn();

			expect( consent.getSource() ).toBeNull();
			expect( consent.hasConsent( 'advertisement' ) ).toBe( true );
			expect( consent.whenConsent( 'advertisement', callback ) ).toBe(
				true
			);
			expect( callback ).toHaveBeenCalledTimes( 1 );
			expect( consent.getAttributes( 'advertisement' ) ).toEqual( {} );
		} );

		it( 'never loads when consent is required', () => {
			const consent = createConsent( {
				settings: { requireConsent: true },
			} );
			const callback = jest.fn();

			expect( consent.hasConsent( 'advertisement' ) ).toBe( false );
			expect( consent.whenConsent( 'advertisement', callback ) ).toBe(
				false
			);

			fireConsentUpdate();

			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'loads content without a category even when consent is required', () => {
			const consent = createConsent( {
				settings: { requireConsent: true },
			} );
			const callback = jest.fn();

			expect( consent.whenConsent( '', callback ) ).toBe( true );
			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'with CookieYes', () => {
		it( 'uses CookieYes when its consent function exists', () => {
			setCookieYesConsent( { advertisement: true } );

			const consent = createConsent( {
				settings: { requireConsent: false },
			} );

			expect( consent.getSource() ).toBe( 'cookieyes' );
			expect( consent.hasConsent( 'advertisement' ) ).toBe( true );
			expect( consent.hasConsent( 'analytics' ) ).toBe( false );
			expect( consent.getAttributes( 'advertisement' ) ).toEqual( {
				'data-cookieyes': 'cookieyes-advertisement',
			} );
		} );

		it( 'ignores the require consent setting', () => {
			setCookieYesConsent( { advertisement: false } );

			const consent = createConsent( {
				settings: { requireConsent: false },
			} );

			expect( consent.hasConsent( 'advertisement' ) ).toBe( false );
		} );

		it( 'waits for CookieYes while the page loads when the server reports it', () => {
			const win = {
				document: Object.assign( document.createElement( 'div' ), {
					readyState: 'loading',
				} ),
			};
			const consent = createConsent( {
				win,
				settings: { requireConsent: false, sources: [ 'cookieyes' ] },
			} );
			const callback = jest.fn();

			expect( consent.getSource() ).toBe( 'cookieyes' );
			expect( consent.whenConsent( 'advertisement', callback ) ).toBe(
				false
			);
			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'ignores a reported CookieYes without its consent function after the page has loaded', () => {
			const consent = createConsent( {
				settings: { requireConsent: false, sources: [ 'cookieyes' ] },
			} );
			const callback = jest.fn();

			expect( consent.getSource() ).toBeNull();
			expect( consent.whenConsent( 'advertisement', callback ) ).toBe(
				true
			);
			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'lets the require consent setting decide when a reported CookieYes does not run', () => {
			const consent = createConsent( {
				settings: { requireConsent: true, sources: [ 'cookieyes' ] },
			} );

			expect( consent.hasConsent( 'advertisement' ) ).toBe( false );
		} );

		it( 'runs the callback once, after the visitor accepts the category', () => {
			setCookieYesConsent( { advertisement: false } );

			const consent = createConsent();
			const callback = jest.fn();

			expect( consent.whenConsent( 'advertisement', callback ) ).toBe(
				false
			);

			// The visitor accepts another category only.
			setCookieYesConsent( { advertisement: false, analytics: true } );
			fireConsentUpdate();

			expect( callback ).not.toHaveBeenCalled();

			setCookieYesConsent( { advertisement: true } );
			fireConsentUpdate();
			fireConsentUpdate();

			expect( callback ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not run the callback after the visitor rejects', () => {
			setCookieYesConsent( { advertisement: false } );

			const consent = createConsent();
			const callback = jest.fn();

			consent.whenConsent( 'advertisement', callback );
			fireConsentUpdate();

			expect( callback ).not.toHaveBeenCalled();
		} );

		it( 'follows grant and revoke while the page stays open', () => {
			setCookieYesConsent( { advertisement: false } );

			const consent = createConsent();
			const onGrant = jest.fn();
			const onRevoke = jest.fn();

			const watcher = consent.watchConsent( 'advertisement', {
				onGrant,
				onRevoke,
			} );

			expect( watcher.granted ).toBe( false );
			expect( onGrant ).not.toHaveBeenCalled();

			setCookieYesConsent( { advertisement: true } );
			fireConsentUpdate();
			fireConsentUpdate();

			expect( onGrant ).toHaveBeenCalledTimes( 1 );
			expect( onRevoke ).not.toHaveBeenCalled();

			setCookieYesConsent( { advertisement: false } );
			fireConsentUpdate();

			expect( onRevoke ).toHaveBeenCalledTimes( 1 );

			setCookieYesConsent( { advertisement: true } );
			fireConsentUpdate();

			expect( onGrant ).toHaveBeenCalledTimes( 2 );

			watcher.stop();
			setCookieYesConsent( { advertisement: false } );
			fireConsentUpdate();

			expect( onRevoke ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'grants at once to a returning visitor', () => {
			setCookieYesConsent( { advertisement: true } );

			const consent = createConsent();
			const onGrant = jest.fn();

			expect(
				consent.watchConsent( 'advertisement', { onGrant } ).granted
			).toBe( true );
			expect( onGrant ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
