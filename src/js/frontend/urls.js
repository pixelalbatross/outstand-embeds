/**
 * Validation of the URLs that the server prints into the placeholder. Markup can come from an
 * editor field that any author controls, so the front end loads only URLs that pass these checks
 * and builds every embed URL itself.
 */

const FACEBOOK_HOST = 'www.facebook.com';

const INSTAGRAM_HOST = 'www.instagram.com';

const INSTAGRAM_PATH = /^\/(?:p|reel)\/[A-Za-z0-9_-]+\/$/;

/**
 * Parse an HTTPS URL on one host, without a port or credentials.
 *
 * @param {string} value The URL.
 * @param {string} host  The allowed host.
 * @return {?URL} The URL, or null when it is not an HTTPS URL on the host.
 */
function parseUrl( value, host ) {
	let url;

	try {
		url = new URL( value );
	} catch ( error ) {
		return null;
	}

	if (
		'https:' !== url.protocol ||
		host !== url.hostname ||
		url.port ||
		url.username ||
		url.password
	) {
		return null;
	}

	return url;
}

/**
 * Validate the URL of a Facebook post or video.
 *
 * @param {?string} value The URL from the placeholder.
 * @return {?string} The URL, or null when it is not a `www.facebook.com` HTTPS URL.
 */
export function getFacebookUrl( value ) {
	const url = parseUrl( value || '', FACEBOOK_HOST );

	return url ? url.href : null;
}

/**
 * Validate the permalink of an Instagram post or reel.
 *
 * @param {?string} value The URL from the placeholder.
 * @return {?string} The permalink without a query, or null when it is not `/p/<code>/` or `/reel/<code>/` on `www.instagram.com`.
 */
export function getInstagramUrl( value ) {
	const url = parseUrl( value || '', INSTAGRAM_HOST );

	if ( ! url || ! INSTAGRAM_PATH.test( url.pathname ) ) {
		return null;
	}

	return `https://${ INSTAGRAM_HOST }${ url.pathname }`;
}

/**
 * The URL of Facebook's video plugin iframe for a validated Facebook video URL.
 *
 * @param {string} href The validated video URL.
 * @return {string} The iframe URL.
 */
export function getFacebookVideoSrc( href ) {
	const args = new URLSearchParams( {
		href,
		show_text: 'false',
		width: '560',
	} );

	return `https://${ FACEBOOK_HOST }/plugins/video.php?${ args.toString() }`;
}
