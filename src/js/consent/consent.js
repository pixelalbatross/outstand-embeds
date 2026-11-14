/**
 * Cookie consent for content that loads from third parties.
 *
 * A consent source is a consent tool on the page. Each source is an object with:
 *
 * - `name`: the source slug.
 * - `isActive( win, settings )`: whether the tool is active on the page.
 * - `hasConsent( win, category )`: whether the visitor accepted a category.
 * - `subscribe( win, callback )`: calls `callback` when the consent changes; returns an unsubscribe function.
 * - `getAttributes( category )`: attributes that register an element with the tool under a category.
 *
 * The first active source decides. With no active source, content loads at once, unless the
 * `requireConsent` setting is on.
 */

/**
 * Internal dependencies
 */
import cookieyes from './sources/cookieyes';

export const SOURCES = [ cookieyes ];

/**
 * Create the consent helper.
 *
 * @param {Object}   options          The options.
 * @param {Window}   options.win      The window.
 * @param {Object}   options.settings The settings: `requireConsent` (boolean) and `sources` (slugs of the tools that the server reports as active).
 * @param {Object[]} options.sources  The consent sources, in order of priority.
 * @return {Object} The consent helper.
 */
export function createConsent( {
	win = window,
	settings = {},
	sources = SOURCES,
} = {} ) {
	/**
	 * The active consent source.
	 *
	 * @return {?Object} The source, or null when no consent tool is active.
	 */
	function getActiveSource() {
		return (
			sources.find( ( source ) => source.isActive( win, settings ) ) ||
			null
		);
	}

	/**
	 * Whether content in a cookie category may load. An empty category needs no consent.
	 *
	 * @param {string} category The category slug.
	 * @return {boolean} True when the content may load.
	 */
	function hasConsent( category ) {
		if ( ! category ) {
			return true;
		}

		const source = getActiveSource();

		if ( ! source ) {
			return ! settings.requireConsent;
		}

		return source.hasConsent( win, category );
	}

	/**
	 * Run a callback once the content in a cookie category may load: at once when it may load
	 * now, else when the visitor accepts the category. With `requireConsent` on and no consent
	 * tool active, the callback never runs.
	 *
	 * @param {string}   category The category slug.
	 * @param {Function} callback Called once, with no arguments.
	 * @return {boolean} True when the callback ran at once.
	 */
	function whenConsent( category, callback ) {
		if ( hasConsent( category ) ) {
			callback();
			return true;
		}

		const source = getActiveSource();

		if ( ! source ) {
			return false;
		}

		const unsubscribe = source.subscribe( win, () => {
			if ( ! hasConsent( category ) ) {
				return;
			}

			unsubscribe();
			callback();
		} );

		return false;
	}

	/**
	 * Follow the consent of a cookie category: call `onGrant` when the content may load, and
	 * `onRevoke` when the visitor withdraws consent later. It keeps listening, so the callbacks
	 * can run again, until `stop()` is called.
	 *
	 * @param {string}   category           The category slug.
	 * @param {Object}   callbacks          The callbacks.
	 * @param {Function} callbacks.onGrant  Called when the content may load, at once when it may now.
	 * @param {Function} callbacks.onRevoke Called when the content may no longer load.
	 * @return {{granted: boolean, stop: Function}} Whether the content may load now, and a function that stops the listener.
	 */
	function watchConsent( category, { onGrant, onRevoke } ) {
		let granted = false;
		let unsubscribe = null;

		const update = () => {
			const next = hasConsent( category );

			if ( next === granted ) {
				return;
			}

			granted = next;

			if ( granted ) {
				onGrant?.();
			} else {
				onRevoke?.();
			}
		};

		update();

		unsubscribe = getActiveSource()?.subscribe( win, update ) || null;

		return {
			granted,
			stop: () => unsubscribe?.(),
		};
	}

	/**
	 * The slug of the active consent source.
	 *
	 * @return {?string} The slug, or null when no consent tool is active.
	 */
	function getSource() {
		return getActiveSource()?.name || null;
	}

	/**
	 * The attributes that register an iframe or a script with the active consent tool under a
	 * category. Set them before the element's `src`.
	 *
	 * @param {string} category The category slug.
	 * @return {Object} The attribute names and values; empty when no tool is active.
	 */
	function getAttributes( category ) {
		const source = getActiveSource();

		if ( ! source || ! category ) {
			return {};
		}

		return source.getAttributes( category );
	}

	return { hasConsent, whenConsent, watchConsent, getSource, getAttributes };
}
