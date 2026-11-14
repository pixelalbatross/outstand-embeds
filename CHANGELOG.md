# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-14

### Added

- Initial release.
- Facebook posts, photos, videos, live videos and reels embed in the Embed block, with an editor preview. The supported URLs include `/photo/?fbid=…`, `/story.php?story_fbid=…&id=…` and `/watch/live/?v=…`.
- Instagram posts and reels embed in the Embed block, with an editor preview.
- Embeds wait for consent from CookieYes, or load at once when no consent tool is active and "Require consent" is off. An embed goes away and the notice returns when the visitor withdraws consent.
- The front end loads only validated Facebook and Instagram URLs and builds the iframe URL itself. The consent category of an embed comes from the server, and a placeholder that the server did not render waits for the category in the settings.
- CookieYes counts as a consent source only when its current front end runs.
- Embeds that the page adds after load are set up, and `window.outstandEmbeds.init( root )` sets up a part of the page on demand.
- The Facebook SDK and Instagram's `embed.js` are loaded once, and an SDK script that is already on the page is reused.
- The consent helper is available to other scripts through the `outstand-embeds-consent` handle.
- The plugin includes a European Portuguese (pt_PT) translation.

[Unreleased]: https://github.com/pixelalbatross/outstand-embeds/compare/1.0.0...HEAD
[1.0.0]: https://github.com/pixelalbatross/outstand-embeds/releases/tag/1.0.0
