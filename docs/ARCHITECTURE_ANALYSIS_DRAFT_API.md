# 임시저장(기사 조회) HTML 반환 원인 분석

## 증상
- **에러 메시지**: "기사 조회 실패: 서버가 JSON 대신 HTML을 반환했습니다. API 경로를 확인하세요"
- **의미**: `/api/admin/news.php` 요청에 대해 서버가 `application/json` 대신 `text/html`(index.html)을 반환함

---

## 1. 요청 흐름 (아키텍처)

```
[프론트엔드] adminFetch('/api/admin/news.php?status_filter=draft&per_page=50')
      ↓
[브라우저] GET https://www.thegist.co.kr/api/admin/news.php?status_filter=draft&per_page=50
      ↓
[Apache + .htaccess] 라우팅 결정
      ↓
   ┌─────────────────────────────────────────────────────────────┐
   │ 1) RewriteCond %{REQUEST_FILENAME} -f (파일 존재?)           │
   │    → YES: 해당 PHP 파일 직접 실행 → JSON 반환 ✓              │
   │    → NO:  다음 규칙으로                                       │
   │                                                              │
   │ 2) RewriteRule ^api/(.*)$ index.php [QSA,L]                   │
   │    → index.php로 내부 리라이트                                 │
   └─────────────────────────────────────────────────────────────┘
      ↓
[index.php] $requestUri = $_SERVER['REQUEST_URI']
      ↓
   isApiRequest = str_starts_with($requestUri, '/api/')
      ↓
   ┌─────────────────────────────────────────────────────────────┐
   │ TRUE  → Router → JSON 응답 ✓                                  │
   │ FALSE → index.html (SPA) → HTML 반환 ✗ (현재 증상)            │
   └─────────────────────────────────────────────────────────────┘
```

---

## 2. HTML이 반환되는 조건

`index.php`에서 **SPA 분기**로 가려면:

```php
$isApiRequest = str_starts_with($requestUri, '/api/');
```

이 값이 **false**여야 한다. 즉, `$requestUri`가 `/api/`로 시작하지 않아야 함.

---

## 3. 원인 후보 (우선순위)

### 3-1. [가장 유력] Rewrite 후 REQUEST_URI 변경

**가설**: `RewriteRule ^api/(.*)$ index.php`로 리라이트할 때, Apache가 `REQUEST_URI`를 **실제 처리 대상 경로**인 `/index.php`로 바꿈.

- 원래 요청: `/api/admin/news.php?status_filter=draft`
- 리라이트 후: `REQUEST_URI` = `/index.php` (또는 `/index.php?status_filter=...`)
- `str_starts_with('/index.php', '/api/')` → **false**
- → SPA 분기 → **index.html 반환** → HTML

**검증**: 서버에서 `var_dump($_SERVER['REQUEST_URI'], $_SERVER['REDIRECT_URL'] ?? 'N/A');` 출력해 확인.

---

### 3-2. 파일 미존재로 인한 404 → ErrorDocument

**가설**: `public/api/admin/news.php`가 서버에 없어 404 발생 → `ErrorDocument 404 /index.php`로 내부 리다이렉트 → `REQUEST_URI`가 `/index.php`로 설정됨.

- 배포 구조: `public/*` → `deploy/` → FTP `/html/`
- 서버 경로: `/html/api/admin/news.php` 예상
- DocumentRoot가 `/html/`가 아니거나, FTP 업로드 경로가 다르면 실제 파일 경로와 불일치 가능

---

### 3-3. api/admin 전용 규칙 부재

**현재 .htaccess**:
- `api/auth/kakao`, `api/auth/seed-admin-user`, `api/analysis/news/:id` → **명시적 규칙 있음**
- `api/admin/*.php` → **명시적 규칙 없음** (주석 처리됨)
- `api/admin/` → index.php로 보내는 규칙이 **주석 처리**되어 있음

```apache
# Admin API: 실제 PHP 파일 직접 제공 (Router 경유 시 HTML 응답 이슈 회피)
# RewriteRule ^api/admin/ index.php [QSA,L]
```

→ `api/admin/news.php`는 **파일 존재 여부**에만 의존.  
→ 파일이 없으면 `RewriteRule ^api/(.*)$ index.php`로 fallback.

---

### 3-4. projectRoot 경로 불일치 (Router 경유 시)

Router의 Admin PHP 프록시:

```php
$scriptPath = $projectRoot . '/public/api/admin/' . $script;  // 1차
if (!is_file($scriptPath)) {
    $scriptPath = $projectRoot . '/api/admin/' . $script;     // 2차
}
```

- 배포 구조: `public/*`가 `deploy/` **루트**로 복사됨 (`deploy/api/admin/news.php`)
- `projectRoot` = `/html` (config 기준)
- 1차: `/html/public/api/admin/news.php` → **없음**
- 2차: `/html/api/admin/news.php` → **있음** (정상)

→ Router가 처리할 경우에는 스크립트를 찾을 수 있음.  
→ 문제는 **index.php로 요청이 들어올 때 `REQUEST_URI`가 `/api/`로 시작하는지** 여부.

---

## 4. 아키텍처 요약

| 구분 | 현재 | 문제점 |
|------|------|--------|
| **Admin API 라우팅** | 파일 존재 시 직접 실행, 없으면 index.php | index.php 리라이트 시 REQUEST_URI가 바뀌면 SPA로 빠짐 |
| **api/admin 규칙** | 주석 처리 | 명시적 라우팅 없음 |
| **isApiRequest 판단** | `REQUEST_URI`만 사용 | 리라이트 후 URI 변경 시 오판 |
| **ErrorDocument 404** | `/index.php` | 404 시 SPA가 반환될 수 있음 |

---

## 5. 권장 수정 방향 (구체적 수정은 하지 않음)

1. **REQUEST_URI 보정**
   - `index.php`에서 `REDIRECT_URL` 또는 `REDIRECT_REQUEST_URI` 등으로 원본 URI 복원
   - 또는 `index.php`가 API 요청으로 들어왔을 때 `REDIRECT_*` 변수로 `/api/` 여부 판단

2. **API 전용 진입점 분리**
   - `api.php`를 두고, API 요청만 `api.php`로 리라이트
   - `index.php`는 SPA 전용으로 사용

3. **api/admin 명시적 규칙**
   - `api/admin/*.php`를 **실제 파일**로 직접 실행하도록 규칙 추가
   - 또는 `api/admin/`을 `index.php`로 보내되, `REQUEST_URI` 보정 로직 추가

4. **배포/경로 검증**
   - 실제 서버에 `/html/api/admin/news.php` 존재 여부 확인
   - DocumentRoot와 FTP 업로드 경로 일치 여부 확인

---

## 6. 검증용 체크리스트

- [ ] 서버에서 `$_SERVER['REQUEST_URI']`, `$_SERVER['REDIRECT_URL']` 로그
- [ ] `/api/admin/news.php` 직접 호출 시 응답 Content-Type
- [ ] 배포 후 `api/admin/news.php` 파일 존재 여부
- [ ] DocumentRoot와 `api/admin/` 실제 경로 매핑
