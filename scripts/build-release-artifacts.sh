#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

plugin_file="conceptplug.php"
readme_file="readme.txt"
public_dir="${PUBLIC_DOWNLOADS_DIR:-public/downloads}"
site_url="${CONCEPTPLUG_SITE_URL:-https://conceptplug.com}"
signing_key="${CONCEPTPLUG_UPDATE_SIGNING_KEY:-}"
public_key="includes/conceptplug-update-public-key.pem"

[[ -n "$signing_key" ]] || {
  echo "CONCEPTPLUG_UPDATE_SIGNING_KEY must point to a private key stored outside this repository." >&2
  exit 1
}
[[ -n "${SOURCE_DATE_EPOCH:-}" && "$SOURCE_DATE_EPOCH" =~ ^[0-9]+$ ]] || {
  echo "SOURCE_DATE_EPOCH is required for a release and must be the release commit Unix timestamp." >&2
  exit 1
}
repo_root="$(pwd -P)"
signing_key="$(realpath "$signing_key" 2>/dev/null || true)"
[[ -n "$signing_key" && -f "$signing_key" ]] || {
  echo "Ed25519 signing key not found. Set CONCEPTPLUG_UPDATE_SIGNING_KEY." >&2
  exit 1
}
case "$signing_key" in
  "$repo_root"|"$repo_root"/*)
    echo "Refusing a signing key stored inside the plugin repository." >&2
    exit 1
    ;;
esac
key_mode="$(stat -c '%a' "$signing_key")"
if (( (8#$key_mode & 8#077) != 0 )); then
  echo "Signing key permissions must not grant group/other access (expected 0600)." >&2
  exit 1
fi

version="$(sed -n "s/^ \* Version:[[:space:]]*//p" "$plugin_file" | head -1)"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo "Cannot read plugin version from $plugin_file" >&2
  exit 1
}

./scripts/build-zip.sh >/dev/null

zip_src="build/conceptplug-${version}.zip"
[[ -s "$zip_src" ]] || {
  echo "Missing $zip_src" >&2
  exit 1
}

[[ -s "$public_key" ]] || {
  echo "Missing pinned Ed25519 public key: $public_key" >&2
  exit 1
}
mkdir -p "$public_dir"
chmod 0755 "$public_dir"
derived_public="$(mktemp)"
signature_raw="$(mktemp)"
manifest_tmp="$(mktemp "$public_dir/.conceptplug-update.json.tmp.XXXXXX")"
trap 'rm -f "$derived_public" "$signature_raw" "$manifest_tmp"' EXIT
openssl pkey -in "$signing_key" -pubout -out "$derived_public" >/dev/null 2>&1
if ! cmp -s "$derived_public" "$public_key"; then
  echo "Signing key does not match the public key pinned in the plugin." >&2
  exit 1
fi

zip_tmp="$public_dir/.conceptplug.zip.tmp"
cp "$zip_src" "$zip_tmp"
chmod 0644 "$zip_tmp"
mv -f "$zip_tmp" "$public_dir/conceptplug.zip"

sha256="$(sha256sum "$public_dir/conceptplug.zip" | awk '{print $1}')"
sha_tmp="$public_dir/.conceptplug.zip.sha256.tmp"
printf '%s  conceptplug.zip\n' "$sha256" > "$sha_tmp"
chmod 0644 "$sha_tmp"
mv -f "$sha_tmp" "$public_dir/conceptplug.zip.sha256"

openssl pkeyutl -sign -rawin -inkey "$signing_key" -in "$public_dir/conceptplug.zip" -out "$signature_raw"
openssl pkeyutl -verify -pubin -inkey "$public_key" -rawin -in "$public_dir/conceptplug.zip" -sigfile "$signature_raw" >/dev/null
sig_tmp="$public_dir/.conceptplug.zip.sig.tmp"
base64 -w 0 "$signature_raw" > "$sig_tmp"
printf '\n' >> "$sig_tmp"
chmod 0644 "$sig_tmp"
mv -f "$sig_tmp" "$public_dir/conceptplug.zip.sig"

pub_tmp="$public_dir/.conceptplug-update-public-key.pem.tmp"
cp "$public_key" "$pub_tmp"
chmod 0644 "$pub_tmp"
mv -f "$pub_tmp" "$public_dir/conceptplug-update-public-key.pem"

public_key_fingerprint="$(openssl pkey -pubin -in "$public_key" -outform DER 2>/dev/null | tail -c 32 | sha256sum | awk '{print $1}')"

requires="$(sed -n "s/^ \* Requires at least:[[:space:]]*//p" "$plugin_file" | head -1)"
requires_php="$(sed -n "s/^ \* Requires PHP:[[:space:]]*//p" "$plugin_file" | head -1)"
tested="$(awk -F': ' '/^Tested up to:/{print $2; exit}' "$readme_file")"

python3 - "$manifest_tmp" "$public_dir/conceptplug-update.json" "$readme_file" "$version" "$site_url" "$requires" "$requires_php" "$tested" "$sha256" "$public_key_fingerprint" <<'PY'
import json
from datetime import datetime, timezone
from pathlib import Path
import os
import re
import sys

tmp_path, final_path, readme_path = map(Path, sys.argv[1:4])
version, site_url, requires, requires_php, tested, sha256, public_key_fingerprint = sys.argv[4:]
epoch = int(os.environ["SOURCE_DATE_EPOCH"])
readme = readme_path.read_text(encoding="utf-8")

description_match = re.search(r"^== Description ==\s*$\n(.*?)(?=^== .+ ==\s*$)", readme, re.M | re.S)
changelog_match = re.search(r"^== Changelog ==\s*$\n(.*)\Z", readme, re.M | re.S)
if not description_match or not description_match.group(1).strip():
    raise SystemExit("Description section missing or empty in readme.txt")
if not changelog_match or not changelog_match.group(1).strip():
    raise SystemExit("Changelog section missing or empty in readme.txt")
description = description_match.group(1).strip()
changelog = changelog_match.group(1).strip()

payload = {
    "name": "ConceptPlug",
    "slug": "conceptplug",
	"version": version,
	"download_url": f"{site_url}/downloads/conceptplug.zip",
	"homepage": site_url,
	"requires": requires,
	"requires_php": requires_php,
	"tested": tested,
	"sha256": sha256,
	"sha256_url": f"{site_url}/downloads/conceptplug.zip.sha256",
	"signature_url": f"{site_url}/downloads/conceptplug.zip.sig",
    "signature_algorithm": "ed25519",
	"public_key_url": f"{site_url}/downloads/conceptplug-update-public-key.pem",
	"public_key_fingerprint": public_key_fingerprint,
    "last_updated": datetime.fromtimestamp(epoch, timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
    "sections": {
		"description": description,
		"changelog": changelog,
    },
}
tmp_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
os.chmod(tmp_path, 0o644)
os.replace(tmp_path, final_path)
print(f"Wrote {final_path}")
PY

unzip -l "$public_dir/conceptplug.zip" 'conceptplug/conceptplug.php' >/dev/null
(cd "$public_dir" && sha256sum -c conceptplug.zip.sha256 >/dev/null)
test "$(unzip -p "$public_dir/conceptplug.zip" conceptplug/conceptplug.php | sed -n "s/^ \* Version:[[:space:]]*//p" | head -1)" = "$version"
test "$(unzip -p "$public_dir/conceptplug.zip" conceptplug/readme.txt | awk -F': ' '/^Stable tag:/{print $2; exit}')" = "$version"
test "$(unzip -p "$public_dir/conceptplug.zip" conceptplug/includes/conceptplug-update-public-key.pem | sha256sum | awk '{print $1}')" = "$(sha256sum "$public_key" | awk '{print $1}')"

echo "Release artifacts ready in ${public_dir}/ (v${version})"
