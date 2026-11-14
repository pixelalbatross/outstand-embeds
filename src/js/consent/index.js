/**
 * The consent helper, exposed as `window.outstandEmbeds.consent`.
 *
 * Other scripts can depend on the `outstand-embeds-consent` handle and call
 * `window.outstandEmbeds.consent.hasConsent( category )` or
 * `window.outstandEmbeds.consent.whenConsent( category, callback )`.
 */

/**
 * Internal dependencies
 */
import { createConsent } from './consent';

const root = window.outstandEmbeds || {};

root.consent = createConsent( { win: window, settings: root.settings || {} } );

window.outstandEmbeds = root;
