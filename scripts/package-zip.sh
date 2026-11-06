#!/usr/bin/env bash
# Package this plugin as a WordPress-update ZIP.
#
# Required archive root (never a versioned folder):
#   expertstatsplugin-master/wp-codeable.php
#
# Version belongs in:
#   - wp-codeable.php plugin header (`Version:`)
#   - the ZIP filename (default expertstatsplugin-<version>-hybrid.zip)
# Version must NOT appear as the ZIP's top-level directory. A root such as
# expertstatsplugin-0.4.3/ installs a second inactive plugin and does not
# replace the live expertstatsplugin-master/ copy. Do not remove that
# duplicate with the WP uninstaller (shared tables/options).
#
# Usage:
#   ./scripts/package-zip.sh
#   ./scripts/package-zip.sh /tmp/expertstatsplugin-0.4.3-hybrid.zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_ROOT_NAME="expertstatsplugin-master"
HEADER_FILE="$ROOT/wp-codeable.php"

if [[ ! -f "$HEADER_FILE" ]]; then
	echo "package-zip: missing $HEADER_FILE" >&2
	exit 1
fi

HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$HEADER_FILE" | head -n 1 | tr -d '[:space:]')"
if [[ -z "$HEADER_VERSION" ]]; then
	echo "package-zip: could not read Version header" >&2
	exit 1
fi

OUT="${1:-}"
if [[ -z "$OUT" ]]; then
	OUT="$(pwd)/expertstatsplugin-${HEADER_VERSION}-hybrid.zip"
fi
if [[ "$OUT" != /* ]]; then
	OUT="$(pwd)/$OUT"
fi
mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"

STAGE="$(mktemp -d "${TMPDIR:-/tmp}/wpcable-zip.XXXXXX")"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

DEST="$STAGE/$PLUGIN_ROOT_NAME"
mkdir -p "$DEST"

# Lean production ZIP: omit tests, screenshot artifacts, VCS, and nested
# node_modules. Keep plugin PHP/JS/CSS, CHANGELOG, and docs/*.md (no screenshots).
EXCLUDES=(
	.git
	.github
	.cursor
	node_modules
	tests
	docs/screenshots
	codeable-live-audit
)

if command -v rsync >/dev/null 2>&1; then
	rsync -a \
		--exclude '.git/' \
		--exclude '.github/' \
		--exclude '.cursor/' \
		--exclude 'node_modules/' \
		--exclude 'tests/' \
		--exclude 'docs/screenshots/' \
		--exclude 'codeable-live-audit/' \
		--exclude '*.zip' \
		"$ROOT/" "$DEST/"
else
	cp -a "$ROOT/." "$DEST/"
	for rel in "${EXCLUDES[@]}"; do
		rm -rf "$DEST/$rel"
	done
	find "$DEST" -name '*.zip' -delete
fi

if [[ ! -f "$DEST/wp-codeable.php" ]]; then
	echo "package-zip: staged tree missing wp-codeable.php" >&2
	exit 1
fi

# The directory name inside the archive is the WP plugin slug. Never version it.
( cd "$STAGE" && zip -qr "$OUT" "$PLUGIN_ROOT_NAME" )

echo "Wrote $OUT"
echo "Archive root: $PLUGIN_ROOT_NAME/wp-codeable.php (header Version: $HEADER_VERSION)"
