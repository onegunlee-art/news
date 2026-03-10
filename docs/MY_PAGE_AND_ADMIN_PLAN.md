# My Page · Admin 연동 구상

## 1. 개요

- **My Page**: 모바일 기준 상단 왼쪽 햄버거(세로 3선) 탭 시 진입하는 사용자 전용 페이지.
- **Admin**: 동일한 콘텐츠(문의 이메일, The Gist, 저작권, 이용약관, 개인정보처리방침 등)를 편집하고, 저장 시 사이트 전역에 즉시 반영.

---

## 2. 모바일 헤더 동작 (이미 반영됨)

- **현재**: 모바일에서 왼쪽에 햄버거 아이콘(세로 3선), 클릭 시 `isAuthenticated ? '/profile' : '/register'` 이동.
- **유지**: "My page" 텍스트는 PC에서만 표시, 모바일은 아이콘만 사용 → 추가 수정 없음.

---

## 3. My Page 구성 (섹션별)

### 3.1 즐겨찾기

| 항목 | 내용 |
|------|------|
| **데이터** | 기존 `newsApi.getBookmarks(1, 20)` 사용. |
| **표시** | 카드 리스트 (제목, 요약, 매체, 저장일). 없으면 **"즐겨 찾기 등록한 컨텐츠가 없습니다"** 문구 + 뉴스 둘러보기 유도. |
| **Admin** | 해당 없음 (사용자별 데이터). |

### 3.2 알림 설정 (푸시 알림)

| 항목 | 내용 |
|------|------|
| **기능** | "새 글이 올라오면 푸시 알림" 온/오프. **실제 동작** 필요. |
| **구현 방향** | 1) **Web Push (VAPID)**: 백엔드에 FCM 또는 직접 푸시 발송 로직 + 프론트 Service Worker 구독/수신. 2) **단순화**: 구독 시 백엔드에 `push_subscription` 저장, 새 뉴스 수집 시 해당 엔드포인트로 푸시 발송. Admin에서는 “푸시 발송 테스트” 등 선택 기능. |
| **저장** | 사용자별 설정: DB `user_settings` 또는 `users` 확장 컬럼 `push_enabled` + `push_endpoint` 등. |
| **Admin** | (선택) 푸시 테스트 발송, 알림 문구 템플릿 수정. |

### 3.3 보기 설정

| 항목 | 내용 |
|------|------|
| **글씨 크기** | 3단계: **작게 / 보통 / 크게**. 기본값 **보통**. |
| **저장** | `localStorage` (예: `view_font_size`). 적용: 루트/레이아웃에 `data-font-size` 또는 CSS 클래스로 전역 적용. |
| **화면 흑백** | 토글. `localStorage` (예: `view_grayscale`). CSS `filter: grayscale(100%)`를 레이아웃 래퍼에 적용. |
| **Admin** | 해당 없음 (클라이언트 설정). |

### 3.4 문의하기

| 항목 | 내용 |
|------|------|
| **기능** | 입력 폼(제목 선택/직접, 본문) → 전송 시 **수신 이메일로 자동 발송**. 수신 주소는 Admin에서 설정. |
| **기본 수신** | `onegunlee@gmail.com` (설정 없을 때). |
| **API** | `POST /contact` (미구현 시 추가). Body: `subject`, `message`. 서버에서 `GET /settings/site`로 `contact_email` 조회 후 메일 발송 (PHP `mail()` 또는 SMTP). |
| **Admin** | "문의 수신 이메일" 수정 → `contact_email` 저장 → 문의하기 발송 시 즉 반영. (이미 Admin 사이트 설정에 있음.) |

### 3.5 구독

| 항목 | 내용 |
|------|------|
| **표시** | 사용 중인 구독 플랜명 + (선택) 만료일. **해지하기** 버튼. |
| **데이터** | 현재 `authStore`: `isSubscribed`, `user.subscription_expires_at` 등. 백엔드에 구독/결제 테이블이 있으면 연동. 없으면 “구독 중”/“해지하기”만 표시하고, 해지 시 API 호출 또는 안내 문구. |
| **Admin** | 구독 목록/해지 처리 등은 기존 Admin 유저 관리와 연동. |

### 3.6 The Gist (회사 비전)

| 항목 | 내용 |
|------|------|
| **표시** | My Page 하단 또는 전용 섹션에 문구 표시. **Admin에서 편집한 내용** 그대로 노출. |
| **데이터** | `GET /settings/site` → `the_gist_vision`. |
| **Admin** | 이미 "The Gist 비전 문구" 필드로 저장 → 즉시 반영. |

### 3.7 저작권

| 항목 | 내용 |
|------|------|
| **표시** | My Page 하단에 저작권 문구. Admin 설정값 사용. |
| **데이터** | `GET /settings/site` → `copyright_text`. |
| **Admin** | "저작권 문구" 필드 → 저장 시 My Page·푸터 동일 반영. (이미 있음.) |

### 3.8 이용약관 · 개인정보 처리 방침

| 항목 | 내용 |
|------|------|
| **표시** | My Page에서 **링크 또는 버튼** → 클릭 시 기존처럼 **모달** 또는 `/terms`, `/privacy` 페이지로 열기. (Footer와 동일 패턴: TermsModal, PrivacyPolicyModal.) |
| **데이터** | `GET /settings/terms`, `GET /settings/privacy`. |
| **Admin** | 이용약관/개인정보처리방침 편집 → 저장 시 사이트 전역 반영. (이미 Admin에 있음.) |

---

## 4. Admin과의 1:1 대응

- **문의 수신 이메일** → Admin "사이트 설정" `contact_email` → 문의하기 발송 시 사용.
- **The Gist 비전** → Admin `the_gist_vision` → My Page(및 푸터) 표시.
- **저작권** → Admin `copyright_text` → My Page·푸터.
- **이용약관** → Admin `terms_of_service` → My Page 링크/모달 + `/terms` + 푸터.
- **개인정보처리방침** → Admin `privacy_policy` → My Page 링크/모달 + `/privacy` + 푸터.

Admin에서 위 항목 수정 후 저장하면 My Page·푸터·문의 발송에 **즉시** 반영되도록 유지.

---

## 5. 푸터와의 일관성

- 푸터에 **The Gist 비전**, **저작권** 표시 시 `siteSettingsApi.getSite()` 사용해 동일 소스 적용.
- 이용약관/개인정보처리방침은 푸터와 동일하게 모달 또는 공개 페이지 링크.

---

## 6. 백엔드 정리

- **POST /contact**  
  - Body: `subject?`, `message`.  
  - `settings`에서 `contact_email` 조회 후 해당 주소로 이메일 발송.  
  - (미구현 시 새로 추가.)
- **푸시 알림**  
  - Web Push 구독 저장용 API (예: `POST /user/push-subscription`, `PATCH /user/settings`) 및 실제 발송 스크립트/큐는 별도 구현.

---

## 7. 디자인 방향

- **My Page**: 카드형 섹션 구분, 여백·타이포 명확히.
- **색상**: 기존 primary(테마색) 유지, 섹션별 구분은 배경/보더로만.
- **모바일 우선**: 터치 영역 충분, 스크롤 시 헤더는 기존대로.
- **접근성**: 보기 설정(글씨 크기·흑백)이 즉시 적용되도록 루트/레이아웃에 반영.

---

## 8. 구현 순서 제안

1. **My Page 레이아웃 재구성**  
   - 즐겨찾기 / 들었던 오디오 유지, 아래 섹션 추가: 알림 설정, 보기 설정, 문의하기, 구독, The Gist, 저작권, 이용약관, 개인정보처리방침.
2. **보기 설정**  
   - 글씨 크기 3단계 + 흑백 토글, `localStorage` + 전역 CSS 적용.
3. **문의하기**  
   - My Page 폼 + `POST /contact` API 구현(수신 주소는 `contact_email`).
4. **푸터**  
   - `getSite()`로 비전·저작권 표시 (이미지 하단 바 텍스트).
5. **푸시 알림**  
   - Web Push 구독 저장 + 발송 플로우 (실제 동작).
6. **Admin**  
   - 기존 사이트 설정/이용약관/개인정보 유지, 필요 시 “문의 이메일” 등 라벨/안내만 정리.

이 구상대로 구현하면 My Page와 Admin이 동일한 설정으로 일치하고, 디자인은 기존 테마를 유지하면서 섹션별로 정리된 형태가 됩니다.
