# InstaHost WordPress MCP 1.0.0 Release

**Release date:** 2026-09-11

**Release type:** Minor

**Audience:** WordPress operators, developers, and MCP client users

## Summary

The first release of InstaHost WordPress MCP provides a secure, installable WordPress plugin for inspecting and managing WordPress content from compatible MCP clients.

## What's new

- Authenticated JSON-RPC MCP endpoint at `/wp-json/instahost-mcp/v1/mcp`.
- Tools to inspect site metadata, list content, retrieve content, and search posts and pages.
- Administrator-controlled tools for creating, updating, trashing, and deleting content.
- Compatibility with modern stateless MCP 2026-07-28 requests and legacy MCP 2025-03-26 initialization.
- WordPress settings page showing the endpoint and write-tool control.

## Security

- Anonymous requests are rejected.
- The MCP user must have the WordPress `edit_posts` capability.
- Per-post read, edit, and delete capability checks protect content operations.
- Publishing, private/future status transitions, and trashing require the corresponding WordPress capabilities.
- Revisions and autosaves are excluded, and password-protected content requires edit access.
- Write tools are disabled by default.
- Modern MCP protocol headers and request metadata must match.
- Browser `Origin` headers are restricted to the WordPress installation unless explicitly extended through a filter.
- Content passes through WordPress sanitization and content APIs.
- HTTPS is required operationally when using Application Passwords.

## Breaking changes and operator actions

- There are no upgrade-breaking changes in this initial release.
- Operators must create a WordPress Application Password for the MCP account.
- Write tools must be enabled explicitly under **Settings > InstaHost MCP** if required.

## Known issues and residual risks

- The server returns complete JSON responses and does not provide request-scoped SSE progress events.
- A live WordPress installation is required for end-to-end authentication and content-operation validation.

## Validation

- PHP syntax validation on PHP 8.0 and PHP 8.3 in GitHub Actions.
- Standalone protocol and configuration smoke tests.
- Version consistency verification across plugin metadata and release documentation.
- Deterministic installable ZIP build.

## Deployment and rollback

- Install `instahost-wordpress-mcp-1.0.0.zip` through WordPress **Plugins > Add Plugin > Upload Plugin**, then activate it.
- Roll back by deactivating and deleting the plugin. Uninstall removes the plugin's write-tools option and does not delete WordPress content.
