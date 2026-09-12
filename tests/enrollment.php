<?php
/**
 * Managed enrollment and route-scoped bearer authentication tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'REST_REQUEST', true );
define( 'DAY_IN_SECONDS', 86400 );
define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.1.0' );
define( 'INSTAHOST_WORDPRESS_MCP_REGISTRY_URL', 'https://registry.example.test/v1/enroll' );
define( 'INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN', 'single-use-enrollment-token' );

$GLOBALS['options']          = array();
$GLOBALS['scheduled']        = array();
$GLOBALS['roles']            = array();
$GLOBALS['users']            = array();
$GLOBALS['remote_requests']  = array();
$GLOBALS['logged_in']        = false;
$GLOBALS['next_user_id']     = 100;
$_SERVER['REQUEST_URI']      = '/wp-json/mcp/instahost-wordpress';
$_SERVER['HTTP_AUTHORIZATION'] = '';

final class WP_Error {
	public function __construct(
		public string $code,
		public string $message,
		public array $data = array()
	) {}

	public function get_error_message(): string {
		return $this->message;
	}
}

final class WP_User {
	public function __construct( public int $ID, public string $user_login ) {}
}

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function add_action( mixed ...$args ): void {}
function add_filter( mixed ...$args ): void {}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function wp_parse_args( array $args, array $defaults ): array { return array_merge( $defaults, $args ); }
function get_option( string $name, mixed $default = false ): mixed { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( string $name, mixed $value, bool $autoload = true ): bool { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( string $name ): bool { unset( $GLOBALS['options'][ $name ] ); return true; }
function esc_url_raw( string $value ): string { return $value; }
function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
function wp_is_uuid( string $value ): bool { return 1 === preg_match( '/^[a-f0-9-]{36}$/', $value ); }
function wp_generate_uuid4(): string { return '11111111-2222-4333-8444-555555555555'; }
function wp_next_scheduled( string $hook ): int|false { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( int $timestamp, string $hook ): bool { $GLOBALS['scheduled'][ $hook ] = $timestamp; return true; }
function wp_unschedule_event( int $timestamp, string $hook ): bool { unset( $GLOBALS['scheduled'][ $hook ] ); return true; }
function get_role( string $role ): mixed { return $GLOBALS['roles'][ $role ] ?? null; }
function add_role( string $role, string $label, array $capabilities ): object { $GLOBALS['roles'][ $role ] = (object) compact( 'role', 'label', 'capabilities' ); return $GLOBALS['roles'][ $role ]; }
function get_user_by( string $field, mixed $value ): WP_User|false {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( ( 'id' === $field && $user->ID === (int) $value ) || ( 'login' === $field && $user->user_login === $value ) ) {
			return $user;
		}
	}
	return false;
}
function wp_insert_user( array $data ): int {
	$id = ++$GLOBALS['next_user_id'];
	$GLOBALS['users'][ $id ] = new WP_User( $id, $data['user_login'] );
	return $id;
}
function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string { return str_repeat( 'x', $length ); }
function update_user_meta( int $user_id, string $key, mixed $value ): bool { return true; }
function wp_hash_password( string $token ): string { return password_hash( $token, PASSWORD_DEFAULT ); }
function wp_check_password( string $token, string $hash ): bool { return password_verify( $token, $hash ); }
function home_url( string $path = '' ): string { return 'https://site.example.test' . $path; }
function rest_url( string $path = '' ): string { return 'https://site.example.test/wp-json/' . ltrim( $path, '/' ); }
function get_bloginfo( string $field ): string { return '7.1'; }
function wp_json_encode( mixed $value, int $flags = 0 ): string|false { return json_encode( $value, $flags ); }
function wp_safe_remote_post( string $url, array $args ): array {
	$GLOBALS['remote_requests'][] = array( 'url' => $url, 'args' => $args );
	return array(
		'response' => array( 'code' => 201 ),
		'body'     => '{"registration_id":"reg_test_123"}',
	);
}
function wp_remote_retrieve_response_code( array $response ): int { return (int) $response['response']['code']; }
function wp_remote_retrieve_body( array $response ): string { return (string) $response['body']; }
function sanitize_text_field( string $value ): string { return trim( $value ); }
function is_user_logged_in(): bool { return $GLOBALS['logged_in']; }
function wp_unslash( string $value ): string { return $value; }
function untrailingslashit( string $value ): string { return rtrim( $value, '/\\' ); }

require_once dirname( __DIR__ ) . '/includes/class-instahost-wordpress-mcp-enrollment.php';

Instahost_WordPress_MCP_Enrollment::activate();
assert_true( 1 === count( $GLOBALS['scheduled'] ), 'Configured activation must schedule one enrollment event.' );

$GLOBALS['scheduled'] = array();
Instahost_WordPress_MCP_Enrollment::enroll();
assert_true( 1 === count( $GLOBALS['remote_requests'] ), 'Enrollment must make exactly one registry request.' );

$request = $GLOBALS['remote_requests'][0];
assert_true( INSTAHOST_WORDPRESS_MCP_REGISTRY_URL === $request['url'], 'Enrollment must use the configured HTTPS registry URL.' );
assert_true(
	'Bearer ' . INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN === $request['args']['headers']['Authorization'],
	'Enrollment must authenticate with the deployment-time one-time token.'
);
$payload = json_decode( $request['args']['body'], true );
assert_true( is_array( $payload ), 'Enrollment payload must be JSON.' );
assert_true( 'https://site.example.test/wp-json/mcp/instahost-wordpress' === $payload['endpoint_url'], 'Payload must advertise the dedicated endpoint.' );
assert_true( str_starts_with( $payload['credential']['token'], 'ihmcp_' ), 'Connection credential must use the plugin-specific prefix.' );
assert_true( ! isset( $payload['administrator'], $payload['content'], $payload['application_password'] ), 'Payload must not contain administrator credentials or content.' );
assert_true( false === $payload['capabilities']['publish_or_delete'], 'Managed identity must not advertise publish/delete authority.' );

$state = Instahost_WordPress_MCP_Enrollment::state();
assert_true( 'enrolled' === $state['status'], 'Successful registry response must persist enrolled state.' );
assert_true( 'reg_test_123' === $state['registration_id'], 'Registration ID must be persisted.' );
Instahost_WordPress_MCP_Enrollment::enroll();
assert_true( 1 === count( $GLOBALS['remote_requests'] ), 'An enrolled site must not rotate its credential on a duplicate cron invocation.' );

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $payload['credential']['token'];
$user_id = Instahost_WordPress_MCP_Enrollment::authenticate_connection_token( false );
assert_true( 101 === $user_id, 'Valid managed token must authenticate as the dedicated service user.' );

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer external-jwt-token';
assert_true( false === Instahost_WordPress_MCP_Enrollment::authenticate_connection_token( false ), 'Non-plugin bearer tokens must be left for JWT/OAuth providers.' );
assert_true( null === Instahost_WordPress_MCP_Enrollment::reject_invalid_connection_token( null ), 'Non-plugin bearer tokens must not be rejected.' );

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ihmcp_invalid';
assert_true( false === Instahost_WordPress_MCP_Enrollment::authenticate_connection_token( false ), 'Invalid managed token must not authenticate.' );
$invalid = Instahost_WordPress_MCP_Enrollment::reject_invalid_connection_token( null );
assert_true( $invalid instanceof WP_Error && 401 === $invalid->data['status'], 'Invalid managed token must fail closed with 401.' );

$_SERVER['REQUEST_URI']        = '/wp-json/wp/v2/posts';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $payload['credential']['token'];
assert_true( false === Instahost_WordPress_MCP_Enrollment::authenticate_connection_token( false ), 'Managed token must not authenticate outside the dedicated MCP route.' );

fwrite( STDOUT, "PASS: managed enrollment and route-scoped bearer authentication\n" );
