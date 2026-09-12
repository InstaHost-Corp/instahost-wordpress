# Managed automatic MCP enrollment

InstaHost WordPress MCP can register itself with an approved MCP registry
after activation. This is intended for managed deployments where the installer
can provision a single-use enrollment token before activating the plugin.
Ordinary installs do not phone home.

## WordPress configuration

Add the registry URL and a newly issued single-use token to `wp-config.php`
through the deployment system or a secret-safe configuration mechanism:

```php
define( 'INSTAHOST_WORDPRESS_MCP_REGISTRY_URL', 'https://registry.example.com/v1/enroll' );
define( 'INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN', '<single-use-token>' );
```

Do not commit either value. The URL must use HTTPS. The plugin uses
`wp_safe_remote_post()`, disables redirects, verifies TLS, and makes no request
unless both constants are present.

Activation schedules enrollment through WP-Cron. A successful enrollment:

1. Creates a stable random instance ID.
2. Creates the restricted `instahost_mcp_agent` role and one dedicated user.
3. Generates a 256-bit `ihmcp_` connection token.
4. Stores only the token's WordPress password hash locally.
5. Sends the registry endpoint metadata and raw connection token once over
   HTTPS.
6. Stores only non-secret registration status and ID from the response.

Managed sites must have a functioning WordPress cron runner. Where built-in
loopback cron is disabled, run the normal system-cron/WP-CLI scheduler; an
administrator can also use **Retry enrollment** after the scheduler is
available.

The dedicated role can read and edit existing content so the MCP read tools
work, but it does not receive `publish_posts`, `delete_posts`, or
`upload_files`. Write abilities remain disabled globally until a WordPress
administrator enables them. Even then, the managed identity cannot publish or
delete unless an administrator separately changes its role.

After successful enrollment, remove the one-time enrollment token from
`wp-config.php`. It is not needed for normal MCP connections. A future manual
retry requires a newly issued token.

## Enrollment request

The plugin sends:

```json
{
  "schema_version": "1.0",
  "instance_id": "uuid",
  "site_url": "https://example.com/",
  "endpoint_url": "https://example.com/wp-json/mcp/instahost-wordpress",
  "plugin_version": "1.1.0",
  "wordpress": "7.1",
  "credential": {
    "type": "bearer",
    "token": "ihmcp_<secret>"
  },
  "capabilities": {
    "read": true,
    "writes_enabled": false,
    "publish_or_delete": false
  }
}
```

The HTTP `Authorization` header carries the separate single-use enrollment
token. The payload never includes an administrator username/password, WordPress
Application Password, post content, settings, cookies, or database data.

The registry returns HTTP `2xx` and:

```json
{
  "registration_id": "registry-generated-id"
}
```

Missing or invalid responses fail closed. The plugin deletes the attempted
connection credential, records a non-secret error, and retries with exponential
backoff up to eight attempts. An administrator can retry or revoke under
**Settings > InstaHost MCP**.

## Registry requirements

The registry is a separate security boundary and must:

- Issue high-entropy, single-use, short-lived enrollment tokens and store only
  their hashes.
- Rate-limit enrollment by token, source, hostname, and instance ID.
- Reject non-HTTPS URLs, URL userinfo, redirects, private/link-local/loopback
  destinations, and mismatched `site_url`/`endpoint_url` hosts.
- Resolve all addresses before connecting and protect against DNS rebinding.
- Challenge the advertised MCP endpoint with the supplied connection token,
  initialize an MCP session, call `instahost-wordpress/get-site-info`, and
  verify that the returned site URL matches the enrollment payload.
- Encrypt connection tokens at rest with a registry-managed key and never log,
  return, expose, or place them in command arguments.
- Build clients with the pinned, reviewed
  `@automattic/mcp-wordpress-remote@0.4.0` bridge or a separately reviewed
  compatible client.
- Treat the token as scoped only to
  `/wp-json/mcp/instahost-wordpress`; a request to any other REST route must
  remain anonymous.
- Preserve the plugin's reported read/write capability state. Enrollment must
  never enable WordPress write abilities.
- Mark a registration disconnected when the token is revoked or endpoint
  verification fails.

Until an approved registry implements these controls, configure clients
manually with OAuth, JWT, or Application Passwords as described in the README.

## Recovery

- **Local revoke:** use **Settings > InstaHost MCP > Revoke connection**. The
  local token hash is removed immediately, so a registry copy can no longer
  authenticate.
- **Retry:** issue a new single-use enrollment token, update `wp-config.php`,
  then choose **Retry enrollment**.
- **Plugin rollback:** deactivate or uninstall the plugin. Uninstall removes
  the role and enrollment options. The dedicated user remains without the
  removed role so WordPress does not accidentally delete content it may own.
