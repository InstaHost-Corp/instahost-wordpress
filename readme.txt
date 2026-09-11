=== InstaHost WordPress MCP ===
Contributors: instahost
Tags: mcp, ai, abilities-api, automation, application passwords
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: mcp-adapter
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add secure WordPress content abilities to the official MCP Adapter.

== Description ==

InstaHost WordPress MCP extends the official WordPress MCP Adapter with focused content-management abilities and a dedicated endpoint:

`https://example.com/wp-json/mcp/instahost-wordpress`

Available abilities include:

* Get site information.
* List posts and pages.
* Get a post by ID.
* Search posts and pages.
* Create, update, trash, and delete content when write tools are enabled.

The MCP Adapter owns protocol transport, sessions, error handling, and ability execution. Access requires an authenticated WordPress user with the `edit_posts` capability. Every ability has its own WordPress permission callback. Write abilities are disabled by default.

== Installation ==

1. Download `mcp-adapter.zip` from the official MCP Adapter GitHub release, then install and activate it.
2. Upload the `instahost-wordpress-mcp` folder to `/wp-content/plugins/`.
3. Activate "InstaHost WordPress MCP" in WordPress.
4. Open Settings > InstaHost MCP to see the endpoint and optionally enable write abilities.

== MCP client configuration ==

For desktop clients, install or run `@automattic/mcp-wordpress-remote` and set:

* `WP_API_URL=https://example.com/wp-json/mcp/instahost-wordpress`
* OAuth settings, a JWT token, or `WP_API_USERNAME` and `WP_API_PASSWORD`.

The settings page provides a ready-to-edit client configuration using a WordPress Application Password.

MCP Adapter releases: `https://github.com/WordPress/mcp-adapter/releases/latest`

== Security ==

* MCP Adapter provides the transport and session implementation.
* The dedicated server rejects anonymous users and requires `edit_posts`.
* Every ability applies operation-specific WordPress capability checks.
* Write abilities are disabled until an administrator explicitly enables them.
* Content is sanitized through WordPress APIs before storage.
* Revisions, autosaves, and inaccessible password-protected content are excluded.
* Use OAuth where available, or HTTPS whenever Application Passwords are used.

== Changelog ==

= 1.0.0 =
* Initial release.
