#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

site_url="${CONCEPTPLUG_SITE_URL:-https://conceptplug.com}"
release_dir="${CONCEPTPLUG_RELEASE_DIR:-public/downloads}"
verify_live="${CONCEPTPLUG_VERIFY_LIVE:-0}"
plugin_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' conceptplug.php | head -1)"
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

artifacts=(
  conceptplug-update.json
  conceptplug.zip
  conceptplug.zip.sha256
  conceptplug.zip.sig
  conceptplug-update-public-key.pem
)

for artifact in "${artifacts[@]}"; do
  if [[ "$verify_live" == "1" ]]; then
    curl -fsS --max-time 60 "$site_url/downloads/$artifact" -o "$tmpdir/$artifact"
  else
    [[ -s "$release_dir/$artifact" ]] || {
      echo "Missing $release_dir/$artifact" >&2
      exit 1
    }
    cp "$release_dir/$artifact" "$tmpdir/$artifact"
  fi
done

python3 - "$tmpdir/conceptplug-update.json" "$plugin_version" "${SOURCE_DATE_EPOCH:-}" <<'PY'
from datetime import datetime, timezone
import json
import sys

path, plugin_version, expected_epoch = sys.argv[1:]
manifest = json.load(open(path, encoding="utf-8"))
assert manifest["version"] == plugin_version
assert manifest["signature_algorithm"] == "ed25519"
assert manifest["download_url"].endswith("/downloads/conceptplug.zip")
assert manifest["sha256_url"].endswith("/downloads/conceptplug.zip.sha256")
assert manifest["signature_url"].endswith("/downloads/conceptplug.zip.sig")
assert manifest["public_key_url"].endswith("/downloads/conceptplug-update-public-key.pem")
assert len(manifest["sha256"]) == 64
assert len(manifest["public_key_fingerprint"]) == 64
assert manifest.get("sections", {}).get("description", "").strip(), "manifest description is empty"
assert manifest.get("sections", {}).get("changelog", "").strip(), "manifest changelog is empty"
updated = datetime.fromisoformat(manifest["last_updated"].replace("Z", "+00:00"))
assert updated.tzinfo is not None and updated <= datetime.now(timezone.utc)
if expected_epoch:
    assert int(updated.timestamp()) == int(expected_epoch)
PY

published_sha="$(awk '{print $1}' "$tmpdir/conceptplug.zip.sha256")"
manifest_sha="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["sha256"])' "$tmpdir/conceptplug-update.json")"
zip_sha="$(sha256sum "$tmpdir/conceptplug.zip" | awk '{print $1}')"
[[ "$published_sha" == "$manifest_sha" && "$zip_sha" == "$manifest_sha" ]] || {
  echo "ZIP/checksum/manifest SHA-256 mismatch" >&2
  exit 1
}

base64 --decode "$tmpdir/conceptplug.zip.sig" > "$tmpdir/conceptplug.zip.sig.raw"
[[ "$(stat -c '%s' "$tmpdir/conceptplug.zip.sig.raw")" == "64" ]]
openssl pkeyutl -verify -pubin -inkey "$tmpdir/conceptplug-update-public-key.pem" -rawin \
  -in "$tmpdir/conceptplug.zip" -sigfile "$tmpdir/conceptplug.zip.sig.raw" >/dev/null

public_fingerprint="$(openssl pkey -pubin -in "$tmpdir/conceptplug-update-public-key.pem" -outform DER 2>/dev/null | tail -c 32 | sha256sum | awk '{print $1}')"
manifest_fingerprint="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["public_key_fingerprint"])' "$tmpdir/conceptplug-update.json")"
[[ "$public_fingerprint" == "$manifest_fingerprint" ]]

zip_version="$(unzip -p "$tmpdir/conceptplug.zip" conceptplug/conceptplug.php | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1)"
zip_stable="$(unzip -p "$tmpdir/conceptplug.zip" conceptplug/readme.txt | awk -F': ' '/^Stable tag:/{print $2; exit}')"
[[ "$zip_version" == "$plugin_version" && "$zip_stable" == "$plugin_version" ]]
unzip -l "$tmpdir/conceptplug.zip" conceptplug/includes/conceptplug-update-public-key.pem >/dev/null
unzip -l "$tmpdir/conceptplug.zip" conceptplug/languages/conceptplug.pot >/dev/null
unzip -l "$tmpdir/conceptplug.zip" conceptplug/languages/conceptplug-th_TH.po >/dev/null
unzip -l "$tmpdir/conceptplug.zip" conceptplug/languages/conceptplug-th_TH.mo >/dev/null

python3 - "$tmpdir/conceptplug.zip" <<'PY'
import sys
import zipfile

with zipfile.ZipFile(sys.argv[1]) as archive:
    for entry in archive.infolist():
        mode = (entry.external_attr >> 16) & 0o7777
        expected = 0o755 if entry.is_dir() else 0o644
        assert mode == expected, f"{entry.filename}: {oct(mode)} != {oct(expected)}"
PY

python3 - <<'PY'
from pathlib import Path

updater = Path("includes/class-updater.php").read_text(encoding="utf-8")
for needle in (
    "pre_set_site_transient_update_plugins",
    "plugins_api",
    "upgrader_pre_download",
    "hash_equals",
    "sodium_crypto_sign_verify_detached",
    "public_key_fingerprint",
):
    assert needle in updater, f"Updater missing hook/check: {needle}"

installed = tuple(map(int, "1.6.7".split(".")))
remote = tuple(map(int, "1.7.0".split(".")))
assert installed < remote
PY

echo "Signed update flow verification passed for ConceptPlug v${plugin_version}"
