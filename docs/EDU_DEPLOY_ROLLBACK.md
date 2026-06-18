# GIST EDU — 배포 롤백 해시

사고 시 `git checkout <HEAD>` 또는 EC2에서 해당 커밋으로 되돌린 뒤 재배포.

| 날짜 | HEAD | 설명 |
|------|------|------|
| 2026-06-18 | `a0c9d6d` | evidence bridge 멘트 작업 직전 (catalog explore + quest_frame backfill fix) |
| 2026-06-18 | `a4601b0` | evidence bridge 멘트 배포 (고정 템플릿 + reason 구절, LLM refinePrompt 제거) |
| 2026-06-18 | `d3666d1` | deploy: composer.json rsync chown fix (a4601b0 배포 exit 23 후속) |
| 2026-06-18 | `1f9b502` | deploy: composer 단일 rsync + pre-chown + --no-times (|| fallback 제거) |
| 2026-06-18 | `a71b103` | P1-0 dialogue turn_id (append-only, state read-only normalize, R7 write-back guard) |
| 2026-06-18 | `a435f30` | P1-0 legacy in-progress abandon (59 sessions, blueprint.abandoned_at, resumable stage filter) |
| 2026-06-18 | `9df0579` | P1-1 QuestConfig read layer (eduResolveQuestConfig, parity only, call-site 0) |

## P1-2+ 라이브 완주 게이트 (R4–R6)

분기 이관 조각마다 `--live` 실행:

| 게이트 | 스크립트 | 퀘스트 타입 |
|--------|----------|-------------|
| R4 | `php tools/edu_live_iran_e2e.php --live` | 이란 decision_inquiry |
| R5 | `php tools/edu_live_decision_e2e.php --live` | 일본 decision_inquiry |
| R6 | `php tools/edu_myth_bust_e2e_smoke.php --live` | 핵 myth_bust |

P1-0/1: R0 `--live` + 로컬 R0–R3 + parity로 충분.

## 참고

- Hammer 톤 완화: `95b533d` (PR #5, `e37c8f2` merge)
- Hammer 톤만 되돌릴 때: `e37c8f2` 직전 `6b3b9c0`
