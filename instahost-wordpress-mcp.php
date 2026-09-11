<?php
/**
 * Plugin Name:       InstaHost WordPress MCP
 * Plugin URI:        https://insta.host/
 * Description:       Exposes authenticated WordPress content tools through the Model Context Protocol.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            InstaHost
 * Author URI:        https://insta.host/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       instahost-wordpress-mcp
 */

defined( 'ABSPATH' ) || exit;

define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.0.0' );
define( 'INSTAHOST_WORDPRESS_MCP_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-instahost-wordpress-mcp-server.php';
require_once __DIR__ . '/includes/class-instahost-wordpress-mcp-admin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		$server = new Instahost_WordPress_MCP_Server();
		$server->register();

		if ( is_admin() ) {
			$admin = new Instahost_WordPress_MCP_Admin();
			$admin->register();
		}
	}
);
