# Changelog

All notable changes to InstaHost WordPress MCP are documented here.

## [1.0.0] - 2026-09-11

### Added

- Integration with the official WordPress MCP Adapter and Abilities API.
- Dedicated MCP Adapter endpoint at `/wp-json/mcp/instahost-wordpress`.
- Read-only abilities for site information, content listing, content retrieval, and search.
- Optional abilities to create, update, trash, and permanently delete WordPress content.
- Ready-to-edit `@automattic/mcp-wordpress-remote@0.4.0` client configuration.
- OAuth, JWT, and WordPress Application Password connection guidance.
- Two-layer transport and ability permission enforcement, protected-content controls, and input sanitization.
- Administrator settings page with write abilities disabled by default.
- Automated PHP compatibility checks, adapter integration smoke tests, and deterministic ZIP packaging.

[1.0.0]: https://github.com/InstaHost-Corp/instahost-wordpress/releases/tag/v1.0.0
