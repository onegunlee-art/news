# 임시저장/기사 조회 에러 근본 원인 분석

## 1. 문제 요약

| 증상 | 시점 |
|------|------|
| "서버가 JSON 대신 HTML을 반환" | 임시저장 목록, 편집 버튼 클릭 시 |
| "500 Internal Server Error" | api/admin/news.php?id=94, 99 등 단건 조회 시 |

---

## 2. 아키텍처 흐름 (현재)

```
[프론트] adminFetch('/api/admin/news.php?id=94')
    ↓
[Apache] 요청: /api/admin/news.php?id=94
    ↓
[.htaccess] RewriteCond %{REQUEST_FILENAME} -f  (파일 존재?)
    ├─ YES → 해당 PHP 파일 직접 실행 (news.php)
    └─ NO  → RewriteRule ^api/(.*)$ api.php?__path=/api/$1 [QSA,L]
              ↓
         [api.php] __path로 경로 복원 → Router → news.php include
```

---

## 3. 수정 이력과 이유

| 수정 | 이유 | 타당성 |
|------|------|--------|
| api/admin → index.php 리라이트 주석 | "HTML 반환" 회피 | **문제**: index.php는 SPA+API 혼합. Rewrite 후 REQUEST_URI가 /index.php로 바뀌면 isApiRequest=false → HTML 반환 |
| REDIRECT_URL, __api_path로 원본 URI 복원 | REQUEST_URI 변경 시 API 경로 복원 | **호스팅 의존**: REDIRECT_* 변수가 dothome에서 설정되지 않을 수 있음 |
| api.php 신규 추가, api/* → api.php 리라이트 | API 전용 진입점으로 HTML 반환 원천 차단 | **타당**: api.php는 항상 JSON만 반환 |
| api/admin 리라이트 제거 (직접 실행) | index.php 경유 제거 | **역효과**: 파일이 없으면 fallback으로 api.php로 가는데, 그 과정에서 500 발생 가능 |

---

## 4. 근본 원인 후보

### 4-1. 닷홈(Dothome) 서버 특성

| 항목 | 내용 |
|------|------|
| **DocumentRoot vs FTP 경로** | FTP 업로드: `/html/`. DocumentRoot가 `/html/`가 아니면 `REQUEST_FILENAME` 경로 불일치 → 파일 "없음"으로 판단 → api.php로 fallback |
| **REQUEST_FILENAME** | `RewriteCond %{REQUEST_FILENAME} -f`가 실패하면 항상 api.php 경유. 닷홈 경로 구조에 따라 실패 가능 |
| **REDIRECT_* 변수** | Apache 리라이트 시 원본 URI 전달용. CGI/FastCGI 환경에서는 설정되지 않을 수 있음 |
| **PHP 실행 방식** | mod_php vs CGI: 헤더, 환경변수 동작이 다를 수 있음 |

### 4-2. 배포 구조

| 단계 | 동작 |
|------|------|
| build-frontend | `npm run build` → `../../public` (emptyOutDir: false) |
| artifact | `public/` 전체 업로드 (api.php, api/, index.html, assets 포함) |
| deploy | artifact를 `public/`에 다운로드 → `cp -r public/* deploy/` |
| FTP | `deploy/` → 서버 `/html/` |

**가능한 이슈**: artifact에 `api/`가 누락되면 deploy 시 `api/admin/news.php`가 없음 → api.php 경유 → Router가 include 시 파일 없음 → 404 또는 500.

### 4-3. 500 에러 가능 원인

1. **Router 경유 시**: AuthService, Database, JWT 등 백엔드 의존성 실패
2. **news.php include 시**: log.php, imageSearch.php 등 require 실패
3. **news.php 실행 시**: DB 연결 실패, 쿼리 오류, json_encode 실패
4. **경로 문제**: `projectRoot` 또는 `scriptPath` 잘못 계산 → include 실패

---

## 5. 닷홈 vs 코드 이슈 구분

| 구분 | 닷홈 이슈 | 코드/배포 이슈 |
|------|-----------|----------------|
| HTML 반환 | REQUEST_URI 변경, REDIRECT_* 미지원 | index.php의 isApiRequest 판단 로직 |
| 500 에러 | PHP/CGI 설정, 경로 해석 차이 | news.php 의존성, DB, json_encode |
| 파일 없음 | DocumentRoot와 실제 경로 불일치 | artifact에 api/ 누락, deploy 경로 오류 |

---

## 6. 검증 방법

### 6-1. 서버에서 직접 확인

1. **파일 존재 여부**
   - FTP 또는 파일 매니저로 `/html/api/admin/news.php` 존재 확인
   - `/html/api.php` 존재 확인

2. **직접 URL 호출**
   - `https://www.thegist.co.kr/api/admin/news.php?id=94` 브라우저에서 접속
   - HTML이면 → index.php/SPA 경유 (파일 없음 또는 리라이트 오류)
   - JSON이면 → news.php 직접 실행 성공
   - 500이면 → news.php 또는 api.php 내부 오류

3. **500 응답 본문**
   - F12 → Network → 500 요청 → Response 탭
   - `message`, `file`, `line` 확인 → 실제 예외 위치 파악

### 6-2. 배포 파이프라인 확인

- GitHub Actions 로그에서 "OK: api/admin/news.php 있음" 출력 여부
- "WARN"이면 artifact에 api/ 누락

---

## 7. 결론

- **HTML 반환**: 닷홈에서 Rewrite 후 `REQUEST_URI`가 바뀌는 환경 특성 + 기존 index.php 설계가 원인. `api.php` 도입은 적절한 대응.
- **500 에러**: 닷홈 환경(경로, PHP 설정) 또는 news.php/백엔드 의존성 문제 가능. **실제 에러 메시지(file, line, message)** 확인이 필수.
- **권장**: 500 응답 본문의 `message`를 확인한 뒤, 그 내용에 따라 닷홈 설정 vs 코드 수정을 결정.
