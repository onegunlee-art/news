#!/usr/bin/env bash
# EC2에서 실행: 프로모션 API용 Nginx 블록 존재·순서 검증 후 nginx -t
# 사용: sudo bash verify-promotion-nginx-on-server.sh [sites-설정파일]
# 기본 경로: /etc/nginx/sites-available/thegist (레포 배포 가이드와 동일하게 조정)

set -euo pipefail
CONF="${1:-/etc/nginx/sites-available/thegist}"

if [[ ! -f "$CONF" ]]; then
  echo "설정 파일 없음: $CONF" >&2
  echo "예: sudo $0 /etc/nginx/sites-enabled/thegist" >&2
  exit 1
fi

ok=1
if ! grep -q 'location = /api/subscription/verify-promo' "$CONF"; then
  echo "누락: location = /api/subscription/verify-promo"
  ok=0
fi
if ! grep -q 'location = /api/admin/promotion-codes' "$CONF"; then
  echo "누락: location = /api/admin/promotion-codes"
  ok=0
fi

promo_line=$(grep -n 'location = /api/admin/promotion-codes' "$CONF" | head -1 | cut -d: -f1 || true)
admin_re_line=$(grep -nE 'location[[:space:]]+~[[:space:]]+\^/api/admin/' "$CONF" | head -1 | cut -d: -f1 || true)

if [[ -z "$promo_line" || -z "$admin_re_line" ]]; then
  echo "블록 라인 확인 실패 (promotion-codes 또는 admin 정규식 location 미발견)"
  ok=0
elif [[ "$promo_line" -ge "$admin_re_line" ]]; then
  echo "순서 오류: promotion-codes 블록은 반드시 'location ~ ^/api/admin/' 보다 위에 있어야 합니다."
  ok=0
fi

if [[ "$ok" -ne 1 ]]; then
  echo "" >&2
  echo "레포 aws/thegist-nginx.conf 의 구독·관리자 구간을 서버 $CONF 에 수동 반영 후 nginx -t && systemctl reload nginx 하세요." >&2
  exit 1
fi

echo "프로모션 API Nginx 블록: OK ($(basename "$CONF"))"
nginx -t
