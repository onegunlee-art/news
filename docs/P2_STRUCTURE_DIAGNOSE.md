# P2-B 1단계 — 구조 진단 (rough)

> **내부 전용** — 학생 화면·reflection·글에 **노출 금지**  
> 점수/등급 없음. the gist **경첩·축** 기준으로 “어디를 채웠나”만 진단.

---

## 도구

| 파일 | 역할 |
|------|------|
| `public/api/edu/lib/eduStructureDiagnose.php` | 진단 함수 (축 커버리지 규칙 + LLM 보조) |
| `public/api/edu/lib/eduStudentInsights.php` | 진단 → `edu_student_insights` 저장·조회 (2단계) |
| `tools/edu_structure_diagnose.php` | CLI |
| `tools/edu_structure_diagnose_test.php` | LLM 없이 규칙 경로 회귀 |
| `tools/edu_backfill_student_insights.php` | completed 세션 백필 (멱등) |
| `tools/edu_structure_insights_list.php` | 학생별 시간순 이력 조회 |

---

## CLI (1단계)

```bash
# fixture (LLM 없음)
php tools/edu_structure_diagnose_test.php
php tools/edu_structure_diagnose.php --fixture=docs/structure_diagnoses/fixture-630-sample.json

# EC2 / 로컬 Supabase
php tools/edu_structure_diagnose.php --quest-code=Q-AUTO-NUKE-630 --latest=3
php tools/edu_structure_diagnose.php --session=SESSION_UUID --live --write
php tools/edu_structure_diagnose.php --session=SESSION_UUID --save-insights
```

`--live`: LLM으로 tension/clarity/evidence/structure_note 보강  
`--write`: `docs/structure_diagnoses/{session_id}.json`  
`--save-insights`: `edu_student_insights`에 1행 저장 (멱등)

---

## 출력 스키마

```json
{
  "diagnose_version": "p2-b-v1-rough",
  "quest_code": "Q-AUTO-NUKE-630",
  "session_id": "...",
  "internal_only": true,
  "axes_covered": [
    {
      "axis_id": "military",
      "point": "...",
      "covered": true,
      "status": "engaged|shallow|skipped|missing",
      "student_quote": "..."
    }
  ],
  "tension_engaged": "양면|한쪽|없음",
  "conclusion_clarity": "명확|모호",
  "evidence_linked": "yes|no",
  "structure_note": "구조 서술 (등급/점수 금지)"
}
```

**axes_covered**는 blueprint `guide_axis_answers`에서 **규칙으로** 산출 (axis_guide 세션).

---

## 2단계 — edu_student_insights 저장

### 테이블

`database/migrations/edu_student_insights.sql` — Supabase SQL Editor에서 수동 실행.

- 세션당 1행 (`unique(session_id)`)
- denormalized: `axes_engaged_count`, `axes_total`, `tension_engaged`, …
- 전체 JSON: `diagnose_json` jsonb
- **score/grade 컬럼 없음**, `internal_only=true`

### 저장 시점

- [`public/api/edu/session/compose.php`](public/api/edu/session/compose.php) — `stage=completed` 직후 `eduSaveStructureInsight()` (fail-safe, compose 응답 무영향)
- 기본 **rule_fallback** (LLM 없음). env `EDU_STRUCTURE_DIAGNOSE_LIVE=1` 시 LLM 보강

### 백필 (미팅 데모)

```bash
php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630 --dry-run
php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630
php tools/edu_backfill_student_insights.php --quest-code=Q-AUTO-NUKE-630 --student-id=UUID
```

### 조회 (성장 흐름)

```bash
php tools/edu_structure_insights_list.php --student-id=UUID
php tools/edu_structure_insights_list.php --display-name=이원근
php tools/edu_structure_insights_list.php --student-id=UUID --json
```

---

## 검증 체크리스트

1. 본인 630 완료 세션 → `edu_student_insights` 1행씩
2. 동일 `student_id` → `diagnosed_at` ASC 시간순
3. `axes_engaged_count/axes_total`로 “1축 → 3축” 성장 읽힘
4. score/grade 없음, structure_note는 구조 서술
5. compose·학생 UI 변화 없음
6. 백필 재실행 → 중복 없음 (`unique(session_id)`)

---

## 안 함 (3단계 이후)

- 코칭 적응 (fact 길이 등 P2-B 3단계)
- 학생 UI / 부모 리포트 UI
