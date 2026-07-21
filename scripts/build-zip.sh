#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
version="$(sed -n "s/^ \* Version:[[:space:]]*//p" conceptplug.php | head -1)"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Cannot read plugin version." >&2; exit 1; }

destination="${1:-$PWD/build}"
mkdir -p "$destination"
destination="$(cd "$destination" && pwd)"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/conceptplug"
rsync -a \
  --exclude=.git --exclude=.github --exclude=.gitignore --exclude=build --exclude=scripts \
	--exclude=phpcs.xml.dist --exclude=phpstan.neon.dist --exclude=phpstan-bootstrap.php --exclude=composer.json --exclude=composer.lock --exclude=vendor --exclude=release --exclude=public \
  --exclude=.release-secrets --exclude=modules/woocommerce/assets/demo-source \
  ./ "$staging/conceptplug/"

# Demo presets are shipped as small WebP assets so the free local workflow does
# not depend on ConceptPlug's API or CDN after installation.
mkdir -p "$staging/conceptplug/modules/woocommerce/assets/demo"
install -m 0644 release/conceptplug-web/public/conwoo/demo/v3/*.webp \
  "$staging/conceptplug/modules/woocommerce/assets/demo/"

# Normalize permissions before archiving so restrictive developer umasks cannot
# produce CSS/JS/PHP files that the web server cannot read after installation.
find "$staging/conceptplug" -type d -exec chmod 0755 {} +
find "$staging/conceptplug" -type f -exec chmod 0644 {} +

# A fixed/release-provided epoch and sorted paths make repeated builds byte-for-byte
# reproducible. The ZIP contains source assets and translations; source files are
# never minified or rewritten by this build.
source_date_epoch="${SOURCE_DATE_EPOCH:-1704067200}"
if ! [[ "$source_date_epoch" =~ ^[0-9]+$ ]]; then
  echo "SOURCE_DATE_EPOCH must be a Unix timestamp." >&2
  exit 1
fi
find "$staging/conceptplug" -exec touch -h -d "@${source_date_epoch}" {} +

tmp_zip="$destination/.conceptplug-$version.zip.tmp"
final_zip="$destination/conceptplug-$version.zip"
rm -f "$tmp_zip"
(
  cd "$staging"
  LC_ALL=C find conceptplug -print | LC_ALL=C sort | zip -X -q "$tmp_zip" -@
)
chmod 0644 "$tmp_zip"
mv -f "$tmp_zip" "$final_zip"

tmp_sha="$destination/.conceptplug-$version.zip.sha256.tmp"
( cd "$destination" && sha256sum "conceptplug-$version.zip" > "$tmp_sha" )
chmod 0644 "$tmp_sha"
mv -f "$tmp_sha" "$destination/conceptplug-$version.zip.sha256"

# Release smoke: every file must be world-readable and directories executable.
if zipinfo -l "$final_zip" | awk '$1 ~ /^d/ && substr($1,1,10) != "drwxr-xr-x" {bad=1} $1 ~ /^-/ && substr($1,1,10) != "-rw-r--r--" {bad=1} END {exit bad}'; then
  :
else
  echo "ZIP contains unsafe file permissions." >&2
  exit 1
fi

echo "$destination/conceptplug-$version.zip"
