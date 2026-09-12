<?php
/**
 * Verifies that ordinary installs do not phone home.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.1.0' );

$GLOBALS['options']   = array();
$GLOBALS['scheduled'] = array();

final class WP_Error {}

function add_action( mixed ...$args ): void {}
function add_filter( mixed ...$args ): void {}
function wp_parse_args( array $args, array $defaults ): array { return array_merge( $defaults, $args ); }
function get_option( string $name, mixed $default = false ): mixed { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( string $name, mixed $value, bool $autoload = true ): bool { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( string $name ): bool { unset( $GLOBALS['options'][ $name ] ); return true; }
function esc_url_raw( string $value ): string { return $value; }
function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
function wp_is_uuid( string $value ): bool { return false; }
function wp_generate_uuid4(): string { return '11111111-2222-4333-8444-555555555555'; }
function wp_next_scheduled( string $hook ): int|false { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( int $timestamp, string $hook ): bool { $GLOBALS['scheduled'][ $hook ] = $timestamp; return true; }

require_once dirname( __DIR__ ) . '/includes/class-instahost-wordpress-mcp-enrollment.php';

Instahost_WordPress_MCP_Enrollment::activate();
$state = Instahost_WordPress_MCP_Enrollment::state();
if ( 'unconfigured' !== $state['status'] || ! empty( $GLOBALS['scheduled'] ) ) {
	fwrite( STDERR, "FAIL: unconfigured install attempted managed enrollment\n" );
	exit( 1 );
}

fwrite( STDOUT, "PASS: unconfigured installs make no enrollment request\n" );
