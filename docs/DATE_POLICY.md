# 기사 날짜 정책

## 단일 기준 (표시·정렬)

- **사용자에게 보이는 기사 날짜**: `published_at` (우리 사이트에서 **게시 버튼을 누른 시점**). 값이 없으면 `created_at` 사용.
- **임시저장** 상태에서는 퍼블리싱 날짜가 없음. **게시**로 전환할 때 그 시점이 `published_at`으로 저장됨.
- **목록·이전/다음·최신 판단** 모두 `COALESCE(published_at, created_at)` 기준으로 정렬하여 일치시킴.

## 필드 역할

| 필드 | 역할 |
|------|------|
| `published_at` | 우리 사이트에서 기사를 **게시한 시점**. 표시·정렬의 기본 기준. draft→published 전환 시 자동 설정. |
| `created_at` | 레코드 최초 생성 시점 (임시저장 생성 포함). `published_at`이 없을 때만 표시·정렬에 사용. |
| `updated_at` | 내부 수정 이력용. 게시일 표시에는 사용하지 않음. |

## API 응답

- 목록·상세 API는 표시용 날짜를 **`display_date`** 로 내려준다. `display_date = published_at ?? created_at`.
- 상세 API의 `published_at` 응답값도 동일하게 `display_date`와 같게 전달.
- 프론트는 **`display_date` 우선**, 없으면 `published_at`을 사용해 날짜를 렌더링한다.

## 검증 체크리스트 (회귀 확인용)

다음 케이스에서 **홈 카드 / 전체기사 리스트 / 상세 헤더 / 이전·다음 기사 순서**가 동일한지 확인한다.

1. **임시저장 후 게시한 기사** – 게시한 시점이 `published_at`으로 저장되고, 목록·상세 날짜와 이전/다음 순서가 이 기준으로 일치해야 함.
2. **published_at이 null인 기존 기사** – `created_at`으로 fallback 되어 표시·정렬됨.
3. **수정 이력이 있는 기사** – 상세는 `updated_at`이 아닌 `display_date`(published_at 우선)만 표시.
4. **대표 기사** – 목록과 상세 날짜가 일치하는지 확인.
