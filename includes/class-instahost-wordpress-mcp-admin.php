<?php
/**
 * Admin settings.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'ABSPATH' ) || exit;

final class Instahost_WordPress_MCP_Admin {
	private const OPTION_WRITES = 'instahost_wordpress_mcp_enable_writes';

	/**
	 * Registers admin hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Adds the settings page.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'InstaHost WordPress MCP', 'instahost-wordpress-mcp' ),
			__( 'InstaHost MCP', 'instahost-wordpress-mcp' ),
			'manage_options',
			'instahost-wordpress-mcp',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			'instahost_wordpress_mcp',
			self::OPTION_WRITES,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$endpoint = rest_url( 'mcp/instahost-wordpress' );
		$config   = array(
			'mcpServers' => array(
				'instahost-wordpress' => array(
					'command' => 'npx',
					'args'    => array( '-y', '@automattic/mcp-wordpress-remote@0.4.0' ),
					'env'     => array(
						'WP_API_URL'     => $endpoint,
						'WP_API_USERNAME' => 'your-wordpress-username',
						'WP_API_PASSWORD' => 'your-application-password',
						'OAUTH_ENABLED'   => 'false',
					),
				),
			),
		);
		$enrollment = Instahost_WordPress_MCP_Enrollment::state();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'InstaHost WordPress MCP', 'instahost-wordpress-mcp' ); ?></h1>
			<p>
				<?php esc_html_e( 'This plugin uses the official WordPress MCP Adapter. Connect through this endpoint:', 'instahost-wordpress-mcp' ); ?>
				<code><?php echo esc_html( $endpoint ); ?></code>
			</p>
			<p>
				<?php esc_html_e( 'For desktop MCP clients, use the Automattic remote bridge with OAuth, JWT, or a WordPress Application Password.', 'instahost-wordpress-mcp' ); ?>
			</p>
			<h2><?php esc_html_e( 'Managed automatic connection', 'instahost-wordpress-mcp' ); ?></h2>
			<p>
				<?php esc_html_e( 'Enrollment status:', 'instahost-wordpress-mcp' ); ?>
				<strong><?php echo esc_html( (string) $enrollment['status'] ); ?></strong>
			</p>
			<?php if ( ! empty( $enrollment['registry_host'] ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Registry: %s', 'instahost-wordpress-mcp' ), $enrollment['registry_host'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $enrollment['last_error'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $enrollment['last_error'] ); ?></p></div>
			<?php endif; ?>
			<p class="description">
				<?php esc_html_e( 'Managed enrollment runs automatically only when the registry URL and one-time enrollment token are supplied through wp-config.php. It creates a restricted, revocable MCP identity and never sends administrator credentials or site content.', 'instahost-wordpress-mcp' ); ?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=instahost_mcp_retry_enrollment' ), 'instahost_mcp_retry_enrollment' ) ); ?>">
					<?php esc_html_e( 'Retry enrollment', 'instahost-wordpress-mcp' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=instahost_mcp_revoke_enrollment' ), 'instahost_mcp_revoke_enrollment' ) ); ?>">
					<?php esc_html_e( 'Revoke connection', 'instahost-wordpress-mcp' ); ?>
				</a>
			</p>
			<h2><?php esc_html_e( 'Application Password client configuration', 'instahost-wordpress-mcp' ); ?></h2>
			<textarea class="large-text code" rows="16" readonly><?php echo esc_textarea( wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
			<form method="post" action="options.php">
				<?php settings_fields( 'instahost_wordpress_mcp' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Write tools', 'instahost-wordpress-mcp' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_WRITES ); ?>"
									value="1"
									<?php checked( get_option( self::OPTION_WRITES, false ) ); ?>
								/>
								<?php esc_html_e( 'Enable create, update, trash, and delete tools', 'instahost-wordpress-mcp' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disabled by default. WordPress capability checks still apply when enabled.', 'instahost-wordpress-mcp' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
