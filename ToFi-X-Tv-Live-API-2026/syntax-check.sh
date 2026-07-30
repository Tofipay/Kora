#!/usr/bin/env bash
# فحص صيغة جميع ملفات PHP في المشروع.
# الاستخدام:  bash syntax-check.sh

set -u
cd "$(dirname "$0")"

FILES=(
  index.php
  hls-core.php
  viewers.php
  override.php
  panel.php
  error.php
  worker.php
  config.example.php
  tests/origin.php
  tests/router.php
  tests/run-tests.php
  tests/test-viewers.php
  tests/test-worker.php
  tests/dev-serve.php
  loadtest/hls-loadtest.php
)

if [ -f config.php ]; then
  FILES+=(config.php)
fi

STATUS=0

for FILE in "${FILES[@]}"; do
  if [ ! -f "$FILE" ]; then
    printf '  ?  %s (غير موجود)\n' "$FILE"
    continue
  fi

  if OUTPUT=$(php -l "$FILE" 2>&1); then
    printf '  ✓  %s\n' "$FILE"
  else
    printf '  ✗  %s\n%s\n' "$FILE" "$OUTPUT"
    STATUS=1
  fi
done

if [ "$STATUS" -eq 0 ]; then
  printf '\nجميع الملفات سليمة.\n'
else
  printf '\nتوجد أخطاء صيغة.\n'
fi

exit "$STATUS"
