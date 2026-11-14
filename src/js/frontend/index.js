/**
 * Consent-gated embeds.
 *
 * The server renders a placeholder with the embed data in data attributes, a hidden notice and a
 * link to the original content. This script shows the notice until the visitor has consented to
 * the placeholder's cookie category, then loads the embed. It removes the embed again when the
 * visitor withdraws consent. It sets up the embeds that the page adds later, and
 * `window.outstandEmbeds.init( root )` sets up a part of the page on demand.
 */

/**
 * Internal dependencies
 */
import loadFacebook from './providers/facebook';
import loadInstagram from './providers/instagram';
import './embeds.css';

const SELECTOR = '.outstand-embed[data-provider]';

const OBSERVER_DELAY = 50;

const LOADERS = {
	facebook: loadFacebook,
	instagram: loadInstagram,
};

/**
 * The children that the server rendered in each placeholder: the notice and the link.
 */
const originals = new WeakMap();

/**
 * The consent category of a placeholder. The markup is not trusted, because an author can write
 * any category into a Custom HTML block. The category comes from the list that the server printed
 * for the embeds it rendered. A placeholder that is not in the list waits for the default
 * category of the plugin settings.
 *
 * @param {HTMLElement} node     The placeholder.
 * @param {Object}      settings The consent settings.
 * @return {string} The category slug. An empty string needs no consent.
 */
function getCategory( node, settings ) {
	const key = `${ node.dataset.provider } ${ node.dataset.url }`;
	const categories = settings.categories || {};

	if ( Object.prototype.hasOwnProperty.call( categories, key ) ) {
		return categories[ key ];
	}

	return settings.category || '';
}

/**
 * Show the notice of a placeholder.
 *
 * @param {HTMLElement} node The placeholder.
 */
function showNotice( node ) {
	node.querySelector( '.outstand-embed__notice' )?.removeAttribute(
		'hidden'
	);
}

/**
 * Remove a loaded embed and restore the notice and the link.
 *
 * @param {HTMLElement} node The placeholder.
 */
function unload( node ) {
	delete node.dataset.embedActive;

	node.replaceChildren( ...( originals.get( node ) || [] ) );
	node.classList.remove( 'is-loaded' );

	showNotice( node );
}

/**
 * Set up one placeholder.
 *
 * @param {HTMLElement} node    The placeholder.
 * @param {Object}      consent The consent helper.
 */
function initEmbed( node, consent ) {
	const load = LOADERS[ node.dataset.provider ];

	if ( ! load || node.dataset.embedReady ) {
		return;
	}

	node.dataset.embedReady = '1';
	originals.set( node, Array.from( node.childNodes ) );

	const category = getCategory( node, window.outstandEmbeds.settings || {} );

	let watcher = null;

	// A placeholder that left the page stops following the consent.
	const isStale = () => {
		if ( node.isConnected ) {
			return false;
		}

		watcher?.stop();
		delete node.dataset.embedReady;

		return true;
	};

	watcher = consent.watchConsent( category, {
		onGrant: () => {
			if ( isStale() ) {
				return;
			}

			node.dataset.embedActive = '1';

			if ( false === load( node, consent.getAttributes( category ) ) ) {
				delete node.dataset.embedActive;
				showNotice( node );
			}
		},
		onRevoke: () => {
			if ( ! isStale() ) {
				unload( node );
			}
		},
	} );

	if ( ! watcher.granted ) {
		showNotice( node );
	}
}

/**
 * Set up the embeds in a part of the page.
 *
 * @param {Document|Element} root The part of the page. The whole document by default.
 */
function init( root = document ) {
	const consent = window.outstandEmbeds?.consent;

	if ( ! consent ) {
		return;
	}

	if ( root.matches?.( SELECTOR ) ) {
		initEmbed( root, consent );
	}

	root.querySelectorAll( SELECTOR ).forEach( ( node ) =>
		initEmbed( node, consent )
	);
}

/**
 * Set up the embeds that the page adds after it has loaded.
 */
function observe() {
	const pending = new Set();
	let timer = null;

	const flush = () => {
		timer = null;

		pending.forEach( ( node ) => init( node ) );
		pending.clear();
	};

	new window.MutationObserver( ( mutations ) => {
		mutations.forEach( ( mutation ) =>
			mutation.addedNodes.forEach( ( node ) => {
				if (
					1 === node.nodeType &&
					( node.matches( SELECTOR ) ||
						node.querySelector( SELECTOR ) )
				) {
					pending.add( node );
				}
			} )
		);

		if ( pending.size && null === timer ) {
			timer = setTimeout( flush, OBSERVER_DELAY );
		}
	} ).observe( document.body, { childList: true, subtree: true } );
}

/**
 * Set up the embeds on the page, and watch for embeds that the page adds later.
 */
function start() {
	init();
	observe();
}

window.outstandEmbeds = window.outstandEmbeds || {};
window.outstandEmbeds.init = init;

if ( 'loading' !== document.readyState ) {
	start();
} else {
	document.addEventListener( 'DOMContentLoaded', start );
}
