# EDU 7단계 Phase 1 — 레벨별 구조 깊이 검증

> **목적:** 같은 글에서 AI가 level 1 / 4 / 7 **다른 깊이**의 경첩·축을 뽑을 수 있는지 확인.  
> **범위:** 검증 도구만. 라이브 코치·level 1/7 프로덕션 **변경 없음**.

## 핵심 질문

630(핵 억지) 같은 글에서:

| level | 기대 깊이 |
|-------|-----------|
| 1 (초등) | 경첩 한 면, 1~2축, 단순 질문 ("핵이 있으면 안전할까?") |
| 4 (중등) | 양면 경첩, 3축 |
| 7 (고등) | 다층 경첩, 3~4축, counter_angle(반론의 반론) |

**셋이 비슷하면** → 7단계 전체 재설계. **깔끔히 다르면** → Phase 2(LLM 진단) · Phase 3(사이 단계) 진행.

## 실행 (EC2 / MySQL + LLM)

```bash
# 630만
php tools/edu_level_depth_verify.php 630

# 재현 확인
php tools/edu_level_depth_verify.php 630 150 196

# 프롬프트만 (DB/LLM 불필요)
php tools/edu_level_depth_verify.php --dry-run

# 정적 검사
php tools/edu_level_depth_verify_static.php
```

## 산출물

- `docs/level_depth_verify/630.json` — level 1/4/7 추출 + compare 요약
- `docs/level_depth_verify/630.md` — 사람 검수용 나란히 표

## 사람 검수 (이원근)

CLI 마지막 `HUMAN CHECK` + `.md` 체크리스트:

1. level 1이 **초등이 따질 만큼 단순**한가?
2. level 7이 **진짜 깊은**가?
3. level 4가 **중간**인가?
4. 셋이 **서로 비슷하지 않은**가?

자동 compare 표(hinge 글자 수, side_b 유무, axes 수, counter 축)는 참고용 — **눈으로 판정**이 갈림길.

## 관련 파일

- `public/api/edu/lib/eduLevelDepthExtract.php` — 레벨별 프롬프트·정규화 (검증 전용)
- `tools/edu_level_depth_verify.php` — CLI
- 기존 `eduHingeExtract` / `eduAxisExtract` — **미변경** (1단 깊이 검증용)

## 다음 단계 (검증 후)

- **깊이 OK** → Phase 2 LLM 진단, Phase 3 level 2~6 정의
- **흐릿** → "구조 깊이" 대신 다른 레벨 구분 방식 검토
