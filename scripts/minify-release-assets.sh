#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

destination="${1:-${CONCEPTPLUG_MINIFIED_ASSET_DIR:-}}"
[[ -n "$destination" ]] || {
  echo "Usage: $0 /path/to/staging-output" >&2
  echo "Source assets are never minified in place." >&2
  exit 64
}

repo_root="$(pwd -P)"
mkdir -p "$destination"
destination="$(cd "$destination" && pwd -P)"
[[ "$destination" != "$repo_root" ]] || {
  echo "Refusing to overwrite the plugin source tree." >&2
  exit 1
}

js_files=(
  assets/js/core-admin.js
  assets/js/billing.js
  modules/woocommerce/assets/js/woocommerce-admin.js
  modules/woocommerce/assets/js/woocommerce-enhance.js
)

for src in "${js_files[@]}"; do
  [[ -f "$src" ]] || continue
  output="$destination/$src"
  mkdir -p "$(dirname "$output")"
  if command -v terser >/dev/null 2>&1; then
    terser "$src" --compress --mangle --output "$output"
  elif command -v bun >/dev/null 2>&1; then
    bunx terser "$src" --compress --mangle --output "$output"
  else
    echo "terser is required to build minified staging assets." >&2
    exit 1
  fi
  chmod 0644 "$output"
done

for src in assets/css/*.css modules/woocommerce/assets/css/*.css; do
  [[ -f "$src" ]] || continue
  output="$destination/$src"
  mkdir -p "$(dirname "$output")"
  if command -v cleancss >/dev/null 2>&1; then
    cleancss -o "$output" "$src"
  elif command -v bun >/dev/null 2>&1; then
    bunx cleancss -o "$output" "$src"
  else
    cp "$src" "$output"
  fi
  chmod 0644 "$output"
done

echo "Minified staging assets written to $destination; source files were unchanged."
