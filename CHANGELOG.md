# Changelog

All notable changes to InstaHost WordPress MCP are documented here.

## [1.0.0] - 2026-09-11

### Added

- Authenticated MCP endpoint at `/wp-json/instahost-mcp/v1/mcp`.
- Stateless MCP 2026-07-28 and legacy MCP 2025-03-26 compatibility.
- Read-only tools for site information, content listing, content retrieval, and search.
- Optional tools to create, update, trash, and permanently delete WordPress content.
- WordPress Application Password support through the standard REST authentication path.
- Same-origin browser protection, per-request protocol validation, capability enforcement, and input sanitization.
- Administrator settings page with write tools disabled by default.
- Automated PHP compatibility checks, protocol smoke tests, and deterministic ZIP packaging.

[1.0.0]: https://github.com/InstaHost-Corp/instahost-wordpress/releases/tag/v1.0.0
