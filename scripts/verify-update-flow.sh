#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

site_url="${CONCEPTPLUG_SITE_URL:-https://conceptplug.com}"
manifest_url="${site_url}/downloads/conceptplug-update.json"
zip_url="${site_url}/downloads/conceptplug.zip"
hash_url="${site_url}/downloads/conceptplug.zip.sha256"

plugin_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' conceptplug.php | head -1)"

echo "Checking ${manifest_url}"
manifest_json="$(curl -fsS "$manifest_url")"
manifest_version="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["version"])' <<<"$manifest_json")"
manifest_sha="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["sha256"])' <<<"$manifest_json")"

[[ "$manifest_version" == "$plugin_version" ]] || {
  echo "Manifest version ${manifest_version} != plugin header ${plugin_version}" >&2
  exit 1
}

echo "Checking ${hash_url}"
published_sha="$(curl -fsS "$hash_url" | awk '{print $1}')"
[[ "$published_sha" == "$manifest_sha" ]] || {
  echo "Published sha256 does not match manifest" >&2
  exit 1
}

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
curl -fsS "$zip_url" -o "$tmpdir/conceptplug.zip"
zip_sha="$(sha256sum "$tmpdir/conceptplug.zip" | awk '{print $1}')"
[[ "$zip_sha" == "$manifest_sha" ]] || {
  echo "Downloaded zip sha256 mismatch" >&2
  exit 1
}

unzip -p "$tmpdir/conceptplug.zip" conceptplug/conceptplug.php > "$tmpdir/header.php"
zip_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$tmpdir/header.php" | head -1)"
[[ "$zip_version" == "$plugin_version" ]] || {
  echo "Zip header version ${zip_version} != ${plugin_version}" >&2
  exit 1
}

python3 - <<PY
from pathlib import Path
import re

updater = Path("includes/class-updater.php").read_text()
for needle in (
    "pre_set_site_transient_update_plugins",
    "plugins_api",
    "upgrader_pre_download",
    "hash_equals",
):
    if needle not in updater:
        raise SystemExit(f"Updater missing hook/check: {needle}")

installed = "1.1.0"
remote = "${manifest_version}"
from distutils.version import LooseVersion
assert LooseVersion(installed) < LooseVersion(remote), "WP should see an update for 1.1.0 installs"
print("WP update path: installed", installed, "< remote", remote)
PY

echo "Update flow verification passed for v${plugin_version}"
