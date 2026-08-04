#!/bin/bash
# PHP-FPM pm.max_children 안전 증가 (503 임시저장 완화)
# 사용: sudo bash aws/tune-php-fpm-safe.sh
# 메모리 확인 후 가용 범위 내에서만 max_children 상향. reload만 사용(restart 아님).

set -euo pipefail

POOL="/etc/php/8.2/fpm/pool.d/www.conf"
if [ ! -f "$POOL" ]; then
  echo "ERROR: Pool config not found: $POOL"
  exit 1
fi

HEADROOM_MB=512
MAX_CAP=25
MIN_TARGET=15
MAX_REQUESTS=500

echo "=== PHP-FPM tune (503 mitigation) ==="
echo ""
echo "--- free -h ---"
free -h
echo ""

TOTAL_MB=$(free -m | awk '/^Mem:/ {print $2}')
AVAIL_MB=$(free -m | awk '/^Mem:/ {print $7}')
if [ -z "$AVAIL_MB" ] || [ "$AVAIL_MB" -lt 1 ]; then
  AVAIL_MB=$(free -m | awk '/^Mem:/ {print $4}')
fi

WORKER_MB=50
if pgrep -f "php-fpm: pool www" >/dev/null 2>&1; then
  SAMPLE=$(ps -C php-fpm -o rss= 2>/dev/null | awk '{s+=$1; n++} END {if(n>0) print int(s/n/1024); else print 0}')
  if [ "${SAMPLE:-0}" -ge 25 ]; then
    WORKER_MB=$SAMPLE
  fi
fi

BUDGET_MB=$((AVAIL_MB - HEADROOM_MB))
if [ "$BUDGET_MB" -lt 1 ]; then
  echo "ERROR: Not enough available memory (${AVAIL_MB}MB avail, need headroom ${HEADROOM_MB}MB)"
  exit 1
fi

CALC_MAX=$((BUDGET_MB / WORKER_MB))
TARGET=$CALC_MAX
if [ "$TARGET" -gt "$MAX_CAP" ]; then
  TARGET=$MAX_CAP
fi
if [ "$TARGET" -lt "$MIN_TARGET" ] && [ "$CALC_MAX" -ge "$MIN_TARGET" ]; then
  TARGET=$MIN_TARGET
fi
if [ "$TARGET" -lt 5 ]; then
  TARGET=5
fi

CURRENT=$(grep -E '^pm\.max_children\s*=' "$POOL" | tail -1 | sed 's/[^0-9]*//g')
CURRENT=${CURRENT:-5}

echo "--- Memory calculation ---"
echo "Total RAM:     ${TOTAL_MB} MB"
echo "Available:     ${AVAIL_MB} MB"
echo "Headroom:      ${HEADROOM_MB} MB"
echo "Worker est:    ${WORKER_MB} MB/worker"
echo "Budget:        ${BUDGET_MB} MB (= available - headroom)"
echo "Calc max:      ${CALC_MAX} (= budget / worker)"
echo "Target:        ${TARGET} (cap ${MAX_CAP})"
echo "Current:       ${CURRENT}"
echo ""

if [ "$TARGET" -le "$CURRENT" ]; then
  echo "SKIP: current pm.max_children ($CURRENT) >= safe target ($TARGET)"
else
  BACKUP="${POOL}.bak.$(date +%Y%m%d%H%M%S)"
  cp "$POOL" "$BACKUP"
  echo "Backup: $BACKUP"

  sed -i "s/^pm\.max_children\s*=.*/pm.max_children = ${TARGET}/" "$POOL"

  if grep -q '^pm\.max_requests\s*=' "$POOL"; then
    sed -i "s/^pm\.max_requests\s*=.*/pm.max_requests = ${MAX_REQUESTS}/" "$POOL"
  else
    echo "pm.max_requests = ${MAX_REQUESTS}" >> "$POOL"
  fi

  if grep -q '^pm\.max_spare_servers\s*=' "$POOL"; then
    SPARE=$((TARGET / 2))
    [ "$SPARE" -lt 3 ] && SPARE=3
    [ "$SPARE" -gt 10 ] && SPARE=10
    sed -i "s/^pm\.max_spare_servers\s*=.*/pm.max_spare_servers = ${SPARE}/" "$POOL"
  fi

  echo "Applied: pm.max_children ${CURRENT} -> ${TARGET}, pm.max_requests=${MAX_REQUESTS}"
fi

echo ""
echo "--- php-fpm config test ---"
php-fpm8.2 -t

echo "--- reload php8.2-fpm (not restart) ---"
systemctl reload php8.2-fpm
systemctl is-active php8.2-fpm

echo ""
echo "=== Done ==="
grep -E '^pm\.(max_children|max_requests|max_spare_servers)\s*=' "$POOL" || true
