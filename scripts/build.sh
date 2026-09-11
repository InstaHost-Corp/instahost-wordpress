#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
VERSION=$(php "$ROOT/scripts/check-version.php" | awk '/^PASS: version [0-9.]+ is consistent$/ { print $3 }')
[ -n "$VERSION" ] || {
	printf 'FAIL: unable to determine package version\n' >&2
	exit 1
}
PACKAGE="instahost-wordpress-mcp-$VERSION"
DIST="$ROOT/dist"
STAGE="$DIST/$PACKAGE"

rm -rf "$STAGE"
mkdir -p "$STAGE/includes"

cp "$ROOT/instahost-wordpress-mcp.php" "$STAGE/"
cp "$ROOT/uninstall.php" "$STAGE/"
cp "$ROOT/readme.txt" "$STAGE/"
cp "$ROOT/includes/"*.php "$STAGE/includes/"

rm -f "$DIST/$PACKAGE.zip"
(
	cd "$DIST"
	find "$PACKAGE" -type f -exec touch -t 202609110000 {} +
	zip -X -q -r "$PACKAGE.zip" "$PACKAGE"
)
rm -rf "$STAGE"

printf 'PASS: built %s\n' "$DIST/$PACKAGE.zip"
