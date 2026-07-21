#!/usr/bin/env bash
set -Eeuo pipefail

cat >&2 <<'EOF'
This legacy publisher is disabled. It previously cloned/pushed a second web
repository and could deploy Vercel directly, bypassing the ConceptPlug monorepo
release gates.

Use the single monorepo release path instead:
  1. Build signed artifacts with conceptplug/scripts/build-release-artifacts.sh
     from CI, providing SOURCE_DATE_EPOCH and an external signing key.
  2. Run `bun run sync:downloads` and `bun run verify` in conceptplug-web.
  3. Let the protected monorepo CI workflow publish the verified site/artifacts.

There is intentionally no environment-variable bypass in this script.
EOF

exit 64
