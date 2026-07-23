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

if ! grep -q 'create_topup_intent' "$ROOT/includes/class-api-client.php"; then
  echo "top-up intent API client missing" >&2
  exit 1
fi

if ! grep -q 'create_subscription_checkout' "$ROOT/includes/class-api-client.php"; then
  echo "subscription checkout API client missing" >&2
  exit 1
fi

if ! grep -q 'Monthly subscription' "$ROOT/admin/views/billing.php"; then
  echo "billing.php missing Monthly subscription UI" >&2
  exit 1
fi

if ! grep -q 'resolve_billing_config' "$ROOT/admin/class-admin-menu.php"; then
  echo "billing page does not hydrate live billing config" >&2
  exit 1
fi

if ! grep -q 'resolve_billing_config( \$account, true )' "$ROOT/admin/class-admin-menu.php"; then
  echo "billing page must force-refresh live billing config (not stale account/transient)" >&2
  exit 1
fi

if ! grep -q 'Prefer live/public billing-config' "$ROOT/admin/class-admin-menu.php"; then
  echo "resolve_billing_config must prefer live billing-config over account cache" >&2
  exit 1
fi

if ! grep -q 'subscription_plus_topup' "$ROOT/assets/js/billing.js"; then
  echo "billing.js missing subscription mode branch" >&2
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

if ! grep -q 'billing_url' "$ROOT/modules/woocommerce/assets/js/woocommerce-admin.js"; then
  echo "WooCommerce module 402 handler still missing billing_url" >&2
  exit 1
fi

echo "billing smoke checks passed"
