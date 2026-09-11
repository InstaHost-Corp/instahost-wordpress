<?php
/**
 * Standalone integration smoke tests for MCP Adapter registration.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'INSTAHOST_WORDPRESS_MCP_VERSION', '1.0.0' );

$GLOBALS['writes_enabled']     = false;
$GLOBALS['logged_in']         = true;
$GLOBALS['capabilities']      = array( 'edit_posts' => true );
$GLOBALS['registered']        = array();
$GLOBALS['posts']             = array();
$GLOBALS['post_statuses']     = array();

final class WP_Error {
	/**
	 * @param array<string, mixed> $data Error data.
	 */
	public function __construct(
		public string $code,
		public string $message,
		public array $data = array()
	) {}

	public function get_error_message(): string {
		return $this->message;
	}
}

final class WP_Post {
	public string $post_type = 'post';
	public string $post_status = 'publish';
	public string $post_password = '';
	public string $post_name = 'example';
	public string $post_content = 'Content';
	public bool $revision = false;
	public bool $autosave = false;

	public function __construct( public int $ID ) {}
}

final class Fake_Adapter {
	/** @var array<int, mixed> */
	public array $arguments = array();

	public function create_server( mixed ...$arguments ): object {
		$this->arguments = $arguments;
		return (object) array( 'id' => $arguments[0] );
	}
}

function wp_register_ability( string $name, array $definition ): object {
	$GLOBALS['registered'][ $name ] = $definition;
	return (object) array( 'name' => $name );
}

function get_option( string $name, mixed $default = false ): mixed {
	return 'instahost_wordpress_mcp_enable_writes' === $name ? $GLOBALS['writes_enabled'] : $default;
}

function is_user_logged_in(): bool {
	return $GLOBALS['logged_in'];
}

function current_user_can( string $capability, mixed ...$args ): bool {
	return $GLOBALS['capabilities'][ $capability ] ?? false;
}

function sanitize_key( string $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function rest_sanitize_boolean( mixed $value ): bool {
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
}

function get_post( int $id ): ?WP_Post {
	return $GLOBALS['posts'][ $id ] ?? null;
}

function get_post_status( int $id ): string|false {
	return $GLOBALS['post_statuses'][ $id ] ?? false;
}

function wp_trash_post( int $id ): WP_Post|false {
	$post = get_post( $id );
	if ( ! $post ) {
		return false;
	}

	$post->post_status             = 'trash';
	$GLOBALS['post_statuses'][ $id ] = 'trash';
	return $post;
}

function wp_delete_post( int $id, bool $force = false ): WP_Post|false {
	$post = get_post( $id );
	if ( ! $post || ! $force ) {
		return false;
	}

	unset( $GLOBALS['posts'][ $id ], $GLOBALS['post_statuses'][ $id ] );
	return $post;
}

function get_post_type_object( string $post_type ): ?object {
	if ( 'post' !== $post_type ) {
		return null;
	}

	return (object) array(
		'show_in_rest' => true,
		'cap'          => (object) array(
			'create_posts'       => 'create_posts',
			'publish_posts'      => 'publish_posts',
			'read_private_posts' => 'read_private_posts',
			'edit_others_posts'  => 'edit_others_posts',
		),
	);
}

function is_wp_error( mixed $value ): bool {
	return $value instanceof WP_Error;
}

function wp_is_post_revision( WP_Post $post ): bool {
	return $post->revision;
}

function wp_is_post_autosave( WP_Post $post ): bool {
	return $post->autosave;
}

function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-instahost-wordpress-mcp-abilities.php';

Instahost_WordPress_MCP_Abilities::register();
assert_true( 4 === count( $GLOBALS['registered'] ), 'Read-only mode must register four abilities.' );
assert_true(
	array_keys( $GLOBALS['registered'] ) === Instahost_WordPress_MCP_Abilities::ability_names(),
	'Ability registration order must be deterministic.'
);
foreach ( $GLOBALS['registered'] as $definition ) {
	assert_true( true === $definition['meta']['public'], 'Every registered ability must explicitly opt in to MCP exposure.' );
	assert_true( true === $definition['meta']['annotations']['readOnlyHint'], 'Read-only abilities must be annotated.' );
}

$GLOBALS['writes_enabled'] = true;
$GLOBALS['registered']     = array();
Instahost_WordPress_MCP_Abilities::register();
assert_true( 7 === count( $GLOBALS['registered'] ), 'Write mode must register exactly three mutation abilities.' );
assert_true(
	false === $GLOBALS['registered']['instahost-wordpress/create-post']['meta']['annotations']['readOnlyHint'],
	'Create ability must be annotated as mutating.'
);
assert_true(
	true === $GLOBALS['registered']['instahost-wordpress/delete-post']['meta']['annotations']['destructiveHint'],
	'Delete ability must be annotated as destructive.'
);

$adapter = new Fake_Adapter();
Instahost_WordPress_MCP_Abilities::register_server( $adapter );
assert_true( 'instahost-wordpress' === $adapter->arguments[0], 'Custom MCP server ID must be stable.' );
assert_true( 'mcp' === $adapter->arguments[1], 'Custom MCP server namespace must use the adapter namespace.' );
assert_true( 'instahost-wordpress' === $adapter->arguments[2], 'Custom MCP route must be stable.' );
assert_true( 7 === count( $adapter->arguments[9] ), 'Custom server must expose all enabled abilities.' );

$transport_permission = $adapter->arguments[12];
$GLOBALS['logged_in'] = false;
$denied               = $transport_permission();
assert_true( $denied instanceof WP_Error && 401 === $denied->data['status'], 'Anonymous transport access must return 401.' );
$GLOBALS['logged_in']                   = true;
$GLOBALS['capabilities']['edit_posts'] = false;
$denied                                 = $transport_permission();
assert_true( $denied instanceof WP_Error && 403 === $denied->data['status'], 'Users without edit_posts must return 403.' );
$GLOBALS['capabilities']['edit_posts'] = true;
assert_true( true === $transport_permission(), 'Authenticated editors must pass the transport gate.' );

$GLOBALS['capabilities']['create_posts']  = true;
$GLOBALS['capabilities']['publish_posts'] = false;
$publish_denied = Instahost_WordPress_MCP_Abilities::can_create_post(
	array(
		'title'  => 'Example',
		'status' => 'publish',
	)
);
assert_true(
	$publish_denied instanceof WP_Error && 'instahost_mcp_publish_denied' === $publish_denied->code,
	'Publishing must require publish_posts.'
);
$GLOBALS['capabilities']['publish_posts'] = true;
assert_true(
	true === Instahost_WordPress_MCP_Abilities::can_create_post( array( 'title' => 'Example', 'status' => 'publish' ) ),
	'Authorized publishing must pass.'
);

$revision           = new WP_Post( 42 );
$revision->revision = true;
$GLOBALS['posts'][42] = $revision;
$GLOBALS['capabilities']['read_post'] = true;
$GLOBALS['capabilities']['edit_post'] = false;
$raw_denied = Instahost_WordPress_MCP_Abilities::can_get_post( array( 'id' => 42 ) );
assert_true(
	$raw_denied instanceof WP_Error && 'instahost_mcp_post_denied' === $raw_denied->code,
	'Raw post retrieval must require edit access.'
);
$GLOBALS['capabilities']['edit_post'] = true;
$revision_denied = Instahost_WordPress_MCP_Abilities::can_get_post( array( 'id' => 42 ) );
assert_true(
	$revision_denied instanceof WP_Error && 'instahost_mcp_historical_content_denied' === $revision_denied->code,
	'Revisions must not be exposed even to editors.'
);

$protected                = new WP_Post( 43 );
$protected->post_password = 'protected';
$GLOBALS['posts'][43]      = $protected;
$GLOBALS['capabilities']['edit_post'] = false;
$protected_denied = Instahost_WordPress_MCP_Abilities::can_get_post( array( 'id' => 43 ) );
assert_true(
	$protected_denied instanceof WP_Error && 'instahost_mcp_post_denied' === $protected_denied->code,
	'Protected raw content must require edit access.'
);
$GLOBALS['capabilities']['edit_post'] = true;
assert_true(
	true === Instahost_WordPress_MCP_Abilities::can_get_post( array( 'id' => 43 ) ),
	'Editors must be able to access protected content.'
);

$editable = new WP_Post( 44 );
$GLOBALS['posts'][44] = $editable;
$GLOBALS['post_statuses'][44] = 'publish';
assert_true(
	true === Instahost_WordPress_MCP_Abilities::can_update_post( array( 'id' => 44 ) ),
	'An authorized update without a status transition must pass.'
);
$GLOBALS['capabilities']['delete_post'] = false;
$trash_denied = Instahost_WordPress_MCP_Abilities::can_update_post( array( 'id' => 44, 'status' => 'trash' ) );
assert_true(
	$trash_denied instanceof WP_Error && 'instahost_mcp_trash_denied' === $trash_denied->code,
	'Trashing through update must require delete_post.'
);

$GLOBALS['capabilities']['delete_post'] = true;
assert_true(
	true === Instahost_WordPress_MCP_Abilities::can_delete_post( array( 'id' => 44 ) ),
	'Authorized deletion of REST-visible content must pass.'
);
$revision->revision = true;
assert_true(
	false === Instahost_WordPress_MCP_Abilities::can_delete_post( array( 'id' => 42 ) ),
	'Revision deletion must not be exposed.'
);

$trashed = Instahost_WordPress_MCP_Abilities::delete_post( array( 'id' => 44, 'force' => false ) );
assert_true( true === $trashed['trashed'] && false === $trashed['deleted'], 'Non-forced deletion must explicitly trash content.' );
$already_trashed = Instahost_WordPress_MCP_Abilities::delete_post( array( 'id' => 44, 'force' => false ) );
assert_true(
	$already_trashed instanceof WP_Error && 'instahost_mcp_already_trashed' === $already_trashed->code,
	'Already-trashed content must require force for permanent deletion.'
);
$deleted = Instahost_WordPress_MCP_Abilities::delete_post( array( 'id' => 44, 'force' => true ) );
assert_true( true === $deleted['deleted'] && false === $deleted['trashed'], 'Forced deletion must report permanent deletion.' );

fwrite( STDOUT, "PASS: MCP Adapter integration smoke tests\n" );
