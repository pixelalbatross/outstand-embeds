# Outstand Embeds

> Embed third-party content in the Embed block, and load it only after cookie consent. Supports Facebook and Instagram.

WordPress 5.5.2 removed the Facebook and Instagram oEmbed providers from core. This plugin brings the embeds back in the Embed block, and gates them behind the visitor's cookie consent.

## Features

- Paste a Facebook or Instagram URL into an Embed block. The editor shows a live preview.
- Before consent, the page shows a notice and a link to the original content. Nothing loads from Facebook or Instagram.
- After consent, the embed loads in place. When the visitor withdraws consent, the embed goes away and the notice and the link return, without a page reload. A Facebook video loads Facebook's video player in a 16:9 box. A Facebook post loads through the Facebook JS SDK, which sets the height of the post. An Instagram post or reel loads through Instagram's `embed.js`, which sets the height of the post.
- The embed script loads only on pages with a supported embed. The Facebook SDK loads once per page, only for Facebook posts. Instagram's `embed.js` loads once per page, only for Instagram embeds.
- The consent helper is a public script handle, so a theme can gate its own third-party content (maps, players) the same way.

## Supported URLs

Facebook (`facebook.com`, `www.`, `m.`, `web.`; HTTPS only):

- Videos: `/<page>/videos/<id>`, `/<page>/videos/<title>/<id>`, `/watch/?v=<id>`, `/watch/live/?v=<id>`, `/reel/<id>`, `/video.php?v=<id>`.
- Posts: `/<page>/posts/<id>`, `/<page>/photos/…`, `/permalink.php?story_fbid=…`, `/story.php?story_fbid=…&id=…`, `/photo.php?fbid=…`, `/photo/?fbid=…`.

Instagram (`instagram.com`, `www.`, `m.`, `instagr.am`; HTTPS only):

- Posts: `/p/<code>/`, `/<username>/p/<code>/`.
- Reels: `/reel/<code>/`, `/reels/<code>/`, `/<username>/reel/<code>/`.

A URL with a port or credentials (`https://user@…`, `https://…:8443/…`) stays a plain link. Other URLs (profiles, pages, events, stories, `/share/…` links, `fb.watch`) stay a plain link.

## Consent

The first active consent source decides when an embed loads.

| Consent source | Active when | Behaviour |
| --- | --- | --- |
| [CookieYes](https://wordpress.org/plugins/cookie-law-info/) (`cookie-law-info`) | The plugin is active and runs its current (not legacy) version, or `window.getCkyConsent` exists. After the page has loaded, a source without `window.getCkyConsent` counts as no source. | The embed loads when the visitor accepts the consent category, at once for a returning visitor or on the `cookieyes_consent_update` event. The iframe or script gets `data-cookieyes="cookieyes-<category>"`. |
| None | No consent tool is active. | "Require consent" off: the embed loads at once. "Require consent" on: the page shows only the notice and the link. |

The front end does not trust the markup. It loads an embed only from a validated `https://www.facebook.com` or `https://www.instagram.com` URL, and builds the iframe URL itself. The category of an embed comes from the server, which prints the category it chose (after the `outstand_embeds_consent_category` filter) for each embed it renders. The front end ignores the category in the markup, and a placeholder that the server did not render waits for the category in the settings.

Keep the consent category the same as the CookieYes category that lists the Facebook and Instagram cookies. On a mismatch, CookieYes can hold the embed back behind its own placeholder.

The editor preview loads at once: the consent banner does not run in the editor.

## Embeds added after load

The script sets up the embeds that are on the page when it runs, and watches the page for embeds that other scripts add later (for example a Query Loop with enhanced pagination or infinite scroll). To set up a part of the page by hand, call:

```js
window.outstandEmbeds.init( root ); // root: an element, or the document by default.
```

`init()` skips an embed that is already set up. The watcher also sets up embeds that the Interactivity API router inserts, as long as the script is already on the page. The server enqueues the script only when a page renders an embed, so a page whose first render has no embed must enqueue the `outstand-embeds-frontend` handle and the `outstand-embeds` stylesheet itself.

## Settings

Settings › Reading › Embeds:

| Setting | Option | Default |
| --- | --- | --- |
| Consent category | `outstand_embeds_consent_category` | `advertisement` |
| Require consent | `outstand_embeds_require_consent` | Off |

## Filters

### `outstand_embeds_consent_category`

Changes the consent category of one embed. Return an empty string to load the embed without consent.

```php
add_filter(
	'outstand_embeds_consent_category',
	function ( string $category, string $provider, string $url ): string {
		return 'instagram' === $provider ? 'functional' : $category;
	},
	10,
	3
);
```

### `outstand_embeds_require_consent`

Changes the "Require consent" setting: whether embeds wait for consent when no consent tool is active.

```php
add_filter( 'outstand_embeds_require_consent', '__return_true' );
```

## Consent helper for themes

The `outstand-embeds-consent` script handle exposes `window.outstandEmbeds.consent`. It reads the plugin settings, so a theme script follows the same rules as the embeds.

```php
wp_enqueue_script( 'my-theme-map', $src, [ 'outstand-embeds-consent' ], $version, true );
```

```js
const { consent } = window.outstandEmbeds;

// Runs at once with consent, else when the visitor accepts the category.
const loaded = consent.whenConsent( 'functional', () => loadMap() );

if ( ! loaded ) {
	showNotice();
}
```

| Function | Returns |
| --- | --- |
| `watchConsent( category, { onGrant, onRevoke } )` | Calls `onGrant` when the content may load, and `onRevoke` when the visitor withdraws consent later. Returns `{ granted, stop }`: whether the content may load now, and a function that stops the listener. |
| `hasConsent( category )` | `true` when content in the category may load now. An empty category needs no consent. |
| `whenConsent( category, callback )` | Runs `callback` once the content may load. Returns `true` when it ran at once. |
| `getSource()` | The active consent source (`'cookieyes'`), or `null`. |
| `getAttributes( category )` | The attributes that register an iframe or a script with the active consent tool, for example `{ 'data-cookieyes': 'cookieyes-functional' }`. Set them before `src`. |

## Theming

The placeholder uses the class `outstand-embed`, with `is-provider-<facebook|instagram>`, `is-type-<post|video|reel>` and `is-loaded` after the embed loads. Its notice is `.outstand-embed__notice` and its link is `.outstand-embed__link a`.

Set these custom properties on `:root` or on `.outstand-embed` to change the look:

| Property | Default |
| --- | --- |
| `--outstand-embed-gap` | `0.75em` |
| `--outstand-embed-padding` | `1.5em` |
| `--outstand-embed-min-height` | `200px` |
| `--outstand-embed-color` | `inherit` |
| `--outstand-embed-background` | `rgb(0 0 0 / 5%)` |
| `--outstand-embed-border-radius` | `0` |
| `--outstand-embed-notice-font-size` | `0.875em` |
| `--outstand-embed-link-color` | `currentcolor` |
| `--outstand-embed-facebook-max-width` | `500px` |
| `--outstand-embed-instagram-max-width` | `540px` |

```css
:root {
	--outstand-embed-background: var(--wp--preset--color--muted);
	--outstand-embed-border-radius: 8px;
}
```

## Installation

### Manual installation

1. Download the latest release ZIP from the [Releases page](https://github.com/pixelalbatross/outstand-embeds/releases/latest).
2. Go to Plugins › Add New › Upload Plugin in your WordPress admin area.
3. Upload the ZIP file and click Install Now.
4. Activate the plugin.

### Install with Composer

```bash
composer require outstand/embeds
```

Then activate the plugin from your WordPress admin area or with WP-CLI.

The plugin checks this GitHub repository for updates.

## Requirements

- WordPress 7.1 or higher.
- PHP 8.2 or higher.

## Development

```bash
composer install
npm install
npm run build
```

Lint:

```bash
composer run lint
npm run lint:js
npm run lint:css
```

Tests:

```bash
npm run test:js      # Jest, no Docker
npm run test:setup   # first time only: starts wp-env (Docker)
npm run test:unit    # PHPUnit in wp-env
```

Translations: `npm run makepot` updates `languages/outstand-embeds.pot`. After editing a `.po` file, run `npm run makemo` and `npm run makephp`. The plugin bundles a European Portuguese (`pt_PT`) translation.

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](https://github.com/pixelalbatross/outstand-embeds/blob/main/CHANGELOG.md).
