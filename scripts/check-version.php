<?php
/**
 * Verifies published version representations.
 */

declare(strict_types=1);

$root   = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/instahost-wordpress-mcp.php' );
$readme = file_get_contents( $root . '/readme.txt' );
$notes  = file_get_contents( $root . '/RELEASE_NOTES.md' );

if ( false === $plugin || false === $readme || false === $notes ) {
	fwrite( STDERR, "FAIL: unable to read version sources\n" );
	exit( 1 );
}

preg_match( '/^ \\* Version:\\s+([0-9.]+)$/m', $plugin, $plugin_header );
preg_match( "/define\\( 'INSTAHOST_WORDPRESS_MCP_VERSION', '([0-9.]+)' \\);/", $plugin, $plugin_constant );
preg_match( '/^Stable tag:\\s+([0-9.]+)$/m', $readme, $stable_tag );
preg_match( '/^# InstaHost WordPress MCP ([0-9.]+) Release$/m', $notes, $release_notes );

$versions = array(
	$plugin_header[1] ?? '',
	$plugin_constant[1] ?? '',
	$stable_tag[1] ?? '',
	$release_notes[1] ?? '',
);

if ( count( array_unique( $versions ) ) !== 1 || '' === $versions[0] ) {
	fwrite( STDERR, 'FAIL: version mismatch: ' . implode( ', ', $versions ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "PASS: version {$versions[0]} is consistent\n" );
