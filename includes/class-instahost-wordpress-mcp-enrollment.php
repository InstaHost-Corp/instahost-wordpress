<?php
/**
 * Managed-install enrollment and dedicated MCP bearer authentication.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'ABSPATH' ) || exit;

final class Instahost_WordPress_MCP_Enrollment {
	private const CRON_HOOK       = 'instahost_wordpress_mcp_enroll';
	private const ROLE            = 'instahost_mcp_agent';
	private const OPTION_INSTANCE = 'instahost_wordpress_mcp_instance_id';
	private const OPTION_STATE    = 'instahost_wordpress_mcp_enrollment_state';
	private const OPTION_TOKEN    = 'instahost_wordpress_mcp_connection_token_hash';
	private const OPTION_USER     = 'instahost_wordpress_mcp_service_user_id';
	private const TOKEN_PREFIX    = 'ihmcp_';
	private const MAX_ATTEMPTS    = 8;

	/**
	 * Registers runtime, retry, authentication, and administrator hooks.
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, array( self::class, 'enroll' ) );
		add_action( 'admin_post_instahost_mcp_retry_enrollment', array( self::class, 'handle_retry' ) );
		add_action( 'admin_post_instahost_mcp_revoke_enrollment', array( self::class, 'handle_revoke' ) );
		add_filter( 'determine_current_user', array( self::class, 'authenticate_connection_token' ), 20 );
		add_filter( 'rest_authentication_errors', array( self::class, 'reject_invalid_connection_token' ), 20 );

		if ( self::configured() && in_array( self::state()['status'], array( 'pending', 'retrying' ), true ) ) {
			self::schedule( 5 );
		}
	}

	/**
	 * Starts asynchronous enrollment for a managed installation.
	 */
	public static function activate(): void {
		self::ensure_instance_id();
		if ( ! self::configured() ) {
			self::save_state(
				array(
					'status'          => 'unconfigured',
					'attempts'        => 0,
					'last_error'      => '',
					'registration_id' => '',
					'enrolled_at'     => '',
					'registry_host'   => '',
				)
			);
			return;
		}

		self::save_state(
			array(
				'status'          => 'pending',
				'attempts'        => 0,
				'last_error'      => '',
				'registration_id' => '',
				'enrolled_at'     => '',
				'registry_host'   => '',
			)
		);
		self::schedule( 5 );
	}

	/**
	 * Clears pending enrollment work without revoking an enrolled credential.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Registers this site and a route-scoped credential with the configured registry.
	 */
	public static function enroll(): void {
		if ( ! self::configured() ) {
			self::save_state(
				array(
					'status'          => 'unconfigured',
					'attempts'        => 0,
					'last_error'      => '',
					'registration_id' => '',
					'enrolled_at'     => '',
					'registry_host'   => '',
				)
			);
			return;
		}

		$state = self::state();
		if ( 'enrolled' === $state['status'] ) {
			return;
		}
		if ( $state['attempts'] >= self::MAX_ATTEMPTS ) {
			return;
		}

		$service_user = self::ensure_service_user();
		if ( is_wp_error( $service_user ) ) {
			self::record_failure( $service_user->get_error_message(), $state['attempts'] + 1 );
			return;
		}

		try {
			$connection_token = self::TOKEN_PREFIX . bin2hex( random_bytes( 32 ) );
		} catch ( Throwable $error ) {
			self::record_failure( 'Unable to generate a secure connection token.', $state['attempts'] + 1 );
			return;
		}
		update_option( self::OPTION_TOKEN, wp_hash_password( $connection_token ), false );

		$payload = array(
			'schema_version' => '1.0',
			'instance_id'    => self::ensure_instance_id(),
			'site_url'       => home_url( '/' ),
			'endpoint_url'   => rest_url( 'mcp/instahost-wordpress' ),
			'plugin_version' => INSTAHOST_WORDPRESS_MCP_VERSION,
			'wordpress'      => get_bloginfo( 'version' ),
			'credential'     => array(
				'type'  => 'bearer',
				'token' => $connection_token,
			),
			'capabilities'   => array(
				'read'              => true,
				'writes_enabled'    => (bool) get_option( 'instahost_wordpress_mcp_enable_writes', false ),
				'publish_or_delete' => false,
			),
		);

		$response = wp_safe_remote_post(
			self::registry_url(),
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Authorization' => 'Bearer ' . self::enrollment_token(),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ),
				'user-agent'  => 'InstaHost-WordPress-MCP/' . INSTAHOST_WORDPRESS_MCP_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			delete_option( self::OPTION_TOKEN );
			self::record_failure( $response->get_error_message(), $state['attempts'] + 1 );
			return;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['registration_id'] ) ) {
			delete_option( self::OPTION_TOKEN );
			self::record_failure( 'Registry rejected enrollment or returned an invalid response.', $state['attempts'] + 1 );
			return;
		}

		self::save_state(
			array(
				'status'          => 'enrolled',
				'attempts'        => $state['attempts'] + 1,
				'last_error'      => '',
				'registration_id' => sanitize_text_field( (string) $body['registration_id'] ),
				'enrolled_at'     => gmdate( 'c' ),
				'registry_host'   => (string) wp_parse_url( self::registry_url(), PHP_URL_HOST ),
			)
		);
	}

	/**
	 * Authenticates only plugin-issued tokens on the dedicated MCP route.
	 *
	 * @param int|false $user_id Existing authenticated user ID.
	 * @return int|false
	 */
	public static function authenticate_connection_token( $user_id ) {
		if ( $user_id || ! self::is_mcp_request() ) {
			return $user_id;
		}

		$token = self::request_token();
		if ( '' === $token || 0 !== strpos( $token, self::TOKEN_PREFIX ) ) {
			return $user_id;
		}

		$hash            = (string) get_option( self::OPTION_TOKEN, '' );
		$service_user_id = (int) get_option( self::OPTION_USER, 0 );
		if ( '' === $hash || ! $service_user_id || ! wp_check_password( $token, $hash ) ) {
			return $user_id;
		}

		return $service_user_id;
	}

	/**
	 * Makes a malformed or revoked plugin token fail closed without affecting JWT/OAuth.
	 *
	 * @param mixed $result Existing REST authentication result.
	 * @return mixed
	 */
	public static function reject_invalid_connection_token( $result ) {
		if ( null !== $result || ! self::is_mcp_request() || is_user_logged_in() ) {
			return $result;
		}

		$token = self::request_token();
		if ( 0 !== strpos( $token, self::TOKEN_PREFIX ) ) {
			return $result;
		}

		return new WP_Error(
			'instahost_mcp_invalid_connection_token',
			'The InstaHost MCP connection token is invalid or revoked.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Returns non-secret enrollment status for the settings page.
	 *
	 * @return array<string, mixed>
	 */
	public static function state(): array {
		$defaults = array(
			'status'          => self::configured() ? 'pending' : 'unconfigured',
			'attempts'        => 0,
			'last_error'      => '',
			'registration_id' => '',
			'enrolled_at'     => '',
			'registry_host'   => '',
		);
		$state = get_option( self::OPTION_STATE, array() );
		return wp_parse_args( is_array( $state ) ? $state : array(), $defaults );
	}

	/**
	 * Whether deployment-time managed enrollment is configured.
	 */
	public static function configured(): bool {
		$url   = self::registry_url();
		$token = self::enrollment_token();
		return '' !== $token && 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	}

	/**
	 * Administrator-triggered retry after configuration or a transient failure.
	 */
	public static function handle_retry(): void {
		self::assert_admin_action( 'instahost_mcp_retry_enrollment' );
		delete_option( self::OPTION_TOKEN );
		self::save_state(
			array(
				'status'          => self::configured() ? 'pending' : 'unconfigured',
				'attempts'        => 0,
				'last_error'      => '',
				'registration_id' => '',
				'enrolled_at'     => '',
				'registry_host'   => '',
			)
		);
		if ( self::configured() ) {
			self::schedule( 1 );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=instahost-wordpress-mcp' ) );
		exit;
	}

	/**
	 * Revokes the local connection immediately. Registry copies become unusable.
	 */
	public static function handle_revoke(): void {
		self::assert_admin_action( 'instahost_mcp_revoke_enrollment' );
		delete_option( self::OPTION_TOKEN );
		self::save_state(
			array(
				'status'          => 'revoked',
				'attempts'        => 0,
				'last_error'      => '',
				'registration_id' => '',
				'enrolled_at'     => '',
				'registry_host'   => '',
			)
		);
		self::deactivate();
		wp_safe_redirect( admin_url( 'options-general.php?page=instahost-wordpress-mcp' ) );
		exit;
	}

	/**
	 * @return int|WP_Error
	 */
	private static function ensure_service_user() {
		self::ensure_role();
		$user_id = (int) get_option( self::OPTION_USER, 0 );
		if ( $user_id && get_user_by( 'id', $user_id ) ) {
			return $user_id;
		}

		$instance = str_replace( '-', '', self::ensure_instance_id() );
		$login    = 'instahost-mcp-' . substr( $instance, 0, 12 );
		$existing = get_user_by( 'login', $login );
		if ( $existing instanceof WP_User ) {
			$user_id = (int) $existing->ID;
		} else {
			$user_id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_pass'    => wp_generate_password( 64, true, true ),
					'display_name' => 'InstaHost MCP Agent',
					'role'         => self::ROLE,
				)
			);
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			update_user_meta( $user_id, '_instahost_mcp_managed_user', '1' );
		}

		update_option( self::OPTION_USER, (int) $user_id, false );
		return (int) $user_id;
	}

	/**
	 * Creates a restricted role that can read/edit existing content but cannot
	 * publish or delete. Write tools remain globally disabled by default.
	 */
	private static function ensure_role(): void {
		$capabilities = array(
			'read'                 => true,
			'edit_posts'           => true,
			'edit_others_posts'    => true,
			'edit_published_posts' => true,
			'read_private_posts'   => true,
			'upload_files'         => false,
			'publish_posts'        => false,
			'delete_posts'         => false,
		);
		$role = get_role( self::ROLE );
		if ( ! $role ) {
			add_role( self::ROLE, 'InstaHost MCP Agent', $capabilities );
			return;
		}
		foreach ( $capabilities as $capability => $grant ) {
			$grant ? $role->add_cap( $capability ) : $role->remove_cap( $capability );
		}
	}

	/**
	 * @return string
	 */
	private static function ensure_instance_id(): string {
		$instance_id = (string) get_option( self::OPTION_INSTANCE, '' );
		if ( ! wp_is_uuid( $instance_id ) ) {
			$instance_id = wp_generate_uuid4();
			update_option( self::OPTION_INSTANCE, $instance_id, false );
		}
		return $instance_id;
	}

	/**
	 * @param string $message Failure message.
	 * @param int    $attempts Attempt count.
	 */
	private static function record_failure( string $message, int $attempts ): void {
		self::save_state(
			array(
				'status'          => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'retrying',
				'attempts'        => $attempts,
				'last_error'      => sanitize_text_field( $message ),
				'registration_id' => '',
				'enrolled_at'     => '',
				'registry_host'   => (string) wp_parse_url( self::registry_url(), PHP_URL_HOST ),
			)
		);
		if ( $attempts < self::MAX_ATTEMPTS ) {
			self::schedule( min( DAY_IN_SECONDS, 300 * ( 2 ** max( 0, $attempts - 1 ) ) ) );
		}
	}

	/**
	 * @param int $delay Delay in seconds.
	 */
	private static function schedule( int $delay ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + max( 1, $delay ), self::CRON_HOOK );
		}
	}

	/**
	 * @param array<string, mixed> $state State.
	 */
	private static function save_state( array $state ): void {
		update_option( self::OPTION_STATE, wp_parse_args( $state, self::state() ), false );
	}

	/**
	 * @return string
	 */
	private static function registry_url(): string {
		return defined( 'INSTAHOST_WORDPRESS_MCP_REGISTRY_URL' )
			? esc_url_raw( (string) INSTAHOST_WORDPRESS_MCP_REGISTRY_URL )
			: '';
	}

	/**
	 * @return string
	 */
	private static function enrollment_token(): string {
		return defined( 'INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN' )
			? trim( (string) INSTAHOST_WORDPRESS_MCP_ENROLLMENT_TOKEN )
			: '';
	}

	/**
	 * @return string
	 */
	private static function request_token(): string {
		$header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? trim( (string) $_SERVER['HTTP_AUTHORIZATION'] ) : '';
		if ( 0 !== stripos( $header, 'Bearer ' ) ) {
			return '';
		}
		return trim( substr( $header, 7 ) );
	}

	/**
	 * @return bool
	 */
	private static function is_mcp_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		if ( isset( $_GET['rest_route'] ) ) {
			if ( ! is_string( $_GET['rest_route'] ) ) {
				return false;
			}
			return '/mcp/instahost-wordpress' === untrailingslashit( wp_unslash( $_GET['rest_route'] ) );
		}
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		return (bool) preg_match( '#/(?:wp-json/)?mcp/instahost-wordpress/?$#', $path );
	}

	/**
	 * @param string $action Nonce action.
	 */
	private static function assert_admin_action( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage MCP enrollment.', 'instahost-wordpress-mcp' ) );
		}
		check_admin_referer( $action );
	}
}
