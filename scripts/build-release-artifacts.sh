#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

plugin_file="conceptplug.php"
readme_file="readme.txt"
public_dir="${PUBLIC_DOWNLOADS_DIR:-public/downloads}"
site_url="${CONCEPTPLUG_SITE_URL:-https://conceptplug.com}"

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

mkdir -p "$public_dir"
cp "$zip_src" "$public_dir/conceptplug.zip"

sha256="$(sha256sum "$public_dir/conceptplug.zip" | awk '{print $1}')"
printf '%s  conceptplug.zip\n' "$sha256" > "$public_dir/conceptplug.zip.sha256"

requires="$(sed -n "s/^ \* Requires at least:[[:space:]]*//p" "$plugin_file" | head -1)"
requires_php="$(sed -n "s/^ \* Requires PHP:[[:space:]]*//p" "$plugin_file" | head -1)"
tested="$(awk -F': ' '/^Tested up to:/{print $2; exit}' "$readme_file")"
description="$(awk 'f{print; exit} /^$/ && p{f=1; next} /^Modular WordPress/{p=1}' "$readme_file")"
changelog="$(python3 - <<'PY'
from pathlib import Path
import re

text = Path("readme.txt").read_text()
match = re.search(r"== Changelog ==\s*(.*)\Z", text, re.S)
if not match:
    raise SystemExit("Changelog section missing in readme.txt")
block = match.group(1).strip()
print(block)
PY
)"

python3 - <<PY
import json
from datetime import datetime, timezone
from pathlib import Path

payload = {
    "name": "ConceptPlug",
    "slug": "conceptplug",
    "version": "${version}",
    "download_url": "${site_url}/downloads/conceptplug.zip",
    "homepage": "${site_url}",
    "requires": "${requires}",
    "requires_php": "${requires_php}",
    "tested": "${tested}",
    "sha256": "${sha256}",
    "last_updated": datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
    "sections": {
        "description": """${description}""",
        "changelog": """${changelog}""",
    },
}
path = Path("${public_dir}") / "conceptplug-update.json"
path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
print(f"Wrote {path}")
PY

unzip -l "$public_dir/conceptplug.zip" 'conceptplug/conceptplug.php' >/dev/null
(cd "$public_dir" && sha256sum -c conceptplug.zip.sha256 >/dev/null)

echo "Release artifacts ready in ${public_dir}/ (v${version})"
