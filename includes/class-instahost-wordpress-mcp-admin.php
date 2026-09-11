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

		$endpoint = rest_url( 'instahost-mcp/v1/mcp' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'InstaHost WordPress MCP', 'instahost-wordpress-mcp' ); ?></h1>
			<p>
				<?php esc_html_e( 'Connect an MCP client to this authenticated endpoint:', 'instahost-wordpress-mcp' ); ?>
				<code><?php echo esc_html( $endpoint ); ?></code>
			</p>
			<p>
				<?php esc_html_e( 'Create a WordPress Application Password for a user with the Editor or Administrator role, then use HTTP Basic authentication.', 'instahost-wordpress-mcp' ); ?>
			</p>
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
