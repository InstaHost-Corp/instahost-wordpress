# InstaHost WordPress MCP 1.0.0 Release

**Release date:** 2026-09-11

**Release type:** Minor

**Audience:** WordPress operators, developers, and MCP client users

## Summary

The first release of InstaHost WordPress MCP provides secure content-management abilities on top of the official WordPress MCP Adapter, with direct configuration for Automattic's desktop-client bridge.

## What's new

- Dedicated MCP Adapter endpoint at `/wp-json/mcp/instahost-wordpress`.
- WordPress Abilities to inspect site metadata, list content, retrieve content, and search posts and pages.
- Administrator-controlled abilities for creating, updating, trashing, and deleting content.
- Official MCP Adapter transport, session, schema, and error-handling implementation.
- Ready-to-edit `@automattic/mcp-wordpress-remote` configuration for OAuth, JWT, or Application Password authentication.
- WordPress settings page showing the endpoint and write-ability control.

## Security

- MCP Adapter rejects anonymous transport access.
- The dedicated server requires the WordPress `edit_posts` capability.
- Per-post read, edit, and delete capability checks protect content operations.
- Publishing, private/future status transitions, and trashing require the corresponding WordPress capabilities.
- Revisions and autosaves are excluded, and password-protected content requires edit access.
- Write abilities are disabled by default.
- Content passes through WordPress sanitization and content APIs.
- OAuth is preferred where available; HTTPS is required when using Application Passwords.

## Breaking changes and operator actions

- There are no upgrade-breaking changes in this initial release.
- Operators must install and activate WordPress MCP Adapter 0.6.1 or newer.
- Operators must configure `@automattic/mcp-wordpress-remote` or another compatible client.
- Write abilities must be enabled explicitly under **Settings > InstaHost MCP** if required.

## Known issues and residual risks

- MCP protocol support follows the installed MCP Adapter version.
- OAuth availability depends on the authorization providers installed on the WordPress site.

## Validation

- PHP syntax validation on PHP 8.0 and PHP 8.3 in GitHub Actions.
- Standalone MCP Adapter registration, transport permission, and ability permission smoke tests.
- Version consistency verification across plugin metadata and release documentation.
- Deterministic installable ZIP build.
- Live WordPress 7.1 test with the exact MCP Adapter 0.6.1 release asset.
- Application Password initialization, session handling, anonymous denial, four read abilities, seven write-enabled abilities, site-info, create, get, and permanent-delete calls.

## Deployment and rollback

- Install `instahost-wordpress-mcp-1.0.0.zip` through WordPress **Plugins > Add Plugin > Upload Plugin**, then activate it.
- Roll back by deactivating and deleting the plugin. Uninstall removes the plugin's write-tools option and does not delete WordPress content.
