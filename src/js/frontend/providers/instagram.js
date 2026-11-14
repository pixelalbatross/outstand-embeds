/**
 * Instagram embeds. A post or reel loads through Instagram's embed.js, which replaces the
 * blockquote with an iframe and sets the iframe height from the height that the iframe reports.
 */

/**
 * Internal dependencies
 */
import setAttributes from '../set-attributes';
import { getInstagramUrl } from '../urls';

const SCRIPT_SRC = 'https://www.instagram.com/embed.js';

const SCRIPT_SELECTOR = 'script[src*="instagram.com/embed.js"]';

const EMBED_VERSION = '14';

let scriptPromise = null;

/**
 * Load Instagram's embed.js once per page. A script element that is already on the page is
 * reused.
 *
 * @param {Object} attributes Consent attributes for the script.
 * @return {Promise<Object>} Resolves with the `instgrm` object.
 */
function loadScript( attributes ) {
	if ( scriptPromise ) {
		return scriptPromise;
	}

	// Another script on the page has already loaded embed.js.
	if ( window.instgrm?.Embeds ) {
		scriptPromise = Promise.resolve( window.instgrm );
		return scriptPromise;
	}

	scriptPromise = new Promise( ( resolve, reject ) => {
		let script = document.querySelector( SCRIPT_SELECTOR );

		if ( ! script ) {
			script = document.createElement( 'script' );

			setAttributes( script, attributes );

			script.async = true;
			script.src = SCRIPT_SRC;

			document.body.append( script );
		}

		script.addEventListener( 'load', () => {
			if ( window.instgrm?.Embeds ) {
				resolve( window.instgrm );
				return;
			}

			reject( new Error( 'Instagram embed.js did not load.' ) );
		} );
		script.addEventListener( 'error', reject );
	} );

	return scriptPromise;
}

/**
 * Load an Instagram embed. The placeholder keeps its notice and link until embed.js has loaded, so
 * the link stays when the script fails to load. The post is not rendered when the placeholder is
 * no longer active when the script loads.
 *
 * @param {HTMLElement} node       The placeholder.
 * @param {Object}      attributes Consent attributes for the script.
 * @return {boolean} False when the placeholder URL is not a valid Instagram permalink and nothing loads.
 */
export default function loadInstagram( node, attributes ) {
	const permalink = getInstagramUrl( node.dataset.url );

	if ( ! permalink ) {
		return false;
	}

	loadScript( attributes )
		.then( ( instgrm ) => {
			if ( ! node.dataset.embedActive ) {
				return;
			}

			const quote = document.createElement( 'blockquote' );
			const link = document.createElement( 'a' );

			quote.className = 'instagram-media';
			quote.setAttribute( 'data-instgrm-permalink', permalink );
			quote.setAttribute( 'data-instgrm-version', EMBED_VERSION );
			quote.setAttribute( 'data-instgrm-captioned', '' );

			link.href = permalink;
			link.textContent = node.dataset.title || permalink;
			quote.append( link );

			node.replaceChildren( quote );
			node.classList.add( 'is-loaded' );

			instgrm.Embeds.process();
		} )
		.catch( () => {} );

	return true;
}
