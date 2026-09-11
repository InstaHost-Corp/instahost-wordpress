<?php
/**
 * Standalone protocol smoke tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.0.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['writes_enabled'] = false;
$GLOBALS['logged_in']      = true;
$GLOBALS['capabilities']   = array( 'edit_posts' => true );

final class WP_REST_Response {
	public mixed $data;
	public int $status;

	public function __construct( mixed $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

final class WP_REST_Request {
	/**
	 * @param array<string, string> $headers Headers.
	 * @param array<string, mixed>  $payload JSON payload.
	 */
	public function __construct(
		private array $headers = array(),
		private array $payload = array()
	) {}

	public function get_header( string $name ): string {
		return $this->headers[ strtolower( $name ) ] ?? '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_json_params(): array {
		return $this->payload;
	}
}

final class WP_Post {
	public int $ID;
	public string $post_type = 'post';
	public string $post_password = '';
	public bool $revision = false;
	public bool $autosave = false;

	public function __construct( int $id ) {
		$this->ID = $id;
	}
}

function get_option( string $name, mixed $default = false ): mixed {
	return 'instahost_wordpress_mcp_enable_writes' === $name ? $GLOBALS['writes_enabled'] : $default;
}

function is_user_logged_in(): bool {
	return $GLOBALS['logged_in'];
}

function current_user_can( string $capability, mixed ...$args ): bool {
	return $GLOBALS['capabilities'][ $capability ] ?? false;
}

function sanitize_text_field( string $value ): string {
	return trim( $value );
}

function sanitize_key( string $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\\-]/', '', $value ) ?? '' );
}

function get_post_type_object( string $post_type ): ?object {
	if ( 'post' !== $post_type ) {
		return null;
	}

	return (object) array(
		'show_in_rest' => true,
		'cap'          => (object) array(
			'publish_posts' => 'publish_posts',
		),
	);
}

function wp_is_post_revision( WP_Post $post ): bool {
	return $post->revision;
}

function wp_is_post_autosave( WP_Post $post ): bool {
	return $post->autosave;
}

function home_url( string $path = '' ): string {
	return 'https://example.com' . $path;
}

function site_url( string $path = '' ): string {
	return 'https://example.com/wp' . $path;
}

function rest_url(): string {
	return 'https://example.com/wp-json/';
}

function wp_parse_url( string $url ): array|false {
	return parse_url( $url );
}

function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
	return $value;
}

function wp_json_encode( mixed $value, int $flags = 0 ): string|false {
	return json_encode( $value, $flags );
}

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function invoke_private( object $object, string $method, mixed ...$arguments ): mixed {
	$reflection = new ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}
	return $reflection->invoke( $object, ...$arguments );
}

require_once dirname( __DIR__ ) . '/includes/class-instahost-wordpress-mcp-server.php';

$server     = new Instahost_WordPress_MCP_Server();
$initialize = invoke_private( $server, 'initialize_result' );
$discover   = invoke_private( $server, 'discover_result' );
$tools      = invoke_private( $server, 'get_tools' );

assert_true( '2025-03-26' === $initialize['protocolVersion'], 'Legacy initialization version must remain supported.' );
assert_true( in_array( '2026-07-28', $discover['supportedVersions'], true ), 'Modern protocol version must be discoverable.' );
assert_true( 4 === count( $tools ), 'Write tools must be disabled by default.' );
assert_true( 'wordpress_get_site_info' === $tools[0]['name'], 'Tool order must be deterministic.' );

assert_true( true === $server->can_access(), 'Authenticated editors must access the endpoint.' );
$GLOBALS['logged_in'] = false;
assert_true( false === $server->can_access(), 'Anonymous users must not access the endpoint.' );
$GLOBALS['logged_in'] = true;

$GLOBALS['writes_enabled'] = true;
$write_tools               = invoke_private( $server, 'get_tools' );
assert_true( 7 === count( $write_tools ), 'Enabling writes must expose exactly three mutation tools.' );

$modern_payload = array(
	'jsonrpc' => '2.0',
	'id'      => 1,
	'method'  => 'server/discover',
	'params'  => array(
		'_meta' => array(
			'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
		),
	),
);
$modern_request = new WP_REST_Request(
	array(
		'mcp-protocol-version' => '2026-07-28',
		'mcp-method'           => 'server/discover',
	)
);
assert_true( null === invoke_private( $server, 'validate_protocol', $modern_request, $modern_payload, 1 ), 'Matching modern metadata must pass.' );

$bad_version_request = new WP_REST_Request(
	array(
		'mcp-protocol-version' => '2025-03-26',
		'mcp-method'           => 'server/discover',
	)
);
$bad_version = invoke_private( $server, 'validate_protocol', $bad_version_request, $modern_payload, 1 );
assert_true( 400 === $bad_version->status, 'Mismatched protocol headers must return HTTP 400.' );
assert_true( -32020 === $bad_version->data['error']['code'], 'Header mismatches must use the MCP HeaderMismatch code.' );

$missing_meta_payload = array(
	'jsonrpc' => '2.0',
	'id'      => 2,
	'method'  => 'tools/list',
	'params'  => array(),
);
$missing_meta_request = new WP_REST_Request(
	array(
		'mcp-protocol-version' => '2026-07-28',
		'mcp-method'           => 'tools/list',
	)
);
$missing_meta = invoke_private( $server, 'validate_protocol', $missing_meta_request, $missing_meta_payload, 2 );
assert_true( 400 === $missing_meta->status, 'Modern headers without body metadata must fail.' );
assert_true( -32020 === $missing_meta->data['error']['code'], 'Missing modern metadata must use the HeaderMismatch code.' );

$malformed_method = new WP_REST_Request(
	array(),
	array(
		'jsonrpc' => '2.0',
		'id'      => 3,
		'method'  => '<b>tools/call</b>',
		'params'  => array(
			'name'      => 'wordpress_delete_post',
			'arguments' => array( 'id' => 42 ),
		),
	)
);
$malformed_method_response = $server->handle_request( $malformed_method );
assert_true( 404 === $malformed_method_response->status, 'Malformed method identifiers must not be normalized into executable methods.' );

$malformed_tool = invoke_private(
	$server,
	'call_tool',
	array(
		'name'      => 'wordpress_delete_post!',
		'arguments' => array( 'id' => 42 ),
	)
);
assert_true( true === $malformed_tool['isError'], 'Malformed tool identifiers must not be normalized into executable tools.' );
assert_true( str_contains( $malformed_tool['content'][0]['text'], 'Unknown tool' ), 'Malformed tools must return an unknown-tool error.' );

$same_origin = new WP_REST_Request( array( 'origin' => 'https://example.com' ) );
$evil_origin = new WP_REST_Request( array( 'origin' => 'https://evil.example' ) );
assert_true( null === invoke_private( $server, 'validate_origin', $same_origin, 1 ), 'Same-origin browser requests must pass.' );
assert_true( 403 === invoke_private( $server, 'validate_origin', $evil_origin, 1 )->status, 'Cross-origin browser requests must fail closed.' );

$tool_result = invoke_private( $server, 'tool_result', array( 'ok' => true ) );
assert_true( 'complete' === $tool_result['resultType'], 'Modern tool results must be complete results.' );
assert_true( false === $tool_result['isError'], 'Successful tool results must not be marked as errors.' );

$post_type = get_post_type_object( 'post' );
$GLOBALS['capabilities']['publish_posts'] = false;
try {
	invoke_private( $server, 'assert_status_change_allowed', 'publish', $post_type );
	assert_true( false, 'Publishing without publish_posts must fail.' );
} catch ( RuntimeException $exception ) {
	assert_true( str_contains( $exception->getMessage(), 'cannot publish' ), 'Publishing must fail for the capability reason.' );
}
$GLOBALS['capabilities']['publish_posts'] = true;
invoke_private( $server, 'assert_status_change_allowed', 'publish', $post_type );

$GLOBALS['capabilities']['delete_post'] = false;
try {
	invoke_private( $server, 'assert_status_change_allowed', 'trash', $post_type, 42 );
	assert_true( false, 'Trashing without delete_post must fail.' );
} catch ( RuntimeException $exception ) {
	assert_true( str_contains( $exception->getMessage(), 'cannot trash' ), 'Trashing must fail for the capability reason.' );
}

$revision           = new WP_Post( 42 );
$revision->revision = true;
try {
	invoke_private( $server, 'assert_post_content_access', $revision );
	assert_true( false, 'Revision content must be rejected.' );
} catch ( RuntimeException $exception ) {
	assert_true( str_contains( $exception->getMessage(), 'Revisions' ), 'Revision rejection must be explicit.' );
}

$protected                = new WP_Post( 43 );
$protected->post_password = 'protected';
$GLOBALS['capabilities']['edit_post'] = false;
try {
	invoke_private( $server, 'assert_post_content_access', $protected );
	assert_true( false, 'Password-protected content must require edit access.' );
} catch ( RuntimeException $exception ) {
	assert_true( str_contains( $exception->getMessage(), 'Password-protected' ), 'Protected-content rejection must be explicit.' );
}
$GLOBALS['capabilities']['edit_post'] = true;
invoke_private( $server, 'assert_post_content_access', $protected );

fwrite( STDOUT, "PASS: protocol smoke tests\n" );
