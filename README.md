# InstaHost WordPress MCP

An installable WordPress plugin that exposes authenticated content tools through the Model Context Protocol (MCP).

## Endpoint

```text
https://example.com/wp-json/instahost-mcp/v1/mcp
```

The endpoint accepts MCP JSON-RPC messages over HTTP POST. It supports stateless MCP `2026-07-28` requests and legacy MCP `2025-03-26` initialization. Authenticate with a WordPress username and Application Password using HTTP Basic authentication.

## Included tools

- `wordpress_get_site_info`
- `wordpress_list_posts`
- `wordpress_get_post`
- `wordpress_search`
- `wordpress_create_post` (optional)
- `wordpress_update_post` (optional)
- `wordpress_delete_post` (optional)

Write tools are disabled by default. Enable them under **Settings > InstaHost MCP**.

Modern clients must provide matching request `_meta`, `MCP-Protocol-Version`, `Mcp-Method`, and (for tool calls) `Mcp-Name` values. Browser requests are restricted to the site's own origin by default; additional trusted origins can be supplied with the `instahost_wordpress_mcp_allowed_origins` WordPress filter.

## Install

Copy or upload the `Instahost-Wordpress-MCP` directory into `wp-content/plugins`, then activate **InstaHost WordPress MCP**.

## Requirements

- WordPress 6.5 or newer
- PHP 8.0 or newer
- HTTPS for remote access

## License

GPL-2.0-or-later
