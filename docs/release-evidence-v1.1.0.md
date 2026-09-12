# Release evidence: InstaHost WordPress MCP 1.1.0

## Classification

- **Outcome:** public plugin release candidate; no registry deployment in this change
- **Materiality:** T2 moderate
- **Evidence depth:** L3 identity/security
- **Repository:** `InstaHost-Corp/instahost-wordpress`
- **Release date:** 2026-09-12

This candidate adds an outbound enrollment path, a dedicated machine identity,
and a bearer credential. It therefore changes identity, credential, privacy,
and external-service boundaries. The plugin release is not deployable as a
complete automatic-connection service until an independently reviewed registry
implements `docs/managed-enrollment.md`.

## Security contract

- Ordinary installs make no outbound request.
- Enrollment requires an explicitly provisioned HTTPS registry URL and
  single-use deployment token.
- The enrollment token authenticates registration; the generated connection
  token authenticates later MCP sessions. They are separate credentials.
- The payload contains endpoint/version/capability metadata and a generated
  connection token. It contains no administrator credential, content, cookie,
  setting, or database data.
- The local connection token is stored only as a WordPress password hash.
- The generated `ihmcp_` credential authenticates only on
  `/wp-json/mcp/instahost-wordpress`.
- Other bearer tokens are left for JWT/OAuth providers.
- The dedicated role cannot publish, delete, or upload files. Global write
  tools remain disabled by default.
- Enrollment uses `wp_safe_remote_post`, TLS verification, no redirects, a
  15-second timeout, bounded exponential retry, and immediate local revocation.

## Validation

- PHP lint: PASS for all PHP source and tests.
- Existing MCP Adapter smoke tests: PASS.
- Zero-day trash-retention regression test: PASS.
- Managed enrollment success and payload-minimization test: PASS.
- Route-scoped valid/invalid bearer authentication tests: PASS.
- JWT/OAuth bearer non-interference test: PASS.
- Duplicate-cron credential-rotation guard test: PASS.
- Unconfigured-install no-phone-home test: PASS.
- Version consistency: PASS at `1.1.0`.
- Deterministic package build: PASS.
- ZIP integrity and runtime allowlist: PASS.
- Candidate package SHA-256:
  `61fe9242af63e432b9ccc68f309c780873e4b178fafefd341fd2b3b7aa33658d`.

## External gate

No approved registry endpoint or DNS name existed at implementation baseline:
`mcp.insta.host`, `wordpress-mcp.insta.host`, and `api.insta.host` did not
resolve. A future registry release must separately pass architecture,
Security/Privacy, independent QA, Platform/SRE, DNS/edge, credential-storage,
negative-control, live endpoint-challenge, recovery, and publication gates.
The plugin must not be configured with a placeholder or unreviewed receiver.

## Recovery

- **Connection revoke:** use **Settings > InstaHost MCP > Revoke connection**;
  the local token hash is deleted immediately.
- **Plugin rollback:** remove the registry constants and reinstall 1.0.0.
- **Rollback validation:** the settings page reports no enrolled connection,
  an `ihmcp_` token returns HTTP 401, and existing OAuth/JWT/Application
  Password access retains its previous behavior.
- Uninstall removes the enrollment options and role without deleting content.
