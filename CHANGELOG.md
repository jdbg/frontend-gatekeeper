# Changelog

All notable changes to Frontend Gatekeeper are documented in this file.

## 1.0.4 - 2026-05-23

- Fix: the access parameter is no longer appended to URLs under `/wp-content/`, `/wp-includes/`, `/wp-admin/`, or to WordPress core PHP entry points (`wp-login.php`, `wp-cron.php`, `xmlrpc.php`, and similar). This prevents 500 errors triggered when the parameter ended up on theme or plugin asset URLs, and keeps the gate from interfering with core requests.

## 1.0.3 - 2026-05-23

- Added a Settings link on the Plugins screen row for quicker access to the settings page.

## 1.0.2 - 2026-05-23

- Gate is now disabled by default on activation so newly installed sites stay public until configured.

## 1.0.1 - 2026-05-22

- Added Bulgarian (bg_BG) translation.

## 1.0.0 - 2026-05-20

- Initial release.
- Frontend gate that hides public WordPress pages unless a configured URL parameter and value are present.
- Per-site WP Admin settings under **Settings > Frontend Gatekeeper** with an On/Off toggle switch.
- Generated access URL display in the settings screen, with a Copy-to-clipboard button and a full-URL preview below the input.
- Logged-in users always bypass the gate, alongside wp-admin, REST API, AJAX, cron, and `wp-login.php`.
- Same-site URL propagation for WordPress-generated links.
- Classic menu link attribute support.
- Gutenberg and block-based theme support through the `render_block` filter.
- Footer-script fallback for frontend links/forms generated outside normal WordPress rendering.
- Multisite-aware URL scoping for subdomain and subdirectory networks.
- ZIP artifact ignore rule so packaged plugin builds stay out of git.
