<?php
/**
 * WordPress abilities exposed through MCP Adapter.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'ABSPATH' ) || exit;

final class Instahost_WordPress_MCP_Abilities {
	private const OPTION_WRITES = 'instahost_wordpress_mcp_enable_writes';

	/**
	 * Registers the plugin's WordPress abilities.
	 */
	public static function register(): void {
		foreach ( self::definitions() as $name => $definition ) {
			wp_register_ability( $name, $definition );
		}
	}

	/**
	 * Registers a focused MCP Adapter server for these abilities.
	 *
	 * @param object $adapter MCP Adapter instance.
	 */
	public static function register_server( object $adapter ): void {
		$server = $adapter->create_server(
			'instahost-wordpress',
			'mcp',
			'instahost-wordpress',
			'InstaHost WordPress MCP',
			'Focused WordPress content management abilities from InstaHost.',
			INSTAHOST_WORDPRESS_MCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			self::ability_names(),
			array(),
			array(),
			static function () {
				if ( ! is_user_logged_in() ) {
					return new WP_Error(
						'instahost_mcp_authentication_required',
						'Authentication is required.',
						array( 'status' => 401 )
					);
				}

				if ( ! current_user_can( 'edit_posts' ) ) {
					return new WP_Error(
						'instahost_mcp_insufficient_capability',
						'The edit_posts capability is required.',
						array( 'status' => 403 )
					);
				}

				return true;
			}
		);

		if ( is_wp_error( $server ) ) {
			error_log( 'InstaHost WordPress MCP server registration failed: ' . $server->get_error_message() );
		}
	}

	/**
	 * Returns registered ability names in deterministic order.
	 *
	 * @return string[]
	 */
	public static function ability_names(): array {
		$names = array(
			'instahost-wordpress/get-site-info',
			'instahost-wordpress/list-posts',
			'instahost-wordpress/get-post',
			'instahost-wordpress/search',
		);

		if ( self::writes_enabled() ) {
			$names[] = 'instahost-wordpress/create-post';
			$names[] = 'instahost-wordpress/update-post';
			$names[] = 'instahost-wordpress/delete-post';
		}

		return $names;
	}

	/**
	 * Returns ability definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function definitions(): array {
		$definitions = array(
			'instahost-wordpress/get-site-info' => array(
				'label'               => 'Get WordPress Site Information',
				'description'         => 'Get basic information about this WordPress site.',
				'category'            => 'site',
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'get_site_info' ),
				'permission_callback' => array( self::class, 'can_read_site' ),
				'meta'                => self::meta( true, false, true ),
			),
			'instahost-wordpress/list-posts'    => array(
				'label'               => 'List WordPress Content',
				'description'         => 'List posts, pages, or another REST-visible post type.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'default' => 'post' ),
						'status'    => array(
							'type'    => 'string',
							'enum'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
							'default' => 'publish',
						),
						'search'    => array( 'type' => 'string' ),
						'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ),
					),
				),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'list_posts' ),
				'permission_callback' => array( self::class, 'can_list_posts' ),
				'meta'                => self::meta( true, false, true ),
			),
			'instahost-wordpress/get-post'      => array(
				'label'               => 'Get WordPress Content',
				'description'         => 'Get a post or page by ID. Revisions, autosaves, and inaccessible protected content are excluded.',
				'category'            => 'site',
				'input_schema'        => self::id_schema(),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'get_post' ),
				'permission_callback' => array( self::class, 'can_get_post' ),
				'meta'                => self::meta( true, false, true ),
			),
			'instahost-wordpress/search'        => array(
				'label'               => 'Search WordPress Content',
				'description'         => 'Search published WordPress posts and pages.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array( 'type' => 'string', 'minLength' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
					),
					'required'   => array( 'query' ),
				),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'search' ),
				'permission_callback' => array( self::class, 'can_read_site' ),
				'meta'                => self::meta( true, false, true ),
			),
		);

		if ( self::writes_enabled() ) {
			$definitions['instahost-wordpress/create-post'] = array(
				'label'               => 'Create WordPress Content',
				'description'         => 'Create a WordPress post, page, or another REST-visible post type.',
				'category'            => 'site',
				'input_schema'        => self::write_schema( false ),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'create_post' ),
				'permission_callback' => array( self::class, 'can_create_post' ),
				'meta'                => self::meta( false, false, false ),
			);
			$definitions['instahost-wordpress/update-post'] = array(
				'label'               => 'Update WordPress Content',
				'description'         => 'Update a WordPress post or page.',
				'category'            => 'site',
				'input_schema'        => self::write_schema( true ),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'update_post' ),
				'permission_callback' => array( self::class, 'can_update_post' ),
				'meta'                => self::meta( false, true, false ),
			);
			$definitions['instahost-wordpress/delete-post'] = array(
				'label'               => 'Delete WordPress Content',
				'description'         => 'Move content to trash or permanently delete it.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
						'force' => array( 'type' => 'boolean', 'default' => false ),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => self::object_output_schema(),
				'execute_callback'    => array( self::class, 'delete_post' ),
				'permission_callback' => array( self::class, 'can_delete_post' ),
				'meta'                => self::meta( false, true, false ),
			);
		}

		return $definitions;
	}

	/**
	 * Checks broad read access.
	 */
	public static function can_read_site(): bool {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Checks list access for the requested post type and status.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return bool|WP_Error
	 */
	public static function can_list_posts( array $input = array() ) {
		if ( ! self::can_read_site() ) {
			return false;
		}

		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
		$status    = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';
		$object    = self::accessible_post_type( $post_type );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		if ( 'private' === $status && ! current_user_can( $object->cap->read_private_posts ) ) {
			return new WP_Error( 'instahost_mcp_private_content_denied', 'Private content access is denied.' );
		}

		return true;
	}

	/**
	 * Checks access to one post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return bool|WP_Error
	 */
	public static function can_get_post( array $input = array() ) {
		if ( ! self::can_read_site() ) {
			return false;
		}

		$post = get_post( absint( $input['id'] ?? 0 ) );
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'instahost_mcp_post_denied', 'Content not found or access denied.' );
		}

		return self::check_content_access( $post );
	}

	/**
	 * Checks post creation access.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return bool|WP_Error
	 */
	public static function can_create_post( array $input = array() ) {
		if ( ! self::writes_enabled() ) {
			return new WP_Error( 'instahost_mcp_writes_disabled', 'Write abilities are disabled.' );
		}

		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
		$object    = self::accessible_post_type( $post_type );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		if ( ! current_user_can( $object->cap->create_posts ) ) {
			return new WP_Error( 'instahost_mcp_create_denied', 'Content creation is denied.' );
		}

		return self::check_status_permission(
			isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft',
			$object
		);
	}

	/**
	 * Checks post update access.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return bool|WP_Error
	 */
	public static function can_update_post( array $input = array() ) {
		if ( ! self::writes_enabled() ) {
			return new WP_Error( 'instahost_mcp_writes_disabled', 'Write abilities are disabled.' );
		}

		$id   = absint( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'instahost_mcp_update_denied', 'Content not found or update denied.' );
		}

		$object = self::accessible_post_type( $post->post_type );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		if ( ! isset( $input['status'] ) ) {
			return true;
		}

		return self::check_status_permission( sanitize_key( (string) $input['status'] ), $object, $id );
	}

	/**
	 * Checks post deletion access.
	 *
	 * @param array<string, mixed> $input Ability input.
	 */
	public static function can_delete_post( array $input = array() ): bool {
		$id = absint( $input['id'] ?? 0 );
		if ( ! self::writes_enabled() || 0 === $id ) {
			return false;
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return false;
		}

		return ! is_wp_error( self::accessible_post_type( $post->post_type ) )
			&& current_user_can( 'delete_post', $id );
	}

	/**
	 * Returns site metadata.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_site_info(): array {
		return array(
			'name'              => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'url'               => home_url( '/' ),
			'wordpress_version' => get_bloginfo( 'version' ),
			'language'          => get_bloginfo( 'language' ),
			'timezone'          => wp_timezone_string(),
			'post_types'        => array_values( get_post_types( array( 'show_in_rest' => true ), 'names' ) ),
			'writes_enabled'    => self::writes_enabled(),
		);
	}

	/**
	 * Lists WordPress content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function list_posts( array $input = array() ): array {
		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
		$status    = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';
		$per_page  = min( 100, max( 1, absint( $input['per_page'] ?? 10 ) ) );
		$page      = max( 1, absint( $input['page'] ?? 1 ) );
		$object    = self::accessible_post_type( $post_type );
		if ( is_wp_error( $object ) ) {
			return array( 'error' => $object->get_error_message() );
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			's'              => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( 'publish' !== $status && ! current_user_can( $object->cap->edit_others_posts ) ) {
			$args['author'] = get_current_user_id();
		}

		$query = new WP_Query( $args );
		$posts = array_filter(
			$query->posts,
			static function ( WP_Post $post ): bool {
				return current_user_can( 'read_post', $post->ID );
			}
		);

		return array(
			'items'       => array_values( array_map( array( self::class, 'format_post' ), $posts ) ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Gets one post.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_post( array $input = array() ) {
		$post = get_post( absint( $input['id'] ?? 0 ) );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'instahost_mcp_post_missing', 'Content not found.' );
		}

		$access = self::check_content_access( $post );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		return self::format_post( $post, true );
	}

	/**
	 * Searches published posts and pages.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function search( array $input = array() ): array {
		$query = new WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				's'              => sanitize_text_field( (string) ( $input['query'] ?? '' ) ),
				'posts_per_page' => min( 50, max( 1, absint( $input['per_page'] ?? 10 ) ) ),
			)
		);

		return array(
			'items' => array_map( array( self::class, 'format_post' ), $query->posts ),
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Creates WordPress content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_post( array $input = array() ) {
		$post_type = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : 'post';
		$id        = wp_insert_post( self::prepare_post_data( $input, $post_type ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return self::format_post( get_post( $id ), true );
	}

	/**
	 * Updates WordPress content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_post( array $input = array() ) {
		$id   = absint( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'instahost_mcp_post_missing', 'Content not found.' );
		}

		$data       = self::prepare_post_data( $input, $post->post_type );
		$data['ID'] = $id;
		$result     = wp_update_post( $data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::format_post( get_post( $result ), true );
	}

	/**
	 * Deletes WordPress content.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function delete_post( array $input = array() ) {
		$id     = absint( $input['id'] ?? 0 );
		$force  = rest_sanitize_boolean( $input['force'] ?? false );
		$post   = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'instahost_mcp_delete_failed', 'Content could not be deleted.' );
		}

		if ( ! $force && 'trash' === $post->post_status ) {
			return new WP_Error(
				'instahost_mcp_already_trashed',
				'Content is already in the trash. Set force to true to permanently delete it.'
			);
		}

		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result instanceof WP_Post ) {
			return new WP_Error( 'instahost_mcp_delete_failed', 'Content could not be deleted.' );
		}

		return array(
			'id'      => $id,
			'deleted' => $force,
			'trashed' => ! $force && 'trash' === get_post_status( $id ),
		);
	}

	/**
	 * Checks access to full content.
	 *
	 * @return true|WP_Error
	 */
	private static function check_content_access( WP_Post $post ) {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return new WP_Error( 'instahost_mcp_historical_content_denied', 'Revisions and autosaves are unavailable.' );
		}

		$object = self::accessible_post_type( $post->post_type );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		if ( '' !== $post->post_password && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'instahost_mcp_protected_content_denied', 'Password-protected content requires edit access.' );
		}

		return true;
	}

	/**
	 * Checks status transition capabilities.
	 *
	 * @param object $post_type_object WordPress post type object.
	 * @return true|WP_Error
	 */
	private static function check_status_permission( string $status, object $post_type_object, int $post_id = 0 ) {
		$allowed = array( 'draft', 'pending', 'publish', 'private', 'trash' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'instahost_mcp_invalid_status', 'Invalid or unsupported post status.' );
		}

		if (
			in_array( $status, array( 'publish', 'private' ), true )
			&& ! current_user_can( $post_type_object->cap->publish_posts )
		) {
			return new WP_Error( 'instahost_mcp_publish_denied', 'Publishing is denied.' );
		}

		if ( 'trash' === $status && ( 0 === $post_id || ! current_user_can( 'delete_post', $post_id ) ) ) {
			return new WP_Error( 'instahost_mcp_trash_denied', 'Trashing is denied.' );
		}

		return true;
	}

	/**
	 * Resolves a REST-visible post type.
	 *
	 * @return object|WP_Error
	 */
	private static function accessible_post_type( string $post_type ) {
		$object = get_post_type_object( $post_type );
		if ( ! $object || ! $object->show_in_rest ) {
			return new WP_Error( 'instahost_mcp_invalid_post_type', 'Invalid or inaccessible post type.' );
		}

		return $object;
	}

	/**
	 * Builds safe post data.
	 *
	 * @param array<string, mixed> $input     Ability input.
	 * @return array<string, mixed>
	 */
	private static function prepare_post_data( array $input, string $post_type ): array {
		$data = array( 'post_type' => $post_type );
		$map  = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
			'status'  => 'post_status',
			'slug'    => 'post_name',
		);

		foreach ( $map as $argument => $field ) {
			if ( ! array_key_exists( $argument, $input ) ) {
				continue;
			}

			$value          = (string) $input[ $argument ];
			$data[ $field ] = in_array( $argument, array( 'content', 'excerpt' ), true )
				? wp_kses_post( $value )
				: sanitize_text_field( $value );
		}

		return $data;
	}

	/**
	 * Formats content for ability output.
	 *
	 * @return array<string, mixed>
	 */
	public static function format_post( ?WP_Post $post, bool $include_content = false ): array {
		if ( ! $post ) {
			return array();
		}

		$result = array(
			'id'       => $post->ID,
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'title'    => get_the_title( $post ),
			'slug'     => $post->post_name,
			'excerpt'  => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'url'      => get_permalink( $post ),
			'modified' => get_post_modified_time( DATE_ATOM, true, $post ),
		);

		if ( $include_content ) {
			$result['content'] = $post->post_content;
		}

		return $result;
	}

	/**
	 * Returns whether mutation abilities are enabled.
	 */
	private static function writes_enabled(): bool {
		return (bool) get_option( self::OPTION_WRITES, false );
	}

	/**
	 * Builds MCP metadata.
	 *
	 * @return array<string, mixed>
	 */
	private static function meta( bool $read_only, bool $destructive, bool $idempotent ): array {
		return array(
			'public'      => true,
			'annotations' => array(
				'readOnlyHint'    => $read_only,
				'destructiveHint' => $destructive,
				'idempotentHint'  => $idempotent,
				'openWorldHint'   => false,
			),
		);
	}

	/**
	 * Generic object output schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function object_output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
		);
	}

	/**
	 * ID input schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function id_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'required'   => array( 'id' ),
		);
	}

	/**
	 * Write ability input schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function write_schema( bool $require_id ): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer', 'minimum' => 1 ),
				'post_type' => array( 'type' => 'string', 'default' => 'post' ),
				'title'     => array( 'type' => 'string', 'minLength' => 1 ),
				'content'   => array( 'type' => 'string' ),
				'excerpt'   => array( 'type' => 'string' ),
				'status'    => array(
					'type'    => 'string',
					'enum'    => array( 'draft', 'pending', 'publish', 'private', 'trash' ),
					'default' => 'draft',
				),
				'slug'      => array( 'type' => 'string' ),
			),
			'required'   => $require_id ? array( 'id' ) : array( 'title' ),
		);
	}
}
