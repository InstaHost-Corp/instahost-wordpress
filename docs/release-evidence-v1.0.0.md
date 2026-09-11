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

This public artifact adds authenticated WordPress content abilities and optional mutation paths. Engineering, security, independent QA, signed-merge, packaging, and rollback evidence therefore apply.

## Authoritative upstream contracts

| Dependency | Reference | Observed version | Contract used |
|---|---|---:|---|
| WordPress MCP Adapter | <https://github.com/WordPress/mcp-adapter> | 0.6.1 / `23cb53e0b82f39238eec1c38cb055e28aa30fa7c` | Abilities API registration, `mcp_adapter_init`, custom HTTP server creation, transport permission callback, MCP session lifecycle |
| Automattic MCP WordPress Remote | <https://github.com/Automattic/mcp-wordpress-remote> | 0.4.0 / `7190a4ccf9a16b909433133ecf90c52c9bb04da4` | Full MCP endpoint URL through `WP_API_URL`; OAuth, JWT, Application Password, custom-header, and session forwarding |

Both upstream projects were read from their public Git repositories on 2026-09-11. No upstream source is vendored. MCP Adapter is a required WordPress plugin; MCP WordPress Remote is a recommended client-side bridge.

## Architecture and contract

- The plugin registers WordPress Abilities on `wp_abilities_api_init`.
- It creates a focused MCP Adapter server on `mcp_adapter_init`.
- The endpoint is `/wp-json/mcp/instahost-wordpress`.
- MCP Adapter owns transport framing, protocol negotiation, session lifecycle, schemas, and error handling.
- The transport permission callback rejects anonymous users and requires `edit_posts`.
- Individual abilities retain WordPress `read_post`, `edit_post`, `delete_post`, post-type creation, publishing, private-content, and edit-others capability checks.
- Write abilities are disabled by default and require an administrator setting change.
- The settings page generates a pinned `@automattic/mcp-wordpress-remote@0.4.0` configuration using the exact endpoint.
- The source artifact contains only runtime PHP, `readme.txt`, and `uninstall.php`; tests, CI, evidence, and development documentation are excluded from the installable ZIP.

## Risk-to-test traceability

| Risk | Control | Executable evidence |
|---|---|---|
| Anonymous or low-privilege transport access | MCP Adapter custom-server permission callback | `tests/smoke.php` verifies 401, 403, and editor success |
| Ability exposed unintentionally | Explicit `meta.public=true` and deterministic ability allowlist | `tests/smoke.php` verifies registered names and metadata |
| Unauthorized or ambiguous mutation | Write setting, per-object/status callbacks, explicit trash API, retention guard, force-only permanent delete | `tests/smoke.php` verifies publish/trash denial, zero-day retention rejection, and trash/delete semantics |
| Historical or protected content disclosure | Reject revisions/autosaves and require edit access for protected content | `tests/smoke.php` rejection cases |
| Tool-list context growth | Focused custom server and seven-ability maximum | Registration smoke test |
| Client transport or supply-chain drift | Use official MCP Adapter endpoint and pin Automattic remote 0.4.0 | Upstream source review and documented configuration |
| Version drift | One consistency check across plugin metadata and release notes | `php scripts/check-version.php` |
| Broken PHP package | Multi-version lint and ZIP integrity checks | GitHub Actions plus local lint and `unzip -tq` |
| Runtime files omitted or polluted | Deterministic allowlist package build | `sh scripts/build.sh` |

## Pre-freeze validation

- `find ... -name '*.php' ... php -l`: PASS on local PHP 8.5.10.
- `php tests/smoke.php`: PASS.
- `INSTAHOST_TEST_TRASH_DAYS=0 php tests/smoke.php`: PASS; permission and execution rejected non-forced deletion before the trash API was called, while explicit force deletion remained available.
- Official `php:8.0-cli` container: PHP lint, normal smoke, zero-day trash smoke, and version consistency PASS.
- GitHub Actions uses only the GitHub-owned checkout action, pinned to an immutable commit SHA to satisfy the organization policy.
- `php scripts/check-version.php`: PASS, all representations are `1.0.0`.
- `sh scripts/build.sh`: PASS.
- `unzip -tq dist/instahost-wordpress-mcp-1.0.0.zip`: PASS.
- Source-tree credential-pattern scan: PASS; matches are documentation placeholders or references to WordPress Application Passwords.
- Reproducible plugin ZIP SHA-256 before final freeze: `6a24c7a0ab375ac9d02efb3cf8f789ef1ff07b7870a72373bd8e73805d825bcb`.
- MCP Adapter 0.6.1 release asset SHA-256 matched its GitHub digest: `1c3cd47c32e99b4e7d8690a44a7890256e92a8b96f61776cbe1894e5483cf676`.
- Live WordPress 7.1 plus MCP Adapter 0.6.1 release-asset validation: PASS.
- Application Password initialize and MCP session creation: PASS.
- Anonymous initialize rejection: PASS with HTTP 401.
- Read-only tool discovery: PASS with exactly four InstaHost abilities.
- Write-enabled tool discovery: PASS with exactly seven InstaHost abilities.
- Live `get-site-info`, draft creation, draft retrieval, and permanent deletion: PASS.
- Corrective live schema check confirmed `future` is absent from create/update status enums.
- Corrective live deletion checks confirmed explicit trash, rejection of repeat non-forced trash, and force-only permanent deletion.
- Unit coverage confirms zero-day trash retention fails closed before non-forced deletion.
- Post-test content cleanup and write-option reset: PASS.

## Dependencies and bounded resources

- WordPress 6.9+, PHP 8.0+, and MCP Adapter 0.6.1+ are runtime requirements.
- MCP WordPress Remote 0.4.0+ is optional and runs on Node.js 18+ on the client machine.
- No third-party code, Composer packages, npm packages, database migrations, scheduled tasks, persistent files, or external secrets are bundled.
- MCP Adapter session storage and limits remain authoritative.
- Request and response size remain governed by WordPress, PHP, and the web server.
- OAuth discovery remains dependent on the authorization provider installed on the target site. Application Password authentication and content mutation were verified live.

## Validation pivot

The first harness used the MCP Adapter Git source checkout without its Composer-built runtime dependencies and failed closed. A WordPress.org download URL was then checked and found absent. Following the upstream release contract, validation pivoted to the exact GitHub v0.6.1 `mcp-adapter.zip` asset, verified its published digest, recreated the WordPress environment, and passed the full live flow.

## Recovery

- **Rollback point:** deactivate or remove InstaHost WordPress MCP 1.0.0.
- **Rollback command:** deactivate and delete the plugin through WordPress administration or remove its plugin directory from `wp-content/plugins`.
- **Rollback validation:** confirm the plugin is absent from the active plugin list and `/wp-json/mcp/instahost-wordpress` is no longer registered.
- Uninstall removes only `instahost_wordpress_mcp_enable_writes`; it never deletes or alters WordPress content or MCP Adapter state.

## Publication gates

Final engineering, independent QA, signed merge, CI, tag, GitHub Release, release-asset digest, and default-branch verification will be recorded in pull request 1 and the v1.0.0 GitHub Release.
