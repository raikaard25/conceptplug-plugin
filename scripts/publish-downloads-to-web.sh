#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

web_repo="${CONCEPTPLUG_WEB_REPO_URL:-https://github.com/raikaard25/conceptplug.git}"
web_branch="${CONCEPTPLUG_WEB_BRANCH:-main}"
artifacts_dir="public/downloads"
overlay_dir="release/conceptplug-web"
workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

for path in "$artifacts_dir/conceptplug.zip" "$artifacts_dir/conceptplug-update.json" "$artifacts_dir/conceptplug.zip.sha256"; do
  [[ -s "$path" ]] || {
    echo "Missing $path. Run ./scripts/build-release-artifacts.sh first." >&2
    exit 1
  }
done

if [[ -n "${GITHUB_TOKEN:-}" ]]; then
  token="$(tr -d '[:space:]' <<<"$GITHUB_TOKEN")"
  repo_path="${web_repo#https://}"
  git clone --depth 1 --branch "$web_branch" "https://x-access-token:${token}@${repo_path}" "$workdir/web"
else
  git clone --depth 1 --branch "$web_branch" "$web_repo" "$workdir/web"
fi

mkdir -p "$workdir/web/public/downloads"
cp "$artifacts_dir/conceptplug.zip" "$workdir/web/public/downloads/"
cp "$artifacts_dir/conceptplug-update.json" "$workdir/web/public/downloads/"
cp "$artifacts_dir/conceptplug.zip.sha256" "$workdir/web/public/downloads/"
cp "$overlay_dir/src/pages/download.astro" "$workdir/web/src/pages/download.astro"
cp "$overlay_dir/src/pages/activation-complete.astro" "$workdir/web/src/pages/activation-complete.astro"

python3 - "$workdir/web/src/layouts/Base.astro" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
text = path.read_text()
text = text.replace('href="https://wordpress.org/plugins/conceptplug/"', 'href="/download"')
text = text.replace(
    '\t\t\t\t\ttarget="_blank"\n\t\t\t\t\trel="noopener noreferrer"\n\t\t\t\t\tclass="hidden rounded-lg bg-cp-primary',
    '\t\t\t\t\tclass="hidden rounded-lg bg-cp-primary',
)
nav_marker = '<a href="/pricing" class="hover:text-cp-primary">Pricing</a>'
download_link = '<a href="/download" class="hover:text-cp-primary">Download</a>'
if download_link not in text:
    text = text.replace(nav_marker, download_link + '\n\t\t\t\t\t' + nav_marker, 1)
if 'href="/download"' not in text:
    raise SystemExit('Failed to patch Base.astro download link')
path.write_text(text)
PY

cd "$workdir/web"
git add public/downloads src/pages/download.astro src/pages/activation-complete.astro src/layouts/Base.astro
if git diff --cached --quiet; then
  echo "No web changes to publish."
  exit 0
fi

version="$(python3 -c 'import json; print(json.load(open("public/downloads/conceptplug-update.json"))["version"])')"
git -c user.name="conceptplug-release" -c user.email="release@conceptplug.com" commit -m "chore: publish ConceptPlug plugin downloads v${version}"
git push origin "$web_branch"
echo "Published downloads v${version} to ${web_repo} (${web_branch})"

if [[ -n "${CONCEPTPLUG_VERCEL_DEPLOY_HOOK:-}" ]]; then
  curl -fsS -X POST "$CONCEPTPLUG_VERCEL_DEPLOY_HOOK" >/dev/null
  echo "Triggered Vercel deploy hook."
elif command -v bun >/dev/null 2>&1 && command -v npx >/dev/null 2>&1; then
  bun install --frozen-lockfile
  bun run build
  deploy_url="$(
    npx vercel@latest deploy dist --prod --yes 2>&1 \
      | sed -n 's/.*Production[[:space:]]*\(.*\.vercel\.app\).*/\1/p' \
      | head -1
  )"
  if [[ -z "$deploy_url" ]]; then
    echo "warning: could not detect Vercel deployment URL; site may need manual deploy." >&2
  else
    npx vercel@latest alias "$deploy_url" "${CONCEPTPLUG_SITE_DOMAIN:-conceptplug.com}" >/dev/null
    echo "Aliased ${CONCEPTPLUG_SITE_DOMAIN:-conceptplug.com} to ${deploy_url}"
  fi
fi
