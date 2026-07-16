#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
version="$(sed -n "s/^ \* Version:[[:space:]]*//p" conceptplug.php | head -1)"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Cannot read plugin version." >&2; exit 1; }

if [[ -x ./scripts/minify-release-assets.sh ]]; then
  ./scripts/minify-release-assets.sh
fi

destination="${1:-$PWD/build}"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
mkdir -p "$destination" "$staging/conceptplug"
rsync -a \
  --exclude=.git --exclude=.github --exclude=.gitignore --exclude=build --exclude=scripts \
  --exclude=phpcs.xml.dist --exclude=release --exclude=public \
  --exclude=languages --exclude=modules/conwoo/assets/demo-source \
  ./ "$staging/conceptplug/"
( cd "$staging" && zip -qr "$destination/conceptplug-$version.zip" conceptplug )
sha256sum "$destination/conceptplug-$version.zip" > "$destination/conceptplug-$version.zip.sha256"
echo "$destination/conceptplug-$version.zip"
