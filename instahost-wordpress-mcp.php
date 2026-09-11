<?php
/**
 * Plugin Name:       InstaHost WordPress MCP
 * Plugin URI:        https://insta.host/
 * Description:       Exposes authenticated WordPress content tools through the Model Context Protocol.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Requires Plugins:  mcp-adapter
 * Author:            InstaHost
 * Author URI:        https://insta.host/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       instahost-wordpress-mcp
 */

defined( 'ABSPATH' ) || exit;

define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.0.0' );
define( 'INSTAHOST_WORDPRESS_MCP_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-instahost-wordpress-mcp-abilities.php';
require_once __DIR__ . '/includes/class-instahost-wordpress-mcp-admin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		if ( is_admin() ) {
			$admin = new Instahost_WordPress_MCP_Admin();
			$admin->register();
		}
	}
);

add_action( 'wp_abilities_api_init', array( Instahost_WordPress_MCP_Abilities::class, 'register' ) );
add_action( 'mcp_adapter_init', array( Instahost_WordPress_MCP_Abilities::class, 'register_server' ) );
