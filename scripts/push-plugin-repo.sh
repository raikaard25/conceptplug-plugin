#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

plugin_repo="${CONCEPTPLUG_PLUGIN_REPO_URL:-https://github.com/raikaard25/conceptplug-plugin.git}"
plugin_branch="${CONCEPTPLUG_PLUGIN_BRANCH:-main}"
workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

if [[ -n "${GITHUB_TOKEN:-}" ]]; then
  token="$(tr -d '[:space:]' <<<"$GITHUB_TOKEN")"
  repo_path="${plugin_repo#https://}"
  git clone --depth 1 --branch "$plugin_branch" "https://x-access-token:${token}@${repo_path}" "$workdir/plugin" 2>/dev/null || {
    mkdir -p "$workdir/plugin"
    git -C "$workdir/plugin" init -b "$plugin_branch"
    git -C "$workdir/plugin" remote add origin "https://x-access-token:${token}@${repo_path}"
  }
else
  git clone --depth 1 --branch "$plugin_branch" "$plugin_repo" "$workdir/plugin" 2>/dev/null || {
    mkdir -p "$workdir/plugin"
    git -C "$workdir/plugin" init -b "$plugin_branch"
    git -C "$workdir/plugin" remote add origin "$plugin_repo"
  }
fi

rsync -a --delete \
  --exclude '.git' \
  --exclude 'build/' \
  --exclude 'public/downloads/conceptplug.zip' \
  --exclude 'public/downloads/conceptplug-update.json' \
  --exclude 'public/downloads/conceptplug.zip.sha256' \
  ./ "$workdir/plugin/"

version="$(sed -n 's/^ \* Version:[[:space:]]*//p' conceptplug.php | head -1)"
cd "$workdir/plugin"
git add -A
if git diff --cached --quiet; then
  echo "No plugin changes to push."
  exit 0
fi

git -c user.name="conceptplug-release" -c user.email="release@conceptplug.com" \
  commit -m "release: ConceptPlug v${version} (updater + API activation sync)"
git push -u origin "$plugin_branch"
echo "Pushed ConceptPlug v${version} to ${plugin_repo} (${plugin_branch})"
