=== InstaHost WordPress MCP ===
Contributors: instahost
Tags: mcp, ai, api, automation, application passwords
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose authenticated WordPress content tools through the Model Context Protocol.

== Description ==

InstaHost WordPress MCP provides a JSON-RPC MCP endpoint at:

`https://example.com/wp-json/instahost-mcp/v1/mcp`

The endpoint supports current stateless MCP requests, legacy MCP initialization, server discovery, ping, tool discovery, and tool calls. Available tools include:

* Get site information.
* List posts and pages.
* Get a post by ID.
* Search posts and pages.
* Create, update, trash, and delete content when write tools are enabled.

Access requires an authenticated WordPress user with the `edit_posts` capability. WordPress Application Passwords are recommended for remote clients. Write tools are disabled by default and retain WordPress capability checks when enabled.

== Installation ==

1. Upload the `Instahost-Wordpress-MCP` folder to `/wp-content/plugins/`.
2. Activate "InstaHost WordPress MCP" in WordPress.
3. Open Settings > InstaHost MCP to see the endpoint and optionally enable write tools.
4. Create an Application Password in Users > Profile for the WordPress account used by your MCP client.

== MCP client configuration ==

Use a Streamable HTTP-compatible MCP client and configure the endpoint URL with HTTP Basic authentication:

* Username: WordPress username.
* Password: WordPress Application Password.

Example legacy initialization request:

`{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"example","version":"1.0.0"}}}`

The plugin also supports the stateless MCP `2026-07-28` protocol. Modern clients must send the required `MCP-Protocol-Version`, `Mcp-Method`, and, for tool calls, `Mcp-Name` headers with matching `_meta` request metadata.

== Security ==

* The MCP endpoint does not permit anonymous access.
* Users must have the `edit_posts` capability.
* Browser Origin headers are restricted to this WordPress installation by default.
* Every content mutation is checked against the current user's WordPress capabilities.
* Write tools are disabled until an administrator explicitly enables them.
* Content is sanitized through WordPress APIs before storage.
* Use HTTPS whenever Application Passwords are used.

== Changelog ==

= 1.0.0 =
* Initial release.
