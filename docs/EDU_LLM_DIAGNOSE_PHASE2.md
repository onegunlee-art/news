# EDU Phase 2 — LLM 구조 진단 + 레벨 판정

> 완주(compose) 시 **LLM 1회**로 tension/evidence/레벨 판정. 실패 시 **rule fallback**. 코치 FSM·UI 무관.

## 두 목표

1. **정확한 평가** — `tension_engaged`(양면), `evidence_linked`(fact 연결) LLM 판정 → XP 반영
2. **레벨 판정** — `exploration_depth_level` (1~7) 저장 (Phase 4 레벨업 연결은 나중)

## 동작

| 단계 | 동작 |
|------|------|
| compose 완료 | `eduSaveStructureInsight()` → `eduStructureDiagnoseSession()` |
| 기본 | `eduStructureDiagnoseResolveLlm()` → `chat()` (gpt-5.4 등) |
| LLM 성공 | `diagnose_mode=llm`, tension/evidence/level from LLM |
| LLM 실패 | `diagnose_mode=rule_fallback`, rule + level 추정 |
| insight 저장 실패 | compose·완주 **정상** (try/catch) |

## 롤백 env

```bash
# rule only (Phase 1 동작)
EDU_STRUCTURE_DIAGNOSE_RULE_ONLY=1
```

## DB (Supabase SQL Editor 1회)

```bash
# 파일: database/migrations/edu_student_insights_level.sql
alter table edu_student_insights add column if not exists exploration_depth_level ...
```

## 검증 (이원근)

```bash
php tools/edu_llm_diagnose_phase2_static_verify.php
php tools/edu_structure_diagnose_test.php

# EC2 — 세션 LLM 진단
php tools/edu_structure_diagnose.php --session=UUID --live --save-insights

# rule fallback 강제
EDU_STRUCTURE_DIAGNOSE_RULE_ONLY=1 php tools/edu_structure_diagnose.php --session=UUID --save-insights
```

### 사람 검수

- 일부러 **양면 + fact** 엮은 완주 → `tension_engaged=양면`, `evidence_linked=yes`
- **대충** 완주 → 낮은 tension/level
- LLM 끊어도 완주·저장 OK (`diagnose_mode=rule_fallback`)

## XP

기존 `eduXpFromStructureDiagnose` 유지 — LLM이 tension/evidence를 정확히 주면 XP만 개선됨.

## 안 함

- 레벨업 연결 (Phase 4)
- 사이 단계 2~6 (Phase 3)
- 코치 FSM 변경
