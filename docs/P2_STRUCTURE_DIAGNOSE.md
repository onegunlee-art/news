# P2-B 1단계 — 구조 진단 (rough)

> **내부 전용** — 학생 화면·reflection·글에 **노출 금지**  
> 점수/등급 없음. the gist **경첩·축** 기준으로 “어디를 채웠나”만 진단.

---

## 도구

| 파일 | 역할 |
|------|------|
| `public/api/edu/lib/eduStructureDiagnose.php` | 진단 함수 (축 커버리지 규칙 + LLM 보조) |
| `tools/edu_structure_diagnose.php` | CLI |
| `tools/edu_structure_diagnose_test.php` | LLM 없이 규칙 경로 회귀 |

---

## CLI

```bash
# fixture (LLM 없음)
php tools/edu_structure_diagnose_test.php
php tools/edu_structure_diagnose.php --fixture=docs/structure_diagnoses/fixture-630-sample.json

# EC2 / 로컬 Supabase
php tools/edu_structure_diagnose.php --quest-code=Q-AUTO-NUKE-630 --latest=3
php tools/edu_structure_diagnose.php --session=SESSION_UUID --live --write
```

`--live`: LLM으로 tension/clarity/evidence/structure_note 보강  
`--write`: `docs/structure_diagnoses/{session_id}.json`

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

## 검증 (이원근)

1. 본인 630 세션 `--session=...` 넣었을 때 **실제로 거친 축**과 `axes_covered` 일치?
2. 결과를 읽을 때 **점수 냄새** 없이 **구조 지도**로 읽히나?

---

## 안 함 (이번)

- `edu_student_insights` 저장
- 코칭 적응 (fact 길이 등 P2-B 2·3단계)
- 학생 UI 변경
