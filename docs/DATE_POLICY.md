# 기사 날짜 정책

## 핵심 원칙: 첫 게시일 고정

`published_at`은 **최초 게시 시점**에 한 번만 설정되며, 이후 수정·임시저장·재게시로 변경되지 않는다.

## 단일 기준 (표시·정렬)

- **사용자에게 보이는 기사 날짜**: `published_at` (우리 사이트에서 **처음 게시 버튼을 누른 시점**). 값이 없으면 `created_at` 사용.
- **임시저장(draft)으로 새로 만들 때** 관리자 API(POST)는 `published_at`을 **저장하지 않고 항상 `null`로 둔다.** 관리 폼의 “원작 게시일” 등으로 채운 값은 우리 사이트 게시일이 아니므로 DB `published_at`에 넣지 않는다.
- **게시(published) 상태로 저장**할 때: 신규 POST는 서버 시각이 `published_at`이 된다. PUT은 아래 관리자 API 규칙을 따른다.
- 이미 **DB에서 `status = published`인 기사**를 게시 상태로 수정 저장하면 `published_at`은 **유지**된다.
- 현재 DB **`status`가 draft인 행**을 게시로 전환하면 `published_at`은 **그때의 서버 시각**으로 설정된다 (과거에 draft에 잘못 들어간 원본기사일 등을 보정). 임시저장으로 내려갔다가 다시 올리는 경우에도 동일하게 적용되므로, 과거 게시일을 그대로 쓰려면 관리 화면에서 게시일을 별도 보정하거나 정책을 문서화한다.
- 임시저장(draft)으로 저장할 때는 UPDATE에서 `published_at`을 건드리지 않는다 (기존 값 보존).
- **목록·이전/다음·최신 판단** 모두 `COALESCE(published_at, created_at)` 기준으로 정렬하여 일치시킴.

## 필드 역할

| 필드 | 역할 |
|------|------|
| `published_at` | **우리 사이트 기준 게시 시점**. POST published·PUT에서 draft→published 등 규칙으로 설정. **게시 상태 유지 수정** 시에는 유지. (`status` 컬럼 없는 레거시 DB는 PUT 시 기존 규칙 유지) |
| `created_at` | 레코드 최초 생성 시점 (임시저장 생성 포함). `published_at`이 없을 때만 표시·정렬에 사용. |
| `updated_at` | 내부 수정 이력용. 게시일 표시에는 사용하지 않음. |

## API 응답

- 목록·상세 API는 표시용 날짜를 **`display_date`** 로 내려준다. `display_date = published_at ?? created_at`.
- 상세 API의 `published_at` 응답값도 동일하게 `display_date`와 같게 전달.
- 프론트는 **`display_date` 우선**, 없으면 `published_at`을 사용해 날짜를 렌더링한다.

## 검증 체크리스트 (회귀 확인용)

다음 케이스에서 **홈 카드 / 전체기사 리스트 / 상세 헤더 / 이전·다음 기사 순서**가 동일한지 확인한다.

1. **임시저장 후 게시한 기사** – 최초 게시 시점이 `published_at`으로 저장되고, 목록·상세 날짜와 이전/다음 순서가 이 기준으로 일치해야 함.
2. **이미 게시된 기사 수정 저장** – `published_at`이 변하지 않고, 목록 순서도 유지됨.
3. **게시 → 임시저장 → 다시 게시** – `status` 컬럼이 있으면 다시 게시 시점의 서버 시각이 `published_at`으로 잡힐 수 있음 (draft에 넣지 않은 우리 사이트 게시일만 `published_at`에 남기도록 한 결과).
4. **published_at이 null인 기존 기사** – `created_at`으로 fallback 되어 표시·정렬됨.
5. **대표 기사** – 목록과 상세 날짜가 일치하는지 확인.
