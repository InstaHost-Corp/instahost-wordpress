<?php
/**
 * MCP server implementation.
 *
 * @package InstaHost_WordPress_MCP
 */

defined( 'ABSPATH' ) || exit;

final class Instahost_WordPress_MCP_Server {
	private const REST_NAMESPACE = 'instahost-mcp/v1';
	private const REST_ROUTE     = '/mcp';
	private const OPTION_WRITES  = 'instahost_wordpress_mcp_enable_writes';
	private const MODERN_VERSION = '2026-07-28';
	private const LEGACY_VERSION = '2025-03-26';

	/**
	 * Registers WordPress hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/**
	 * Registers the MCP endpoint.
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);
	}

	/**
	 * Restricts MCP access to authenticated users who can edit posts.
	 */
	public function can_access(): bool {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Handles an MCP JSON-RPC request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) || empty( $payload['method'] ) ) {
			return $this->error_response( null, -32600, 'Invalid JSON-RPC request.', 400 );
		}

		$id     = $payload['id'] ?? null;
		$method = sanitize_text_field( (string) $payload['method'] );
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		$origin_error = $this->validate_origin( $request, $id );
		if ( $origin_error ) {
			return $origin_error;
		}

		$protocol_error = $this->validate_protocol( $request, $payload, $id );
		if ( $protocol_error ) {
			return $protocol_error;
		}

		try {
			switch ( $method ) {
				case 'initialize':
					return $this->success_response( $id, $this->initialize_result() );
				case 'server/discover':
					return $this->success_response( $id, $this->discover_result() );
				case 'ping':
					return $this->success_response( $id, (object) array() );
				case 'tools/list':
					return $this->success_response(
						$id,
						array(
							'resultType' => 'complete',
							'tools'      => $this->get_tools(),
							'ttlMs'      => MINUTE_IN_SECONDS * 5 * 1000,
							'cacheScope' => 'private',
						)
					);
				case 'tools/call':
					return $this->success_response( $id, $this->call_tool( $params ) );
				case 'notifications/initialized':
				case 'notifications/cancelled':
					return new WP_REST_Response( null, 202 );
				default:
					return $this->error_response( $id, -32601, 'Method not found.', 404 );
			}
		} catch ( Throwable $throwable ) {
			error_log( 'InstaHost WordPress MCP error: ' . $throwable->getMessage() );
			return $this->error_response( $id, -32603, 'Internal server error.', 500 );
		}
	}

	/**
	 * Returns MCP server metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function initialize_result(): array {
		return array(
			'protocolVersion' => self::LEGACY_VERSION,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => array(
				'name'    => 'instahost-wordpress-mcp',
				'title'   => 'InstaHost WordPress MCP',
				'version' => INSTAHOST_WORDPRESS_MCP_VERSION,
			),
			'instructions'    => 'Use these tools to inspect WordPress content. Write tools are available only when enabled by an administrator.',
		);
	}

	/**
	 * Returns modern MCP discovery metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function discover_result(): array {
		return array(
			'resultType'        => 'complete',
			'supportedVersions' => array( self::MODERN_VERSION, self::LEGACY_VERSION ),
			'capabilities'      => array(
				'tools' => (object) array(),
			),
			'_meta'             => array(
				'io.modelcontextprotocol/serverInfo' => array(
					'name'    => 'instahost-wordpress-mcp',
					'version' => INSTAHOST_WORDPRESS_MCP_VERSION,
				),
			),
			'instructions'      => 'Use these tools to inspect WordPress content. Write tools are available only when enabled by an administrator.',
			'ttlMs'             => HOUR_IN_SECONDS * 1000,
			'cacheScope'        => 'private',
		);
	}

	/**
	 * Returns available MCP tools.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_tools(): array {
		$tools = array(
			array(
				'name'        => 'wordpress_get_site_info',
				'description' => 'Get basic information about this WordPress site.',
				'inputSchema' => $this->object_schema(),
				'annotations' => array( 'readOnlyHint' => true ),
			),
			array(
				'name'        => 'wordpress_list_posts',
				'description' => 'List posts, pages, or other public post types.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'default' => 'post' ),
						'status'    => array( 'type' => 'string', 'default' => 'publish' ),
						'search'    => array( 'type' => 'string' ),
						'page'      => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
						'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10 ),
					),
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
			array(
				'name'        => 'wordpress_get_post',
				'description' => 'Get a WordPress post or page by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'   => array( 'id' ),
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
			array(
				'name'        => 'wordpress_search',
				'description' => 'Search WordPress posts and pages.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array( 'type' => 'string', 'minLength' => 1 ),
						'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 10 ),
					),
					'required'   => array( 'query' ),
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
		);

		if ( $this->writes_enabled() ) {
			$tools[] = array(
				'name'        => 'wordpress_create_post',
				'description' => 'Create a WordPress post or page.',
				'inputSchema' => $this->post_write_schema( false ),
				'annotations' => array(
					'readOnlyHint'    => false,
					'destructiveHint' => false,
				),
			);
			$tools[] = array(
				'name'        => 'wordpress_update_post',
				'description' => 'Update a WordPress post or page.',
				'inputSchema' => $this->post_write_schema( true ),
				'annotations' => array(
					'readOnlyHint'    => false,
					'destructiveHint' => true,
				),
			);
			$tools[] = array(
				'name'        => 'wordpress_delete_post',
				'description' => 'Move a post to trash, or permanently delete it when force is true.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer', 'minimum' => 1 ),
						'force' => array( 'type' => 'boolean', 'default' => false ),
					),
					'required'   => array( 'id' ),
				),
				'annotations' => array(
					'readOnlyHint'    => false,
					'destructiveHint' => true,
				),
			);
		}

		return $tools;
	}

	/**
	 * Executes a requested tool.
	 *
	 * @param array<string, mixed> $params Tool-call parameters.
	 * @return array<string, mixed>
	 */
	private function call_tool( array $params ): array {
		$name      = isset( $params['name'] ) ? sanitize_key( (string) $params['name'] ) : '';
		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		try {
			switch ( $name ) {
				case 'wordpress_get_site_info':
					$result = $this->get_site_info();
					break;
				case 'wordpress_list_posts':
					$result = $this->list_posts( $arguments );
					break;
				case 'wordpress_get_post':
					$result = $this->get_post( $arguments );
					break;
				case 'wordpress_search':
					$result = $this->search( $arguments );
					break;
				case 'wordpress_create_post':
					$this->assert_writes_enabled();
					$result = $this->create_post( $arguments );
					break;
				case 'wordpress_update_post':
					$this->assert_writes_enabled();
					$result = $this->update_post( $arguments );
					break;
				case 'wordpress_delete_post':
					$this->assert_writes_enabled();
					$result = $this->delete_post( $arguments );
					break;
				default:
					throw new InvalidArgumentException( 'Unknown tool.' );
			}

			return $this->tool_result( $result );
		} catch ( Throwable $throwable ) {
			return $this->tool_result(
				array( 'error' => $throwable->getMessage() ),
				true
			);
		}
	}

	/**
	 * Gets basic site information.
	 *
	 * @return array<string, mixed>
	 */
	private function get_site_info(): array {
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

		return array(
			'name'              => get_bloginfo( 'name' ),
			'description'       => get_bloginfo( 'description' ),
			'url'               => home_url( '/' ),
			'wordpress_version' => get_bloginfo( 'version' ),
			'language'          => get_bloginfo( 'language' ),
			'timezone'          => wp_timezone_string(),
			'post_types'        => array_values( $post_types ),
			'writes_enabled'    => $this->writes_enabled(),
		);
	}

	/**
	 * Lists posts.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function list_posts( array $arguments ): array {
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$status    = isset( $arguments['status'] ) ? sanitize_key( (string) $arguments['status'] ) : 'publish';
		$per_page  = min( 100, max( 1, absint( $arguments['per_page'] ?? 10 ) ) );
		$page      = max( 1, absint( $arguments['page'] ?? 1 ) );

		$this->assert_accessible_post_type( $post_type );
		$post_type_object = get_post_type_object( $post_type );
		$query_arguments  = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			's'              => isset( $arguments['search'] ) ? sanitize_text_field( (string) $arguments['search'] ) : '',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		if ( 'private' === $status && ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
			throw new RuntimeException( 'You cannot read private content of this post type.' );
		}

		if ( 'publish' !== $status && ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			$query_arguments['author'] = get_current_user_id();
		}

		$query = new WP_Query( $query_arguments );

		$readable_posts = array_filter(
			$query->posts,
			static function ( WP_Post $post ): bool {
				return current_user_can( 'read_post', $post->ID );
			}
		);

		return array(
			'items'       => array_values( array_map( array( $this, 'format_post' ), $readable_posts ) ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Gets one post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function get_post( array $arguments ): array {
		$id   = $this->required_id( $arguments );
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'read_post', $id ) ) {
			throw new RuntimeException( 'Post not found or access denied.' );
		}

		return $this->format_post( $post, true );
	}

	/**
	 * Searches content.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function search( array $arguments ): array {
		$query_text = isset( $arguments['query'] ) ? sanitize_text_field( (string) $arguments['query'] ) : '';
		if ( '' === $query_text ) {
			throw new InvalidArgumentException( 'query is required.' );
		}

		$query = new WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				's'              => $query_text,
				'posts_per_page' => min( 50, max( 1, absint( $arguments['per_page'] ?? 10 ) ) ),
			)
		);

		return array(
			'items' => array_map( array( $this, 'format_post' ), $query->posts ),
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * Creates a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function create_post( array $arguments ): array {
		$post_type = isset( $arguments['post_type'] ) ? sanitize_key( (string) $arguments['post_type'] ) : 'post';
		$this->assert_accessible_post_type( $post_type );

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
			throw new RuntimeException( 'You cannot create this post type.' );
		}

		if ( empty( $arguments['title'] ) ) {
			throw new InvalidArgumentException( 'title is required.' );
		}

		$id = wp_insert_post( $this->prepare_post_data( $arguments, $post_type ), true );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}

		return $this->format_post( get_post( $id ), true );
	}

	/**
	 * Updates a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function update_post( array $arguments ): array {
		$id   = $this->required_id( $arguments );
		$post = get_post( $id );

		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $id ) ) {
			throw new RuntimeException( 'Post not found or access denied.' );
		}

		$data       = $this->prepare_post_data( $arguments, $post->post_type );
		$data['ID'] = $id;
		$result     = wp_update_post( $data, true );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}

		return $this->format_post( get_post( $result ), true );
	}

	/**
	 * Deletes a post.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>
	 */
	private function delete_post( array $arguments ): array {
		$id = $this->required_id( $arguments );
		if ( ! current_user_can( 'delete_post', $id ) ) {
			throw new RuntimeException( 'Post not found or access denied.' );
		}

		$force  = rest_sanitize_boolean( $arguments['force'] ?? false );
		$result = wp_delete_post( $id, $force );
		if ( ! $result instanceof WP_Post ) {
			throw new RuntimeException( 'The post could not be deleted.' );
		}

		return array(
			'id'      => $id,
			'deleted' => $force,
			'trashed' => ! $force,
		);
	}

	/**
	 * Builds safe wp_insert_post data.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @param string               $post_type Post type.
	 * @return array<string, mixed>
	 */
	private function prepare_post_data( array $arguments, string $post_type ): array {
		$data = array( 'post_type' => $post_type );
		$map  = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
			'status'  => 'post_status',
			'slug'    => 'post_name',
		);

		foreach ( $map as $argument => $field ) {
			if ( ! array_key_exists( $argument, $arguments ) ) {
				continue;
			}

			$value          = (string) $arguments[ $argument ];
			$data[ $field ] = in_array( $argument, array( 'content', 'excerpt' ), true )
				? wp_kses_post( $value )
				: sanitize_text_field( $value );
		}

		if ( isset( $data['post_status'] ) && ! get_post_status_object( $data['post_status'] ) ) {
			throw new InvalidArgumentException( 'Invalid post status.' );
		}

		return $data;
	}

	/**
	 * Formats a post for MCP output.
	 *
	 * @param WP_Post|null $post            Post.
	 * @param bool         $include_content Include full content.
	 * @return array<string, mixed>
	 */
	private function format_post( ?WP_Post $post, bool $include_content = false ): array {
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
	 * Validates an accessible REST post type.
	 */
	private function assert_accessible_post_type( string $post_type ): void {
		$object = get_post_type_object( $post_type );
		if ( ! $object || ! $object->show_in_rest ) {
			throw new InvalidArgumentException( 'Invalid or inaccessible post type.' );
		}
	}

	/**
	 * Validates and returns an ID argument.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 */
	private function required_id( array $arguments ): int {
		$id = absint( $arguments['id'] ?? 0 );
		if ( 0 === $id ) {
			throw new InvalidArgumentException( 'id is required.' );
		}

		return $id;
	}

	/**
	 * Throws when write tools are disabled.
	 */
	private function assert_writes_enabled(): void {
		if ( ! $this->writes_enabled() ) {
			throw new RuntimeException( 'Write tools are disabled by the site administrator.' );
		}
	}

	/**
	 * Whether write tools are enabled.
	 */
	private function writes_enabled(): bool {
		return (bool) get_option( self::OPTION_WRITES, false );
	}

	/**
	 * Validates the Origin header against this WordPress installation.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param mixed           $id      Request ID.
	 */
	private function validate_origin( WP_REST_Request $request, $id ): ?WP_REST_Response {
		$origin = trim( (string) $request->get_header( 'origin' ) );
		if ( '' === $origin ) {
			return null;
		}

		$allowed_origins = array_filter(
			array(
				$this->url_origin( home_url( '/' ) ),
				$this->url_origin( site_url( '/' ) ),
				$this->url_origin( rest_url() ),
			)
		);

		/**
		 * Filters browser origins allowed to call the MCP endpoint.
		 *
		 * @param string[] $allowed_origins Allowed origins including scheme and host.
		 * @param string   $origin          Request origin.
		 */
		$allowed_origins = apply_filters( 'instahost_wordpress_mcp_allowed_origins', array_unique( $allowed_origins ), $origin );

		if ( ! is_array( $allowed_origins ) || ! in_array( $this->url_origin( $origin ), $allowed_origins, true ) ) {
			return $this->error_response( $id, -32000, 'Invalid Origin header.', 403 );
		}

		return null;
	}

	/**
	 * Validates modern MCP metadata while retaining legacy client support.
	 *
	 * @param WP_REST_Request    $request REST request.
	 * @param array<string,mixed> $payload JSON-RPC payload.
	 * @param mixed              $id      Request ID.
	 */
	private function validate_protocol( WP_REST_Request $request, array $payload, $id ): ?WP_REST_Response {
		$params       = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();
		$meta         = isset( $params['_meta'] ) && is_array( $params['_meta'] ) ? $params['_meta'] : array();
		$body_version = isset( $meta['io.modelcontextprotocol/protocolVersion'] )
			? sanitize_text_field( (string) $meta['io.modelcontextprotocol/protocolVersion'] )
			: '';
		$header_version = sanitize_text_field( (string) $request->get_header( 'mcp-protocol-version' ) );

		// Requests without modern metadata are treated as legacy MCP 2025-03-26.
		if ( '' === $body_version ) {
			return null;
		}

		if ( self::MODERN_VERSION !== $body_version ) {
			return $this->unsupported_version_response( $id, $body_version );
		}

		if ( $header_version !== $body_version ) {
			return $this->error_response( $id, -32023, 'MCP protocol version header does not match request metadata.', 400 );
		}

		$method        = isset( $payload['method'] ) ? (string) $payload['method'] : '';
		$header_method = (string) $request->get_header( 'mcp-method' );
		if ( $header_method !== $method ) {
			return $this->error_response( $id, -32023, 'Mcp-Method header does not match the JSON-RPC method.', 400 );
		}

		if ( 'tools/call' === $method ) {
			$name        = isset( $params['name'] ) ? (string) $params['name'] : '';
			$header_name = (string) $request->get_header( 'mcp-name' );
			if ( $header_name !== $name ) {
				return $this->error_response( $id, -32023, 'Mcp-Name header does not match the requested tool.', 400 );
			}
		}

		return null;
	}

	/**
	 * Returns an unsupported protocol version error.
	 *
	 * @param mixed  $id        Request ID.
	 * @param string $requested Requested protocol version.
	 */
	private function unsupported_version_response( $id, string $requested ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => -32022,
					'message' => 'Unsupported protocol version.',
					'data'    => array(
						'supported' => array( self::MODERN_VERSION, self::LEGACY_VERSION ),
						'requested' => $requested,
					),
				),
			),
			400
		);
	}

	/**
	 * Extracts a normalized origin from a URL.
	 */
	private function url_origin( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$origin .= ':' . absint( $parts['port'] );
		}

		return $origin;
	}

	/**
	 * Builds an MCP tool result.
	 *
	 * @param mixed $data     Result data.
	 * @param bool  $is_error Whether this is an error.
	 * @return array<string, mixed>
	 */
	private function tool_result( $data, bool $is_error = false ): array {
		return array(
			'resultType'        => 'complete',
			'content'           => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				),
			),
			'structuredContent' => $data,
			'isError'           => $is_error,
		);
	}

	/**
	 * Returns a JSON-RPC success response.
	 *
	 * @param mixed                $id     Request ID.
	 * @param array<string, mixed> $result Result.
	 */
	private function success_response( $id, array $result ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			)
		);
	}

	/**
	 * Returns a JSON-RPC error response.
	 *
	 * @param mixed  $id      Request ID.
	 * @param int    $code    JSON-RPC code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status.
	 */
	private function error_response( $id, int $code, string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => $code,
					'message' => $message,
				),
			),
			$status
		);
	}

	/**
	 * Returns an empty object JSON schema.
	 *
	 * @return array<string, mixed>
	 */
	private function object_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => (object) array(),
			'additionalProperties' => false,
		);
	}

	/**
	 * Returns the write-tool input schema.
	 *
	 * @param bool $require_id Whether an ID is required.
	 * @return array<string, mixed>
	 */
	private function post_write_schema( bool $require_id ): array {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array( 'type' => 'integer', 'minimum' => 1 ),
				'post_type' => array( 'type' => 'string', 'default' => 'post' ),
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string' ),
				'excerpt'   => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string', 'default' => 'draft' ),
				'slug'      => array( 'type' => 'string' ),
			),
		);

		$schema['required'] = $require_id ? array( 'id' ) : array( 'title' );
		return $schema;
	}
}
