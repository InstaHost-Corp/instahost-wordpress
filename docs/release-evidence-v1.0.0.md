# Release evidence: InstaHost WordPress MCP 1.0.0

## Classification

- **Outcome:** GitHub source and installable plugin publication
- **Evidence depth:** L3 stateful/security
- **Materiality:** T2 moderate
- **Executive sponsor:** CTO - Patrick Hamid
- **Delivery and implementation owner:** GitHub Copilot CLI release session
- **Communications owner:** InstaHost
- **Repository:** `InstaHost-Corp/instahost-wordpress`
- **Release date:** 2026-09-11

This is a new public artifact with an authenticated WordPress API and optional content mutation paths. Engineering, security, independent QA, signed-merge, packaging, and rollback evidence therefore apply.

## Architecture and contract

- The plugin registers one WordPress REST route: `POST /wp-json/instahost-mcp/v1/mcp`.
- WordPress REST authentication remains authoritative; Application Passwords are the recommended remote credential mechanism.
- Endpoint access requires an authenticated user with `edit_posts`.
- Individual content operations retain WordPress `read_post`, `edit_post`, `delete_post`, post-type creation, private-content, and edit-others capability checks.
- Write tools are disabled by default and require an administrator setting change.
- Modern MCP requests use protocol `2026-07-28`; legacy initialization uses `2025-03-26`.
- Modern protocol version, method, and tool-name headers must match the JSON-RPC body metadata.
- Browser requests with an `Origin` header fail closed unless the normalized origin matches the WordPress installation or an explicitly filtered allowlist.
- The source artifact contains only runtime PHP, `readme.txt`, and `uninstall.php`; tests, CI, release evidence, and development documentation are excluded from the installable ZIP.

## Risk-to-test traceability

| Risk | Control | Executable evidence |
|---|---|---|
| Anonymous access | REST permission callback requires authentication and `edit_posts` | Source review plus live-install requirement |
| Unauthorized mutation | Write-tools setting and per-object capability checks | `tests/smoke.php` verifies default tool exposure; source review verifies capability gates |
| Protocol/header confusion | Matching modern body and HTTP metadata | `tests/smoke.php` matching and mismatch cases |
| Browser DNS-rebinding/cross-origin access | Origin validation | `tests/smoke.php` same-origin and hostile-origin cases |
| Version drift | One consistency check across plugin metadata and notes | `php scripts/check-version.php` |
| Broken PHP package | Multi-version lint and ZIP integrity checks | GitHub Actions plus local lint and `unzip -tq` |
| Runtime files omitted or polluted | Deterministic allowlist package build | `sh scripts/build.sh` |

## Pre-freeze validation

- `find ... -name '*.php' ... php -l`: PASS on local PHP 8.5.10.
- `php tests/smoke.php`: PASS.
- `php scripts/check-version.php`: PASS, all representations are `1.0.0`.
- `sh scripts/build.sh`: PASS.
- `unzip -tq dist/instahost-wordpress-mcp-1.0.0.zip`: PASS.
- Source-tree credential-pattern scan: PASS; matches were documentation references to WordPress Application Passwords only.
- Installable ZIP SHA-256 before publication: `084ce22e232dbffb872e5052c22c97e656bca9a775ae5e0a76c7d3805b770e88`.

## External dependencies and bounded resources

- WordPress 6.5+ and PHP 8.0+ are declared runtime dependencies.
- No third-party PHP packages, remote APIs, database migrations, scheduled tasks, persistent files, or external secrets are introduced.
- Request and response size remain governed by the existing WordPress/PHP/web-server limits.
- End-to-end authentication and content mutation require a live WordPress installation and are recorded as a residual validation boundary for the initial source release.

## Recovery

- **Rollback point:** remove or deactivate version 1.0.0.
- **Rollback command:** deactivate and delete the plugin through WordPress administration or remove its plugin directory from `wp-content/plugins`.
- **Rollback validation:** confirm the plugin is absent from the active plugin list and `POST /wp-json/instahost-mcp/v1/mcp` returns no registered route.
- Uninstall removes only `instahost_wordpress_mcp_enable_writes`; it does not delete or alter WordPress content.

## Publication gates

Final engineering, independent QA, signed merge, CI, tag, GitHub Release, release-asset digest, and default-branch verification are recorded in the GitHub pull request and release.
