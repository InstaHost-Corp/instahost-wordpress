=== InstaHost WordPress MCP ===
Contributors: instahost
Tags: mcp, ai, abilities-api, automation, application passwords
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: mcp-adapter
Stable tag: 1.1.0
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

For desktop clients, install or run the tested `@automattic/mcp-wordpress-remote@0.4.0` bridge and set:

* `WP_API_URL=https://example.com/wp-json/mcp/instahost-wordpress`
* OAuth settings, a JWT token, or `WP_API_USERNAME` and `WP_API_PASSWORD`.

The settings page provides a ready-to-edit client configuration using a WordPress Application Password.

MCP Adapter releases: `https://github.com/WordPress/mcp-adapter/releases/latest`

== Managed automatic enrollment ==

Managed deployments can provision these constants through `wp-config.php`:

`INSTAHOST_WORDPRESS_MCP_REGISTRY_URL`

`INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN`

When both are present, activation schedules an HTTPS enrollment. The plugin
creates a restricted dedicated MCP identity and sends the registry a
revocable bearer token scoped to the dedicated MCP route. It does not send
administrator credentials, content, cookies, or database data.

Ordinary public installs make no outbound enrollment request. The receiving
registry must verify the advertised endpoint, protect against SSRF and DNS
rebinding, encrypt connection credentials at rest, and never enable write
abilities. See `docs/managed-enrollment.md` in the source repository.

== Security ==

* MCP Adapter provides the transport and session implementation.
* The dedicated server rejects anonymous users and requires `edit_posts`.
* Every ability applies operation-specific WordPress capability checks.
* Write abilities are disabled until an administrator explicitly enables them.
* Managed connection tokens authenticate only on the dedicated MCP route.
* Invalid plugin tokens fail closed without intercepting unrelated JWT/OAuth bearer tokens.
* Phone-home enrollment is disabled unless deployment-time registry URL and token constants are present.
* Content is sanitized through WordPress APIs before storage.
* Revisions, autosaves, and inaccessible password-protected content are excluded.
* Use OAuth where available, or HTTPS whenever Application Passwords are used.

== Changelog ==

= 1.1.0 =
* Added managed activation-time MCP registry enrollment.
* Added a restricted dedicated MCP identity and revocable route-scoped bearer credential.
* Added bounded retries, administrator status/retry/revoke controls, and no-phone-home behavior for ordinary installs.

= 1.0.0 =
* Initial release.
