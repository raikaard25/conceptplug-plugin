#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

command -v xgettext >/dev/null || { echo "xgettext is required." >&2; exit 1; }
command -v msgmerge >/dev/null || { echo "msgmerge is required." >&2; exit 1; }
command -v msgfmt >/dev/null || { echo "msgfmt is required." >&2; exit 1; }

mkdir -p languages
mapfile -d '' php_files < <(find . -type f -name '*.php' \
	-not -path './build/*' -not -path './release/*' -not -path './vendor/*' -print0 | LC_ALL=C sort -z)
[[ "${#php_files[@]}" -gt 0 ]]

pot_tmp="$(mktemp languages/.conceptplug.pot.XXXXXX)"
trap 'rm -f "$pot_tmp"' EXIT
xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --sort-output \
  --add-comments=translators \
  --copyright-holder=ConceptPlug \
  --package-name=ConceptPlug \
  --package-version=1.7.0 \
  --msgid-bugs-address=support@conceptplug.com \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=_ex:1,2c \
  --keyword=_n:1,2 \
  --keyword=_nx:1,2,4c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --output="$pot_tmp" \
  "${php_files[@]}"
chmod 0644 "$pot_tmp"
mv -f "$pot_tmp" languages/conceptplug.pot

po_file="languages/conceptplug-th_TH.po"
[[ -s "$po_file" ]] || { echo "Missing curated Thai source: $po_file" >&2; exit 1; }
msgmerge --quiet --update --backup=none --sort-output "$po_file" languages/conceptplug.pot
po_tmp="$(mktemp languages/.conceptplug-th_TH.po.XXXXXX)"
msgattrib --clear-fuzzy --empty --no-obsolete --sort-output --output-file="$po_tmp" "$po_file"
chmod 0644 "$po_tmp"
mv -f "$po_tmp" "$po_file"
msgfmt --check --check-format -o languages/.conceptplug-th_TH.mo.tmp "$po_file"
chmod 0644 languages/.conceptplug-th_TH.mo.tmp
mv -f languages/.conceptplug-th_TH.mo.tmp languages/conceptplug-th_TH.mo

python3 - "$po_file" languages/conceptplug-th_TH.mo <<'PY'
from datetime import datetime, timezone
import gettext
import json
import os
from pathlib import Path
import sys

po_path = Path(sys.argv[1])
mo_path = Path(sys.argv[2])
with mo_path.open('rb') as stream:
    translation = gettext.GNUTranslations(stream)

messages = {
    key: [value]
    for key, value in sorted(translation._catalog.items())
    if isinstance(key, str) and key and isinstance(value, str) and value
}
messages[''] = {
    'domain': 'messages',
    'lang': 'th_TH',
    'plural-forms': 'nplurals=1; plural=0;',
}
epoch = int(os.environ.get('SOURCE_DATE_EPOCH', str(int(datetime.now(timezone.utc).timestamp()))))
revision = datetime.fromtimestamp(epoch, timezone.utc).strftime('%Y-%m-%d %H:%M+0000')
handles = (
    'conceptplug-core-admin',
    'conceptplug-billing',
    'cp-woocommerce-admin',
    'cp-woocommerce-enhance',
)
for handle in handles:
    payload = {
        'translation-revision-date': revision,
        'generator': 'ConceptPlug scripts/build-translations.sh',
        'source': handle,
        'domain': 'messages',
        'locale_data': {'messages': messages},
    }
    destination = po_path.parent / f'conceptplug-th_TH-{handle}.json'
    temporary = destination.with_name(f'.{destination.name}.tmp')
    temporary.write_text(json.dumps(payload, ensure_ascii=False, separators=(',', ':')) + '\n', encoding='utf-8')
    os.chmod(temporary, 0o644)
    os.replace(temporary, destination)
PY

echo "Built conceptplug.pot, Thai MO, and WordPress script translation JSON files."
