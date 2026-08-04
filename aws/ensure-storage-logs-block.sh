#!/bin/bash
# storage/logs 웹 접근 차단 — thegist nginx server 블록 전체에 적용
# 사용: sudo bash aws/ensure-storage-logs-block.sh
set -euo pipefail

MARK='location ^~ /storage/logs/'
SNIPPET_FILE=$(mktemp)
cat > "$SNIPPET_FILE" << 'EOF'
    # storage/logs — 에러 로그 웹 노출 차단 (auto-added by CI)
    location ^~ /storage/logs/ {
        deny all;
        return 403;
    }

EOF

patched=0
skipped=0

for conf in /etc/nginx/sites-enabled/* /etc/nginx/sites-available/*; do
  [ -f "$conf" ] || continue
  if ! grep -q 'root /var/www/thegist/public' "$conf" 2>/dev/null; then
    continue
  fi
  if grep -qF "$MARK" "$conf" 2>/dev/null; then
    echo "SKIP (already has block): $conf"
    skipped=$((skipped + 1))
    continue
  fi
  backup="${conf}.bak.$(date +%Y%m%d%H%M%S)"
  cp "$conf" "$backup"
  echo "Backup: $backup"

  # root 지시어 바로 다음에 삽입 (server 블록 공통 앵커)
  awk -v snippet="$SNIPPET_FILE" '
    /root \/var\/www\/thegist\/public;/ {
      print
      while ((getline line < snippet) > 0) print line
      close(snippet)
      next
    }
    { print }
  ' "$conf" > "${conf}.new"

  if grep -qF "$MARK" "${conf}.new"; then
    mv "${conf}.new" "$conf"
    echo "PATCHED: $conf"
    patched=$((patched + 1))
  else
    rm -f "${conf}.new"
    echo "WARN: could not patch $conf (anchor missing)"
  fi
done

rm -f "$SNIPPET_FILE"

echo "--- nginx -t ---"
nginx -t

echo "--- reload nginx ---"
systemctl reload nginx

echo "Done: patched=$patched skipped=$skipped"
curl -sS -o /dev/null -w "local curl storage/logs => HTTP %{http_code}\n" \
  -H 'Host: www.thegist.co.kr' \
  'http://127.0.0.1/storage/logs/error_news.log' || true
