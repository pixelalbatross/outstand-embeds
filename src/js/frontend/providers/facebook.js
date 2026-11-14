/**
 * Facebook embeds. A video loads as an iframe in a 16:9 box. A post loads through the Facebook JS
 * SDK, which sets the height of the post iframe. Both are built from the validated post or video
 * URL.
 */

/**
 * Internal dependencies
 */
import setAttributes from '../set-attributes';
import { getFacebookUrl, getFacebookVideoSrc } from '../urls';

const SDK_VERSION = 'v27.0';

const SDK_SCRIPT_ID = 'facebook-jssdk';

const SDK_WAIT_INTERVAL = 100;

const SDK_WAIT_TIMEOUT = 10000;

let sdkPromise = null;

/**
 * The Facebook SDK locale for the page language, for example `pt_PT` for `pt-PT`. A language
 * without a region uses `en_US`.
 *
 * @return {string} The SDK locale.
 */
function getSdkLocale() {
	const locale = document.documentElement.lang.replace( '-', '_' );

	return /^[a-z]{2}_[A-Z]{2}$/.test( locale ) ? locale : 'en_US';
}

/**
 * Wait for an SDK that another script loads. It resolves when the SDK calls `fbAsyncInit`, after
 * the init function that was set before, or when `FB.XFBML` appears. It rejects after a timeout.
 *
 * @param {HTMLScriptElement} script The SDK script element on the page.
 * @return {Promise<Object>} Resolves with the `FB` object.
 */
function waitForSdk( script ) {
	return new Promise( ( resolve, reject ) => {
		const previous = window.fbAsyncInit;
		let timer = null;
		let elapsed = 0;

		const done = () => {
			if ( ! window.FB?.XFBML ) {
				return false;
			}

			clearInterval( timer );
			resolve( window.FB );

			return true;
		};

		window.fbAsyncInit = () => {
			if ( 'function' === typeof previous ) {
				previous();
			}

			done();
		};

		script.addEventListener( 'error', () => {
			clearInterval( timer );
			reject( new Error( 'The Facebook SDK did not load.' ) );
		} );

		if ( done() ) {
			return;
		}

		timer = setInterval( () => {
			elapsed += SDK_WAIT_INTERVAL;

			if ( done() ) {
				return;
			}

			if ( elapsed >= SDK_WAIT_TIMEOUT ) {
				clearInterval( timer );
				reject( new Error( 'The Facebook SDK did not load.' ) );
			}
		}, SDK_WAIT_INTERVAL );
	} );
}

/**
 * Add the SDK script. The init function that another script set stays in place and runs first.
 * Without one, the SDK is initialised here.
 *
 * @param {Object} attributes Consent attributes for the script.
 * @return {Promise<Object>} Resolves with the `FB` object.
 */
function addSdkScript( attributes ) {
	return new Promise( ( resolve, reject ) => {
		const previous = window.fbAsyncInit;

		window.fbAsyncInit = () => {
			if ( 'function' === typeof previous ) {
				previous();
			} else {
				window.FB.init( { xfbml: false, version: SDK_VERSION } );
			}

			resolve( window.FB );
		};

		if ( ! document.getElementById( 'fb-root' ) ) {
			const root = document.createElement( 'div' );
			root.id = 'fb-root';
			document.body.prepend( root );
		}

		const script = document.createElement( 'script' );

		setAttributes( script, attributes );

		script.id = SDK_SCRIPT_ID;
		script.async = true;
		script.crossOrigin = 'anonymous';
		script.src = `https://connect.facebook.net/${ getSdkLocale() }/sdk.js`;
		script.addEventListener( 'error', reject );

		document.body.append( script );
	} );
}

/**
 * Load the Facebook JS SDK once per page. An SDK that is already on the page, loaded or loading,
 * is reused.
 *
 * @param {Object} attributes Consent attributes for the script.
 * @return {Promise<Object>} Resolves with the `FB` object.
 */
function loadSdk( attributes ) {
	if ( sdkPromise ) {
		return sdkPromise;
	}

	const existing = document.getElementById( SDK_SCRIPT_ID );

	if ( window.FB?.XFBML ) {
		sdkPromise = Promise.resolve( window.FB );
	} else if ( existing ) {
		sdkPromise = waitForSdk( existing );
	} else {
		sdkPromise = addSdkScript( attributes );
	}

	return sdkPromise;
}

/**
 * Replace a placeholder's notice and link with a video iframe.
 *
 * @param {HTMLElement} node       The placeholder.
 * @param {string}      href       The validated video URL.
 * @param {Object}      attributes Consent attributes for the iframe.
 */
function loadVideo( node, href, attributes ) {
	const iframe = document.createElement( 'iframe' );

	setAttributes( iframe, attributes );

	iframe.src = getFacebookVideoSrc( href );
	iframe.title = node.dataset.title || '';
	iframe.loading = 'lazy';
	iframe.allowFullscreen = true;
	iframe.setAttribute(
		'allow',
		'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share'
	);

	node.replaceChildren( iframe );
	node.classList.add( 'is-loaded' );
}

/**
 * Render a post through the Facebook SDK. The placeholder keeps its notice and link until the SDK
 * has loaded, so the link stays when the SDK fails to load. The post is not rendered when the
 * placeholder is no longer active when the SDK loads.
 *
 * @param {HTMLElement} node       The placeholder.
 * @param {string}      href       The validated post URL.
 * @param {Object}      attributes Consent attributes for the SDK script.
 */
function loadPost( node, href, attributes ) {
	loadSdk( attributes )
		.then( ( FB ) => {
			if ( ! node.dataset.embedActive ) {
				return;
			}

			const post = document.createElement( 'div' );

			post.className = 'fb-post';
			post.dataset.href = href;
			post.dataset.showText = 'true';
			post.dataset.lazy = 'true';

			// A fluid width fits the post to the width of the box.
			post.dataset.width = 'auto';

			node.replaceChildren( post );
			node.classList.add( 'is-loaded' );

			FB.XFBML.parse( node );
		} )
		.catch( () => {} );
}

/**
 * Load a Facebook embed.
 *
 * @param {HTMLElement} node       The placeholder.
 * @param {Object}      attributes Consent attributes for the iframe or the SDK script.
 * @return {boolean} False when the placeholder URL is not a valid Facebook URL and nothing loads.
 */
export default function loadFacebook( node, attributes ) {
	const href = getFacebookUrl( node.dataset.url );

	if ( ! href ) {
		return false;
	}

	if ( 'video' === node.dataset.type ) {
		loadVideo( node, href, attributes );
	} else {
		loadPost( node, href, attributes );
	}

	return true;
}
