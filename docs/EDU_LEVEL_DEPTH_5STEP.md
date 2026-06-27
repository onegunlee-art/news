# EDU Level Depth — 5단 검증 (Phase 5)

7단 staircase FAIL 후 **5단으로 재설계**. 프로덕션 코치 무관 — 검증 도구만.

## 5단 좌표

| L | 대상 | hinge | axes | scaffold | 특징 |
|---|------|-------|------|----------|------|
| L1 | 초6 | 한 면 | 1~2 | heavy | 일상어, side_b 없음 |
| L2 | 초고~중1 | 양면 입문 | 2~3 | heavyish | 짧은 A/B |
| L3 | 중2~3 | 양면 | 3 | medium | 반론 1겹(counter) |
| L4 | 고1~2 | 다층 입문+근거 | 3 | light | 2층 긴장 |
| L5 | 최상위 고3 | the gist | 3~4 | minimal | 반론의 반론 |

**7→5 매핑:** L1←L1, L2←L2~3, L3←L4, L4←L5~6, L5←L7

디폴트 L1, 졸업 L5.

## 실행 (EC2)

```bash
cd /var/www/thegist
php tools/edu_level_depth_verify.php 630 150
```

`--stdout-only` — JSON/MD 저장 생략  
`--dry-run` — 프롬프트만 출력

## staircase_ok 판정

- scaffold_score 단조 감소 (heavy → minimal)
- axis_count 단조 증가
- 인접 4쌍(1→2 … 4→5) **distinct** — hinge/axes/scaffold/side_b/counter 중 하나라도 차이
- hinge_len 단조는 **참고만** (FAIL해도 pass 가능)

FAIL → 4단 재설계 검토.

## 산출

- `docs/level_depth_verify/{news_id}.json`
- `docs/level_depth_verify/{news_id}.md`

## 다음 (통과 시)

- 사이 코치 L2/L4 구현
- 레벨업 엔진 (관문 4×5회)
