<?php
/**
 * Removes plugin options.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'instahost_wordpress_mcp_enable_writes' );
delete_option( 'instahost_wordpress_mcp_instance_id' );
delete_option( 'instahost_wordpress_mcp_enrollment_state' );
delete_option( 'instahost_wordpress_mcp_connection_token_hash' );
delete_option( 'instahost_wordpress_mcp_service_user_id' );
remove_role( 'instahost_mcp_agent' );
