#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

require_file() {
  if [[ ! -f "$1" ]]; then
    echo "missing file: $1" >&2
    exit 1
  fi
}

require_file "$ROOT/admin/views/billing.php"
require_file "$ROOT/assets/js/billing.js"

if ! grep -q 'conceptplug-billing' "$ROOT/admin/class-admin-menu.php"; then
  echo "billing submenu not registered" >&2
  exit 1
fi

if ! grep -q 'create_payment_intent' "$ROOT/includes/class-api-client.php"; then
  echo "payment intent API client missing" >&2
  exit 1
fi

if grep -q 'data-secure-checkout' "$ROOT/admin/class-admin-menu.php"; then
  echo "external checkout still linked from credits bar" >&2
  exit 1
fi

if grep -q 'conceptplug_checkout_session' "$ROOT/assets/js/core-admin.js"; then
  echo "external checkout popup still wired in core-admin.js" >&2
  exit 1
fi

if ! grep -q 'billing_url' "$ROOT/modules/conwoo/assets/js/conwoo.js"; then
  echo "conwoo 402 handler still missing billing_url" >&2
  exit 1
fi

echo "billing smoke checks passed"
