# EDU 7단계 Phase 3 Step 1 — 사이 단계(L2/3/5/6) 깊이 검증

> **목적:** 같은 글(630 등)에서 AI가 level **1~7** 깊이를 **계단**처럼 뽑을 수 있는지 확인.  
> **범위:** 검증 도구만. 라이브 코치·레벨업 **변경 없음**.

## 핵심 질문

| level | 기대 깊이 |
|-------|-----------|
| 1 | 한 면, 1~2축, 비계 듬뿍 (Phase 1 확인) |
| 2 | 양면 입문, 2축, 비계 많음 |
| 3 | 양면, 2~3축, 비계 중상 (L4 바로 아래) |
| 4 | 양면, 3축, 비계 중 (Phase 1 확인) |
| 5 | 3축+근거 강조, 비계 중하 |
| 6 | 다층 입문, 3~4축, 비계 적음 (L7 바로 아래) |
| 7 | 다층, 4축, 반론의 반론, 비계 0 (Phase 1 확인) |

**계단이 매끄럽고 2↔3, 5↔6이 구분되면** → 사이 코치 + 레벨업 엔진.  
**흐릿하면** → 7단→5단(1/3/5/7) 등 재설계.

## 실행 (EC2 / MySQL + LLM)

```bash
cd /var/www/thegist

# 630 — 7단 전체
php tools/edu_level_depth_verify.php 630

# 재현
php tools/edu_level_depth_verify.php 630 150

# Phase 1만 (1/4/7)
php tools/edu_level_depth_verify.php 630 --levels=1,4,7

# 프롬프트만
php tools/edu_level_depth_verify.php --dry-run

# 정적
php tools/edu_level_depth_verify_static.php
```

## 산출물

- `docs/level_depth_verify/630.json` — 7단 추출 + compare + staircase
- `docs/level_depth_verify/630.md` — **7단 계단 표** + 인접 delta + 사람 검수 체크리스트

## 사람 검수 (이원근)

CLI `7-STEP STAIRCASE` 표 + `.md` 체크리스트:

1. hinge/축/비계가 **1→7 계단**인가?
2. **2 vs 3**, **5 vs 6** 구분되는가?
3. 띄엄띄엄이면 **단계 수 줄이기** (가짜 7단보다 진짜 4~5단)

## 관련 파일

- `public/api/edu/lib/eduLevelDepthExtract.php` — L1~7 spec + staircase 분석
- `tools/edu_level_depth_verify.php` — CLI

## 안 함 (이번 Step)

- 사이 코치 프로덕션
- 레벨업 엔진 / 관문 연결
