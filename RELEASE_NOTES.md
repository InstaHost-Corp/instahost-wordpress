# InstaHost WordPress MCP 1.1.0 Release

**Release date:** 2026-09-12

**Release type:** Minor

**Audience:** WordPress operators, developers, and MCP client users

## Summary

Version 1.1.0 adds secure managed enrollment so deployment automation can
register a newly installed WordPress MCP endpoint without copying
administrator credentials into client configuration.

## What's new

- Activation-time registration when a registry URL and single-use enrollment
  token are explicitly provisioned through `wp-config.php`.
- Restricted dedicated MCP identity with a generated route-scoped bearer
  credential.
- Automatic bounded retry plus administrator-visible status, manual retry, and
  immediate local revocation.
- Registry-side contract for endpoint challenge, SSRF/DNS-rebinding
  protection, encrypted credential storage, and capability preservation.
- Ordinary installs with no managed configuration make no outbound request.

## Security

- MCP Adapter still rejects anonymous transport access and each ability keeps
  its WordPress capability checks.
- Write abilities remain disabled by default.
- Managed bearer credentials work only on
  `/wp-json/mcp/instahost-wordpress`.
- The managed role cannot publish, delete, or upload files.
- Enrollment sends no administrator password, Application Password, content,
  cookie, setting, or database data.
- JWT/OAuth bearer tokens without the plugin-specific prefix are not
  intercepted.
- HTTPS verification is mandatory and redirects are disabled for enrollment.

## Breaking changes and operator actions

- There are no upgrade-breaking changes.
- Operators must install and activate WordPress MCP Adapter 0.6.1 or newer.
- Managed auto-enrollment requires an approved registry implementation, a new
  single-use enrollment token, and two `wp-config.php` constants.
- Remove the one-time enrollment token from `wp-config.php` after successful
  registration.
- Sites without managed configuration continue using OAuth, JWT, or
  Application Passwords with the pinned
  `@automattic/mcp-wordpress-remote@0.4.0` bridge.
- Write abilities must still be enabled explicitly under
  **Settings > InstaHost MCP** if required.

## Known issues and residual risks

- MCP protocol support follows the installed MCP Adapter version.
- OAuth availability depends on authorization providers installed on the
  WordPress site.
- The plugin-side enrollment client does not itself provide a registry.
  Automatic connection requires a separately deployed registry satisfying
  `docs/managed-enrollment.md`.

## Validation

- PHP syntax and existing MCP Adapter smoke tests.
- Zero-day trash-retention regression test.
- Managed enrollment payload, retry state, route scoping, token validation,
  JWT/OAuth non-interference, and unconfigured-install tests.
- Version consistency verification and deterministic installable ZIP build.

## Deployment and rollback

- Install `instahost-wordpress-mcp-1.1.0.zip` through WordPress
  **Plugins > Add Plugin > Upload Plugin**, then activate it.
- Roll back to 1.0.0 by revoking the managed connection, removing the two
  enrollment constants, and installing the prior package.
- Uninstall removes enrollment options, the local token hash, and the managed
  role. It does not delete WordPress content.
