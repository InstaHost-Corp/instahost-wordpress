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
	 */
	public function __construct( private array $headers = array() ) {}

	public function get_header( string $name ): string {
		return $this->headers[ strtolower( $name ) ] ?? '';
	}
}

function get_option( string $name, mixed $default = false ): mixed {
	return 'instahost_wordpress_mcp_enable_writes' === $name ? $GLOBALS['writes_enabled'] : $default;
}

function sanitize_text_field( string $value ): string {
	return trim( $value );
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

$same_origin = new WP_REST_Request( array( 'origin' => 'https://example.com' ) );
$evil_origin = new WP_REST_Request( array( 'origin' => 'https://evil.example' ) );
assert_true( null === invoke_private( $server, 'validate_origin', $same_origin, 1 ), 'Same-origin browser requests must pass.' );
assert_true( 403 === invoke_private( $server, 'validate_origin', $evil_origin, 1 )->status, 'Cross-origin browser requests must fail closed.' );

$tool_result = invoke_private( $server, 'tool_result', array( 'ok' => true ) );
assert_true( 'complete' === $tool_result['resultType'], 'Modern tool results must be complete results.' );
assert_true( false === $tool_result['isError'], 'Successful tool results must not be marked as errors.' );

fwrite( STDOUT, "PASS: protocol smoke tests\n" );
