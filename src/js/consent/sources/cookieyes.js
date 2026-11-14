/**
 * The CookieYes consent source (cookie-law-info plugin).
 *
 * CookieYes defines `window.getCkyConsent()`, which returns the consent state of each cookie
 * category, and fires `cookieyes_consent_update` on the document when the visitor changes it.
 */

const EVENT = 'cookieyes_consent_update';

/**
 * Whether CookieYes is the active consent source: its consent function exists, or the server
 * reports the plugin and the page is still loading. After the page has loaded, a missing consent
 * function means that CookieYes does not run on the page.
 *
 * @param {Window} win      The window.
 * @param {Object} settings The consent settings.
 * @return {boolean} True when CookieYes is active.
 */
function isActive( win, settings ) {
	if ( 'function' === typeof win.getCkyConsent ) {
		return true;
	}

	return (
		( settings.sources || [] ).includes( 'cookieyes' ) &&
		'loading' === win.document.readyState
	);
}

/**
 * Whether the visitor has accepted a CookieYes category.
 *
 * @param {Window} win      The window.
 * @param {string} category The category slug.
 * @return {boolean} True when the visitor accepted the category.
 */
function hasConsent( win, category ) {
	if ( 'function' !== typeof win.getCkyConsent ) {
		return false;
	}

	return true === win.getCkyConsent()?.categories?.[ category ];
}

/**
 * Call a function each time the visitor changes the consent.
 *
 * @param {Window}   win      The window.
 * @param {Function} callback The function to call.
 * @return {Function} A function that removes the listener.
 */
function subscribe( win, callback ) {
	win.document.addEventListener( EVENT, callback );

	return () => win.document.removeEventListener( EVENT, callback );
}

/**
 * The attributes that register an iframe or a script with CookieYes under a category. CookieYes
 * then lets the element load when the visitor has accepted that category.
 *
 * @param {string} category The category slug.
 * @return {Object} The attribute names and values.
 */
function getAttributes( category ) {
	return { 'data-cookieyes': `cookieyes-${ category }` };
}

export default {
	name: 'cookieyes',
	isActive,
	hasConsent,
	subscribe,
	getAttributes,
};
