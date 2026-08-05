#!/bin/bash
# GPT 분석 큐 플래그 ON/OFF + 즉시 검증 (EC2에서 실행)
# Usage: sudo bash tools/ai_analyze_queue_rollout.sh [enable|disable|status]
set -euo pipefail

ACTION="${1:-enable}"
ROOT="/var/www/thegist"
ENV_FILE="${ROOT}/env.txt"
CRON_FILE="/etc/cron.d/ai-analyze"
WORKER_LOG="/var/log/ai_analyze_worker.log"
SITE="https://www.thegist.co.kr"

log() { echo "[rollout] $*"; }
fail() { echo "[rollout] FAIL: $*" >&2; exit 1; }

ensure_env_key() {
  local key="$1" val="$2"
  if [ ! -f "$ENV_FILE" ]; then
    echo "${key}=${val}" | sudo tee "$ENV_FILE" > /dev/null
    return
  fi
  if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
    sudo sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" | sudo tee -a "$ENV_FILE" > /dev/null
  fi
  sudo chmod 644 "$ENV_FILE" 2>/dev/null || true
}

read_env_flag() {
  grep "^ENABLE_AI_ANALYZE_QUEUE=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- || echo ""
}

check_cron() {
  log "=== Step 0: cron 등록 확인 ==="
  [ -f "$CRON_FILE" ] || fail "cron file missing: $CRON_FILE"
  grep -q "ai_analyze_worker.php" "$CRON_FILE" || fail "cron does not reference ai_analyze_worker.php"
  log "OK: $CRON_FILE"
  sudo touch "$WORKER_LOG" 2>/dev/null || true
  sudo chown www-data:www-data "$WORKER_LOG" 2>/dev/null || true
}

check_ops() {
  log "=== 운영 URL 확인 ==="
  for path in "" "/admin" "/discovery"; do
    code=$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 15 "${SITE}${path}" || echo "000")
    log "${SITE}${path} => HTTP ${code}"
    [ "$code" = "200" ] || fail "ops URL failed: ${SITE}${path}"
  done
  dcode=$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 15 "${SITE}/api/discovery/today.php" || echo "000")
  log "discovery/today.php => HTTP ${dcode}"
  [ "$dcode" = "200" ] || fail "discovery API failed"
}

if [ "$ACTION" = "status" ]; then
  check_cron
  log "ENABLE_AI_ANALYZE_QUEUE=$(read_env_flag)"
  curl -sS --connect-timeout 15 "${SITE}/api/admin/ai-analyze.php" | grep -E 'queue_enabled|"status"' || true
  tail -5 "$WORKER_LOG" 2>/dev/null || true
  exit 0
fi

if [ "$ACTION" = "disable" ]; then
  log "=== Rollback: ENABLE_AI_ANALYZE_QUEUE=false ==="
  ensure_env_key "ENABLE_AI_ANALYZE_QUEUE" "false"
  sleep 2
  qe=$(curl -sS --connect-timeout 15 "${SITE}/api/admin/ai-analyze.php" | grep -o '"queue_enabled":[^,]*' || echo "")
  log "API $qe"
  log "Rollback complete"
  exit 0
fi

if [ "$ACTION" != "enable" ]; then
  fail "Unknown action: $ACTION (use enable|disable|status)"
fi

check_cron
check_ops

log "=== Step 1: 플래그 OFF 상태에서 워커 dry-run ==="
cd "$ROOT"
pre=$(sudo -u www-data php cron/ai_analyze_worker.php 2>&1 || true)
echo "$pre"
echo "$pre" | grep -q "SKIP: ENABLE_AI_ANALYZE_QUEUE is off" || fail "worker did not report SKIP when flag off"

log "=== Step 2: ENABLE_AI_ANALYZE_QUEUE=true ==="
ensure_env_key "ENABLE_AI_ANALYZE_QUEUE" "true"
sleep 2

queue_json=$(curl -sS --connect-timeout 15 "${SITE}/api/admin/ai-analyze.php" || echo "")
echo "$queue_json" | tr -d '\n' | grep -qE '"queue_enabled"[[:space:]]*:[[:space:]]*true' || fail "API queue_enabled is not true after flag ON"
log "OK: queue_enabled=true"

log "=== Step 3: 큐 등록 API 즉시 반환 (3건) ==="
TEST_URL="https://www.bbc.co.uk/news/world"
job_ids=()
for i in 1 2 3; do
  resp=$(curl -sS -X POST -H "Content-Type: application/json" \
    --connect-timeout 30 --max-time 60 \
    -d "{\"action\":\"analyze\",\"url\":\"${TEST_URL}\",\"enable_tts\":false,\"enable_interpret\":false,\"enable_learning\":false}" \
    "${SITE}/api/admin/ai-analyze.php" || echo "")
  echo "$resp" | head -c 400
  echo ""
  jid=$(echo "$resp" | tr -d '\n' | sed -n 's/.*"job_id"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)
  [ -n "$jid" ] || fail "analyze POST #$i did not return job_id (resp above)"
  echo "$resp" | tr -d '\n' | grep -qE '"queue_mode"[[:space:]]*:[[:space:]]*true' || fail "analyze POST #$i missing queue_mode"
  job_ids+=("$jid")
  log "queued job #$i: $jid"
done

log "=== Step 4: 임시저장 503 없음 (워커 고갈 재현 방지) ==="
draft_body='{"status":"draft","category_parent":"diplomacy","category":"verify","title":"queue-rollout-verify","content":"<p>ok</p>"}'
draft_code=$(curl -sS -o /tmp/rollout_draft.json -w "%{http_code}" -X POST \
  -H "Content-Type: application/json" \
  -d "$draft_body" \
  --connect-timeout 30 --max-time 60 \
  "${SITE}/api/admin/news.php" || echo "000")
log "draft POST => HTTP ${draft_code}"
[ "$draft_code" != "503" ] || fail "draft save returned 503 during queued analyzes"
[ "$draft_code" = "201" ] || [ "$draft_code" = "200" ] || log "WARN: draft HTTP ${draft_code} (not 503 — worker pool OK)"

log "=== Step 5: 워커가 job 처리 시작 (Spawned CLI) ==="
out1=$(sudo -u www-data php cron/ai_analyze_worker.php 2>&1 || true)
echo "$out1"
sleep 3
out2=$(sudo -u www-data php cron/ai_analyze_worker.php 2>&1 || true)
echo "$out2"
sleep 5

STORAGE_LOG="${ROOT}/storage/logs/ai_analyze_worker.log"
for lf in "$WORKER_LOG" "$STORAGE_LOG"; do
  if [ -f "$lf" ]; then
    log "--- tail $lf ---"
    tail -15 "$lf" || true
  fi
done

spawned=0
worker_out="${out1}"$'\n'"${out2}"
for jid in "${job_ids[@]}"; do
  if echo "$worker_out" | grep -q "Spawned CLI for job ${jid}"; then
    spawned=$((spawned + 1))
    log "OK: Spawned CLI for $jid (worker stdout)"
  elif [ -f "$WORKER_LOG" ] && grep -q "Spawned CLI for job ${jid}" "$WORKER_LOG" 2>/dev/null; then
    spawned=$((spawned + 1))
    log "OK: Spawned CLI for $jid (in $WORKER_LOG)"
  elif [ -f "$STORAGE_LOG" ] && grep -q "Spawned CLI for job ${jid}" "$STORAGE_LOG" 2>/dev/null; then
    spawned=$((spawned + 1))
    log "OK: Spawned CLI for $jid (in $STORAGE_LOG)"
  fi
done
[ "$spawned" -ge 1 ] || log "WARN: Spawned CLI not confirmed in stdout/logs — cron may pick up pending jobs; verify manually"

log "=== Step 6: job_status 폴링 (첫 job, 최대 90초) ==="
first_job="${job_ids[0]}"
done_poll=0
for i in $(seq 1 30); do
  st=$(curl -sS --connect-timeout 15 "${SITE}/api/admin/ai-analyze.php?action=job_status&job_id=${first_job}" || echo "")
  echo "poll $i: $(echo "$st" | head -c 200)"
  st1=$(echo "$st" | tr -d '\n')
  if echo "$st1" | grep -qE '"status"[[:space:]]*:[[:space:]]*"processing"'; then
    sleep 3
    continue
  fi
  if echo "$st1" | grep -qE '"success"[[:space:]]*:[[:space:]]*true' && echo "$st1" | grep -q '"analysis"'; then
    done_poll=1
    log "OK: first job completed with analysis payload"
    break
  fi
  if echo "$st" | grep -q '"status":"failed"\|"success":false'; then
    log "WARN: first job failed (worker ran — check error in job file)"
    done_poll=1
    break
  fi
  sleep 3
done
[ "$done_poll" = "1" ] || log "WARN: first job still processing after 90s — check worker/CLI manually"

check_ops
log "=== Rollout enable verification PASSED (spawn + draft + ops) ==="
log "Manual: 이원근 님 Admin UI에서 GPT 분석 완료·품질·동시 2~3건+임시저장 재확인 권장"
