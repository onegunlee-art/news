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
| 2026-06-18 | `4997a0e` | P1-2a R0 fixture asserts → QuestConfig (test-only, behavior 0) |
| 2026-06-18 | `2b228af` | P1-2b eduQuestMatchesFrameFilter → QuestConfig derive (catalog frame filter) |
| 2026-06-18 | `56533bd` | deploy: aws/thegist-server-locations.inc rsync chown fix |
| 2026-06-18 | `4c3a6de` | R4–R6 live gates: quest_id 고정, evidence 턴 보강, today 의존 금지 (tools+docs only) |
| 2026-06-18 | `13a8c8b` | R5 evidence reasoning/evidence 4-turn split + gate fail assert (tools only) |
| 2026-06-18 | `8f0deff` | P1-2c eduPublicQuestPayload entry_mode additive (QuestConfig derive, FE/chat 0) |
| 2026-06-18 | `4150588` | P1-2d eduQuestToListItem entry_mode additive (list.php, FE/chat 0) |

## P1-2+ 라이브 완주 게이트 (R4–R6)

분기 이관 조각마다 `--live` 실행:

| 게이트 | 스크립트 | 고정 quest_code |
|--------|----------|-----------------|
| R4 | `php tools/edu_live_iran_e2e.php` | `Q-IRAN-FOREVER-001` (convergent) |
| R5 | `php tools/edu_live_decision_e2e.php` | `Q-G09-DEC-2022` (일본 decision) |
| R6 | `php tools/edu_myth_bust_e2e_smoke.php --live` | `Q-NUKE-AXIS-630` (myth_bust) |

세 게이트 모두 `list.php`에서 quest_id를 찾아 `start.php`에 전달한다. today 의존 금지.

P1-0/1: R0 `--live` + 로컬 R0–R3 + parity로 충분.

## 참고

- Hammer 톤 완화: `95b533d` (PR #5, `e37c8f2` merge)
- Hammer 톤만 되돌릴 때: `e37c8f2` 직전 `6b3b9c0`
