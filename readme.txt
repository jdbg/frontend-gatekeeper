=== Frontend Gatekeeper ===
Contributors: jdbg
Tags: maintenance mode, private site, coming soon, preview, access control
Requires at least: 5.7
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hide the public WordPress frontend behind a secret URL parameter. Anyone with the link gets in; everyone else sees a 404.

== Description ==

Frontend Gatekeeper hides the public frontend of your WordPress site unless visitors arrive with a configured URL parameter and value. It is useful for staging sites, soft launches, client previews, and pre-release content where you want to share a single shareable link instead of issuing individual user accounts.

Logged-in users always see the site normally, so editors and administrators are never blocked.

= Features =

* Hide the entire public frontend with a single toggle.
* Configurable parameter name and secret value, set per site.
* Custom blocked message shown to unauthorized visitors (served with a 404 status).
* Same-site link propagation: WordPress-generated links, menus, and block content automatically carry the access parameter forward, so visitors stay authorized while browsing.
* Footer-script fallback that adds the parameter to links and forms rendered outside normal WordPress filters.
* Gutenberg and block-theme aware through the `render_block` filter and `WP_HTML_Tag_Processor` when available.
* Multisite-aware URL scoping for subdomain and subdirectory networks: access on `/site-a/` will not leak the token to `/site-b/`.
* Logged-in users, wp-admin, REST API, AJAX, cron, and `wp-login.php` are always allowed through.

= Typical use cases =

* Sharing a staging or pre-launch site with clients and stakeholders.
* Gating a soft launch behind a single link instead of provisioning accounts.
* Hiding draft or campaign content until you are ready to publish.

== Installation ==

1. Upload the `frontend-gatekeeper` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress. On multisite, you can network-activate or activate per site.
3. Go to **Settings > Frontend Gatekeeper** and configure the parameter name, parameter value, and blocked message.
4. Enable the gate and share the generated access URL.

== Frequently Asked Questions ==

= What happens to logged-in users? =

Logged-in users are never gated. Administrators, editors, and any other authenticated visitor see the site as normal.

= What do unauthorized visitors see? =

They see your configured blocked message, served with an HTTP 404 status and no-cache headers, so the site does not advertise that it exists at that URL.

= Will the access parameter spread to external links? =

No. The parameter is only appended to same-site URLs. Links to other domains, mail/tel/sms/javascript URIs, and fragment-only links are left alone.

= Does it work on multisite? =

Yes. Settings are stored per site, and on subdirectory networks the parameter is only appended to links that fall under the current site's path.

= Is this a real security boundary? =

No. The access parameter is a soft gate suitable for previews and staging. Anyone with the link gets in, and the value can appear in browser history and server logs. Use real authentication for anything sensitive.

= Does it block the REST API, admin, or login page? =

No. wp-admin, AJAX, cron, the REST API, and `wp-login.php` are always allowed through so the site stays manageable.

== Changelog ==

= 1.0.3 =
* Added a Settings link on the Plugins screen row for quicker access to the settings page.

= 1.0.2 =
* Gate is now disabled by default on activation so newly installed sites stay public until configured.

= 1.0.1 =
* Added Bulgarian (bg_BG) translation.

= 1.0.0 =
* Initial release.
* Frontend gate that hides public WordPress pages unless a configured URL parameter and value are present.
* Per-site WP Admin settings under **Settings > Frontend Gatekeeper** with an On/Off toggle switch.
* Generated access URL display in the settings screen, with a Copy-to-clipboard button and full-URL preview.
* Logged-in users always bypass the gate, alongside wp-admin, REST, AJAX, cron, and `wp-login.php`.
* Same-site URL propagation for WordPress-generated links.
* Classic menu link attribute support.
* Gutenberg and block-based theme support through the `render_block` filter.
* Footer-script fallback for frontend links/forms generated outside normal WordPress rendering.
* Multisite-aware URL scoping for subdomain and subdirectory networks.

== Upgrade Notice ==

= 1.0.3 =
Adds a Settings shortcut on the Plugins screen.

= 1.0.2 =
Gate is now off by default on fresh installs.

= 1.0.1 =
Adds Bulgarian translation.

= 1.0.0 =
Initial release.
