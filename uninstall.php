<?php
/**
 * Removes plugin options.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'instahost_wordpress_mcp_enable_writes' );
