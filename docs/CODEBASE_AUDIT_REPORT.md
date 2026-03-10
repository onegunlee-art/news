# News 프로젝트 전체 코드베이스 점검 리포트

**작성일**: 2025-02-21  
**범위**: 프론트엔드, 백엔드, API, 에이전트, DB, 설정

---

## 1. 프로젝트 구조 요약

```
News/
├── src/
│   ├── frontend/     # React 18 + Vite + TypeScript + Zustand
│   ├── backend/      # PHP 8+ MVC (Controllers, Services, Repositories, Core)
│   └── agents/       # AI 파이프라인 (ValidationAgent, AnalysisAgent, GoogleTTSService 등)
├── public/           # 진입점(index.php, api.php), 정적 파일, 직접 PHP API 스크립트
├── config/           # app, routes, database, kakao, agents 등
└── database/         # schema.sql, migrations/
```

---

## 2. 진입점 및 라우팅 흐름

### 2.1 요청 분기

| 요청 유형 | 처리 경로 | 비고 |
|-----------|-----------|------|
| `/api/*` | `api.php` → Router | JSON 응답 |
| 그 외 (SPA) | `index.php` → `index.html` | React SPA |

### 2.2 .htaccess Rewrite 규칙

| 패턴 | 대상 | 비고 |
|------|------|------|
| `api/auth/kakao*` | 직접 PHP (kakao.php, callback.php, token.php) | 카카오 인증 |
| `api/analysis/news/{id}` | `api/analysis/news.php` | AI 분석 |
| `api/admin/*.php` | `api.php?__path=/api/admin/$1` | Router 경유, 인증 적용 |
| `api/*` (파일 없음) | `api.php?__path=/api/$1` | Router |
| **파일 존재** | 직접 제공 | `api/news/detail.php` 등 |

### 2.3 API 경로별 처리 방식

| 프론트엔드 호출 | 실제 처리 | 인증 |
|-----------------|-----------|------|
| `GET /api/admin/news.php` | Router → `public/api/admin/news.php` include | published_only=1이면 생략 |
| `GET /api/news/detail.php?id=` | **직접 PHP** (파일 존재 시) | 없음 |
| `POST /api/news/bookmark` | Router → NewsController::bookmarkByBody | Bearer |
| `GET /api/user/bookmarks` | Router → NewsController::userBookmarks | Bearer |
| `POST /api/tts/generate` | Router → TTSController::generate | 없음 |
| `POST /api/auth/login` | Router → AuthController::login | 없음 |

---

## 3. 주요 데이터 흐름

### 3.1 인증

```
카카오: GET /api/auth/kakao → callback.php → JWT 발급 → /auth/callback#token
이메일: POST /api/auth/login, /register → AuthController
토큰 갱신: 401 시 api 인터셉터 → POST /auth/refresh → localStorage 갱신
```

### 3.2 뉴스 CRUD

- **목록**: `newsApi.getList()` → `/api/admin/news.php` (GET, published_only=1)
- **상세**: `newsApi.getDetail(id)` → `/api/news/detail.php?id=` (직접 PHP)
- **생성/수정/삭제**: `adminFetch` → `/api/admin/news.php` (POST/PUT/DELETE)

### 3.3 TTS/오디오

- **Listen**: `ttsApi.generateStructured()` → `/api/tts/generate` (Router)
- **캐시**: `hash(title|meta|critique|narration|voice)` → 파일 + Supabase media_cache
- **수정 시**: `invalidateTtsCacheForNews(newsId)` (news.php PUT/DELETE)

### 3.4 북마크

- **저장**: `/api/news/bookmark` (Router)
- **삭제**: `/api/news/bookmark?id=` (Router)
- **목록**: `/api/user/bookmarks` (Router)

---

## 4. 상태 관리 (Stores)

| Store | 파일 | 역할 |
|-------|------|------|
| authStore | `store/authStore.ts` | user, accessToken, refreshToken, isAuthenticated, isSubscribed |
| audioPlayerStore | `store/audioPlayerStore.ts` | Listen 팝업, TTS 재생, openAndPlay |
| audioListStore | `store/audioListStore.ts` | 최근 들은 기사 (persist) |
| viewSettingsStore | `store/viewSettingsStore.ts` | fontSize, theme (persist) |

**다크 모드**: `Layout.tsx`에서 `html.setAttribute('data-theme', theme)` 적용, `index.css`의 `[data-theme="dark"]`로 CSS 변수 오버라이드.

---

## 5. 발견된 이슈 및 권장 사항

### 5.1 [심각] DB 비밀번호 하드코딩

**위치**: 여러 파일에 `password => 'romi4120!'` 또는 `config/database.php` fallback

| 파일 | 내용 |
|------|------|
| `public/api/admin/news.php` | `$dbConfig['password'] = 'romi4120!'` |
| `public/api/news/detail.php` | 동일 |
| `public/api/auth/kakao/callback.php` | 동일 |
| `public/api/auth/kakao/token.php` | 동일 |
| `public/api/lib/auth.php` | 동일 |
| `public/api/analysis/news.php` | 동일 |
| `config/database.php` | `getenv('DB_PASSWORD') ?: 'romi4120!'` (fallback) |
| 기타 마이그레이션/백필 스크립트 | 다수 |

**권장**: 환경변수 `DB_PASSWORD` 전용 사용, `config/database.php`에서만 로드 후 공통 DB 연결 유틸 사용.

---

### 5.2 [버그] AdminController::activities - analyses.title 불일치

**위치**: `src/backend/Controllers/AdminController.php` 321행

```php
SELECT 'analysis' as type, title as message, created_at as time 
FROM analyses 
```

**문제**: `analyses` 테이블에 `title` 컬럼이 없음. (schema.sql 기준: id, user_id, news_id, input_text, keywords, sentiment, summary 등만 존재)

**권장**: `news` JOIN으로 `news.title` 사용 또는 `summary`/`input_text` fallback.

---

### 5.3 API 이중 구조

- **Router 기반**: NewsController, AuthController, TTSController 등
- **직접 PHP**: `news/detail.php`, `admin/news.php` (include), `auth/kakao/*.php`

**영향**: 뉴스 목록/상세는 직접 PHP, 북마크는 Router. 일관성 부족.

---

### 5.4 News 모델/Repository 미사용

- `NewsRepository`, `NewsController::index`, `show` 등은 프론트엔드에서 사용되지 않음
- 실제 뉴스 API는 `public/api/admin/news.php`, `public/api/news/detail.php` 중심

---

### 5.5 디버그/테스트 파일

- `public/api/route_debug_simple.php` 존재
- `index.php` fallback HTML에 `test_connection.php` 링크

**권장**: 프로덕션 배포 시 제거 또는 접근 차단.

---

### 5.6 config/database.php fallback

```php
'password' => getenv('DB_PASSWORD') ?: 'romi4120!',
```

환경변수 없을 때 하드코딩 비밀번호 사용. `.env`에 `DB_PASSWORD` 설정 필수.

---

## 6. 정상 동작 확인 항목

| 항목 | 상태 |
|------|------|
| 프론트엔드 라우팅 (App.tsx) | 정상 |
| API 인터셉터 (401 → refresh) | 정상 |
| TTS 캐시 무효화 (PUT/DELETE) | 정상 |
| viewSettingsStore → html data-font-size, data-theme | 정상 |
| .htaccess Rewrite 규칙 | 정상 |

---

## 7. 권장 조치 우선순위

1. **즉시**: `AdminController::activities`의 `analyses.title` → `news.title` JOIN 또는 `summary` 사용으로 수정
2. **단기**: DB 비밀번호 환경변수 통일, 하드코딩 제거
3. **중기**: 뉴스 API를 Router/Controller로 통합 검토
4. **운영**: 디버그/테스트 스크립트 제거 또는 접근 차단

---

## 8. 관련 파일 인덱스

| 역할 | 파일 |
|------|------|
| API 진입 | `public/index.php`, `public/api.php` |
| 라우트 | `config/routes.php` |
| DB | `config/database.php`, `database/schema.sql` |
| 뉴스 API | `public/api/admin/news.php`, `public/api/news/detail.php` |
| TTS | `src/backend/Controllers/TTSController.php`, `public/api/lib/invalidateTtsCache.php` |
| 프론트 API | `src/frontend/src/services/api.ts` |
| 레이아웃/테마 | `src/frontend/src/components/Layout/Layout.tsx`, `src/frontend/src/index.css` |
