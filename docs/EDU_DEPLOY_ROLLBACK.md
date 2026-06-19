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
| 2026-06-18 | `de5ca17` | P1-2f Reflection coach_profile derive + golden fallback parity test |
| 2026-06-18 | `2d9dfc7` | P1-2g GistStyleComposer coach_profile derive + buildContext golden parity test |
| 2026-06-18 | `bf90d2b` | P1-2h eduIsMythBustQuest → entry_mode delegate (call site 0) |
| 2026-06-18 | `6694ce7` | P1-2i eduHammerPayload / eduStudentStanceLabel coach_profile derive (call site 0) |
| 2026-06-18 | `cf57d14` | P1-2j chat.php reasoning phase eduIsMythBustQuest → entry_mode (4 points) |
| 2026-06-18 | `4e96a85` | P1-2k EduExplorePage badge entry_mode derive + quest_frame fallback |
| 2026-06-18 | `a60f202` | P1-2l QuestFlowChat isOpenResponse entry_mode derive + quest_frame fallback |
| 2026-06-18 | `6e7cd15` | fix: EduArticleCard mobile 펼치기 (touch pointerdown 회귀) |
| 2026-06-18 | `4a09cfc` | fix: evidence nudge 예시 퀘스트 기사 derive (nuke 하드코드 제거) |
| 2026-06-19 | `78abee3` | **P1-2m 배포 직전** — m FSM entry 백엔드 배포 전 즉시 롤백 지점 |
| 2026-06-19 | `328fb34` | P1-2m chat.php FSM entry submit_opening/select_stance → entry_mode guards (action alias 유지) |
| 2026-06-19 | `310d6bd` | **P1-2n 배포 직전** — n QuestFlowChat entry action 배포 전 즉시 롤백 지점 (m 포함) |
| 2026-06-19 | `772bcb1` | P1-2n QuestFlowChat stance entry action → entry_mode derive + guards |
| 2026-06-19 | `3f0a5c4` | **P1-3 배포 직전** — P1-2(a~n) 완료; legacy turn/QuestFlow 제거 전 롤백 |
| 2026-06-19 | `0f8ee8f` | P1-3 legacy booleans removed; turn.php + QuestFlowLegacy removed; eduQuestHammerMode |

## P1-2+ 라이브 완주 게이트 (R4–R6)

분기 이관 조각마다 `--live` 실행:

| 게이트 | 스크립트 | 고정 quest_code |
|--------|----------|-----------------|
| R4 | `php tools/edu_live_iran_e2e.php` | `Q-IRAN-FOREVER-001` (convergent) |
| R5 | `php tools/edu_live_decision_e2e.php` | `Q-G09-DEC-2022` (일본 decision) |
| R6 | `php tools/edu_myth_bust_e2e_smoke.php --live` | `Q-NUKE-AXIS-630` (myth_bust) |

세 게이트 모두 `list.php`에서 quest_id를 찾아 `start.php`에 전달한다. today 의존 금지.

P1-0/1: R0 `--live` + 로컬 R0–R3 + parity로 충분.

## P1 완료 후 다음 액션 (P2 / 지도1) — 2026-06-18 발견, 구현 보류

> P1-2j 육안 확인(이란 reasoning followup 정상) 직후 세션 관찰에서 도출.  
> **chat.php god-branch / evidence FSM 분기 재설계**가 수반되므로 P1(P1-2 k~n, P1-3) 완료 후 착수.

1. **hook을 decision_inquiry / convergent로 확장할지 검토**  
   myth_bust(핵억지)에만 hook(서사)이 있고 이란·일본(decision/convergent)에는 없음. 핵억지에서 본 서사의 힘을 다른 퀘스트에도 줄 가치가 있는지 검토. (지도1 정리(A) 후보)

2. **evidence를 고정 의무 단계 → 코치가 필요 시 권하는 선택으로 재설계**  
   이란 세션에서 학생이 evidence gate 없이도 자발적으로 기사를 인용함. evidence 등장이 개연성 없이 “의무 단계”로 느껴짐 → 흐름상 필요할 때 코치가 권하는 선택으로 바꿀 근거. (P2, FSM/evidence 진입 시점 재설계)

3. **핵심 원칙 재확인: 장치는 보조, 문답법이 본질**  
   hook / 기사 / 축 같은 장치는 문답법(좋은 질문이 학생 생각을 끌어내는 능력)을 보조할 뿐 대체하지 못함. P2(코치 깊이) 우선순위 판단에 반영.

4. **퀘스트 기사 snapshot 정합성 (2026-06-18, Q-LENS-NUKE-001 vs Q-NUKE-AXIS-630)**  
   펼치기 UI는 `excerpt`가 있어야 동작 (`EduArticleCard`: expanded && excerptLines.length > 0).  
   630은 backfill 완료, LENS-001 등 lens 시드 퀘스트는 excerpt 0자 → 펼치기 불가.  
   **구조적 공백:** 퀘스트 생성/시드와 snapshot backfill이 분리되어 누락 반복.  
   **즉시:** `php tools/edu_scan_quest_article_excerpts.php` → `--apply` (EC2에서 MySQL 연결 시 no_source까지 처리).  
   **P1 후:** 시드 스크립트에 backfill 자동 포함 또는 생성 파이프라인에 snapshot 단계 내장.

## 참고

- Hammer 톤 완화: `95b533d` (PR #5, `e37c8f2` merge)
- Hammer 톤만 되돌릴 때: `e37c8f2` 직전 `6b3b9c0`
