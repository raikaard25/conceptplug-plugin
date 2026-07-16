#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

minify_js() {
  local src="$1"
  if command -v terser >/dev/null 2>&1; then
    terser "$src" --compress --mangle --output "$src"
    echo "Minified $src"
  elif command -v bun >/dev/null 2>&1; then
    bunx terser "$src" --compress --mangle --output "$src"
    echo "Minified $src (via bunx terser)"
  else
    echo "terser not found; skipping minify for $src" >&2
  fi
}

minify_js assets/js/core-admin.js
minify_js assets/js/billing.js

if compgen -G "assets/js/conwoo*.js" >/dev/null; then
  for f in assets/js/conwoo*.js; do
    minify_js "$f"
  done
fi

if compgen -G "assets/css/*.css" >/dev/null; then
  if command -v bun >/dev/null 2>&1; then
    for f in assets/css/*.css; do
      bunx cleancss -o "$f" "$f" 2>/dev/null && echo "Minified $f" || echo "cleancss skipped $f" >&2
    done
  fi
fi

echo "Release asset minify pass complete."
