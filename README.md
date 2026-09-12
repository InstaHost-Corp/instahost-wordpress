# InstaHost WordPress MCP

An installable WordPress plugin that adds secure content-management abilities to the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).

## Architecture

This plugin does not implement a competing MCP transport. It registers WordPress Abilities and creates a focused MCP Adapter server at:

```text
https://example.com/wp-json/mcp/instahost-wordpress
```

MCP Adapter owns the HTTP transport, MCP sessions, schema conversion, error handling, and ability execution. InstaHost WordPress MCP supplies the content abilities and their WordPress capability checks.

For desktop MCP clients, use [Automattic MCP WordPress Remote](https://github.com/Automattic/mcp-wordpress-remote) as the local stdio-to-WordPress bridge. It supports OAuth 2.1, JWT, Application Passwords, proxy configuration, and MCP Adapter session forwarding.

## Abilities

- `instahost-wordpress/get-site-info`
- `instahost-wordpress/list-posts`
- `instahost-wordpress/get-post`
- `instahost-wordpress/search`
- `instahost-wordpress/create-post` (optional)
- `instahost-wordpress/update-post` (optional)
- `instahost-wordpress/delete-post` (optional)

Write abilities are disabled by default. Enable them under **Settings > InstaHost MCP**.

## Managed automatic enrollment

Managed deployments can make the MCP connection available automatically after
plugin activation. Provision these values through `wp-config.php` without
committing them:

```php
define( 'INSTAHOST_WORDPRESS_MCP_REGISTRY_URL', 'https://registry.example.com/v1/enroll' );
define( 'INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN', '<single-use-token>' );
```

The plugin schedules an HTTPS enrollment, creates a restricted dedicated MCP
identity, and sends the registry a revocable route-scoped bearer token. It
never sends administrator credentials, content, cookies, or database data.
Public installs with no deployment-time token make no outbound request.

The receiving registry must verify the site through the advertised MCP
endpoint, protect against SSRF/DNS rebinding, encrypt the connection token, and
keep writes disabled unless the WordPress administrator enables them. See
[`docs/managed-enrollment.md`](docs/managed-enrollment.md) for the complete
contract.

## Requirements

- WordPress 6.9 or newer
- PHP 8.0 or newer
- [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases/latest) 0.6.1 or newer
- HTTPS for remote access

## Install

1. Download `mcp-adapter.zip` from the [MCP Adapter releases](https://github.com/WordPress/mcp-adapter/releases/latest), then install and activate it.
2. Upload and activate **InstaHost WordPress MCP**.
3. Open **Settings > InstaHost MCP**.
4. Copy the generated `@automattic/mcp-wordpress-remote@0.4.0` configuration and replace the credential placeholders, or configure OAuth/JWT.

## Example remote configuration

```json
{
  "mcpServers": {
    "instahost-wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@0.4.0"],
      "env": {
        "WP_API_URL": "https://example.com/wp-json/mcp/instahost-wordpress",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "your-application-password",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}
```

OAuth is preferred where the WordPress site provides compatible authorization metadata. Application Passwords must be used only over HTTPS.

## Security model

- MCP Adapter transport permission requires an authenticated user with `edit_posts`.
- Every ability independently checks the relevant WordPress capabilities.
- Publish, private, trash, and delete operations have explicit capability gates.
- Non-forced deletion is rejected when WordPress recoverable trash is disabled.
- The tested remote bridge is pinned to `@automattic/mcp-wordpress-remote@0.4.0`.
- Non-published list results are limited to the current author unless the caller can edit others' content.
- Revisions, autosaves, and inaccessible password-protected content are not returned.
- Managed enrollment credentials authenticate only on the dedicated MCP route.
- Invalid `ihmcp_` credentials fail closed without intercepting unrelated JWT/OAuth bearer tokens.
- Public installs make no phone-home request unless a registry URL and one-time enrollment token are explicitly provisioned.
- Mutation input is sanitized through WordPress APIs.
- Uninstall removes only this plugin's setting; it never deletes WordPress content.

## Build and test

```sh
php tests/smoke.php
INSTAHOST_TEST_TRASH_DAYS=0 php tests/smoke.php
php tests/enrollment.php
php tests/enrollment-unconfigured.php
php scripts/check-version.php
sh scripts/build.sh
```

The installable artifact is written to `dist/`.

## License

GPL-2.0-or-later
