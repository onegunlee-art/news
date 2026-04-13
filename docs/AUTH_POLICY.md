# 인증 정책 (Auth Policy)

## 토큰 구조

| 토큰 | 유효 기간 | 설정 위치 |
|------|----------|----------|
| **액세스 (JWT)** | 1시간 | `config/app.php` → `jwt_expiry` |
| **리프레시 (JWT + DB)** | 180일 (6개월) | `config/app.php` → `jwt_refresh_expiry` |

TTL 변경 시 `config/app.php`의 `security` 블록 **한 곳만** 수정하면 모든 로그인 경로에 반영됩니다.

## 로그인 경로

모든 경로가 동일한 TTL·발급 규칙을 따릅니다.

- **카카오**: `public/api/auth/kakao/callback.php`, `token.php` → `lib/auth.php` 공용 헬퍼
- **Google**: `AuthService::handleGoogleCallback` → `JWT.php`
- **이메일/비밀번호 + OTP**: `AuthService::startEmailLogin` / `verifyLoginOtp` → `JWT.php`
- **회원가입**: `AuthService::registerWithEmail` → `JWT.php`

## 슬라이딩 윈도 (Sliding Window)

- 리프레시 갱신(`POST /api/auth/refresh`) 시 **기존 리프레시 폐기 + 새 180일 리프레시 발급**
- 사용자가 180일 내에 한 번이라도 사이트를 열면 → 갱신 → 사실상 무기한 유지
- 180일 동안 전혀 방문하지 않으면 → 리프레시 만료 → 재로그인 필요

## 저장소

### 서버
- DB `user_tokens` 테이블: `token`, `token_type='refresh'`, `expires_at`, `revoked_at`

### 클라이언트 (localStorage)
- `access_token`, `refresh_token`, `user`, `auth-storage` (Zustand persist), `is_subscribed`

## 로그아웃

1. **서버**: `POST /api/auth/logout` → `user_tokens.revoked_at = NOW()`
2. **클라이언트**: 카카오 SDK 로그아웃 + localStorage 키 전부 삭제
3. **전체 디바이스 로그아웃**: `UserRepository::revokeAllTokens(userId)` (관리자용)

## 401 처리 흐름

1. API 응답 401 → axios 인터셉터가 `POST /api/auth/refresh` 호출
2. 성공 → 새 액세스+리프레시 저장, 원래 요청 재시도
3. 실패 → `forceLogoutOnce()` (localStorage 삭제 + 상태 초기화)

## 보안

- 리프레시 토큰은 갱신마다 **로테이션** (기존 폐기 + 신규 발급)
- JWT 서명: HS256, 비밀키는 `JWT_SECRET` 환경변수
- DB `user_tokens`에 `revoked_at` 필드로 즉시 폐기 가능

## 파일 매핑

| 파일 | 역할 |
|------|------|
| `config/app.php` | TTL 설정 (단일 진실의 원천) |
| `public/api/lib/auth.php` | 공용 헬퍼: `getRefreshTtlSeconds()`, `createJwtToken()`, `decodeJwt()` |
| `src/backend/Utils/JWT.php` | 라우터용 JWT 생성·검증 클래스 |
| `src/backend/Services/AuthService.php` | 모든 로그인/갱신/로그아웃 비즈니스 로직 |
| `src/backend/Repositories/UserRepository.php` | `user_tokens` DB CRUD |
| `public/api/auth/kakao/callback.php` | 카카오 OAuth (Nginx 직접 실행) |
| `public/api/auth/kakao/token.php` | 카카오 코드 교환 (프론트 fetch) |
| `src/frontend/src/store/authStore.ts` | 클라이언트 상태·토큰 저장·갱신 |
| `src/frontend/src/services/api.ts` | 401 인터셉터·리프레시 큐 |
