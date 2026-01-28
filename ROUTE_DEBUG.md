# 라우트 오류 디버깅 가이드

## ❌ 오류: "Route not found"

이 오류는 요청한 경로에 해당하는 라우트가 등록되지 않았을 때 발생합니다.

## 🔍 문제 진단

### 1. 라우트 테스트 페이지 확인

브라우저에서 다음 URL을 열어보세요:
```
http://ailand.dothome.co.kr/api/routes_test.php
```

이 페이지에서:
- 등록된 모든 라우트 목록 확인
- 라우트가 제대로 등록되었는지 확인
- 경로와 메서드가 올바른지 확인

### 2. 요청 경로 확인

**예상되는 요청 경로들:**
- `GET /api/auth/kakao` → 라우트: `/auth/kakao`
- `GET /api/auth/kakao/callback` → 라우트: `/auth/kakao/callback`
- `GET /api/health` → 라우트: `/health`

**Router 클래스의 동작:**
1. 요청 URI에서 `/api` prefix를 제거합니다
2. 나머지 경로로 라우트를 매칭합니다
3. 매칭되는 라우트가 없으면 "Route not found" 오류 발생

### 3. 라우트 등록 확인

`config/routes.php` 파일에서 다음 라우트가 등록되어 있는지 확인:

```php
$router->group(['prefix' => '/auth'], function (Router $router) {
    $router->get('/kakao', [AuthController::class, 'kakaoLogin']);
    $router->get('/kakao/callback', [AuthController::class, 'kakaoCallback']);
    // ...
});
```

이렇게 등록되면 실제 경로는:
- `/auth/kakao`
- `/auth/kakao/callback`

## 🔧 해결 방법

### 방법 1: 라우트 테스트 페이지 확인

1. `http://ailand.dothome.co.kr/api/routes_test.php` 접속
2. 등록된 라우트 목록 확인
3. `/auth/kakao` 라우트가 있는지 확인

### 방법 2: 직접 URL 테스트

브라우저에서 다음 URL들을 직접 테스트:

```
http://ailand.dothome.co.kr/api/health
http://ailand.dothome.co.kr/api/auth/kakao
```

**정상 작동 시**: 각각의 응답이 반환됩니다
**오류 발생 시**: "Route not found" 메시지 표시

### 방법 3: 서버 로그 확인

서버의 PHP 에러 로그를 확인하여:
- 라우트 파일이 제대로 로드되는지
- 컨트롤러 클래스가 존재하는지
- 오류 메시지가 있는지

## 📋 체크리스트

- [ ] `config/routes.php` 파일이 서버에 업로드됨
- [ ] `src/backend/Core/Router.php` 파일이 서버에 업로드됨
- [ ] `src/backend/Controllers/AuthController.php` 파일이 서버에 업로드됨
- [ ] 라우트 테스트 페이지에서 라우트가 등록되어 있음
- [ ] `.htaccess` 파일이 올바르게 설정됨

## ⚠️ 자주 발생하는 문제

### 문제 1: 라우트 파일이 로드되지 않음

**증상**: 라우트 테스트 페이지에 라우트가 없음

**해결**:
1. `config/routes.php` 파일이 서버에 있는지 확인
2. 파일 경로가 올바른지 확인
3. 파일 권한 확인 (644)

### 문제 2: 컨트롤러 클래스가 없음

**증상**: "Controller not found" 오류

**해결**:
1. `src/backend/Controllers/AuthController.php` 파일 확인
2. Autoloader가 제대로 작동하는지 확인
3. 네임스페이스가 올바른지 확인

### 문제 3: 경로 매칭 실패

**증상**: 라우트는 있지만 매칭되지 않음

**해결**:
1. 요청 경로와 등록된 경로가 정확히 일치하는지 확인
2. 대소문자 구분 확인
3. 슬래시(/) 위치 확인

## 🧪 테스트 순서

1. **라우트 테스트 페이지 확인**
   ```
   http://ailand.dothome.co.kr/api/routes_test.php
   ```

2. **헬스 체크 테스트**
   ```
   http://ailand.dothome.co.kr/api/health
   ```

3. **카카오 로그인 테스트**
   ```
   http://ailand.dothome.co.kr/api/auth/kakao
   ```

4. **각 단계에서 오류 메시지 확인**

## 📝 요약

"Route not found" 오류는:
1. 라우트가 등록되지 않았거나
2. 경로가 일치하지 않거나
3. 라우트 파일이 로드되지 않았을 때 발생합니다

먼저 라우트 테스트 페이지로 등록된 라우트를 확인하세요!
