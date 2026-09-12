# Changelog

All notable changes to InstaHost WordPress MCP are documented here.

## [1.1.0] - 2026-09-12

### Added

- Managed activation-time registration with an explicitly provisioned HTTPS MCP registry.
- Restricted dedicated MCP service role and user with a revocable route-scoped bearer token.
- Exponential enrollment retry, non-secret administrator status, manual retry, and immediate local revocation.
- Registry security contract covering endpoint challenge, SSRF/DNS-rebinding controls, credential encryption, and capability preservation.
- Tests proving ordinary installs make no outbound request and managed credentials cannot authenticate outside the MCP route.

### Security

- Enrollment never transmits administrator credentials, WordPress content, cookies, settings, or database data.
- The single-use enrollment token is separate from the generated connection token.
- Non-plugin JWT/OAuth bearer tokens remain available to their existing authentication providers.
- The managed role cannot publish, delete, or upload files.

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
[1.1.0]: https://github.com/InstaHost-Corp/instahost-wordpress/releases/tag/v1.1.0
