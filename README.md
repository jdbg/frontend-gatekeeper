# Frontend Gatekeeper

WordPress plugin that hides the public frontend unless a configured URL parameter is present.

## Installation

Copy the `frontend-gatekeeper` folder into `wp-content/plugins/`, then activate **Frontend Gatekeeper** in WP Admin.

On WordPress multisite, the plugin can be network activated or activated per site. Settings are stored per site, so each site can use its own parameter name and value.

## Usage

Go to **Settings > Frontend Gatekeeper** and configure:

- **Enable frontend gate**: On/Off toggle for the frontend lock.
- **Parameter name**: the query parameter key, for example `fronga_access`.
- **Parameter value**: the secret value visitors need in the URL.
- **Blocked message**: message shown when the frontend is hidden.

Example access URL:

```text
https://example.com/?fronga_access=preview-2026
```

Logged-in users always see the site normally and are never gated. wp-admin, the REST API, AJAX, cron, and `wp-login.php` are always allowed through.

When a valid access URL is used, the plugin appends the same parameter to same-site menu links, post links, page links, taxonomy links, and matching frontend links/forms.

Block-based themes and Gutenberg blocks are supported through WordPress URL filters, menu attribute filters, and the `render_block` filter. A small footer script is also printed on authorized frontend requests as a fallback for links that are generated outside normal WordPress rendering.

In multisite subdirectory installs, only links within the current site's path receive the parameter. For example, access granted on `/site-a/` will not append the token to `/site-b/`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
