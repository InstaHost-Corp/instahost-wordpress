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
					'args'    => array( '-y', '@automattic/mcp-wordpress-remote' ),
					'env'     => array(
						'WP_API_URL'     => $endpoint,
						'WP_API_USERNAME' => 'your-wordpress-username',
						'WP_API_PASSWORD' => 'your-application-password',
						'OAUTH_ENABLED'   => 'false',
					),
				),
			),
		);
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
